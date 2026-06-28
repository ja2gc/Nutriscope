<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Recipe;
use App\Models\User;
use App\Services\UsdaService;
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
            'patient_id'      => $patient->id,
        ]);
        $day = MealPlanDay::factory()->create(['meal_plan_id' => $plan->id]);
        return compact('rnd', 'ncp', 'plan', 'day');
    }

    private function url(array $ctx, ?int $itemId = null): string
    {
        $base = "/api/rnd/ncp-records/{$ctx['ncp']->id}/meal-plans/{$ctx['plan']->id}/days/{$ctx['day']->id}/items";
        return $itemId ? "{$base}/{$itemId}" : $base;
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

    public function test_store_with_library_food_populates_snapshot(): void
    {
        $ctx = $this->setupPlan();
        $food = FoodItem::factory()->create([
            'calories'       => 165.0,
            'protein'        => 31.0,
            'carbs'          => 0.0,
            'fat'            => 3.6,
            'micronutrients' => ['sodium' => 74],
            'serving_size'   => 100,
            'serving_unit'   => 'g',
        ]);

        $response = $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'food_item_id' => $food->id,
                'quantity'     => 150,
                'unit'         => 'g',
            ]);

        $response->assertCreated();
        $item = MealPlanItem::first();
        $this->assertNotNull($item->nutrient_snapshot);
        $this->assertEquals(165, $item->nutrient_snapshot['calories']);
        $this->assertEquals(74, $item->nutrient_snapshot['micronutrients']['sodium']);
        $this->assertEquals(100, $item->nutrient_snapshot['serving_size']);
    }

    public function test_store_with_fdc_id_fetches_usda_and_populates_snapshot(): void
    {
        $ctx = $this->setupPlan();

        $this->mock(UsdaService::class, function ($mock) {
            $mock->shouldReceive('fetch')->with(331960)->once()->andReturn([
                'fdc_id'         => 331960,
                'name'           => 'Chicken breast',
                'calories'       => 165.0,
                'protein'        => 31.0,
                'carbs'          => 0.0,
                'fat'            => 3.6,
                'micronutrients' => ['sodium' => 74],
            ]);
        });

        $response = $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'fdc_id'   => '331960',
                'quantity' => 100,
                'unit'     => 'g',
            ]);

        $response->assertCreated();
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
            'total_protein'  => 25.0,
            'total_carbs'    => 38.0,
            'total_fat'      => 16.0,
            'servings'       => 4,
        ]);

        $response = $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'recipe_id' => $recipe->id,
                'quantity'  => 1,
                'unit'      => 'serving',
            ]);

        $response->assertCreated();
        $item = MealPlanItem::first();
        $this->assertEquals($recipe->id, $item->recipe_id);
        $this->assertNull($item->food_item_id);
        $this->assertNull($item->fdc_id);
        $this->assertEquals(420, $item->nutrient_snapshot['calories']);
        $this->assertEquals(4, $item->nutrient_snapshot['serving_size']);
    }

    public function test_store_rejects_multiple_sources(): void
    {
        $ctx = $this->setupPlan();
        $food = FoodItem::factory()->create();

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'food_item_id' => $food->id,
                'fdc_id'       => '331960',
                'quantity'     => 100,
                'unit'         => 'g',
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

    public function test_store_rejects_non_numeric_fdc_id(): void
    {
        $ctx = $this->setupPlan();

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'fdc_id'   => 'abc123!',
                'quantity' => 100,
                'unit'     => 'g',
            ])
            ->assertUnprocessable();
    }

    public function test_destroy_removes_item(): void
    {
        $ctx = $this->setupPlan();
        $item = MealPlanItem::factory()->create(['meal_plan_day_id' => $ctx['day']->id]);

        $this->actingAs($ctx['rnd'])
            ->deleteJson($this->url($ctx, $item->id))
            ->assertNoContent();

        $this->assertDatabaseMissing('meal_plan_items', ['id' => $item->id]);
    }

    public function test_requires_authentication(): void
    {
        $ctx = $this->setupPlan();
        $this->getJson($this->url($ctx))->assertUnauthorized();
    }

    public function test_store_hard_blocks_allergen_conflict(): void
    {
        $ctx = $this->setupPlan();
        \App\Models\Assessment::forceCreate([
            'ncp_record_id' => $ctx['ncp']->id, 'weight' => 70, 'height' => 170,
            'allergies' => ['Peanuts'],
        ]);
        $food = FoodItem::factory()->create(['name' => 'Peanut sauce', 'allergens' => ['peanuts']]);

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), ['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['allergens']]);

        $this->assertDatabaseCount('meal_plan_items', 0);
    }

    public function test_store_warns_on_food_dislike_but_still_adds(): void
    {
        $ctx = $this->setupPlan();
        \App\Models\Assessment::forceCreate([
            'ncp_record_id' => $ctx['ncp']->id, 'weight' => 70, 'height' => 170,
            'food_dislikes' => ['broccoli'],
        ]);
        $food = FoodItem::factory()->create(['name' => 'Broccoli soup', 'allergens' => []]);

        $res = $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), ['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g'])
            ->assertCreated();

        $this->assertNotEmpty($res->json('warnings'));
        $this->assertDatabaseCount('meal_plan_items', 1);
    }

    public function test_snapshot_includes_water_g_for_library_food(): void
    {
        $ctx  = $this->setupPlan();
        $food = FoodItem::factory()->create([
            'calories' => 165.0, 'protein' => 31.0, 'carbs' => 0.0, 'fat' => 3.6,
            'water_g'  => 65.5,
            'serving_size' => 100, 'serving_unit' => 'g',
        ]);

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), ['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g'])
            ->assertCreated();

        $this->assertEquals(65.5, MealPlanItem::first()->nutrient_snapshot['water_g']);
    }

    public function test_snapshot_sets_water_g_null_for_recipe(): void
    {
        $ctx    = $this->setupPlan();
        $recipe = Recipe::factory()->create([
            'total_calories' => 400.0, 'total_protein' => 20.0,
            'total_carbs' => 40.0, 'total_fat' => 15.0, 'servings' => 2,
        ]);

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), ['recipe_id' => $recipe->id, 'quantity' => 1, 'unit' => 'serving'])
            ->assertCreated();

        $snap = MealPlanItem::first()->nutrient_snapshot;
        $this->assertArrayHasKey('water_g', $snap);
        $this->assertNull($snap['water_g']);
    }

    public function test_backfill_command_patches_snapshot_water_g(): void
    {
        $food = FoodItem::factory()->create(['water_g' => 72.3, 'usda_fdc_id' => null]);
        $item = MealPlanItem::factory()->create([
            'food_item_id'       => $food->id,
            'nutrient_snapshot'  => ['name' => 'Test', 'calories' => 100, 'protein' => 5,
                                      'carbs' => 20, 'fat' => 2, 'micronutrients' => [],
                                      'serving_size' => 100, 'serving_unit' => 'g'],
        ]);

        $this->artisan('food:backfill-water')->assertSuccessful();

        $item->refresh();
        $this->assertEquals(72.3, $item->nutrient_snapshot['water_g']);
    }
}
