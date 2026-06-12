<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Budget rollup: planned-vs-actual totals, variance, and trend buckets at
 * day / week / month granularity. Pure over plain arrays so it's unit-tested;
 * the controller feeds it daily data (planned from the menu cycle, actual from
 * received purchase orders).
 */
class BudgetService
{
    /**
     * @param  array<int,array{date:string,planned:float,actual:float}> $days
     * @param  string $granularity  day | week | month
     */
    public static function summarize(array $days, string $granularity = 'day'): array
    {
        $buckets = [];
        $planned = 0.0;
        $actual  = 0.0;

        foreach ($days as $d) {
            $p = (float) ($d['planned'] ?? 0);
            $a = (float) ($d['actual'] ?? 0);
            $planned += $p;
            $actual  += $a;

            $key = self::bucketKey((string) $d['date'], $granularity);
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['bucket' => $key, 'planned' => 0.0, 'actual' => 0.0, 'variance' => 0.0];
            }
            $buckets[$key]['planned'] += $p;
            $buckets[$key]['actual']  += $a;
        }

        ksort($buckets);
        $trend = array_values(array_map(function ($b) {
            $b['planned']  = round($b['planned'], 2);
            $b['actual']   = round($b['actual'], 2);
            $b['variance'] = round($b['actual'] - $b['planned'], 2);
            return $b;
        }, $buckets));

        $variance = round($actual - $planned, 2);

        return [
            'planned'      => round($planned, 2),
            'actual'       => round($actual, 2),
            'variance'     => $variance,
            'variance_pct' => $planned > 0 ? round($variance / $planned * 100, 2) : 0.0,
            'trend'        => $trend,
        ];
    }

    private static function bucketKey(string $date, string $granularity): string
    {
        return match ($granularity) {
            'month' => substr($date, 0, 7),                       // YYYY-MM
            'week'  => Carbon::parse($date)->format('o-\WW'),     // ISO year-week
            default => substr($date, 0, 10),                      // YYYY-MM-DD
        };
    }
}
