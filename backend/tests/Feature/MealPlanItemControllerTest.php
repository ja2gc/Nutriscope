<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private function setupPlan(): array
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncp->id]);
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);
        $day = MealPlanDay::factory()->create(['meal_plan_id' => $plan->id]);

        return compact('rnd', 'ncp', 'plan', 'day');
    }

    private function url(array $ctx, ?string $itemId = null): string
    {
        $base = "/api/rnd/ncp-records/{$ctx['ncp']->uuid}/meal-plans/{$ctx['plan']->uuid}/days/{$ctx['day']->uuid}/items";

        return $itemId ? "{$base}/{$itemId}" : $base;
    }

    public function test_new_menu_item_freezes_original_and_planned_ingredients_and_yield(): void
    {
        $ctx = $this->setupPlan();
        $food = FoodItem::factory()->create([
            'name' => 'Measured rice', 'serving_size' => 100, 'serving_unit' => 'g',
            'calories' => 100, 'protein' => 2, 'carbs' => 20, 'fat' => 1, 'water_g' => 70,
        ]);
        $recipe = Recipe::factory()->create(['name' => 'Five-serving rice', 'category' => 'meal', 'servings' => 5]);
        $ingredient = RecipeIngredient::create(['recipe_id' => $recipe->id, 'food_item_id' => $food->id, 'quantity' => 500, 'unit' => 'g']);
        $recipe->recalculateTotals();
        $response = $this->actingAs($ctx['rnd'])->postJson($this->url($ctx), [
            'recipe_id' => $recipe->uuid, 'quantity' => 1.2, 'unit' => 'serving',
        ])->assertCreated();
        $id = $response->json('data.id');
        $response->assertJsonPath('data.nutrient_snapshot.recipe_yield', 5)
            ->assertJsonPath('data.nutrient_snapshot.recipe_original_ingredients.0.quantity', 500)
            ->assertJsonPath('data.nutrient_snapshot.recipe_ingredients.0.quantity', 500)
            ->assertJsonPath('data.nutrient_snapshot.calories', 100);
        $this->patchJson($this->url($ctx, $id), [
            'quantity' => 1.2, 'ingredient_overrides' => [['id' => $ingredient->id, 'quantity' => 400]],
        ])->assertOk()->assertJsonPath('data.nutrient_snapshot.recipe_original_ingredients.0.quantity', 500)
            ->assertJsonPath('data.nutrient_snapshot.recipe_ingredients.0.quantity', 400)
            ->assertJsonPath('data.nutrient_snapshot.calories', 80);
        $recipe->update(['name' => 'Changed library title', 'servings' => 10]);
        $ingredient->update(['quantity' => 999]);
        $this->getJson($this->url($ctx))->assertOk()
            ->assertJsonPath('data.0.nutrient_snapshot.name', 'Five-serving rice')
            ->assertJsonPath('data.0.recipe_portion.yield', 5)
            ->assertJsonPath('data.0.recipe_portion.ingredients.0.quantity', 400);
        $food->update(['calories' => 999, 'water_g' => 99, 'name' => 'Changed library food']);
        $this->patchJson($this->url($ctx, $id), [
            'ingredient_overrides' => [['id' => $ingredient->id, 'quantity' => 300]],
        ])->assertOk()->assertJsonPath('data.nutrient_snapshot.recipe_yield', 5)
            ->assertJsonPath('data.nutrient_snapshot.calories', 60)
            ->assertJsonPath('data.nutrient_snapshot.water_g', 42)
            ->assertJsonPath('data.nutrient_snapshot.recipe_ingredients.0.name', 'Measured rice')
            ->assertJsonPath('data.nutrient_snapshot.recipe_original_ingredients.0.quantity', 500);
    }

    public function test_index_lists_items_for_a_day(): void
    {
        $ctx = $this->setupPlan();
        MealPlanItem::factory()->count(3)->create(['meal_plan_day_id' => $ctx['day']->id]);

        $this->actingAs($ctx['rnd'])
            ->getJson($this->url($ctx))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_ingredient_override_accepts_six_decimal_json_floats_without_rounding_extra_precision(): void
    {
        $ctx = $this->setupPlan();
        $recipe = Recipe::factory()->create(['servings' => 1]);
        $food = FoodItem::factory()->create();
        $ingredient = $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        $recipe->recalculateTotals();
        $id = $this->actingAs($ctx['rnd'])->postJson($this->url($ctx), [
            'recipe_id' => $recipe->uuid, 'quantity' => 1, 'unit' => 'serving',
        ])->assertCreated()->json('data.id');
        $this->patchJson($this->url($ctx, $id), [
            'ingredient_overrides' => [['id' => $ingredient->id, 'quantity' => 0.000001]],
        ])->assertOk()->assertJsonPath('data.nutrient_snapshot.recipe_ingredients.0.quantity', 0.000001);
        $this->patchJson($this->url($ctx, $id), [
            'ingredient_overrides' => [['id' => $ingredient->id, 'quantity' => 0.0000001]],
        ])->assertUnprocessable();
    }

    public function test_index_lists_items_for_an_older_intervention_plan_in_the_same_ncp(): void
    {
        $ctx = $this->setupPlan();
        MealPlanItem::factory()->create(['meal_plan_day_id' => $ctx['day']->id]);

        // A later plan must not make the older saved plan disappear from history.
        Intervention::factory()->create(['ncp_record_id' => $ctx['ncp']->id]);

        $this->actingAs($ctx['rnd'])
            ->getJson($this->url($ctx))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_legacy_library_food_snapshot_remains_readable(): void
    {
        $ctx = $this->setupPlan();
        $food = FoodItem::factory()->create([
            'calories' => 165.0,
            'protein' => 31.0,
            'carbs' => 0.0,
            'fat' => 3.6,
            'micronutrients' => ['sodium' => 74],
            'serving_size' => 100,
            'serving_unit' => 'g',
        ]);

        MealPlanItem::factory()->create([
            'meal_plan_day_id' => $ctx['day']->id,
            'food_item_id' => $food->id,
            'recipe_id' => null,
            'quantity' => 150,
            'unit' => 'g',
            'nutrient_snapshot' => ['name' => $food->name, 'calories' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6, 'micronutrients' => ['sodium' => 74], 'serving_size' => 100, 'serving_unit' => 'g'],
        ]);

        $this->actingAs($ctx['rnd'])->getJson($this->url($ctx))->assertOk()->assertJsonPath('data.0.nutrient_snapshot.calories', 165);
        $item = MealPlanItem::first();
        $this->assertNotNull($item->nutrient_snapshot);
        $this->assertEquals(165, $item->nutrient_snapshot['calories']);
        $this->assertEquals(74, $item->nutrient_snapshot['micronutrients']['sodium']);
        $this->assertEquals(100, $item->nutrient_snapshot['serving_size']);
    }

    public function test_legacy_usda_snapshot_remains_readable_without_fetching_it_again(): void
    {
        $ctx = $this->setupPlan();

        MealPlanItem::factory()->create([
            'meal_plan_day_id' => $ctx['day']->id,
            'food_item_id' => null,
            'recipe_id' => null,
            'fdc_id' => '331960',
            'quantity' => 100,
            'unit' => 'g',
            'nutrient_snapshot' => [
                'fdc_id' => 331960,
                'name' => 'Chicken breast',
                'serving_size' => 100,
                'serving_unit' => 'g',
                'calories' => 165.0,
                'protein' => 31.0,
                'carbs' => 0.0,
                'fat' => 3.6,
                'micronutrients' => ['sodium' => 74],
            ],
        ]);

        $this->actingAs($ctx['rnd'])->getJson($this->url($ctx))->assertOk()->assertJsonPath('data.0.nutrient_snapshot.calories', 165);
        $item = MealPlanItem::first();
        $this->assertEquals('331960', $item->fdc_id);
        $this->assertNull($item->food_item_id);
        $this->assertEquals(165, $item->nutrient_snapshot['calories']);
        $this->assertEquals(100, $item->nutrient_snapshot['serving_size']);
        $this->assertDatabaseCount('food_items', 0);
    }

    public function test_store_with_recipe_id_populates_snapshot(): void
    {
        $ctx = $this->setupPlan();
        $recipe = Recipe::factory()->create([
            'total_calories' => 420.0,
            'total_protein' => 25.0,
            'total_carbs' => 38.0,
            'total_fat' => 16.0,
            'servings' => 4,
            'prepared_portion_amount' => 125,
            'prepared_portion_unit' => 'g',
        ]);
        $food = FoodItem::factory()->create([
            'serving_size' => 100, 'serving_unit' => 'g',
            'calories' => 210, 'protein' => 12.5, 'carbs' => 19, 'fat' => 8,
        ]);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'food_item_id' => $food->id,
            'quantity' => 200,
            'unit' => 'g',
        ]);

        $response = $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'recipe_id' => $recipe->uuid,
                'quantity' => 1,
                'unit' => 'serving',
            ]);

        $response->assertCreated();
        $item = MealPlanItem::first();
        $this->assertEquals($recipe->id, $item->recipe_id);
        $this->assertNull($item->food_item_id);
        $this->assertNull($item->fdc_id);
        $this->assertEquals(105, $item->nutrient_snapshot['calories']);
        $this->assertEquals(1, $item->nutrient_snapshot['serving_size']);
        $this->assertEquals('serving', $item->nutrient_snapshot['serving_unit']);
    }

    public function test_store_rejects_multiple_sources(): void
    {
        $ctx = $this->setupPlan();
        $food = FoodItem::factory()->create();

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'food_item_id' => $food->uuid,
                'fdc_id' => '331960',
                'quantity' => 100,
                'unit' => 'g',
            ])
            ->assertUnprocessable();
    }

    public function test_store_rejects_no_source(): void
    {
        $ctx = $this->setupPlan();

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), ['quantity' => 100, 'unit' => 'g'])
            ->assertUnprocessable();
    }

    public function test_new_weekly_lines_reject_direct_ingredients_and_usda_sources(): void
    {
        $ctx = $this->setupPlan();
        $food = FoodItem::factory()->create();
        foreach ([['food_item_id' => $food->uuid], ['fdc_id' => '331960']] as $source) {
            $this->actingAs($ctx['rnd'])->postJson($this->url($ctx), $source + ['quantity' => 100, 'unit' => 'g'])
                ->assertUnprocessable()->assertJsonValidationErrors('recipe_id');
        }
        $this->assertDatabaseCount('meal_plan_items', 0);
    }

    public function test_new_menu_item_lines_require_servings_and_two_decimal_quantity(): void
    {
        $ctx = $this->setupPlan();
        $recipe = Recipe::factory()->create();
        $food = FoodItem::factory()->create();
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        foreach ([['quantity' => 1, 'unit' => 'g'], ['quantity' => 0.001, 'unit' => 'serving'], ['quantity' => 1.001, 'unit' => 'serving']] as $portion) {
            $this->actingAs($ctx['rnd'])->postJson($this->url($ctx), ['recipe_id' => $recipe->uuid] + $portion)
                ->assertUnprocessable();
        }
        $this->assertDatabaseCount('meal_plan_items', 0);
    }

    public function test_a_snack_menu_item_can_be_manually_added_to_any_main_meal(): void
    {
        $ctx = $this->setupPlan();
        $recipe = Recipe::factory()->create(['category' => 'snack', 'servings' => 1]);
        $food = FoodItem::factory()->create();
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        foreach (['breakfast', 'lunch', 'dinner'] as $slot) {
            $ctx['day']->update(['meal_type' => $slot]);
            $this->actingAs($ctx['rnd'])->postJson($this->url($ctx), ['recipe_id' => $recipe->uuid, 'quantity' => 0.5, 'unit' => 'serving'])
                ->assertCreated()->assertJsonPath('data.unit', 'serving');
        }
    }

    public function test_store_rejects_non_numeric_fdc_id(): void
    {
        $ctx = $this->setupPlan();

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'fdc_id' => 'abc123!',
                'quantity' => 100,
                'unit' => 'g',
            ])
            ->assertUnprocessable();
    }

    public function test_update_ignores_client_supplied_nutrient_snapshot(): void
    {
        $ctx = $this->setupPlan();
        $item = MealPlanItem::factory()->create([
            'meal_plan_day_id' => $ctx['day']->id,
            'nutrient_snapshot' => ['name' => 'Real', 'calories' => 100, 'protein' => 5, 'carbs' => 10, 'fat' => 2, 'micronutrients' => []],
        ]);

        $this->actingAs($ctx['rnd'])
            ->patchJson($this->url($ctx, $item->uuid), [
                'quantity' => 2,
                'nutrient_snapshot' => ['name' => 'FAKE', 'calories' => 99999],
            ])
            ->assertOk();

        $item->refresh();
        $this->assertEquals(100, $item->nutrient_snapshot['calories']); // server snapshot untouched
        $this->assertEquals(2, (float) $item->quantity);
    }

    public function test_update_recomputes_plan_snapshot_without_changing_source_recipe(): void
    {
        $ctx = $this->setupPlan();
        $food = FoodItem::factory()->create([
            'calories' => 100, 'protein' => 10, 'carbs' => 20, 'fat' => 5,
            'serving_size' => 100, 'micronutrients' => ['sodium' => 50],
        ]);
        $recipe = Recipe::factory()->create(['servings' => 1, 'name' => 'Soup']);
        $ingredient = RecipeIngredient::create([
            'recipe_id' => $recipe->id, 'food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g',
        ]);
        $item = MealPlanItem::factory()->create([
            'meal_plan_day_id' => $ctx['day']->id,
            'recipe_id' => $recipe->id,
            'nutrient_snapshot' => $recipe->planSnapshot(),
        ]);

        // Double the ingredient quantity → all nutrients double (servings = 1).
        $response = $this->actingAs($ctx['rnd'])
            ->patchJson($this->url($ctx, $item->uuid), [
                'ingredient_overrides' => [['id' => $ingredient->id, 'quantity' => 200]],
            ])
            ->assertOk();

        $response->assertJsonPath('data.recipe_portion.ingredients.0.quantity', 200);

        $item->refresh();
        $this->assertEquals(200, $item->nutrient_snapshot['calories']);
        $this->assertEquals(200, $item->nutrient_snapshot['recipe_ingredients'][0]['quantity']);
        $this->assertEquals(100, (float) $ingredient->fresh()->quantity);
    }

    public function test_clinical_override_uses_units_yield_water_and_micronutrients_consistently(): void
    {
        $ctx = $this->setupPlan();
        $food = FoodItem::factory()->create([
            'calories' => 100, 'protein' => 10, 'carbs' => 20, 'fat' => 5,
            'water_g' => 80, 'serving_size' => 100, 'serving_unit' => 'g', 'micronutrients' => ['sodium' => 50],
        ]);
        $recipe = Recipe::factory()->create(['servings' => 5]);
        $ingredient = $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 0.5, 'unit' => 'kg']);
        $item = MealPlanItem::factory()->create([
            'meal_plan_day_id' => $ctx['day']->id, 'recipe_id' => $recipe->id,
            'nutrient_snapshot' => $recipe->planSnapshot(),
        ]);
        $this->actingAs($ctx['rnd'])->patchJson($this->url($ctx, $item->uuid), [
            'ingredient_overrides' => [['id' => $ingredient->id, 'quantity' => 1]],
        ])->assertOk();
        $snapshot = $item->fresh()->nutrient_snapshot;
        $this->assertEquals(200, $snapshot['calories']);
        $this->assertEquals(20, $snapshot['protein']);
        $this->assertEquals(40, $snapshot['carbs']);
        $this->assertEquals(10, $snapshot['fat']);
        $this->assertEquals(160, $snapshot['water_g']);
        $this->assertEquals(100, $snapshot['micronutrients']['sodium']);
        $this->assertEquals(0.5, (float) $ingredient->fresh()->quantity);
    }

    public function test_recipe_item_response_includes_batch_yield_and_ingredient_units(): void
    {
        $ctx = $this->setupPlan();
        $food = FoodItem::factory()->create(['name' => 'Rice']);
        $recipe = Recipe::factory()->create(['servings' => 4]);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id, 'food_item_id' => $food->id, 'quantity' => 400, 'unit' => 'g',
        ]);
        MealPlanItem::factory()->create([
            'meal_plan_day_id' => $ctx['day']->id,
            'recipe_id' => $recipe->id,
        ]);

        $this->actingAs($ctx['rnd'])
            ->getJson($this->url($ctx))
            ->assertOk()
            ->assertJsonPath('data.0.recipe_portion.yield', 4)
            ->assertJsonPath('data.0.recipe_portion.ingredients.0.name', 'Rice')
            ->assertJsonPath('data.0.recipe_portion.ingredients.0.quantity', 400)
            ->assertJsonPath('data.0.recipe_portion.ingredients.0.unit', 'g');
    }

    public function test_destroy_removes_item(): void
    {
        $ctx = $this->setupPlan();
        $item = MealPlanItem::factory()->create(['meal_plan_day_id' => $ctx['day']->id]);

        $this->actingAs($ctx['rnd'])
            ->deleteJson($this->url($ctx, $item->uuid))
            ->assertNoContent();

        $this->assertDatabaseMissing('meal_plan_items', ['id' => $item->id]);
    }

    public function test_requires_authentication(): void
    {
        $ctx = $this->setupPlan();
        $this->getJson($this->url($ctx))->assertUnauthorized();
    }

    public function test_cannot_access_items_of_plan_from_another_ncp(): void
    {
        $a = $this->setupPlan();
        $b = $this->setupPlan();

        // Plan B's id under NCP A's URL must not resolve (MP-04).
        $mismatchUrl = "/api/rnd/ncp-records/{$a['ncp']->uuid}/meal-plans/{$b['plan']->uuid}/days/{$b['day']->uuid}/items";

        $this->actingAs($a['rnd'])
            ->getJson($mismatchUrl)
            ->assertNotFound();
    }

    public function test_cannot_delete_item_via_wrong_day(): void
    {
        $a = $this->setupPlan();
        $otherDay = MealPlanDay::factory()->create([
            'meal_plan_id' => $a['plan']->id,
            'day_of_week' => $a['day']->day_of_week,
            'meal_type' => $a['day']->meal_type === 'breakfast' ? 'dinner' : 'breakfast',
        ]);
        $item = MealPlanItem::factory()->create(['meal_plan_day_id' => $a['day']->id]);

        // item belongs to $a['day'], not $otherDay → 404.
        $url = "/api/rnd/ncp-records/{$a['ncp']->uuid}/meal-plans/{$a['plan']->uuid}/days/{$otherDay->uuid}/items/{$item->uuid}";

        $this->actingAs($a['rnd'])->deleteJson($url)->assertNotFound();
        $this->assertDatabaseHas('meal_plan_items', ['id' => $item->id]);
    }

    public function test_store_hard_blocks_allergen_conflict(): void
    {
        $ctx = $this->setupPlan();
        Assessment::forceCreate([
            'ncp_record_id' => $ctx['ncp']->id, 'weight' => 70, 'height' => 170,
            'allergies' => ['Peanuts'],
        ]);
        $food = FoodItem::factory()->create(['name' => 'Peanut sauce', 'allergens' => ['peanuts']]);
        $recipe = Recipe::factory()->create(['servings' => 1]);
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), ['recipe_id' => $recipe->uuid, 'quantity' => 1, 'unit' => 'serving'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['allergens']]);

        $this->assertDatabaseCount('meal_plan_items', 0);
    }

    public function test_store_warns_on_food_dislike_but_still_adds(): void
    {
        $ctx = $this->setupPlan();
        Assessment::forceCreate([
            'ncp_record_id' => $ctx['ncp']->id, 'weight' => 70, 'height' => 170,
            'food_dislikes' => ['broccoli'],
        ]);
        $food = FoodItem::factory()->create(['name' => 'Broccoli soup', 'allergens' => []]);
        $recipe = Recipe::factory()->create(['name' => 'Broccoli soup', 'servings' => 1]);
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);

        $res = $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), ['recipe_id' => $recipe->uuid, 'quantity' => 1, 'unit' => 'serving'])
            ->assertCreated();

        $this->assertNotEmpty($res->json('warnings'));
        $this->assertDatabaseCount('meal_plan_items', 1);
    }

    public function test_legacy_library_food_preserves_snapshot_water(): void
    {
        $ctx = $this->setupPlan();
        $food = FoodItem::factory()->create([
            'calories' => 165.0, 'protein' => 31.0, 'carbs' => 0.0, 'fat' => 3.6,
            'water_g' => 65.5,
            'serving_size' => 100, 'serving_unit' => 'g',
        ]);

        MealPlanItem::factory()->create([
            'meal_plan_day_id' => $ctx['day']->id, 'food_item_id' => $food->id, 'recipe_id' => null,
            'nutrient_snapshot' => ['name' => $food->name, 'calories' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6, 'micronutrients' => [], 'serving_size' => 100, 'serving_unit' => 'g', 'water_g' => 65.5],
        ]);
        $this->actingAs($ctx['rnd'])->getJson($this->url($ctx))->assertOk()->assertJsonPath('data.0.nutrient_snapshot.water_g', 65.5);

        $this->assertEquals(65.5, MealPlanItem::first()->nutrient_snapshot['water_g']);
    }

    public function test_recipe_snapshot_uses_per_person_water_and_yield(): void
    {
        $ctx = $this->setupPlan();
        $recipe = Recipe::factory()->create([
            'total_calories' => 400.0, 'total_protein' => 20.0,
            'total_carbs' => 40.0, 'total_fat' => 15.0, 'servings' => 2,
            'total_water_g' => 100,
            'prepared_portion_amount' => 1.5,
            'prepared_portion_unit' => 'cup',
        ]);
        $food = FoodItem::factory()->create([
            'serving_size' => 100, 'serving_unit' => 'g', 'water_g' => 100,
            'calories' => 400, 'protein' => 20, 'carbs' => 40, 'fat' => 15,
        ]);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'food_item_id' => $food->id,
            'quantity' => 100,
            'unit' => 'g',
        ]);

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), ['recipe_id' => $recipe->uuid, 'quantity' => 1, 'unit' => 'serving'])
            ->assertCreated();

        $snap = MealPlanItem::first()->nutrient_snapshot;
        $this->assertEquals(50, $snap['water_g']);
        $this->assertEquals(1, $snap['serving_size']);
        $this->assertEquals('serving', $snap['serving_unit']);
    }

    public function test_backfill_command_patches_snapshot_water_g(): void
    {
        $food = FoodItem::factory()->create(['water_g' => 72.3, 'usda_fdc_id' => null]);
        $item = MealPlanItem::factory()->create([
            'food_item_id' => $food->id,
            'nutrient_snapshot' => ['name' => 'Test', 'calories' => 100, 'protein' => 5,
                'carbs' => 20, 'fat' => 2, 'micronutrients' => [],
                'serving_size' => 100, 'serving_unit' => 'g'],
        ]);

        $this->artisan('food:backfill-water')->assertSuccessful();

        $item->refresh();
        $this->assertEquals(72.3, $item->nutrient_snapshot['water_g']);
    }
}
