<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\FSS\ProcurementCostEfficiencyService;
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

    /** Cycle that serves the given weekdays (one fs_item entry per weekday). */
    private function cycle(array $weekdays, int $pop = 40): MenuCycle
    {
        $cycle  = MenuCycle::factory()->create();
        $fsItem = FsItem::factory()->create();
        foreach ($weekdays as $wd) {
            MenuCycleDay::factory()->create([
                'menu_cycle_id'       => $cycle->id,
                'day_of_week'         => $wd,
                'meal_type'           => 'breakfast',
                'fs_item_id'          => $fsItem->id,
                'quantity'            => 1,
                'estimate_population' => $pop,
            ]);
        }
        return $cycle;
    }

    private function listForSpan(MenuCycle $cycle, string $start, string $end): ShoppingList
    {
        return ShoppingList::factory()->create([
            'rnd_user_id'  => $this->rnd->id,
            'menu_cycle_id' => $cycle->id,
            'period_start' => $start,
            'period_end'   => $end,
        ]);
    }

    private function po(ShoppingList $list, string $status, float $total): PurchaseOrder
    {
        return PurchaseOrder::factory()->create([
            'rnd_user_id'      => $this->rnd->id,
            'shopping_list_id' => $list->id,
            'status'           => $status,
            'total_amount'     => $total,
        ]);
    }

    private function servedLog(MenuCycle $cycle, string $date, ?int $served): MealPrepLog
    {
        return MealPrepLog::create([
            'menu_cycle_id'     => $cycle->id,
            'service_date'      => $date,
            'status'            => 'completed',
            'served_population' => $served,
            'total_value'       => 0,
            'has_shortfall'     => false,
        ]);
    }

    // ── ACTUAL: derived from meal prep, gated on full span closure ──────────────

    public function test_actual_is_received_procurement_over_derived_served_when_span_closed(): void
    {
        $cycle = $this->cycle(['Monday', 'Tuesday']);            // serves 2 weekdays
        $list  = $this->listForSpan($cycle, '2026-06-22', '2026-06-23'); // Mon + Tue
        $this->po($list, 'received', 1800);
        $this->po($list, 'received', 1200);                      // cash 3000
        $this->servedLog($cycle, '2026-06-22', 16);
        $this->servedLog($cycle, '2026-06-23', 14);              // Σ served 30

        $r = ProcurementCostEfficiencyService::forShoppingList($list->fresh());

        $this->assertFalse($r['pending']);
        $this->assertEqualsWithDelta(100.0, $r['actual'], 0.01); // 3000 / 30
        $this->assertSame(30, $r['served_population']);
        $this->assertSame(2, $r['service_days_done']);
        $this->assertSame(2, $r['service_days_expected']);
    }

    public function test_actual_pending_until_every_service_day_completed(): void
    {
        $cycle = $this->cycle(['Monday', 'Tuesday']);
        $list  = $this->listForSpan($cycle, '2026-06-22', '2026-06-23');
        $this->po($list, 'received', 3000);
        $this->servedLog($cycle, '2026-06-22', 16);              // only Monday served

        $r = ProcurementCostEfficiencyService::forShoppingList($list->fresh());

        $this->assertTrue($r['pending']);
        $this->assertNull($r['actual']);
        $this->assertSame(1, $r['service_days_done']);
        $this->assertSame(2, $r['service_days_expected']);
        $this->assertStringContainsString('meal prep (1/2 days)', $r['pending_reason']);
    }

    public function test_actual_pending_until_all_pos_received(): void
    {
        $cycle = $this->cycle(['Monday']);
        $list  = $this->listForSpan($cycle, '2026-06-22', '2026-06-22');
        $this->po($list, 'received', 1800);
        $this->po($list, 'ordered', 1200);                       // not received
        $this->servedLog($cycle, '2026-06-22', 30);

        $r = ProcurementCostEfficiencyService::forShoppingList($list->fresh());

        $this->assertTrue($r['pending']);
        $this->assertStringContainsString('PO receipts', $r['pending_reason']);
    }

    public function test_actual_pending_when_no_pos(): void
    {
        $cycle = $this->cycle(['Monday']);
        $list  = $this->listForSpan($cycle, '2026-06-22', '2026-06-22');
        $this->servedLog($cycle, '2026-06-22', 30);

        $r = ProcurementCostEfficiencyService::forShoppingList($list->fresh());

        $this->assertTrue($r['pending']);
        $this->assertStringContainsString('PO receipts', $r['pending_reason']);
    }

    // ── ESTIMATED ──────────────────────────────────────────────────────────────

    public function test_estimated_uses_menu_cycle_span_cost_over_estimate_population(): void
    {
        $cycle  = MenuCycle::factory()->create();
        $fsItem = FsItem::factory()->create();
        MenuCycleDay::factory()->create([
            'menu_cycle_id'       => $cycle->id,
            'day_of_week'         => 'Monday',
            'meal_type'           => 'breakfast',
            'fs_item_id'          => $fsItem->id,
            'quantity'            => 1,
            'estimate_population' => 40,
        ]);
        $list = $this->listForSpan($cycle, '2026-06-22', '2026-06-22');

        $estimated = ProcurementCostEfficiencyService::estimated($list->fresh());

        // total = 40 × unit_cost, head-days = 40 → per-head == unit_cost.
        $this->assertEqualsWithDelta((float) $fsItem->unit_cost, $estimated, 0.01);
    }

    public function test_estimated_null_when_no_menu_cycle(): void
    {
        $list = ShoppingList::factory()->create([
            'rnd_user_id' => $this->rnd->id, 'menu_cycle_id' => null,
            'period_start' => '2026-06-22', 'period_end' => '2026-06-22',
        ]);

        $this->assertNull(ProcurementCostEfficiencyService::estimated($list->fresh()));
    }
}
