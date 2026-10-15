<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\FoodItem;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FoodItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    private User $fss;

    public function test_clinical_baseline_unit_change_preserves_the_measured_basis_and_linked_rows(): void
    {
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g', 'calories' => 100]);
        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id, 'total_calories' => 500]);
        $row = $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 500, 'unit' => 'g']);
        $this->actingAs($this->rnd)->putJson('/api/rnd/food-items/'.$food->uuid, ['serving_unit' => 'kg'])
            ->assertOk()->assertJsonPath('data.serving_unit', 'kg');
        $this->assertEquals(0.1, (float) $food->fresh()->serving_size);
        $this->assertEquals(500, (float) $row->fresh()->quantity);
        $this->assertSame('g', $row->fresh()->unit);
        $this->assertEquals(500, (float) $recipe->fresh()->total_calories);
    }

    public function test_clinical_baseline_conversion_keeps_a_one_gram_basis_nonzero(): void
    {
        $food = FoodItem::factory()->create(['serving_size' => 1, 'serving_unit' => 'g']);
        $this->actingAs($this->rnd)->putJson('/api/rnd/food-items/'.$food->uuid, ['serving_unit' => 'kg'])->assertOk();
        $this->assertEquals(0.001, (float) $food->fresh()->serving_size);
    }

    public function test_clinical_cross_family_baseline_change_lists_affected_items_and_requires_correction(): void
    {
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g']);
        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id]);
        $row = $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 500, 'unit' => 'g']);
        $this->actingAs($this->rnd)->putJson('/api/rnd/food-items/'.$food->uuid, ['serving_unit' => 'mL', 'serving_size' => 100])
            ->assertUnprocessable()->assertJsonPath('affected_recipes.0.recipe_id', $recipe->uuid);
        $this->assertSame('g', $food->fresh()->serving_unit);
        $this->assertSame('g', $row->fresh()->unit);
    }

    public function test_clinical_explicit_baseline_and_row_corrections_are_atomic(): void
    {
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g', 'calories' => 100]);
        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id]);
        $row = $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 500, 'unit' => 'g']);
        $this->actingAs($this->rnd)->putJson('/api/rnd/food-items/'.$food->uuid, [
            'serving_unit' => 'mL', 'serving_size' => 100,
            'recipe_corrections' => [['recipe_id' => $recipe->uuid, 'line_index' => 0, 'quantity' => 450, 'unit' => 'mL']],
        ])->assertOk();
        $this->assertSame('mL', $food->fresh()->serving_unit);
        $this->assertEquals(450, (float) $row->fresh()->quantity);
        $this->assertSame('mL', $row->fresh()->unit);
        $this->assertEquals(450, (float) $recipe->fresh()->total_calories);
    }

    public function test_clinical_precision_losing_baseline_conversion_is_rejected(): void
    {
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g']);
        $this->actingAs($this->rnd)->putJson('/api/rnd/food-items/'.$food->uuid, ['serving_unit' => 'oz'])
            ->assertUnprocessable()->assertJsonValidationErrors('serving_unit');
        $this->assertSame('g', $food->fresh()->serving_unit);
        $this->assertEquals(100, (float) $food->fresh()->serving_size);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND', 'password' => Hash::make('password')]);
        $this->fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
    }

    public function test_rnd_can_list_food_items_paginated(): void
    {
        FoodItem::factory(5)->create();

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/food-items');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_rnd_can_search_food_items_by_name(): void
    {
        FoodItem::factory()->create(['name' => 'Chicken Breast']);
        FoodItem::factory()->create(['name' => 'Brown Rice']);

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/food-items?search=Chicken');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsStringIgnoringCase('Chicken', $data[0]['name']);
    }

    public function test_food_item_search_orders_exact_prefix_then_contains_matches(): void
    {
        FoodItem::factory()->create(['name' => 'Brown Rice']);
        FoodItem::factory()->create(['name' => 'Rice Cake']);
        FoodItem::factory()->create(['name' => 'Rice']);

        $names = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/food-items?search=Rice')
            ->assertOk()
            ->json('data.*.name');

        $this->assertSame(['Rice', 'Rice Cake', 'Brown Rice'], $names);
    }

    public function test_food_item_search_falls_back_to_a_close_typo(): void
    {
        FoodItem::factory()->create(['name' => 'Chicken Breast']);
        FoodItem::factory()->create(['name' => 'Brown Rice']);

        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/food-items?search=Chiken')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Chicken Breast')
            ->assertJsonCount(1, 'data');
    }

    public function test_rnd_can_filter_food_items_by_category(): void
    {
        FoodItem::factory()->create(['category' => 'protein']);
        FoodItem::factory()->create(['category' => 'carbs']);
        FoodItem::factory()->create(['category' => 'protein']);

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/food-items?category=protein');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_rnd_can_create_food_item_manually(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/food-items', [
                'name' => 'Grilled Salmon',
                'category' => 'protein',
                'calories' => 208,
                'protein' => 28,
                'carbs' => 0,
                'fat' => 10,
                'serving_size' => 100,
                'serving_unit' => 'g',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Grilled Salmon');

        $this->assertDatabaseHas('food_items', ['name' => 'Grilled Salmon']);
        $activity = AuditActivity::query()->where('subject_type', FoodItem::class)->sole();
        $event = app(AuditEventPresenter::class)
            ->present($activity->load('causer'), User::factory()->admin()->create())
            ->toArray();
        $changes = collect($event['changes'])->keyBy('field');
        $this->assertSame('nutrition_care', $event['module']);
        $this->assertSame('nutrition_library', $event['domain']);
        $this->assertSame($this->rnd->display_name, $event['actor']['name']);
        $this->assertSame("{$this->rnd->display_name} created food item: Grilled Salmon.", $event['summary']);
        $this->assertNull($changes['name']['before']['value']);
        $this->assertSame('Grilled Salmon', $changes['name']['after']['value']);
        $this->assertEqualsWithDelta(208, $changes['calories']['after']['value'], 0.01);
        $this->assertSame('custom', collect($event['details'])->keyBy('key')['source']['value']);
    }

    public function test_create_food_item_requires_name_and_calories(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/food-items', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'calories']);
    }

    public function test_food_item_rejects_nutrient_and_serving_values_above_bounds(): void
    {
        foreach ([
            [['calories' => 10001], 'calories'],
            [['protein' => 1000.01], 'protein'],
            [['serving_size' => 10000.01], 'serving_size'],
        ] as [$override, $field]) {
            $this->actingAs($this->rnd)->postJson('/api/rnd/food-items', array_merge([
                'name' => 'Validation '.$field,
                'calories' => 200,
            ], $override))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_rnd_can_view_single_food_item(): void
    {
        $food = FoodItem::factory()->create();

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/food-items/{$food->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.id', $food->uuid);
    }

    public function test_rnd_can_update_food_item(): void
    {
        $food = FoodItem::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->rnd)
            ->putJson("/api/rnd/food-items/{$food->uuid}", [
                'name' => 'Updated Name',
                'calories' => 150,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('food_items', ['id' => $food->id, 'name' => 'Updated Name']);

        $activity = AuditActivity::query()->where('subject_type', FoodItem::class)->sole();
        $this->assertSame('updated', $activity->event);
        $this->assertSame(['calories', 'name'], $activity->properties['details']['changed_fields']);
        $event = app(AuditEventPresenter::class)
            ->present($activity->load('causer'), User::factory()->admin()->create())
            ->toArray();
        $changes = collect($event['changes'])->keyBy('field');
        $this->assertStringContainsString('food item: Updated Name', $event['summary']);
        $this->assertSame('Old Name', $changes['name']['before']['value']);
        $this->assertSame('Updated Name', $changes['name']['after']['value']);
        $this->assertEqualsWithDelta((float) $food->calories, $changes['calories']['before']['value'], 0.01);
        $this->assertEqualsWithDelta(150, $changes['calories']['after']['value'], 0.01);
    }

    public function test_rnd_can_delete_food_item(): void
    {
        $food = FoodItem::factory()->create();

        $response = $this->actingAs($this->rnd)
            ->deleteJson("/api/rnd/food-items/{$food->uuid}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('food_items', ['id' => $food->id]);

        $activity = AuditActivity::query()->where('subject_type', FoodItem::class)->sole();
        $this->assertSame('deleted', $activity->event);
        $event = app(AuditEventPresenter::class)
            ->present($activity->load('causer'), User::factory()->admin()->create())
            ->toArray();
        $changes = collect($event['changes'])->keyBy('field');
        $this->assertStringContainsString("food item: {$food->name}", $event['summary']);
        $this->assertSame($food->name, $changes['name']['before']['value']);
        $this->assertNull($changes['name']['after']['value']);
        $this->assertSame('custom', collect($event['details'])->keyBy('key')['source']['value']);
    }

    public function test_non_rnd_cannot_access_food_items(): void
    {
        $response = $this->actingAs($this->fss)
            ->getJson('/api/rnd/food-items');

        $response->assertForbidden();
    }
}
