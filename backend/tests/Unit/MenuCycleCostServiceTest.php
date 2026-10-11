<?php

namespace Tests\Unit;

use App\Services\MenuCycleCostService;
use Tests\TestCase;

/**
 * Pure-logic test (no DB) for the menu-cycle costing engine. This is the single
 * source of truth that the planner summary, the procurement suggested list, and
 * the budget's "planned" figure all consume — so it is tested exhaustively.
 *
 * A menu entry scales its recipe to the ward population (one serving per head)
 * unless a per-entry servings_override is set.
 */
class MenuCycleCostServiceTest extends TestCase
{
    /** Rice recipe: 5000 g for 50 servings, ₱0.052/g. */
    private function riceRecipe(): array
    {
        return [
            'servings' => 50,
            'ingredients' => [
                ['fs_item_id' => 1, 'name' => 'Rice', 'quantity' => 5000, 'unit' => 'g', 'base_unit' => 'g', 'unit_cost' => 0.052],
            ],
        ];
    }

    public function test_single_entry_scales_to_population(): void
    {
        $out = MenuCycleCostService::aggregate([
            ['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'servings_override' => null, 'recipe' => $this->riceRecipe()],
        ], 100);

        // factor 100/50 = 2.0 → 10000 g rice → ₱520
        $this->assertEqualsWithDelta(520.0, $out['total_cost'], 1e-6);
        $this->assertEqualsWithDelta(5.20, $out['cost_per_head'], 1e-6);
        $this->assertEqualsWithDelta(520.0, $out['days']['Monday']['cost'], 1e-6);
        $this->assertEqualsWithDelta(5.20, $out['days']['Monday']['cost_per_head'], 1e-6);

        $this->assertCount(1, $out['ingredient_usage']);
        $this->assertEqualsWithDelta(10000.0, $out['ingredient_usage'][0]['quantity'], 1e-6);
        $this->assertEqualsWithDelta(520.0, $out['ingredient_usage'][0]['cost'], 1e-6);
    }

    public function test_two_days_sum_and_ingredient_usage_aggregates(): void
    {
        $out = MenuCycleCostService::aggregate([
            ['day_of_week' => 'Monday',  'meal_type' => 'lunch', 'servings_override' => null, 'recipe' => $this->riceRecipe()],
            ['day_of_week' => 'Tuesday', 'meal_type' => 'lunch', 'servings_override' => null, 'recipe' => $this->riceRecipe()],
        ], 100);

        $this->assertEqualsWithDelta(1040.0, $out['total_cost'], 1e-6);
        $this->assertEqualsWithDelta(520.0, $out['days']['Tuesday']['cost'], 1e-6);
        // one Rice line, quantity summed across both days
        $this->assertCount(1, $out['ingredient_usage']);
        $this->assertEqualsWithDelta(20000.0, $out['ingredient_usage'][0]['quantity'], 1e-6);
    }

    public function test_servings_override_beats_population(): void
    {
        $out = MenuCycleCostService::aggregate([
            ['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'servings_override' => 25, 'recipe' => $this->riceRecipe()],
        ], 100);

        // factor 25/50 = 0.5 → 2500 g → ₱130, ignoring population 100
        $this->assertEqualsWithDelta(130.0, $out['total_cost'], 1e-6);
    }

    public function test_ingredient_unit_is_converted_to_base(): void
    {
        $out = MenuCycleCostService::aggregate([
            ['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'servings_override' => null, 'recipe' => [
                'servings' => 50,
                'ingredients' => [
                    // entered as 5 kg but costed per gram → 5000 g × ₱0.052
                    ['fs_item_id' => 1, 'name' => 'Rice', 'quantity' => 5, 'unit' => 'kg', 'base_unit' => 'g', 'unit_cost' => 0.052],
                ],
            ]],
        ], 50); // factor 1.0

        $this->assertEqualsWithDelta(260.0, $out['total_cost'], 1e-6);
        $this->assertEqualsWithDelta(5000.0, $out['ingredient_usage'][0]['quantity'], 1e-6);
    }

    public function test_non_auto_and_incompatible_auto_ingredients_are_not_costed_or_procured(): void
    {
        $out = MenuCycleCostService::aggregate([[
            'day_of_week' => 'Monday', 'estimate_population' => 10,
            'recipe' => ['servings' => 10, 'ingredients' => [
                ['fs_item_id' => 1, 'name' => 'Rice', 'quantity' => 1, 'unit' => 'kg', 'base_unit' => 'g', 'unit_cost' => 0.1, 'include_in_generated_lists' => true],
                ['fs_item_id' => 2, 'name' => 'Seasoning', 'quantity' => 2, 'unit' => 'tbsp', 'base_unit' => 'kg', 'unit_cost' => 50, 'include_in_generated_lists' => true],
                ['fs_item_id' => 3, 'name' => 'Garnish', 'quantity' => 2, 'unit' => 'pc', 'base_unit' => 'pc', 'unit_cost' => 5, 'include_in_generated_lists' => false],
            ]],
        ]]);

        $this->assertSame(100.0, $out['total_cost']);
        $this->assertCount(1, $out['ingredient_usage']);
        $this->assertSame(1, $out['ingredient_usage'][0]['fs_item_id']);
        $this->assertSame(1000.0, $out['ingredient_usage'][0]['quantity']);
    }

    public function test_small_mass_conversion_keeps_fractional_inventory_quantity(): void
    {
        $out = MenuCycleCostService::aggregate([[
            'day_of_week' => 'Monday', 'estimate_population' => 1,
            'recipe' => ['servings' => 1, 'ingredients' => [
                ['fs_item_id' => 1, 'name' => 'Spice', 'quantity' => 0.5, 'unit' => 'g', 'base_unit' => 'kg', 'unit_cost' => 1000, 'include_in_generated_lists' => true],
            ]],
        ]]);

        $this->assertEqualsWithDelta(0.0005, $out['ingredient_usage'][0]['quantity'], 0.0000001);
        $this->assertEqualsWithDelta(0.5, $out['total_cost'], 0.001);
    }

    public function test_ready_single_item_costs_one_per_head(): void
    {
        // A ready-to-serve catalog item (e.g. Yakult) in a snack slot — no recipe.
        $out = MenuCycleCostService::aggregate([
            ['day_of_week' => 'Monday', 'meal_type' => 'am_snack', 'servings_override' => null, 'item' => [
                'fs_item_id' => 30, 'name' => 'Yakult', 'unit' => 'pc', 'unit_cost' => 11.0, 'quantity' => 1,
            ]],
        ], 100);

        // 100 heads × 1 pc × ₱11 = ₱1100
        $this->assertEqualsWithDelta(1100.0, $out['total_cost'], 1e-6);
        $this->assertEqualsWithDelta(11.0, $out['cost_per_head'], 1e-6);
        $this->assertEqualsWithDelta(1100.0, $out['days']['Monday']['cost'], 1e-6);

        $this->assertCount(1, $out['ingredient_usage']);
        $this->assertSame(30, $out['ingredient_usage'][0]['fs_item_id']);
        $this->assertEqualsWithDelta(100.0, $out['ingredient_usage'][0]['quantity'], 1e-6);
        $this->assertEqualsWithDelta(1100.0, $out['ingredient_usage'][0]['cost'], 1e-6);
    }

    public function test_recipe_and_ready_item_costs_combine_in_a_day(): void
    {
        $out = MenuCycleCostService::aggregate([
            ['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'servings_override' => null, 'recipe' => $this->riceRecipe()],
            ['day_of_week' => 'Monday', 'meal_type' => 'am_snack', 'servings_override' => null, 'item' => [
                'fs_item_id' => 30, 'name' => 'Yakult', 'unit' => 'pc', 'unit_cost' => 11.0, 'quantity' => 1,
            ]],
        ], 100);

        // rice ₱520 + yakult ₱1100 = ₱1620 on Monday
        $this->assertEqualsWithDelta(1620.0, $out['days']['Monday']['cost'], 1e-6);
        $this->assertCount(2, $out['ingredient_usage']);
    }

    public function test_zero_population_is_safe(): void
    {
        $out = MenuCycleCostService::aggregate([
            ['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'servings_override' => null, 'recipe' => $this->riceRecipe()],
        ], 0);

        $this->assertSame(0.0, $out['total_cost']);
        $this->assertSame(0.0, $out['cost_per_head']);
    }

    public function test_per_day_estimate_population_scales_each_day(): void
    {
        // Population now lives on the day. No shared population passed.
        // Monday 100 heads → factor 2.0 → ₱520; Tuesday 50 heads → factor 1.0 → ₱260.
        $out = MenuCycleCostService::aggregate([
            ['day_of_week' => 'Monday',  'meal_type' => 'lunch', 'estimate_population' => 100, 'recipe' => $this->riceRecipe()],
            ['day_of_week' => 'Tuesday', 'meal_type' => 'lunch', 'estimate_population' => 50,  'recipe' => $this->riceRecipe()],
        ]);

        $this->assertEqualsWithDelta(780.0, $out['total_cost'], 1e-6);
        $this->assertEqualsWithDelta(520.0, $out['days']['Monday']['cost'], 1e-6);
        $this->assertEqualsWithDelta(260.0, $out['days']['Tuesday']['cost'], 1e-6);
        // per-day cost_per_head uses that day's own population
        $this->assertEqualsWithDelta(5.20, $out['days']['Monday']['cost_per_head'], 1e-6);
        $this->assertEqualsWithDelta(5.20, $out['days']['Tuesday']['cost_per_head'], 1e-6);
        // overall cost_per_head = total ÷ head-days (100 + 50 = 150) = 780/150 = 5.20
        $this->assertEqualsWithDelta(5.20, $out['cost_per_head'], 1e-6);
        // ingredient usage sums across days: 10000 g + 5000 g = 15000 g
        $this->assertEqualsWithDelta(15000.0, $out['ingredient_usage'][0]['quantity'], 1e-6);
    }

    public function test_per_day_population_shares_across_meals_in_a_day(): void
    {
        // Two meals on Monday share Monday's headcount (no double-count in head-days).
        $out = MenuCycleCostService::aggregate([
            ['day_of_week' => 'Monday', 'meal_type' => 'lunch',    'estimate_population' => 100, 'recipe' => $this->riceRecipe()],
            ['day_of_week' => 'Monday', 'meal_type' => 'am_snack', 'estimate_population' => 100, 'item' => [
                'fs_item_id' => 30, 'name' => 'Yakult', 'unit' => 'pc', 'unit_cost' => 11.0, 'quantity' => 1,
            ]],
        ]);

        // rice ₱520 + yakult ₱1100 = ₱1620 total; head-days = 100 (one day) → 16.20/head
        $this->assertEqualsWithDelta(1620.0, $out['total_cost'], 1e-6);
        $this->assertEqualsWithDelta(16.20, $out['cost_per_head'], 1e-6);
    }

    public function test_servings_override_still_beats_day_population(): void
    {
        $out = MenuCycleCostService::aggregate([
            ['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'estimate_population' => 100, 'servings_override' => 25, 'recipe' => $this->riceRecipe()],
        ]);

        // factor 25/50 = 0.5 → ₱130, ignoring day population 100
        $this->assertEqualsWithDelta(130.0, $out['total_cost'], 1e-6);
    }
}
