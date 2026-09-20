<?php

namespace App\Services\Reports;

use App\Models\DemographicCensusPeriod;
use App\Models\NcpRecord;
use App\Services\Reports\Generators\DemographicCensusGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StoreMonthlyDemographicCensuses
{
    public function __construct(private readonly DemographicCensusGenerator $generator) {}

    /** @return array{stored:int,rebuilt:int,skipped:int} */
    public function handle(Carbon $now): array
    {
        $firstCycle = NcpRecord::query()->min('created_at');
        if ($firstCycle === null) {
            return ['stored' => 0, 'rebuilt' => 0, 'skipped' => 0];
        }

        $cursor = Carbon::parse($firstCycle, $now->getTimezone())->startOfMonth();
        $lastCompletedMonth = $now->copy()->startOfMonth()->subMonth();
        $stored = 0;
        $rebuilt = $this->rebuildLegacyPeriods($lastCompletedMonth, $now);
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

    private function rebuildLegacyPeriods(Carbon $lastCompletedMonth, Carbon $now): int
    {
        $periods = DemographicCensusPeriod::query()
            ->where('basis_version', '<', DemographicCensusGenerator::BASIS_VERSION)
            ->whereDate('period_start', '<=', $lastCompletedMonth->toDateString())
            ->get();

        foreach ($periods as $period) {
            $census = $this->generator->currentCensus($period->period_start, $period->period_end);
            DB::table('demographic_census_periods')
                ->where('id', $period->id)
                ->update([
                    'census' => json_encode($census, JSON_THROW_ON_ERROR),
                    'source_count' => $census['total'],
                    'basis_version' => DemographicCensusGenerator::BASIS_VERSION,
                    'frozen_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        return $periods->count();
    }
}
