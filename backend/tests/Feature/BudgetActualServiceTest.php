<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\User;
use App\Services\BudgetActualService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for BudgetActualService per-head calculations.
 *
 * Spec (FSS §5):
 *   planned cap        = budget_per_head_day × population
 *   daily per-head     = meal_prep_log.total_value ÷ served_population
 *   span per-head actual = Σ total_value ÷ Σ served_population across served days
 */
class BudgetActualServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
    }

    private function servedLog(int $cycleId, string $date, int $served, float $value): MealPrepLog
    {
        return MealPrepLog::create([
            'menu_cycle_id'     => $cycleId,
            'service_date'      => $date,
            'status'            => 'completed',
            'served_population' => $served,
            'total_value'       => $value,
            'has_shortfall'     => false,
        ]);
    }

    /**
     * Fixture:
     *   budget_per_head_day = 50, population = 200
     *   day 1: total_value 8000, served_population 160
     *   day 2: total_value 9000, served_population 180
     *
     * Expected:
     *   planned cap        = 50 × 200 = 10 000
     *   day-1 per_head     = 8000 / 160 = 50.00
     *   day-2 per_head     = 9000 / 180 = 50.00
     *   span per_head_actual = (8000 + 9000) / (160 + 180) = 17000 / 340 = 50.00
     */
    public function test_planned_cap_is_per_head_day_times_population(): void
    {
        $cycle  = MenuCycle::factory()->create(['week_start_date' => '2026-06-09']);
        $budget = Budget::factory()->create([
            'rnd_user_id'        => $this->rnd->id,
            'menu_cycle_id'      => $cycle->id,
            'budget_per_head_day' => 50.00,
            'population'         => 200,
        ]);

        $this->servedLog($cycle->id, '2026-06-10', 160, 8000.00);
        $this->servedLog($cycle->id, '2026-06-11', 180, 9000.00);

        $result = BudgetActualService::dailySeries(
            $budget,
            Carbon::parse('2026-06-10'),
            Carbon::parse('2026-06-11'),
        );

        // Planned cap = 50 × 200 = 10 000.
        foreach ($result['days'] as $day) {
            $this->assertEqualsWithDelta(10000.0, $day['planned'], 0.01,
                "planned cap on {$day['date']} should be budget_per_head_day × population");
        }
    }

    public function test_daily_per_head_is_total_value_divided_by_served_population(): void
    {
        $cycle  = MenuCycle::factory()->create(['week_start_date' => '2026-06-09']);
        $budget = Budget::factory()->create([
            'rnd_user_id'        => $this->rnd->id,
            'menu_cycle_id'      => $cycle->id,
            'budget_per_head_day' => 50.00,
            'population'         => 200,
        ]);

        $this->servedLog($cycle->id, '2026-06-10', 160, 8000.00);
        $this->servedLog($cycle->id, '2026-06-11', 180, 9000.00);

        $result = BudgetActualService::dailySeries(
            $budget,
            Carbon::parse('2026-06-10'),
            Carbon::parse('2026-06-11'),
        );

        $byDate = collect($result['days'])->keyBy('date');

        // day 1: 8000 / 160 = 50.00
        $this->assertEqualsWithDelta(50.00, $byDate['2026-06-10']['per_head'], 0.01,
            'day-1 per_head = total_value / served_population');

        // day 2: 9000 / 180 = 50.00
        $this->assertEqualsWithDelta(50.00, $byDate['2026-06-11']['per_head'], 0.01,
            'day-2 per_head = total_value / served_population');
    }

    public function test_span_per_head_actual_is_sum_value_over_sum_heads(): void
    {
        $cycle  = MenuCycle::factory()->create(['week_start_date' => '2026-06-09']);
        $budget = Budget::factory()->create([
            'rnd_user_id'        => $this->rnd->id,
            'menu_cycle_id'      => $cycle->id,
            'budget_per_head_day' => 50.00,
            'population'         => 200,
        ]);

        // Deliberately unequal values so simple average != weighted average.
        // day 1: 8000 served to 160  →  50 / head
        // day 2: 4500 served to  90  →  50 / head
        // Σ value = 12500 · Σ heads = 250 → span per-head = 50.00
        $this->servedLog($cycle->id, '2026-06-10', 160, 8000.00);
        $this->servedLog($cycle->id, '2026-06-11',  90, 4500.00);

        $result = BudgetActualService::dailySeries(
            $budget,
            Carbon::parse('2026-06-10'),
            Carbon::parse('2026-06-11'),
        );

        // span per_head_actual = (8000 + 4500) / (160 + 90) = 12500 / 250 = 50.00
        $this->assertNotNull($result['per_head_actual'],
            'per_head_actual must be non-null when served days have recorded populations');

        $this->assertEqualsWithDelta(50.00, $result['per_head_actual'], 0.01,
            'span per_head_actual = Σ total_value / Σ served_population');
    }

    public function test_per_head_actual_null_when_no_served_population_recorded(): void
    {
        $cycle  = MenuCycle::factory()->create(['week_start_date' => '2026-06-09']);
        $budget = Budget::factory()->create([
            'rnd_user_id'        => $this->rnd->id,
            'menu_cycle_id'      => $cycle->id,
            'budget_per_head_day' => 50.00,
            'population'         => 200,
        ]);

        // Log with null served_population — not yet reported.
        MealPrepLog::create([
            'menu_cycle_id'     => $cycle->id,
            'service_date'      => '2026-06-10',
            'status'            => 'completed',
            'served_population' => null,
            'total_value'       => 8000.00,
            'has_shortfall'     => false,
        ]);

        $result = BudgetActualService::dailySeries(
            $budget,
            Carbon::parse('2026-06-10'),
            Carbon::parse('2026-06-10'),
        );

        $this->assertNull($result['per_head_actual'],
            'per_head_actual must be null when no served_population has been recorded');

        $this->assertNull($result['days'][0]['per_head'],
            'daily per_head must be null when served_population is not recorded');
    }
}
