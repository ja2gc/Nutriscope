<?php

namespace App\Services\Reports;

use App\Models\DemographicCensusPeriod;
use App\Services\Reports\Generators\DemographicCensusGenerator;
use Illuminate\Support\Carbon;

class StoreMonthlyDemographicCensuses
{
    public function __construct(private readonly DemographicCensusGenerator $generator) {}

    /** @return array{stored:int,rebuilt:int,skipped:int} */
    public function handle(Carbon $now): array
    {
        $firstCycle = $this->generator->earliestQualifyingCycleAt();
        if ($firstCycle === null) {
            return ['stored' => 0, 'rebuilt' => 0, 'skipped' => 0];
        }

        $cursor = Carbon::parse($firstCycle, $now->getTimezone())->startOfMonth();
        $lastCompletedMonth = $now->copy()->startOfMonth()->subMonth();
        $stored = 0;
        $rebuilt = 0;
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
                    'basis_version' => DemographicCensusGenerator::BASIS_VERSION,
                    'frozen_at' => $now,
                ],
            );
            $period->wasRecentlyCreated ? $stored++ : $skipped++;
            $cursor->addMonth();
        }

        return compact('stored', 'rebuilt', 'skipped');
    }
}
