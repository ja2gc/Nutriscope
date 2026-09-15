<?php

namespace App\Services\Reports;

use App\Models\DemographicCensusPeriod;
use App\Services\Reports\Generators\DemographicCensusGenerator;
use Illuminate\Support\Carbon;

class StoreMonthlyDemographicCensuses
{
    public function __construct(private readonly DemographicCensusGenerator $generator) {}

    /** @return array{stored:int,skipped:int} */
    public function handle(Carbon $now): array
    {
        $cursor = Carbon::parse(
            config('nutriscope-reports.demographic_census_start'),
            $now->getTimezone(),
        )->startOfMonth();
        $lastCompletedMonth = $now->copy()->startOfMonth()->subMonth();
        $stored = 0;
        $skipped = 0;

        while ($cursor->lessThanOrEqualTo($lastCompletedMonth)) {
            $start = $cursor->copy()->startOfMonth();
            $end = $cursor->copy()->endOfMonth();
            if (DemographicCensusPeriod::query()->whereDate('period_start', $start)->exists()) {
                $skipped++;
                $cursor->addMonth();

                continue;
            }

            $census = $this->generator->currentCensus($start, $end);
            $period = DemographicCensusPeriod::query()->firstOrCreate(
                ['period_start' => $start->toDateString()],
                [
                    'period_end' => $end->toDateString(),
                    'census' => $census,
                    'source_count' => $census['total'],
                    'frozen_at' => $now,
                ],
            );
            $period->wasRecentlyCreated ? $stored++ : $skipped++;
            $cursor->addMonth();
        }

        return compact('stored', 'skipped');
    }
}
