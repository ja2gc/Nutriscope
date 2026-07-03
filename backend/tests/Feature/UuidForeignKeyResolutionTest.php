<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\NcpRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the INBOUND uuid→id resolution for request bodies. Frontend pickers submit a
 * related model's public uuid; the FormRequests validate it (exists:...,uuid), then in
 * passedValidation() resolve it to the internal integer FK and merge() it into the input
 * bag — so validated() surfaces the resolved integer (the validated() overrides read the
 * post-merge input, they do NOT revert to the raw uuid).
 *
 * These tests assert the database stores the integer FK, not the uuid string. Deleting the
 * validated() overrides (or the trait's) would make validated() return the raw uuid and
 * either error the insert or corrupt the FK — so this suite is the guard against that.
 */
class UuidForeignKeyResolutionTest extends TestCase
{
    use RefreshDatabase;

    /** StoreMenuCycleRequest — custom validated() for the nested days[] array. */
    public function test_menu_cycle_store_resolves_uuid_day_fks_to_integer_ids(): void
    {
        $rnd    = User::factory()->create(['role' => 'RND']);
        $recipe = FoodServiceRecipe::create(['name' => 'R', 'rnd_user_id' => $rnd->id, 'servings' => 1]);
        $fsItem = FsItem::factory()->create();

        $this->actingAs($rnd)->postJson('/api/fss/menu-cycles', [
            'name' => 'Cycle',
            'days' => [
                ['day_of_week' => 'Monday',  'meal_type' => 'lunch', 'recipe_id'  => $recipe->uuid, 'quantity' => 1],
                ['day_of_week' => 'Tuesday', 'meal_type' => 'lunch', 'fs_item_id' => $fsItem->uuid, 'quantity' => 1],
            ],
        ])->assertCreated();

        // The public uuids submitted in the body must land as the internal integer FKs.
        $this->assertDatabaseHas('menu_cycle_days', ['recipe_id' => $recipe->id]);
        $this->assertDatabaseHas('menu_cycle_days', ['fs_item_id' => $fsItem->id]);
    }

    /** StoreRecipeRequest — custom validated() for the nested ingredients[] array. */
    public function test_recipe_store_resolves_uuid_ingredient_fk_to_integer_id(): void
    {
        $rnd      = User::factory()->create(['role' => 'RND']);
        $foodItem = FoodItem::factory()->create();

        $this->actingAs($rnd)->postJson('/api/rnd/recipes', [
            'name'        => 'Soup',
            'ingredients' => [
                ['food_item_id' => $foodItem->uuid, 'quantity' => 100, 'unit' => 'g'],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('recipe_ingredients', ['food_item_id' => $foodItem->id]);
    }

    /** StoreMealPlanItemRequest — the ResolvesUuidForeignKeys trait for a flat FK field. */
    public function test_meal_plan_item_store_resolves_uuid_fk_to_integer_id(): void
    {
        $rnd          = User::factory()->create(['role' => 'RND']);
        $ncpRecord    = NcpRecord::factory()->create();
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncpRecord->id]);
        $mealPlan     = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $ncpRecord->patient_id,
        ]);
        $day      = MealPlanDay::factory()->create(['meal_plan_id' => $mealPlan->id]);
        $foodItem = FoodItem::factory()->create();

        $this->actingAs($rnd)->postJson(
            "/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$mealPlan->uuid}/days/{$day->uuid}/items",
            ['food_item_id' => $foodItem->uuid, 'quantity' => 1, 'unit' => 'serving'],
        )->assertCreated();

        $this->assertDatabaseHas('meal_plan_items', ['food_item_id' => $foodItem->id]);
    }
}
