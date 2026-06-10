<?php

namespace Tests\Unit;

use App\Services\MealPlanService;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Pure-logic Unit tests for Phase 4 meal-plan algorithm additions.
 * No database — tests use reflection to access private helpers directly.
 *
 * Covers:
 *   4.2  Slot eligibility filter (meal_types)
 *   4.3  ±10% variance computation + flagging (incl. "cannot_validate" for data gaps)
 *   4.4  isFlagged correctly identifies within/outside tolerance
 */
class MealPlanAlgorithmTest extends BaseTestCase
{
    private function svc(): MealPlanService
    {
        return new MealPlanService();
    }

    /**
     * Call a private method on $obj via Reflection.
     */
    private function call(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invoke($obj, ...$args);
    }

    // ── 4.2: Slot eligibility ─────────────────────────────────────────────────

    private function makeRecipe(array $mealTypes, string $name = 'Recipe'): object
    {
        return new class($mealTypes, $name) {
            public string $name;
            public ?array $meal_types;
            public float $total_calories = 400;
            public float $total_protein  = 15;
            public float $total_carbs    = 60;
            public float $total_fat      = 10;
            public ?array $micronutrients = null;

            public function __construct(array $mt, string $n)
            {
                $this->meal_types = $mt ?: null;
                $this->name       = $n;
            }
        };
    }

    private function makeCollection(array $items): \Illuminate\Support\Collection
    {
        return collect($items);
    }

    public function test_breakfast_recipe_eligible_for_breakfast_slot(): void
    {
        $recipe = $this->makeRecipe(['breakfast']);
        $pool   = $this->makeCollection([$recipe]);
        $svc    = $this->svc();

        $filtered = $this->call($svc, 'filterByMealType', [$pool, 'breakfast']);
        $this->assertCount(1, $filtered);
    }

    public function test_breakfast_recipe_not_eligible_for_lunch_slot(): void
    {
        $recipe = $this->makeRecipe(['breakfast']);
        $pool   = $this->makeCollection([$recipe]);
        $svc    = $this->svc();

        $filtered = $this->call($svc, 'filterByMealType', [$pool, 'lunch']);
        $this->assertCount(0, $filtered);
    }

    public function test_any_recipe_eligible_for_all_slots(): void
    {
        $recipe = $this->makeRecipe(['any']);
        $pool   = $this->makeCollection([$recipe]);
        $svc    = $this->svc();

        foreach (['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'] as $slot) {
            $filtered = $this->call($svc, 'filterByMealType', [$pool, $slot]);
            $this->assertCount(1, $filtered, "Recipe with meal_type=[any] should be eligible for slot={$slot}");
        }
    }

    public function test_snack_alias_eligible_for_am_snack_and_pm_snack(): void
    {
        $recipe = $this->makeRecipe(['snack']);
        $pool   = $this->makeCollection([$recipe]);
        $svc    = $this->svc();

        $this->assertCount(1, $this->call($svc, 'filterByMealType', [$pool, 'am_snack']));
        $this->assertCount(1, $this->call($svc, 'filterByMealType', [$pool, 'pm_snack']));
        $this->assertCount(0, $this->call($svc, 'filterByMealType', [$pool, 'breakfast']));
    }

    public function test_null_meal_types_treated_as_any(): void
    {
        $recipe = $this->makeRecipe([]);  // empty → treated as any
        $pool   = $this->makeCollection([$recipe]);
        $svc    = $this->svc();

        foreach (['breakfast', 'lunch', 'dinner'] as $slot) {
            $filtered = $this->call($svc, 'filterByMealType', [$pool, $slot]);
            $this->assertCount(1, $filtered, "Null meal_types should be eligible for all slots");
        }
    }

    public function test_multi_type_recipe_eligible_for_correct_slots(): void
    {
        $recipe = $this->makeRecipe(['lunch', 'dinner']);
        $pool   = $this->makeCollection([$recipe]);
        $svc    = $this->svc();

        $this->assertCount(1, $this->call($svc, 'filterByMealType', [$pool, 'lunch']));
        $this->assertCount(1, $this->call($svc, 'filterByMealType', [$pool, 'dinner']));
        $this->assertCount(0, $this->call($svc, 'filterByMealType', [$pool, 'breakfast']));
        $this->assertCount(0, $this->call($svc, 'filterByMealType', [$pool, 'am_snack']));
    }

    // ── 4.3: isFlagged logic ─────────────────────────────────────────────────

    public function test_within_tolerance_not_flagged(): void
    {
        $svc = $this->svc();
        // 5% deviation — within ±10%
        $variance = ['energy' => 0.05, 'protein' => -0.03, 'carbs' => 0.08, 'fat' => -0.09];
        $this->assertFalse($this->call($svc, 'isFlagged', [$variance]));
    }

    public function test_outside_tolerance_is_flagged(): void
    {
        $svc = $this->svc();
        // energy is +14% — outside ±10%
        $variance = ['energy' => 0.14, 'protein' => 0.02];
        $this->assertTrue($this->call($svc, 'isFlagged', [$variance]));
    }

