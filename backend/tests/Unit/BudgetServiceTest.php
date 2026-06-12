<?php

namespace Tests\Unit;

use App\Services\BudgetService;
use Tests\TestCase;

/**
 * Pure-logic test (no DB) for the budget rollup: planned-vs-actual totals,
 * variance, and trend buckets at day / week / month granularity. Drives the
 * budget dashboard charts over any date range.
 */
class BudgetServiceTest extends TestCase
{
    private function days(): array
    {
        return [
            ['date' => '2026-06-01', 'planned' => 1000, 'actual' => 1100],
            ['date' => '2026-06-02', 'planned' => 1000, 'actual' => 900],
            ['date' => '2026-07-05', 'planned' => 2000, 'actual' => 2500],
        ];
    }

    public function test_totals_and_variance(): void
    {
        $s = BudgetService::summarize($this->days(), 'day');
        $this->assertEqualsWithDelta(4000.0, $s['planned'], 1e-6);
        $this->assertEqualsWithDelta(4500.0, $s['actual'], 1e-6);
        $this->assertEqualsWithDelta(500.0, $s['variance'], 1e-6);          // actual - planned (over)
        $this->assertEqualsWithDelta(12.5, $s['variance_pct'], 1e-6);       // 500 / 4000 * 100
    }

    public function test_day_granularity_keeps_each_date(): void
    {
        $s = BudgetService::summarize($this->days(), 'day');
        $this->assertCount(3, $s['trend']);
        $this->assertSame('2026-06-01', $s['trend'][0]['bucket']);
        $this->assertEqualsWithDelta(1100.0, $s['trend'][0]['actual'], 1e-6);
    }

    public function test_month_granularity_buckets_by_month(): void
    {
        $s = BudgetService::summarize($this->days(), 'month');
        $this->assertCount(2, $s['trend']);
        $this->assertSame('2026-06', $s['trend'][0]['bucket']);
        $this->assertEqualsWithDelta(2000.0, $s['trend'][0]['planned'], 1e-6); // 1000 + 1000
        $this->assertEqualsWithDelta(2000.0, $s['trend'][0]['actual'], 1e-6);  // 1100 + 900
        $this->assertEqualsWithDelta(2500.0, $s['trend'][1]['actual'], 1e-6);
    }

    public function test_empty_is_all_zero(): void
    {
        $s = BudgetService::summarize([], 'day');
        $this->assertSame(0.0, $s['planned']);
        $this->assertSame(0.0, $s['actual']);
        $this->assertSame(0.0, $s['variance_pct']);
        $this->assertSame([], $s['trend']);
    }
}
