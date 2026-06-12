<?php

namespace Tests\Unit;

use App\Http\Controllers\FSS\FsItemController;
use Tests\TestCase;

class PriceTrendShapeTest extends TestCase
{
    public function test_summary_computes_min_max_latest_avg(): void
    {
        $points = [
            ['date' => '2026-01-01', 'unit_price' => 10.0],
            ['date' => '2026-02-01', 'unit_price' => 20.0],
            ['date' => '2026-03-01', 'unit_price' => 30.0],
        ];
        $s = FsItemController::summarizeTrend($points);
        $this->assertSame(10.0, $s['min']);
        $this->assertSame(30.0, $s['max']);
        $this->assertSame(30.0, $s['latest']);
        $this->assertEqualsWithDelta(20.0, $s['avg'], 1e-6);
    }

    public function test_empty_series_is_zeroed(): void
    {
        $s = FsItemController::summarizeTrend([]);
        $this->assertSame(['min' => 0.0, 'max' => 0.0, 'latest' => 0.0, 'avg' => 0.0], $s);
    }
}