    public function test_exactly_at_tolerance_boundary_not_flagged(): void
    {
        $svc = $this->svc();
        // Exactly 10% — should be within (≤0.10)
        $variance = ['energy' => 0.10, 'protein' => -0.10];
        $this->assertFalse($this->call($svc, 'isFlagged', [$variance]));
    }

    public function test_cannot_validate_does_not_trigger_flag(): void
    {
        $svc = $this->svc();
        // cannot_validate is a string — isFlagged only checks float values
        $variance = ['energy' => 0.05, 'sodium_mg' => 'cannot_validate'];
        $this->assertFalse($this->call($svc, 'isFlagged', [$variance]));
    }

    public function test_cannot_validate_alongside_out_of_range_still_flags(): void
    {
        $svc = $this->svc();
        $variance = ['energy' => 0.20, 'sodium_mg' => 'cannot_validate'];
        $this->assertTrue($this->call($svc, 'isFlagged', [$variance]));
    }

    // ── 4.3: computeDayVariance ───────────────────────────────────────────────

    /**
     * Build a minimal slot/item structure for computeDayVariance testing.
     */
    private function makeSlotWithItems(array $nutrients): object
    {
        $item = new class($nutrients) {
            public float $quantity = 1.0;
            public array $nutrient_snapshot;
            public ?int $recipe_id = 1;

            public function __construct(array $n)
            {
                $this->nutrient_snapshot = $n;
            }
        };

        $slot = new class([$item]) {
            public array $items;
            public string $meal_type = 'lunch';

            public function __construct(array $items)
            {
                $this->items = $items;
            }
        };

        return $slot;
    }

    public function test_compute_day_variance_within_tolerance(): void
    {
        $svc = $this->svc();
        // Target energy 2000, actual 2050 → +2.5%
        $slot = $this->makeSlotWithItems([
            'calories' => 2050, 'protein' => 70, 'carbs' => 250, 'fat' => 60, 'water_g' => 0,
            'micronutrients' => [],
        ]);
        $slots   = collect([$slot]);
        $targets = ['energy' => 2000.0, 'protein' => 70.0, 'carbs' => 250.0, 'fat' => 60.0];

        $variance = $this->call($svc, 'computeDayVariance', [$slots, $targets, []]);

        $this->assertEqualsWithDelta(0.025, $variance['energy'], 0.001);
        $this->assertEqualsWithDelta(0.0,   $variance['protein'], 0.001);
    }

    public function test_compute_day_variance_marks_cannot_validate_for_missing_micro(): void
    {
        $svc = $this->svc();
        $slot = $this->makeSlotWithItems([
            'calories' => 2000, 'protein' => 70, 'carbs' => 250, 'fat' => 60, 'water_g' => 0,
            'micronutrients' => [],  // no sodium reported
        ]);
        $slots      = collect([$slot]);
        $targets    = ['energy' => 2000.0];
        $microLimits = ['sodium_mg' => ['min' => 1500, 'unit' => 'mg']];

        $variance = $this->call($svc, 'computeDayVariance', [$slots, $targets, $microLimits]);

        $this->assertSame('cannot_validate', $variance['sodium_mg'],
            'Missing micronutrient must be cannot_validate, never silent pass');
    }

    public function test_compute_day_variance_includes_water_when_fluid_target_set(): void
    {
        $svc = $this->svc();
        $slot = $this->makeSlotWithItems([
            'calories' => 2000, 'protein' => 70, 'carbs' => 250, 'fat' => 60,
            'water_g'  => 1800,  // under 2000 mL target
            'micronutrients' => [],
        ]);
        $slots   = collect([$slot]);
        $targets = ['energy' => 2000.0, 'water' => 2000.0];

        $variance = $this->call($svc, 'computeDayVariance', [$slots, $targets, []]);

        // (1800 - 2000) / 2000 = -0.10
        $this->assertEqualsWithDelta(-0.10, $variance['water'], 0.001);
    }

    public function test_compute_day_variance_quantity_multiplied(): void
    {
        $svc = $this->svc();
        // quantity = 2, calories per serving = 500 → day total = 1000
        $item = new class {
            public float $quantity = 2.0;
            public array $nutrient_snapshot = [
                'calories' => 500, 'protein' => 20, 'carbs' => 80, 'fat' => 15,
                'water_g' => 100, 'micronutrients' => [],
            ];
            public ?int $recipe_id = 1;
        };
        $slot = new class([$item]) {
            public array $items;
            public string $meal_type = 'lunch';
            public function __construct(array $i) { $this->items = $i; }
        };
        $slots   = collect([$slot]);
        $targets = ['energy' => 1000.0, 'protein' => 40.0];

        $variance = $this->call($svc, 'computeDayVariance', [$slots, $targets, []]);

        $this->assertEqualsWithDelta(0.0, $variance['energy'], 0.001);
        $this->assertEqualsWithDelta(0.0, $variance['protein'], 0.001);
    }
}
