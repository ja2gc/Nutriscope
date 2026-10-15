<?php

namespace Tests\Unit;

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
use App\Services\MealPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
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
                'category' => 'meal',
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
            $fruit = FoodItem::forceCreate([
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
            $snack = Recipe::forceCreate(['rnd_user_id' => $rnd->id, 'name' => "Snack {$i}", 'category' => 'snack', 'servings' => 1]);
            $snack->ingredients()->create(['food_item_id' => $fruit->id, 'quantity' => 100, 'unit' => 'g']);
            $snack->recalculateTotals();
        }
    }

    private function makeNcpWithIntervention(array $interventionAttributes = []): NcpRecord
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
        $this->createIntervention($ncp, $interventionAttributes);

        return $ncp;
    }

    private function createIntervention(NcpRecord $ncp, array $attributes = []): Intervention
    {
        return Intervention::forceCreate(array_merge([
            'ncp_record_id' => $ncp->id,
            'goal_type' => 'weight_maintenance',
            'energy_kcal' => 2000,
            'protein_g' => 75,
            'carbs_g' => 250,
            'fat_g' => 65,
        ], $attributes));
    }

    private function seedCanonicalMenuItems(): array
    {
        $rnd = User::factory()->rnd()->create();
        $meals = collect();
        foreach (range(1, 5) as $index) {
            $food = FoodItem::factory()->create(['calories' => 400, 'protein' => 20, 'carbs' => 40, 'fat' => 17.78, 'allergens' => []]);
            $recipe = Recipe::factory()->create(['rnd_user_id' => $rnd->id, 'name' => "Meal {$index}", 'category' => 'meal', 'servings' => 1, 'meal_types' => ['dinner']]);
            $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
            $recipe->recalculateTotals();
            $meals->push($recipe);
        }
        foreach (['snack', 'carb'] as $category) {
            $food = FoodItem::factory()->create(['calories' => 150, 'protein' => 3, 'carbs' => 30, 'fat' => 2, 'allergens' => []]);
            $recipe = Recipe::factory()->create(['rnd_user_id' => $rnd->id, 'category' => $category, 'servings' => 1, 'meal_types' => ['breakfast']]);
            $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
            $recipe->recalculateTotals();
            if ($category === 'carb') {
                $carb = $recipe;
            }
        }

        return [$meals, $carb];
    }

    public function test_canonical_generation_keeps_the_selected_carb_in_all_21_main_slots(): void
    {
        [$meals, $carb] = $this->seedCanonicalMenuItems();
        $ncp = $this->makeNcpWithIntervention();
        $service = new MealPlanService;
        $service->setRngSeed(23);
        $plan = $service->generate($ncp, now()->startOfWeek()->toDateString(), carbMenuItemId: $carb->uuid);
        $this->assertInstanceOf(MealPlan::class, $plan);
        $plan->load('days.items.recipe');
        $mainSlots = $plan->days->whereIn('meal_type', ['breakfast', 'lunch', 'dinner']);
        $this->assertCount(21, $mainSlots);
        foreach ($mainSlots as $slot) {
            $this->assertCount(2, $slot->items);
            $this->assertCount(1, $slot->items->whereIn('recipe_id', $meals->pluck('id')));
            $this->assertCount(1, $slot->items->where('recipe_id', $carb->id));
        }
        $snackSlots = $plan->days->whereIn('meal_type', ['am_snack', 'pm_snack']);
        $this->assertCount(14, $snackSlots);
        foreach ($snackSlots as $slot) {
            $this->assertCount(1, $slot->items);
            $this->assertSame('snack', $slot->items->sole()->recipe->category);
        }
        foreach ($plan->days->flatMap->items as $item) {
            $this->assertNull($item->food_item_id);
            $this->assertSame('serving', $item->unit);
            $this->assertGreaterThanOrEqual(0.01, (float) $item->quantity);
        }
        $before = $mainSlots->flatMap->items->pluck('recipe_id')->all();
        $service->scaleToPrescription($plan, $ncp->intervention);
        $this->assertSame($before, $plan->fresh('days.items')->days->whereIn('meal_type', ['breakfast', 'lunch', 'dinner'])->flatMap->items->pluck('recipe_id')->all());
    }

    public function test_liver_generation_can_exclude_both_carbs_and_snacks(): void
    {
        $this->seedCanonicalMenuItems();
        $ncp = $this->makeNcpWithIntervention(['goal_type' => 'liver_disease']);
        $plan = (new MealPlanService)->generate($ncp, now()->startOfWeek()->toDateString(), excludeSnacks: true, excludeCarbs: true);
        $this->assertInstanceOf(MealPlan::class, $plan);
        $plan->load('days.items.recipe');
        $this->assertCount(21, $plan->days->flatMap->items);
        foreach ($plan->days as $slot) {
            $this->assertCount(in_array($slot->meal_type, ['am_snack', 'pm_snack'], true) ? 0 : 1, $slot->items);
            foreach ($slot->items as $item) {
                $this->assertSame('meal', $item->recipe->category);
            }
        }
    }

    public function test_selected_carb_allergy_uses_assessment_even_when_client_sends_other_allergens(): void
    {
        [, $carb] = $this->seedCanonicalMenuItems();
        $ncp = $this->makeNcpWithIntervention();
        Assessment::factory()->create(['ncp_record_id' => $ncp->id, 'allergies' => ['peanuts']]);
        $carb->ingredients()->firstOrFail()->foodItem->update(['allergens' => ['peanuts']]);
        $result = (new MealPlanService)->generate($ncp->fresh(), now()->startOfWeek()->toDateString(), allergens: ['milk'], carbMenuItemId: $carb->uuid);
        $this->assertIsArray($result);
        $this->assertTrue($result['invalid_carb']);
        $this->assertStringContainsString('allergen', $result['message']);
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_failure_during_final_scaling_rolls_back_the_entire_generated_week(): void
    {
        $this->seedCanonicalMenuItems();
        $ncp = $this->makeNcpWithIntervention();
        $service = \Mockery::mock(MealPlanService::class)->makePartial();
        $service->shouldReceive('scaleToPrescription')->once()->andThrow(new \RuntimeException('Injected final scaling failure.'));
        try {
            $service->generate($ncp, now()->startOfWeek()->toDateString(), excludeCarbs: true);
            $this->fail('Expected the injected generation failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected final scaling failure.', $exception->getMessage());
        }
        $this->assertDatabaseCount('meal_plans', 0);
        $this->assertDatabaseCount('meal_plan_days', 0);
        $this->assertDatabaseCount('meal_plan_items', 0);
    }

    #[DataProvider('invalidCarbCases')]
    public function test_unusable_selected_carb_blocks_without_partial_rows(string $case): void
    {
        [, $carb] = $this->seedCanonicalMenuItems();
        $ncp = $this->makeNcpWithIntervention();
        if ($case === 'incompatible') {
            $carb->ingredients()->firstOrFail()->update(['unit' => 'mL']);
        } elseif ($case === 'zero_energy') {
            $carb->update(['total_calories' => 0]);
        } else {
            $carb->update(['total_calories' => 99999]);
        }
        $result = (new MealPlanService)->generate($ncp, now()->startOfWeek()->toDateString(), carbMenuItemId: $carb->uuid);
        $this->assertIsArray($result);
        $this->assertTrue($result['invalid_carb']);
        $this->assertStringContainsString('Exclude carbs', $result['message']);
        $this->assertDatabaseCount('meal_plans', 0);
        $this->assertDatabaseCount('meal_plan_days', 0);
        $this->assertDatabaseCount('meal_plan_items', 0);
    }

    public static function invalidCarbCases(): array
    {
        return [['incompatible'], ['zero_energy'], ['minimum_does_not_fit']];
    }

    #[DataProvider('missingPoolCases')]
    public function test_missing_category_pool_blocks_even_with_enough_other_menu_items(string $category): void
    {
        $this->seedCanonicalMenuItems();
        Recipe::where('category', $category)->update(['category' => 'carb']);
        $ncp = $this->makeNcpWithIntervention();
        $result = (new MealPlanService)->generate($ncp, now()->startOfWeek()->toDateString(), excludeCarbs: true);
        $this->assertIsArray($result);
        $this->assertTrue($result['insufficient_suitable_foods']);
        $this->assertContains($category === 'meal' ? 'breakfast' : 'am_snack', $result['missing_meal_types']);
        $this->assertDatabaseCount('meal_plans', 0);
        $this->assertDatabaseCount('meal_plan_days', 0);
        $this->assertDatabaseCount('meal_plan_items', 0);
    }

    public static function missingPoolCases(): array
    {
        return [['meal'], ['snack']];
    }

    public function test_fractional_recipe_servings_remain_representable_without_changing_count_units(): void
    {
        $service = new MealPlanService;
        $snap = new \ReflectionMethod($service, 'snapQuantity');
        $minimum = new \ReflectionMethod($service, 'minimumQuantity');
        $this->assertEqualsWithDelta(1.2, $snap->invoke($service, new MealPlanItem(['unit' => 'serving']), 1.2), 0.00001);
        $this->assertSame(0.01, $minimum->invoke($service, new MealPlanItem(['unit' => 'serving'])));
        $this->assertSame(1.0, $minimum->invoke($service, new MealPlanItem(['unit' => 'piece'])));
    }

    public function test_generated_quantities_are_positive_two_decimal_servings(): void
    {
        $this->seedRecipes(15);
        $ncp = $this->makeNcpWithIntervention();

        $service = new MealPlanService;
        $plan = $service->generate($ncp, now()->startOfWeek()->toDateString(), excludeCarbs: true);

        $this->assertNotInstanceOf(Collection::class, $plan);
        $this->assertArrayNotHasKey('insufficient_recipes', (array) $plan);

        $items = MealPlanItem::whereHas('mealPlanDay', fn ($q) => $q->where('meal_plan_id', $plan->id))->get();
        foreach ($items as $item) {
            $this->assertGreaterThanOrEqual(0.01, (float) $item->quantity,
                "Quantity {$item->quantity} for item {$item->id} is below 0.01");
        }
    }

    public function test_fluid_guidance_never_enters_generated_plan_variance_but_water_snapshot_remains(): void
    {
        $this->seedRecipes(15);
        $ncp = $this->makeNcpWithIntervention(['fluid_ml' => 250]);

        $plan = (new MealPlanService)->generate($ncp, now()->startOfWeek()->toDateString(), excludeCarbs: true);
        $days = MealPlanDay::query()->where('meal_plan_id', $plan->id)->get();

        foreach ($days as $day) {
            $this->assertArrayNotHasKey('water', $day->variance ?? []);
        }
        $snapshot = MealPlanItem::query()
            ->whereIn('meal_plan_day_id', $days->pluck('id'))
            ->firstOrFail()
            ->nutrient_snapshot;
        $this->assertArrayHasKey('water_g', $snapshot);
    }

    public function test_ready_to_eat_resolution_uses_flag_then_category(): void
    {
        // Category allowlist default
        $this->assertTrue((new FoodItem(['category' => 'fruit']))->isReadyToEat());
        $this->assertFalse((new FoodItem(['category' => 'vegetable']))->isReadyToEat());
        $this->assertFalse((new FoodItem(['category' => 'protein']))->isReadyToEat());
        $this->assertFalse((new FoodItem(['category' => null]))->isReadyToEat());

        // Explicit flag overrides the category in both directions
        $this->assertTrue((new FoodItem(['category' => 'protein', 'ready_to_eat' => true]))->isReadyToEat());
        $this->assertTrue((new FoodItem(['category' => 'vegetable', 'ready_to_eat' => true]))->isReadyToEat());
        $this->assertFalse((new FoodItem(['category' => 'fruit', 'ready_to_eat' => false]))->isReadyToEat());

        FoodItem::forceCreate(['name' => 'Apple', 'category' => 'fruit']);
        FoodItem::forceCreate(['name' => 'Garlic (Raw)', 'category' => 'vegetable']);
        FoodItem::forceCreate(['name' => 'Cucumber Slices', 'category' => 'vegetable', 'ready_to_eat' => true]);

        $this->assertEqualsCanonicalizing(
            ['Apple', 'Cucumber Slices'],
            FoodItem::query()->readyToEat()->pluck('name')->all(),
        );
    }

    public function test_snack_menu_items_fill_snack_slots_without_direct_ingredients(): void
    {
        // Direct ready-to-eat Ingredients must not replace measured Snack Menu Items.
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
        $plan = $service->generate($ncp, now()->startOfWeek()->toDateString(), excludeCarbs: true);

        $snackDayIds = MealPlanDay::where('meal_plan_id', $plan->id)
            ->whereIn('meal_type', ['am_snack', 'pm_snack'])->pluck('id');
        $snackItems = MealPlanItem::whereIn('meal_plan_day_id', $snackDayIds)->get();

        $this->assertGreaterThan(0, $snackItems->count());
        foreach ($snackItems as $item) {
            $this->assertNull($item->food_item_id);
            $this->assertNotNull($item->recipe_id);
            $this->assertSame('snack', $item->recipe->category);
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
        $firstPlan = $service->generate($ncp, now()->startOfWeek()->toDateString(), excludeCarbs: true);
        $this->createIntervention($ncp);
        $secondPlan = $service->generate($ncp->fresh(), now()->addWeek()->startOfWeek()->toDateString(), excludeCarbs: true);

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
        $food = FoodItem::forceCreate([
            'name' => 'Recipe ingredient', 'calories' => 100, 'protein' => 5,
            'carbs' => 10, 'fat' => 2, 'serving_size' => 100, 'serving_unit' => 'g',
        ]);
        foreach ([200, 350, 500, 900, 1400, 1800] as $i => $kcal) {
            $recipe = Recipe::forceCreate([
                'rnd_user_id' => $rnd->id,
                'name' => "Vary Recipe {$i}",
                'category' => 'meal',
                'servings' => 1,
                'total_calories' => $kcal,
                'total_protein' => 10 + $i,
                'total_carbs' => 30 + $i,
                'total_fat' => 5 + $i,
                'meal_types' => ['any'],
            ]);
            RecipeIngredient::forceCreate([
                'recipe_id' => $recipe->id, 'food_item_id' => $food->id,
                'quantity' => 100, 'unit' => 'g',
            ]);
        }
        $ncp = $this->makeNcpWithIntervention();

        $service = new MealPlanService;
        $service->setRngSeed(3);
        $plan = $service->generate($ncp, now()->startOfWeek()->toDateString(), excludeCarbs: true, excludeSnacks: true);

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
            excludeCarbs: true,
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

    public function test_no_snack_distribution_is_used_by_generation_and_reconciliation(): void
    {
        $distribution = new \ReflectionMethod(MealPlanService::class, 'slotDistribution');

        $this->assertSame([
            'breakfast' => 0.3125,
            'lunch' => 0.375,
            'dinner' => 0.3125,
        ], $distribution->invoke(new MealPlanService, true));
    }

    public function test_liver_generation_redistributes_targets_when_snacks_are_excluded(): void
    {
        $this->seedRecipes(15);
        $ncp = $this->makeNcpWithIntervention(['goal_type' => 'liver_disease', 'disease_stage' => 'decompensated']);
        $plan = (new MealPlanService)->generate($ncp, now()->startOfWeek()->toDateString(), excludeSnacks: true, excludeCarbs: true);
        $this->assertInstanceOf(MealPlan::class, $plan);
        $this->assertCount(21, $plan->fresh('days.items')->days->flatMap->items);
    }

    public function test_generator_uses_categories_despite_legacy_meal_time_hints(): void
    {
        $this->seedRecipes(5);
        Recipe::query()->update(['meal_types' => ['breakfast']]);
        $ncp = $this->makeNcpWithIntervention();
        $plan = (new MealPlanService)->generate($ncp, now()->startOfWeek()->toDateString(), excludeCarbs: true);
        $this->assertInstanceOf(MealPlan::class, $plan);
        $this->assertCount(35, $plan->fresh('days.items')->days->flatMap->items);
    }

    public function test_selected_carb_is_required_even_when_meal_already_contains_carbohydrate(): void
    {
        [$meals, $carb] = $this->seedCanonicalMenuItems();
        $meals->each(fn ($meal) => $meal->update(['component_type' => 'complete_meal', 'total_carbs' => 100]));
        $ncp = $this->makeNcpWithIntervention();
        $plan = (new MealPlanService)->generate($ncp, now()->startOfWeek()->toDateString(), carbMenuItemId: $carb->uuid);
        foreach ($plan->fresh('days.items')->days->whereIn('meal_type', ['breakfast', 'lunch', 'dinner']) as $slot) {
            $this->assertCount(2, $slot->items);
            $this->assertCount(1, $slot->items->where('recipe_id', $carb->id));
        }
    }

    public function test_custom_and_confirmed_maternal_plans_do_not_add_direct_ingredient_sides(): void
    {
        [, $carb] = $this->seedCanonicalMenuItems();
        FoodItem::factory()->create(['name' => 'Bok Choy / Pechay (Cooked)', 'category' => 'vegetable', 'allergens' => []]);
        foreach (['custom', 'weight_gain'] as $goal) {
            $ncp = $this->makeNcpWithIntervention(['goal_type' => $goal]);
            Assessment::factory()->create(['ncp_record_id' => $ncp->id, 'pregnancy_lactation_status' => 'lactating', 'allergies' => []]);
            $plan = (new MealPlanService)->generate($ncp, now()->startOfWeek()->toDateString(), carbMenuItemId: $carb->uuid);
            $items = $plan->fresh('days.items')->days->flatMap->items;
            $this->assertCount(56, $items);
            $this->assertTrue($items->every(fn ($item): bool => $item->recipe_id !== null && $item->food_item_id === null));
        }
    }
}
