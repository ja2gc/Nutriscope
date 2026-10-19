<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\FsItem;
use App\Models\MealPlanItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorConsistencyRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function suggested(string $base = 'kg', string $purchase = 'kg', int $packSize = 1): array
    {
        $actor = User::factory()->rnd()->create();
        $this->actingAs($actor);
        $food = FsItem::factory()->create(['base_unit' => $base, 'purchase_unit' => $purchase, 'purchase_price' => 100, 'units_per_purchase' => $packSize]);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $actor->id, 'week_start_date' => '2026-10-19']);
        MenuCycleDay::create(['menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch', 'fs_item_id' => $food->id, 'quantity' => 1, 'estimate_population' => 10]);
        $payload = ['name' => 'Regression list', 'start_date' => '2026-10-19', 'end_date' => '2026-10-19', 'estimate_population' => 10];
        $saved = $this->postJson('/api/fss/shopping-lists/generate', $payload)->assertCreated()->json('data');

        return [$food, $saved, $payload];
    }

    private function line(array $row): array
    {
        return array_intersect_key($row, array_flip(['id', 'fs_item_uuid', 'source', 'purchase_qty', 'purchase_unit', 'purchase_price', 'supplier_id', 'quantity_mode', 'included_in_po', 'exclusion_note']));
    }

    public function test_rename_does_not_recalculate_unchanged_population_or_replace_item_identity(): void
    {
        [$food, $saved] = $this->suggested();
        $food->update(['include_in_generated_lists' => false]);
        $this->patchJson('/api/fss/shopping-lists/'.$saved['id'], ['name' => 'Renamed', 'estimate_population' => 10, 'items' => [$this->line($saved['items'][0])]])
            ->assertOk()->assertJsonPath('data.items.0.id', $saved['items'][0]['id']);
    }

    public function test_generated_items_cannot_be_deleted_or_omitted(): void
    {
        [, $saved] = $this->suggested();
        $url = '/api/fss/shopping-lists/'.$saved['id'];
        $this->patchJson($url, ['items' => []])->assertUnprocessable();
        $this->deleteJson('/api/fss/shopping-list-items/'.$saved['items'][0]['id'])->assertUnprocessable();
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data.items');
    }

    public function test_nonconvertible_purchase_units_do_not_convert_pieces_into_packs(): void
    {
        [, $saved, $payload] = $this->suggested('piece', 'pack', 10);
        $this->patchJson('/api/fss/shopping-lists/'.$saved['id'], ['items' => [$this->line($saved['items'][0])]])
            ->assertOk()->assertJsonPath('data.items.0.purchase_qty', '10.000000')->assertJsonPath('data.items.0.purchase_unit', 'piece');
        $this->assertSame('piece', $saved['items'][0]['purchase_unit']);
    }

    public function test_generation_baseline_survives_edits_without_rewriting_existing_lists(): void
    {
        [, $saved] = $this->suggested();
        $baseline = $saved['items'][0]['generation_baseline'] ?? null;
        $this->assertNotNull($baseline);
        $line = $this->line($saved['items'][0]);
        $line['purchase_price'] = 999;
        $line['purchase_qty'] = 50;
        $line['quantity_mode'] = 'manual';
        $edited = $this->patchJson('/api/fss/shopping-lists/'.$saved['id'], ['items' => [$line], 'estimate_population' => 20])->assertOk()->json('data');
        $this->assertSame($baseline, $edited['items'][0]['generation_baseline']);
        $this->assertSame($saved['period_start'], $edited['period_start']);
        $this->assertSame($saved['period_end'], $edited['period_end']);
        $this->assertSame($saved['days_span'], $edited['days_span']);
    }

    public function test_batch_save_rejects_ingredient_in_supplies_list(): void
    {
        $this->actingAs(User::factory()->rnd()->create());
        $food = FsItem::factory()->create(['kind' => 'ingredient', 'base_unit' => 'kg']);
        $list = ShoppingList::factory()->create(['procurement_track' => 'supplies', 'list_type' => 'manual', 'status' => 'draft']);
        $this->patchJson('/api/fss/shopping-lists/'.$list->uuid, ['items' => [[
            'fs_item_uuid' => $food->uuid, 'source' => 'manual', 'purchase_qty' => 1, 'purchase_unit' => 'kg', 'purchase_price' => 10,
            'quantity_mode' => 'manual', 'included_in_po' => true,
        ]]])->assertUnprocessable();
    }

    public function test_referenced_library_deletion_preserves_saved_snapshot_and_relationship(): void
    {
        $this->actingAs(User::factory()->rnd()->create());
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g']);
        $recipe = Recipe::factory()->create(['servings' => 2]);
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'food_item_id' => $food->id, 'quantity' => 200, 'unit' => 'g']);
        $recipe->load('ingredients.foodItem');
        $line = MealPlanItem::factory()->create(['recipe_id' => $recipe->id, 'nutrient_snapshot' => $recipe->planSnapshot()]);
        $snapshot = $line->fresh()->nutrient_snapshot;
        $this->deleteJson('/api/rnd/recipes/'.$recipe->uuid)->assertNoContent();
        $this->deleteJson('/api/rnd/food-items/'.$food->uuid)->assertNoContent();
        $this->assertSame($snapshot, $line->fresh()->nutrient_snapshot);
        $edited = $recipe->planSnapshot([], $snapshot, [['food_item_id' => $food->uuid, 'quantity' => 100, 'unit' => 'g']]);
        $this->assertSame($snapshot['recipe_original_ingredients'][0]['food_item'], $edited['recipe_original_ingredients'][0]['food_item']);
        $this->assertSame($recipe->uuid, $line->fresh()->recipe->uuid);
        $this->getJson('/api/rnd/recipes/'.$recipe->uuid)->assertNotFound();
        $this->expectException(\InvalidArgumentException::class);
        $recipe->planSnapshot([], null, [['food_item_id' => $food->uuid, 'quantity' => 100, 'unit' => 'g']]);
    }

    public function test_preparation_notes_can_be_cleared(): void
    {
        $this->actingAs(User::factory()->rnd()->create());
        $recipe = Recipe::factory()->create(['prep_notes' => 'Old notes']);
        $this->putJson('/api/rnd/recipes/'.$recipe->uuid, ['prep_notes' => null])->assertOk()->assertJsonPath('data.prep_notes', null);
    }

    public function test_template_rename_preserves_saved_nutrients_after_library_changes_or_deletion(): void
    {
        $actor = User::factory()->rnd()->create();
        $this->actingAs($actor);
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g', 'calories' => 100]);
        $recipe = Recipe::factory()->create(['rnd_user_id' => $actor->id, 'category' => 'meal', 'servings' => 2]);
        $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 200, 'unit' => 'g']);
        $recipe->recalculateTotals();
        $payload = ['name' => 'Original template', 'lines' => [['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'recipe_id' => $recipe->uuid, 'quantity' => 1, 'unit' => 'serving']]];
        $saved = $this->postJson('/api/rnd/meal-plan-templates', $payload)->assertCreated()->json('data');
        $item = $saved['days'][0]['items'][0];
        $food->update(['calories' => 999]);
        $recipe->recalculateTotals();
        $this->deleteJson('/api/rnd/recipes/'.$recipe->uuid)->assertNoContent();
        $payload['name'] = 'Renamed template';
        $payload['lines'][0]['source_template_item_id'] = $item['id'];
        $changed = $this->putJson('/api/rnd/meal-plan-templates/'.$saved['id'], $payload)->assertOk()->json('data');
        $this->assertSame($item['nutrient_snapshot'], $changed['days'][0]['items'][0]['nutrient_snapshot']);
    }

    public function test_fractional_physical_purchase_units_are_not_rounded_to_whole_kilograms(): void
    {
        [, $saved] = $this->suggested('g', 'kg');
        $this->assertEquals(0.01, (float) $saved['items'][0]['purchase_qty']);
    }
}
