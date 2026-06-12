<?php

namespace Tests\Unit;

use App\Services\ProcurementService;
use Tests\TestCase;

/**
 * Pure-logic test (no DB): turning a menu cycle's whole-cycle ingredient usage
 * into a suggested shopping list for an arbitrary day span. The menu engine gives
 * usage for one full cycle (cycle_days long); the shopping span may be shorter or
 * longer, so quantities and costs scale by span ÷ cycle_days.
 */
class ProcurementServiceTest extends TestCase
{
    private function usage(): array
    {
        return [
            ['fs_item_id' => 1, 'name' => 'Rice', 'unit' => 'g', 'quantity' => 10000, 'cost' => 520],
            ['fs_item_id' => 2, 'name' => 'Pork (kasim)', 'unit' => 'g', 'quantity' => 5000, 'cost' => 1400],
        ];
    }

    public function test_span_equal_to_cycle_is_unchanged(): void
    {
        $items = ProcurementService::suggestedItems($this->usage(), 7, 7);
        $this->assertEqualsWithDelta(10000.0, $items[0]['qty'], 1e-6);
        $this->assertEqualsWithDelta(520.0, $items[0]['total'], 1e-6);
    }

    public function test_double_span_doubles_quantities_and_cost(): void
    {
        $items = ProcurementService::suggestedItems($this->usage(), 7, 14);
        $this->assertEqualsWithDelta(20000.0, $items[0]['qty'], 1e-6);
        $this->assertEqualsWithDelta(1040.0, $items[0]['total'], 1e-6);
        $this->assertEqualsWithDelta(2800.0, $items[1]['total'], 1e-6);
    }

    public function test_partial_span_scales_down(): void
    {
        // 3 of 7 days (qty is rounded to 2 dp for a shopping list)
        $items = ProcurementService::suggestedItems($this->usage(), 7, 3);
        $this->assertEqualsWithDelta(10000 * 3 / 7, $items[0]['qty'], 0.01);
    }

    public function test_each_item_carries_fs_item_id_and_name(): void
    {
        $items = ProcurementService::suggestedItems($this->usage(), 7, 7);
        $this->assertSame(1, $items[0]['fs_item_id']);
        $this->assertSame('Rice', $items[0]['name']);
        $this->assertSame('g', $items[0]['unit']);
    }

    public function test_zero_cycle_days_is_safe(): void
    {
        $items = ProcurementService::suggestedItems($this->usage(), 0, 7);
        // falls back to treating cycle as the span (factor 1)
        $this->assertEqualsWithDelta(10000.0, $items[0]['qty'], 1e-6);
    }
}
