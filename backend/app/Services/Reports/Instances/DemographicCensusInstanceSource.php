<?php

namespace App\Services\Reports\Instances;

use App\Models\DemographicCensusPeriod;
use App\Models\NcpRecord;
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
        $instances = DemographicCensusPeriod::query()
            ->when(! empty($filters['year']), fn ($query) => $query->whereYear('period_start', (int) $filters['year']))
            ->when(! empty($filters['month']), fn ($query) => $query->whereMonth('period_start', (int) $filters['month']))
            ->latest('period_start')
            ->get()
            ->map(fn (DemographicCensusPeriod $period): array => $this->instance(
                $period->period_start,
                $period->period_end,
            ));

        $current = Carbon::now(config('nutriscope-reports.timezone'))->startOfMonth();
        $matchesFilters = (empty($filters['year']) || (int) $filters['year'] === $current->year)
            && (empty($filters['month']) || (int) $filters['month'] === $current->month);
        if ($matchesFilters && NcpRecord::query()->where('created_at', '<=', $current->copy()->endOfMonth())->exists()) {
            $instances->prepend($this->instance($current, $current->copy()->endOfMonth()));
        }

        return $instances->values()->all();
    }

    public function hasData(array $params): bool
    {
        if (empty($params['start']) || empty($params['end'])) {
            return false;
        }

        if (DemographicCensusPeriod::query()
            ->whereDate('period_start', Carbon::parse($params['start'])->toDateString())
            ->whereDate('period_end', Carbon::parse($params['end'])->toDateString())
            ->exists()) {
            return true;
        }

        $start = Carbon::parse($params['start'])->startOfDay();
        $end = Carbon::parse($params['end'])->endOfDay();
        $current = Carbon::now(config('nutriscope-reports.timezone'))->startOfMonth();

        return $start->isSameDay($current)
            && $end->isSameDay($current->copy()->endOfMonth())
            && NcpRecord::query()->where('created_at', '<=', $end)->exists();
    }

    /** @return array{key:string,label:string,params:array{start:string,end:string},date:string} */
    private function instance(Carbon $start, Carbon $end): array
    {
        return [
            'key' => $start->format('Y-m'),
            'label' => $start->format('F Y'),
            'params' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'date' => $start->toDateString(),
        ];
    }
}
