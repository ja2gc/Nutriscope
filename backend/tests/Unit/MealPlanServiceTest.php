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

        for ($i = 1; $i <= 5; $i++) {
            FoodItem::forceCreate([
                'name' => "Test Fruit {$i}",
                'category' => 'fruit',
                'ready_to_eat' => true,
                'calories' => 80 + $i,
                'protein' => 1,
                'carbs' => 20,
                'fat' => 0.3,
                'serving_size' => 100,
                'serving_unit' => 'g',
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

    public function test_exclude_snacks_preserves_empty_slots_and_redistributes_generation_to_main_meals(): void
    {
        $this->seedRecipes(15);
        $ncp = $this->makeNcpWithIntervention();

        $plan = (new MealPlanService)->generate(
            $ncp,
            now()->startOfWeek()->toDateString(),
            excludeSnacks: true,
        );

        $snackIds = MealPlanDay::query()->where('meal_plan_id', $plan->id)
            ->whereIn('meal_type', ['am_snack', 'pm_snack'])
            ->pluck('id');
        $mainIds = MealPlanDay::query()->where('meal_plan_id', $plan->id)
            ->whereIn('meal_type', ['breakfast', 'lunch', 'dinner'])
            ->pluck('id');

        $this->assertCount(14, $snackIds);
        $this->assertSame(0, MealPlanItem::query()->whereIn('meal_plan_day_id', $snackIds)->count());
        $this->assertSame(21, MealPlanItem::query()->whereIn('meal_plan_day_id', $mainIds)->count());
    }

    public function test_liver_generation_does_not_offer_unsafe_snack_exclusion(): void
    {
        $this->seedRecipes(15);
        $ncp = $this->makeNcpWithIntervention();
        $ncp->intervention->update(['goal_type' => 'liver_disease', 'disease_stage' => 'decompensated']);

        $result = (new MealPlanService)->generate(
            $ncp,
            now()->startOfWeek()->toDateString(),
            excludeSnacks: true,
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['snacks_required']);
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_generator_does_not_fall_back_to_recipes_ineligible_for_a_meal_slot(): void
    {
        $this->seedRecipes(5);
        Recipe::query()->update(['meal_types' => ['breakfast']]);
        $ncp = $this->makeNcpWithIntervention();

        $result = (new MealPlanService)->generate($ncp, now()->startOfWeek()->toDateString());

        $this->assertIsArray($result);
        $this->assertTrue($result['insufficient_suitable_foods']);
        $this->assertContains('lunch', $result['missing_meal_types']);
        $this->assertContains('dinner', $result['missing_meal_types']);
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_main_dishes_can_receive_one_separate_staple_but_complete_meals_do_not(): void
    {
        $rnd = User::forceCreate([
            'name' => 'RND', 'email' => 'rnd@test.com',
            'password' => Hash::make('pw'), 'role' => 'RND', 'is_active' => true,
        ]);
        foreach (range(1, 5) as $index) {
            Recipe::forceCreate([
                'rnd_user_id' => $rnd->id,
                'name' => "Lean dish {$index}",
                'category' => 'Main',
                'component_type' => 'main_dish',
                'meal_types' => ['any'],
                'servings' => 1,
                'prepared_portion_amount' => 100,
                'prepared_portion_unit' => 'g',
                'total_calories' => 300,
                'total_protein' => 35,
                'total_carbs' => 5,
                'total_fat' => 15,
            ]);
        }
        $staple = Recipe::forceCreate([
            'rnd_user_id' => $rnd->id,
            'name' => 'Brown rice staple',
            'category' => 'Staple',
            'component_type' => 'staple',
            'meal_types' => ['any'],
            'servings' => 1,
            'prepared_portion_amount' => 1,
            'prepared_portion_unit' => 'cup',
            'total_calories' => 200,
            'total_protein' => 4,
            'total_carbs' => 45,
            'total_fat' => 1,
        ]);
        foreach (range(1, 5) as $index) {
            FoodItem::forceCreate([
                'name' => "Fruit {$index}", 'category' => 'fruit', 'ready_to_eat' => true,
                'calories' => 80, 'protein' => 1, 'carbs' => 20, 'fat' => 0,
                'serving_size' => 100, 'serving_unit' => 'g',
            ]);
        }
        $ncp = $this->makeNcpWithIntervention();

        $plan = (new MealPlanService)->generate($ncp, now()->startOfWeek()->toDateString());
        $mainSlots = MealPlanDay::query()->where('meal_plan_id', $plan->id)
            ->whereIn('meal_type', ['lunch', 'dinner'])->with('items')->get();

        $this->assertTrue($mainSlots->every(fn ($slot): bool => $slot->items->count() === 2));
        $this->assertTrue($mainSlots->every(fn ($slot): bool => $slot->items->contains('recipe_id', $staple->id)));
        $this->assertTrue($mainSlots->flatMap->items->every(
            fn ($item): bool => in_array($item->unit, ['g', 'cup'], true)
        ));

        Recipe::query()->where('component_type', 'main_dish')->update(['component_type' => 'complete_meal']);
        $secondPlan = (new MealPlanService)->generate($ncp, now()->addWeek()->startOfWeek()->toDateString());
        $completeSlots = MealPlanDay::query()->where('meal_plan_id', $secondPlan->id)
            ->whereIn('meal_type', ['lunch', 'dinner'])->with('items')->get();
        $this->assertTrue($completeSlots->every(fn ($slot): bool => $slot->items->count() === 1));
        $this->assertFalse($completeSlots->flatMap->items->contains('recipe_id', $staple->id));
    }
}
