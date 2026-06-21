<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\BudgetActualService;
use App\Services\FSS\ProcurementCostEfficiencyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementCostEfficiencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
    }

    private function receivedPo(string $date, float $total): PurchaseOrder
    {
        return PurchaseOrder::factory()->create([
            'rnd_user_id'   => $this->rnd->id,
            'status'        => 'received',
            'received_date' => $date,
            'total_amount'  => $total,
        ]);
    }

    private function servedLog(int $cycleId, string $date, ?int $served, float $value = 0): MealPrepLog
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

    public function test_happy_path_total_procurement_over_total_served(): void
    {
        $cycle  = MenuCycle::factory()->create();
        $budget = Budget::factory()->create(['rnd_user_id' => $this->rnd->id, 'menu_cycle_id' => $cycle->id]);

        // Procurement cash: 1800 + 1200 = 3000.
        $this->receivedPo('2026-06-10', 1800);
        $this->receivedPo('2026-06-11', 1200);

        // Served headcount: 10, 20 -> total 30 (avg was 15 under old logic).
        $this->servedLog($cycle->id, '2026-06-10', 10);
        $this->servedLog($cycle->id, '2026-06-11', 20);

        $result = ProcurementCostEfficiencyService::forSpan(
            $budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-12')
        );

        // 3000 / 30 = 100. (Old avg-based result would have been 3000/15 = 200.)
        $this->assertEqualsWithDelta(100.0, $result, 0.01);
    }

    /**
     * Explicitly asserts total-served denominator, using a fixture where avg != total
     * so the test fails under the old average logic (avg=15 → 200) but passes with
     * the correct total logic (total=30 → 100).
     */
    public function test_span_per_head_divides_by_total_served_not_average(): void
    {
        $cycle  = MenuCycle::factory()->create();
        $budget = Budget::factory()->create(['rnd_user_id' => $this->rnd->id, 'menu_cycle_id' => $cycle->id]);

        $this->receivedPo('2026-06-10', 3000);

        // Day 1: 10 served, Day 2: 20 served. avg=15, total=30 — they differ.
        $this->servedLog($cycle->id, '2026-06-10', 10);
        $this->servedLog($cycle->id, '2026-06-11', 20);

        $result = ProcurementCostEfficiencyService::forSpan(
            $budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-12')
        );

        // Must be total: 3000 / 30 = 100, not avg: 3000 / 15 = 200.
        $this->assertEqualsWithDelta(100.0, $result, 0.01);
        $this->assertNotEqualsWithDelta(200.0, $result, 0.01);
    }

    public function test_returns_null_when_no_served_population_recorded(): void
    {
        $cycle  = MenuCycle::factory()->create();
        $budget = Budget::factory()->create(['rnd_user_id' => $this->rnd->id, 'menu_cycle_id' => $cycle->id]);

        $this->receivedPo('2026-06-10', 3000);
        // Completed log but served_population is null (not yet reported).
        $this->servedLog($cycle->id, '2026-06-10', null, 500);

        $result = ProcurementCostEfficiencyService::forSpan(
            $budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-12')
        );

        $this->assertNull($result);
    }

    public function test_returns_null_when_avg_served_is_zero(): void
    {
        // served_population is unsigned, so 0 is a valid non-null value. A served
        // row of 0 passes the not-null count guard but makes the average 0 — the
        // explicit avg<=0 guard must catch it rather than divide by zero.
        $cycle  = MenuCycle::factory()->create();
        $budget = Budget::factory()->create(['rnd_user_id' => $this->rnd->id, 'menu_cycle_id' => $cycle->id]);

        $this->receivedPo('2026-06-10', 3000);
        $this->servedLog($cycle->id, '2026-06-10', 0, 500);

        $result = ProcurementCostEfficiencyService::forSpan(
            $budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-12')
        );

        $this->assertNull($result);
    }

    public function test_diverges_from_per_head_actual(): void
    {
        $cycle  = MenuCycle::factory()->create();
        $budget = Budget::factory()->create(['rnd_user_id' => $this->rnd->id, 'menu_cycle_id' => $cycle->id]);

        // Procurement cash (3000) deliberately != food-served value (1200): some
        // procured stock unused this span, or service drew on prior stock.
        $this->receivedPo('2026-06-10', 3000);
        $this->servedLog($cycle->id, '2026-06-10', 10, 600);
        $this->servedLog($cycle->id, '2026-06-11', 20, 600);

        $efficiency = ProcurementCostEfficiencyService::forSpan(
            $budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-12')
        );
        $series = BudgetActualService::dailySeries(
            $budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-12')
        );

        // efficiency = 3000 / total(10+20)=30 -> 100.
        $this->assertEqualsWithDelta(100.0, $efficiency, 0.01);
        // per_head_actual = served value 1200 / served sum 30 -> 40.
        $this->assertEqualsWithDelta(40.0, $series['per_head_actual'], 0.01);
        // The two metrics are genuinely independent, not the same number.
        $this->assertNotEqualsWithDelta($efficiency, $series['per_head_actual'], 0.01);
    }

    public function test_scopes_served_population_to_budget_menu_cycle(): void
    {
        $cycle      = MenuCycle::factory()->create();
        $otherCycle = MenuCycle::factory()->create();
        $budget     = Budget::factory()->create(['rnd_user_id' => $this->rnd->id, 'menu_cycle_id' => $cycle->id]);

        $this->receivedPo('2026-06-10', 2000);
        // This budget's cycle: served 10. Another cycle same day: served 90.
        $this->servedLog($cycle->id, '2026-06-10', 10);
        $this->servedLog($otherCycle->id, '2026-06-10', 90);

        $result = ProcurementCostEfficiencyService::forSpan(
            $budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-12')
        );

        // Only cycle's served counts: 2000 / 10 = 200. If the other cycle leaked
        // in, avg would be 50 and the result 40.
        $this->assertEqualsWithDelta(200.0, $result, 0.01);
    }
}
