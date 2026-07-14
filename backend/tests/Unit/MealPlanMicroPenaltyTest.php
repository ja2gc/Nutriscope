<?php

namespace Tests\Unit;

use App\Services\MealPlanService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * TDD: MealPlanService micronutrient limit penalty scoring.
 *
 * The penalty logic is in the private calcMicroPenalty() method.
 * We test it via reflection so it can be validated before wiring into generate().
 *
 * Rules:
 *  - For each nutrient key in $limits that has a 'max' threshold,
 *    if the recipe's micronutrient value exceeds it, add a flat penalty.
 *  - Penalty = MICRO_PENALTY_PER_EXCESS constant (default 1.0) × number of exceeded limits.
 *  - Recipes within all limits get 0 penalty.
 */
class MealPlanMicroPenaltyTest extends TestCase
{
    private function invokePenalty(array $recipeMicros, array $limits): float
    {
        $service = new MealPlanService;
        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('calcMicroPenalty');
        $method->setAccessible(true);

        return $method->invoke($service, $recipeMicros, $limits);
    }

    // ── within-limit → zero penalty ──────────────────────────────────────────

    public function test_no_penalty_when_within_all_limits(): void
    {
        $micros = ['sodium_mg' => 800.0, 'potassium_mg' => 1500.0];
        $limits = [
            'sodium_mg' => ['max' => 1500, 'unit' => 'mg'],
            'potassium_mg' => ['max' => 2000, 'unit' => 'mg'],
        ];
        $this->assertEquals(0.0, $this->invokePenalty($micros, $limits));
    }

    // ── single exceeded limit → non-zero penalty ─────────────────────────────

    public function test_penalty_when_sodium_exceeds_max(): void
    {
        $micros = ['sodium_mg' => 2000.0];
        $limits = ['sodium_mg' => ['max' => 1500, 'unit' => 'mg']];
        $penalty = $this->invokePenalty($micros, $limits);
        $this->assertGreaterThan(0.0, $penalty);
    }

    // ── two limits exceeded → double penalty ─────────────────────────────────

    public function test_penalty_accumulates_per_exceeded_nutrient(): void
    {
        $micros = ['sodium_mg' => 2000.0, 'potassium_mg' => 3000.0];
        $limits = [
            'sodium_mg' => ['max' => 1500, 'unit' => 'mg'],
            'potassium_mg' => ['max' => 2000, 'unit' => 'mg'],
        ];
        $onePenalty = $this->invokePenalty(['sodium_mg' => 2000.0], ['sodium_mg' => ['max' => 1500, 'unit' => 'mg']]);
        $twoPenalty = $this->invokePenalty($micros, $limits);
        $this->assertEquals($onePenalty * 2, $twoPenalty);
    }

    // ── limit with only 'min' (no 'max') → ignored ───────────────────────────

    public function test_min_only_limits_do_not_add_penalty(): void
    {
        $micros = ['calcium_mg' => 50.0];
        $limits = ['calcium_mg' => ['min' => 1000, 'unit' => 'mg']];  // patient should get MORE, no penalty for high
        $this->assertEquals(0.0, $this->invokePenalty($micros, $limits));
    }

    // ── nutrient present in limits but not in recipe micros → no penalty ─────

    public function test_missing_micro_in_recipe_does_not_count_as_exceeded(): void
    {
        $micros = []; // recipe has no sodium data
        $limits = ['sodium_mg' => ['max' => 1500, 'unit' => 'mg']];
        $this->assertEquals(0.0, $this->invokePenalty($micros, $limits));
    }

    // ── exactly at limit (equal) → no penalty ────────────────────────────────

    public function test_exactly_at_max_is_not_penalised(): void
    {
        $micros = ['sodium_mg' => 1500.0];
        $limits = ['sodium_mg' => ['max' => 1500, 'unit' => 'mg']];
        $this->assertEquals(0.0, $this->invokePenalty($micros, $limits));
    }

    // ── empty limits → no penalty ────────────────────────────────────────────

    public function test_empty_limits_returns_zero_penalty(): void
    {
        $micros = ['sodium_mg' => 3000.0];
        $this->assertEquals(0.0, $this->invokePenalty($micros, []));
    }
}
