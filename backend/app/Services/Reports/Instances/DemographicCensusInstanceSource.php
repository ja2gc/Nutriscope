<?php

namespace App\Services\Reports\Instances;

use App\Models\DemographicCensusPeriod;
use App\Services\Reports\Contracts\InstanceSource;
use Illuminate\Support\Carbon;

class DemographicCensusInstanceSource implements InstanceSource
{
    public function axis(): string
    {
        return 'period';
    }

    public function instances(array $filters): array
    {
        return DemographicCensusPeriod::query()
            ->when(! empty($filters['year']), fn ($query) => $query->whereYear('period_start', (int) $filters['year']))
            ->when(! empty($filters['month']), fn ($query) => $query->whereMonth('period_start', (int) $filters['month']))
            ->latest('period_start')
            ->get()
            ->map(fn (DemographicCensusPeriod $period): array => [
                'key' => $period->period_start->format('Y-m'),
                'label' => $period->period_start->format('F Y'),
                'params' => [
                    'start' => $period->period_start->toDateString(),
                    'end' => $period->period_end->toDateString(),
                ],
                'date' => $period->period_start->toDateString(),
            ])
            ->all();
    }

    public function hasData(array $params): bool
    {
        if (empty($params['start']) || empty($params['end'])) {
            return false;
        }

        return DemographicCensusPeriod::query()
            ->whereDate('period_start', Carbon::parse($params['start'])->toDateString())
            ->whereDate('period_end', Carbon::parse($params['end'])->toDateString())
            ->exists();
    }
}
