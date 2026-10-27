<?php

namespace Tests\Feature;

use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\User;
use App\Services\MenuCycleCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuSlotRecipeTest extends TestCase
{
    use RefreshDatabase;

    private function recipeSlot(User $rnd, bool $locked = false): array
    {
        $item = FsItem::factory()->create([
            'name' => 'Chicken',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'purchase_price' => 100,
            'units_per_purchase' => 1,
        ]);
        $recipe = FoodServiceRecipe::create([
            'rnd_user_id' => $rnd->id,
            'name' => 'Master Adobo',
            'prep_notes' => 'Master notes',
            'servings' => 20,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id' => $item->id,
            'quantity' => 2,
            'unit' => 'kg',
        ]);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id, 'is_active' => false]);
        $day = MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'recipe_id' => $recipe->id,
            'estimate_population' => 100,
            'po_snapshot_locked' => $locked,
        ]);

        return compact('item', 'recipe', 'cycle', 'day');
    }

    public function test_inventory_auto_switch_requires_editable_slot_correction_and_preserves_locked_snapshot(): void
    {
        $rnd = User::factory()->rnd()->create();
        ['item' => $item, 'recipe' => $recipe, 'cycle' => $cycle, 'day' => $day] = $this->recipeSlot($rnd);
        $item->update(['include_in_generated_lists' => false]);
        $recipe->ingredients()->first()->update(['quantity' => 2, 'unit' => 'tbsp']);
        $override = [
            'name' => 'Ward Adobo', 'reference_servings' => 20,
            'ingredients' => [['fs_item_id' => $item->id, 'name' => $item->name, 'quantity' => 2, 'unit' => 'tbsp']],
        ];
        $day->update(['recipe_override' => $override]);
        $locked = MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id, 'recipe_id' => $recipe->id,
            'day_of_week' => 'Tuesday', 'meal_type' => 'lunch',
            'recipe_override' => $override, 'po_snapshot_locked' => true,
            'po_snapshot' => ['name' => 'Frozen Adobo', 'ingredients' => $override['ingredients']],
        ]);

        $url = "/api/fss/fs-items/{$item->uuid}";
        $affected = $this->actingAs($rnd)->patchJson($url, ['include_in_generated_lists' => true])
            ->assertStatus(422)->json('affected_recipes');
        $this->assertCount(2, $affected);
        $slotCorrection = collect($affected)->firstWhere('slot_id', $day->uuid);
        $this->assertSame($cycle->uuid, $slotCorrection['cycle_id']);
        $recipeCorrection = collect($affected)->firstWhere('recipe_id', $recipe->uuid);
        $this->assertNotNull($slotCorrection);
        $this->assertNotNull($recipeCorrection);
        $this->assertSame('tbsp', $day->fresh()->recipe_override['ingredients'][0]['unit']);
        $this->assertFalse($item->fresh()->include_in_generated_lists);

        $this->actingAs($rnd)->patchJson($url, ['include_in_generated_lists' => true, 'recipe_corrections' => [[
            'slot_id' => $day->uuid, 'line_index' => 0, 'quantity' => 0.1, 'unit' => 'kg',
        ]]])->assertStatus(422);
        $this->assertSame('tbsp', $day->fresh()->recipe_override['ingredients'][0]['unit']);
        $this->assertFalse($item->fresh()->include_in_generated_lists);

        $this->actingAs($rnd)->patchJson($url, ['include_in_generated_lists' => true, 'recipe_corrections' => [[
            'recipe_id' => $recipe->uuid, 'line_index' => 0, 'quantity' => 0.1, 'unit' => 'kg',
        ], [
            'slot_id' => $day->uuid, 'line_index' => 0, 'quantity' => 0.1, 'unit' => 'kg',
        ]]])->assertOk();
        $this->assertTrue($item->fresh()->include_in_generated_lists);
        $this->assertSame('kg', $recipe->ingredients()->first()->unit);
        $this->assertSame('kg', $day->fresh()->recipe_override['ingredients'][0]['unit']);
        $this->assertEquals(0.1, $day->fresh()->recipe_override['ingredients'][0]['quantity']);
        $this->assertSame('tbsp', $locked->fresh()->recipe_override['ingredients'][0]['unit']);
        $this->assertSame('tbsp', $locked->fresh()->po_snapshot['ingredients'][0]['unit']);
    }

    public function test_slot_override_is_scaled_instead_of_master_recipe(): void
    {
        $rnd = User::factory()->rnd()->create();
        $rice = FsItem::factory()->create([
            'name' => 'Rice',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'purchase_price' => 50,
            'units_per_purchase' => 1,
        ]);
        $master = FoodServiceRecipe::create([
            'rnd_user_id' => $rnd->id,
            'name' => 'Master Rice',
            'servings' => 10,
        ]);
        $day = new MenuCycleDay([
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'servings_override' => 100,
            'recipe_override' => [
                'name' => 'Slot Rice',
                'reference_servings' => 20,
                'prep_notes' => 'Slot only',
                'ingredients' => [[
                    'fs_item_id' => $rice->id,
                    'name' => 'Rice',
                    'quantity' => 2,
                    'unit' => 'kg',
                ]],
            ],
        ]);
        $day->setRelation('recipe', $master);
        $day->setRelation('fsItem', null);

        $entry = MenuCycleCostService::entryForDay($day);
        $result = MenuCycleCostService::aggregate([$entry]);

        $this->assertSame(20, $entry['recipe']['servings']);
        $this->assertSame('Slot Rice', $entry['recipe']['name']);
        $this->assertEqualsWithDelta(10, $result['ingredient_usage'][0]['quantity'], 0.001);
        $this->assertEqualsWithDelta(500, $result['total_cost'], 0.001);
    }

    public function test_cycle_grid_save_preserves_override_only_for_same_source(): void
    {
        $rnd = User::factory()->rnd()->create();
        $first = FoodServiceRecipe::create(['rnd_user_id' => $rnd->id, 'name' => 'First', 'servings' => 10]);
        $second = FoodServiceRecipe::create(['rnd_user_id' => $rnd->id, 'name' => 'Second', 'servings' => 10]);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id, 'is_active' => false]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'recipe_id' => $first->id,
            'recipe_override' => ['name' => 'Customized', 'reference_servings' => 10, 'ingredients' => []],
        ]);

        $payload = fn (FoodServiceRecipe $recipe) => ['days' => [[
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'recipe_id' => $recipe->uuid,
            'quantity' => 1,
        ]]];

        $this->actingAs($rnd)->patchJson("/api/fss/menu-cycles/{$cycle->uuid}", $payload($first))->assertOk();
        $this->assertDatabaseHas('menu_cycle_days', ['menu_cycle_id' => $cycle->id, 'recipe_id' => $first->id]);
        $this->assertSame('Customized', $cycle->days()->first()->recipe_override['name']);

        $this->actingAs($rnd)->patchJson("/api/fss/menu-cycles/{$cycle->uuid}", $payload($second))->assertOk();
        $this->assertNull($cycle->days()->first()->recipe_override);
    }

    public function test_cycle_can_store_ordered_multiple_lines_in_one_meal_and_exposes_public_line_ids(): void
    {
        $rnd = User::factory()->rnd()->create();
        $recipe = FoodServiceRecipe::create(['rnd_user_id' => $rnd->id, 'name' => 'Paksiw na Bangus', 'servings' => 20]);
        $rice = FsItem::factory()->create(['name' => 'Sautéed Vegetables']);

        $response = $this->actingAs($rnd)->postJson('/api/fss/menu-cycles', [
            'week_start_date' => '2026-09-07',
            'days' => [
                [
                    'day_of_week' => 'Monday',
                    'meal_type' => 'lunch',
                    'line_order' => 1,
                    'recipe_id' => $recipe->uuid,
                    'quantity' => 1,
                ],
                [
                    'day_of_week' => 'Monday',
                    'meal_type' => 'lunch',
                    'line_order' => 2,
                    'fs_item_id' => $rice->uuid,
                    'quantity' => 1,
                ],
            ],
        ])->assertCreated();

        $cycle = MenuCycle::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $lines = $cycle->days()->orderBy('line_order')->get();

        $this->assertCount(2, $lines);
        $this->assertSame([1, 2], $lines->pluck('line_order')->all());
        $this->assertSame($lines->pluck('uuid')->all(), collect($response->json('data.days'))->pluck('id')->all());
        $this->assertSame([1, 2], collect($response->json('data.days'))->pluck('line_order')->all());
        $this->assertNotSame($lines[0]->uuid, $lines[1]->uuid);
    }

    public function test_line_specific_slot_route_uses_public_uuid_and_rejects_cross_cycle_access(): void
    {
        $rnd = User::factory()->rnd()->create();
        ['cycle' => $cycle, 'day' => $line] = $this->recipeSlot($rnd);
        $otherCycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id]);

        $this->actingAs($rnd)
            ->getJson("/api/fss/menu-cycles/{$cycle->uuid}/lines/{$line->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $line->uuid)
            ->assertJsonPath('data.line_order', 1);

        $this->actingAs($rnd)
            ->getJson("/api/fss/menu-cycles/{$otherCycle->uuid}/lines/{$line->uuid}")
            ->assertNotFound();
    }

    public function test_fss_can_view_master_slot_details_but_cannot_update_them(): void
    {
        $rnd = User::factory()->rnd()->create();
        $fss = User::factory()->fss()->create();
        ['cycle' => $cycle] = $this->recipeSlot($rnd);
        $url = "/api/fss/menu-cycles/{$cycle->uuid}/slots/Monday/lunch";

        $this->actingAs($fss)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.name', 'Master Adobo')
            ->assertJsonPath('data.source', 'master')
            ->assertJsonPath('data.reference_servings', 20)
            ->assertJsonPath('data.planned_servings', 100)
            ->assertJsonPath('data.ingredients.0.quantity', 2);

        $this->actingAs($fss)->patchJson($url, [])->assertForbidden();
    }

    public function test_adjust_to_taste_ingredient_remains_measured_and_scaled_in_menu_profile_and_override(): void
    {
        $rnd = User::factory()->rnd()->create();
        ['recipe' => $recipe, 'cycle' => $cycle] = $this->recipeSlot($rnd);
        $salt = FsItem::factory()->create([
            'name' => 'Salt',
            'base_unit' => 'kg',
            'include_in_generated_lists' => false,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id' => $salt->id,
            'quantity' => 2,
            'unit' => 'tbsp',
            'adjust_to_taste' => true,
        ]);
        $url = "/api/fss/menu-cycles/{$cycle->uuid}/slots/Monday/lunch";

        $this->actingAs($rnd)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.ingredients.1.quantity', 2)
            ->assertJsonPath('data.ingredients.1.scaled_quantity', 10)
            ->assertJsonPath('data.ingredients.1.adjust_to_taste', true);

        $this->actingAs($rnd)->patchJson($url, [
            'name' => 'Ward Adobo',
            'reference_servings' => 20,
            'ingredients' => [
                ['fs_item_id' => $recipe->ingredients()->first()->fsItem->uuid, 'quantity' => 2, 'unit' => 'kg'],
                ['fs_item_id' => $salt->uuid, 'quantity' => 2, 'unit' => 'tbsp', 'adjust_to_taste' => true],
            ],
        ])->assertOk()->assertJsonPath('data.ingredients.1.scaled_quantity', 10)
            ->assertJsonPath('data.ingredients.1.adjust_to_taste', true);

        $this->assertEquals(2, $cycle->days()->first()->recipe_override['ingredients'][1]['quantity']);
        $this->assertTrue($cycle->days()->first()->recipe_override['ingredients'][1]['adjust_to_taste']);
    }

    public function test_measured_non_auto_ingredient_scales_in_recipe_unit_without_automatic_cost(): void
    {
        $rnd = User::factory()->rnd()->create();
        ['recipe' => $recipe, 'cycle' => $cycle] = $this->recipeSlot($rnd);
        $salt = FsItem::factory()->create([
            'name' => 'Salt',
            'base_unit' => 'kg',
            'include_in_generated_lists' => false,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id' => $salt->id,
            'quantity' => 2,
            'unit' => 'tbsp',
        ]);

        $this->actingAs($rnd)
            ->getJson("/api/fss/menu-cycles/{$cycle->uuid}/slots/Monday/lunch")
            ->assertOk()
            ->assertJsonPath('data.ingredients.1.quantity', 2)
            ->assertJsonPath('data.ingredients.1.unit', 'tbsp')
            ->assertJsonPath('data.ingredients.1.scaled_quantity', 10)
            ->assertJsonPath('data.ingredients.1.scaled_cost', 0)
            ->assertJsonPath('data.total_cost', 1000);
    }

    public function test_rnd_can_customize_and_restore_one_slot_without_changing_master(): void
    {
        $rnd = User::factory()->rnd()->create();
        ['item' => $item, 'recipe' => $recipe, 'cycle' => $cycle, 'day' => $day] = $this->recipeSlot($rnd);
        $url = "/api/fss/menu-cycles/{$cycle->uuid}/slots/Monday/lunch";
        $payload = [
            'name' => 'Ward Adobo',
            'reference_servings' => 25,
            'planned_servings' => 100,
            'prep_notes' => 'Less salty',
            'ingredients' => [[
                'fs_item_id' => $item->uuid,
                'quantity' => 3,
                'unit' => 'kg',
            ]],
        ];

        $this->actingAs($rnd)->patchJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('data.source', 'custom')
            ->assertJsonPath('data.total_cost', 1200);

        $this->assertSame('Master Adobo', $recipe->fresh()->name);
        $this->assertSame('Ward Adobo', $day->fresh()->recipe_override['name']);
        $this->assertNull($day->fresh()->servings_override);

        $this->actingAs($rnd)->getJson("/api/fss/menu-cycles/{$cycle->uuid}")
            ->assertOk()
            ->assertJsonPath('data.days.0.recipe.name', 'Ward Adobo')
            ->assertJsonPath('data.days.0.recipe.servings', 25)
            ->assertJsonPath('data.days.0.has_recipe_override', true);

        $recipe->update(['name' => 'Later library edit']);
        $this->actingAs($rnd)->deleteJson($url)
            ->assertOk()
            ->assertJsonPath('data.name', 'Master Adobo')
            ->assertJsonPath('data.portion_multiplier', 1);
        $this->assertSame($day->fresh()->recipe_original, $day->fresh()->recipe_override);
    }

    public function test_slot_update_validates_ingredients_and_rejects_locked_slots(): void
    {
        $rnd = User::factory()->rnd()->create();
        ['item' => $item, 'cycle' => $cycle, 'day' => $day] = $this->recipeSlot($rnd, locked: true);
        $url = "/api/fss/menu-cycles/{$cycle->uuid}/slots/Monday/lunch";
        $payload = [
            'name' => 'Locked',
            'reference_servings' => 20,
            'planned_servings' => 100,
            'ingredients' => [
                ['fs_item_id' => $item->uuid, 'quantity' => 2, 'unit' => 'kg'],
                ['fs_item_id' => $item->uuid, 'quantity' => 3, 'unit' => 'kg'],
            ],
        ];

        $this->actingAs($rnd)->patchJson($url, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ingredients.1.fs_item_id');

        $payload['ingredients'] = [$payload['ingredients'][0]];
        $this->actingAs($rnd)->patchJson($url, $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'This menu item is locked to a purchase order.');

        $day->update(['po_snapshot' => [
            'name' => 'Frozen Adobo',
            'prep_notes' => 'Frozen notes',
            'servings' => 20,
            'population' => 100,
            'total_cost' => 999,
            'cost_per_head' => 9.99,
            'ingredient_usage' => [[
                'fs_item_id' => $item->id,
                'name' => 'Chicken',
                'unit' => 'kg',
                'quantity' => 10,
                'cost' => 999,
            ]],
        ]]);
        $item->update(['purchase_price' => 500]);

        $this->actingAs($rnd)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.name', 'Frozen Adobo')
            ->assertJsonPath('data.total_cost', 999)
            ->assertJsonPath('data.ingredients.0.quantity', 2)
            ->assertJsonPath('data.ingredients.0.scaled_quantity', 10);
    }
}
