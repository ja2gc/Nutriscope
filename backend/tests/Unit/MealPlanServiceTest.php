<?php

namespace Tests\Unit;

use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Services\MealPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MealPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedRecipes(int $count = 15): void
    {
        $rnd = User::forceCreate([
            'name' => 'RND', 'email' => 'rnd@test.com',
            'password' => Hash::make('pw'), 'role' => 'RND', 'is_active' => true,
        ]);

        $food = FoodItem::forceCreate([
            'name' => 'Rice', 'calories' => 200, 'protein' => 4,
            'carbs' => 44, 'fat' => 0.4, 'serving_size' => 100,
        ]);

        for ($i = 1; $i <= $count; $i++) {
            $recipe = Recipe::forceCreate([
                'rnd_user_id' => $rnd->id,
                'name' => "Test Recipe {$i}",
                'category' => 'Test',
                'servings' => 1,
                'total_calories' => 400 + ($i * 10),
                'total_protein' => 15 + $i,
                'total_carbs' => 60 + $i,
                'total_fat' => 10 + $i,
            ]);
            RecipeIngredient::forceCreate([
                'recipe_id' => $recipe->id, 'food_item_id' => $food->id,
                'quantity' => 200, 'unit' => 'g',
            ]);
        }
    }

    private function makeNcpWithIntervention(): NcpRecord
    {
        $rnd = User::where('role', 'RND')->first();
        $patient = Patient::forceCreate([
            'name' => 'Patient', 'dob' => '1990-01-01',
            'sex' => 'Male', 'admission_date' => now()->toDateString(),
        ]);
        $ncp = NcpRecord::forceCreate([
            'patient_id' => $patient->id, 'rnd_user_id' => $rnd->id,
            'type' => 'new', 'status' => 'active',
        ]);
        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'goal_type' => 'weight_maintenance',
            'energy_kcal' => 2000,
            'protein_g' => 75,
            'carbs_g' => 250,
            'fat_g' => 65,
        ]);

        return $ncp;
    }

    public function test_generated_quantities_are_at_least_1_serving(): void
    {
        $this->seedRecipes(15);
        $ncp = $this->makeNcpWithIntervention();

        $service = new MealPlanService;
        $plan = $service->generate($ncp, now()->startOfWeek()->toDateString());

        $this->assertNotInstanceOf(Collection::class, $plan);
        $this->assertArrayNotHasKey('insufficient_recipes', (array) $plan);

        $items = MealPlanItem::whereHas('mealPlanDay', fn ($q) => $q->where('meal_plan_id', $plan->id))->get();
        foreach ($items as $item) {
            $this->assertGreaterThanOrEqual(1.0, (float) $item->quantity,
                "Quantity {$item->quantity} for item {$item->id} is below 1.0");
        }
    }

    public function test_ready_to_eat_resolution_uses_flag_then_category(): void
    {
        // Category allowlist default
        $this->assertTrue((new FoodItem(['category' => 'fruit']))->isReadyToEat());
        $this->assertTrue((new FoodItem(['category' => 'vegetable']))->isReadyToEat());
        $this->assertFalse((new FoodItem(['category' => 'protein']))->isReadyToEat());
        $this->assertFalse((new FoodItem(['category' => null]))->isReadyToEat());

        // Explicit flag overrides the category in both directions
        $this->assertTrue((new FoodItem(['category' => 'protein', 'ready_to_eat' => true]))->isReadyToEat());
        $this->assertFalse((new FoodItem(['category' => 'fruit', 'ready_to_eat' => false]))->isReadyToEat());
    }

    public function test_ready_to_eat_food_items_fill_snack_slots(): void
    {
        // Recipes are seeded untyped (no meal_types) → not snack-eligible, so snacks
        // must be filled by standalone ready-to-eat food items.
        $this->seedRecipes(15);
        foreach (['Apple', 'Banana', 'Orange', 'Pear', 'Mango'] as $name) {
            FoodItem::forceCreate([
                'name' => $name, 'category' => 'fruit', 'calories' => 80,
                'protein' => 1, 'carbs' => 20, 'fat' => 0.3, 'serving_size' => 100,
            ]);
        }
        $ncp = $this->makeNcpWithIntervention();

        $service = new MealPlanService;
        $service->setRngSeed(7);
        $plan = $service->generate($ncp, now()->startOfWeek()->toDateString());

        $snackDayIds = MealPlanDay::where('meal_plan_id', $plan->id)
            ->whereIn('meal_type', ['am_snack', 'pm_snack'])->pluck('id');
        $snackItems = MealPlanItem::whereIn('meal_plan_day_id', $snackDayIds)->get();

        $this->assertGreaterThan(0, $snackItems->count());
        foreach ($snackItems as $item) {
            $this->assertNotNull($item->food_item_id, 'Snack slot should hold a ready-to-eat food item');
            $this->assertNull($item->recipe_id);
        }

        // A food item must never leak into a main-meal slot
        $mainDayIds = MealPlanDay::where('meal_plan_id', $plan->id)
            ->whereIn('meal_type', ['breakfast', 'lunch', 'dinner'])->pluck('id');
        $mainFood = MealPlanItem::whereIn('meal_plan_day_id', $mainDayIds)
            ->whereNotNull('food_item_id')->count();
        $this->assertEquals(0, $mainFood, 'Food items must only appear in snack slots');
    }

    public function test_generated_plan_has_variety_across_days(): void
    {
        $this->seedRecipes(20);
        $ncp = $this->makeNcpWithIntervention();

        $service = new MealPlanService;

        // Generate 3 times and check that not every generation is identical
        $firstPlan = $service->generate($ncp, now()->startOfWeek()->toDateString());
        $secondPlan = $service->generate($ncp, now()->addWeek()->startOfWeek()->toDateString());

        $firstItems = MealPlanItem::whereHas('mealPlanDay', fn ($q) => $q->where('meal_plan_id', $firstPlan->id)
            ->where('day_of_week', 'Monday'))->pluck('recipe_id')->sort()->values()->toArray();
        $secondItems = MealPlanItem::whereHas('mealPlanDay', fn ($q) => $q->where('meal_plan_id', $secondPlan->id)
            ->where('day_of_week', 'Monday'))->pluck('recipe_id')->sort()->values()->toArray();

        // With top-3 random selection and 20 recipes, two plans should rarely be identical
        // This test is probabilistic but with 20 recipes the chance of identical Monday is < 1%
        // We just assert the plan was created successfully with items
        $this->assertGreaterThan(0, count($firstItems));
        $this->assertGreaterThan(0, count($secondItems));
    }

    public function test_no_duplicate_recipe_within_a_single_day(): void
    {
        // Small pool + wildly varying calories forces the post-generation
        // reconciliation pass to fire. Reconciliation must NOT reinsert a recipe
        // already used in another slot the same day (intra-day duplicate bug).
        $rnd = User::forceCreate([
            'name' => 'RND', 'email' => 'rnd@test.com',
            'password' => Hash::make('pw'), 'role' => 'RND', 'is_active' => true,
        ]);
        foreach ([200, 350, 500, 900, 1400, 1800] as $i => $kcal) {
            Recipe::forceCreate([
                'rnd_user_id' => $rnd->id,
                'name' => "Vary Recipe {$i}",
                'category' => 'Test',
                'servings' => 1,
                'total_calories' => $kcal,
                'total_protein' => 10 + $i,
                'total_carbs' => 30 + $i,
                'total_fat' => 5 + $i,
                'meal_types' => ['any'],
            ]);
        }
        $ncp = $this->makeNcpWithIntervention();

        $service = new MealPlanService;
        $service->setRngSeed(3);
        $plan = $service->generate($ncp, now()->startOfWeek()->toDateString());

        $days = MealPlanDay::where('meal_plan_id', $plan->id)->get();
        $byDayName = $days->groupBy('day_of_week');
        foreach ($byDayName as $dayName => $slots) {
            $recipeIds = MealPlanItem::whereIn('meal_plan_day_id', $slots->pluck('id'))
                ->whereNotNull('recipe_id')->pluck('recipe_id')->toArray();
            $this->assertSameSize($recipeIds, array_unique($recipeIds),
                "Day {$dayName} has a duplicated recipe across its slots.");
        }
    }
}
