<?php

namespace App\Services\Reports\Instances;

use App\Services\Reports\Contracts\InstanceSource;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Date-range axis: enumerates the year→month buckets that contain data, each
 * rendered with {start, end} params. Bucketing is done in PHP (over a plucked
 * date column) to stay portable across sqlite (tests) and MySQL (dev/prod) —
 * neither YEAR()/MONTH() nor strftime() is shared between them.
 */
class PeriodInstanceSource implements InstanceSource
{
    /** @param Closure():Builder $query A fresh base query (e.g. received POs). */
    public function __construct(
        private Closure $query,
        private string $dateColumn,
    ) {}

    public function axis(): string
    {
        return 'period';
    }

    public function instances(array $filters): array
    {
        $year  = ! empty($filters['year']) ? (int) $filters['year'] : null;
        $month = ! empty($filters['month']) ? (int) $filters['month'] : null;

        $buckets = [];
        foreach ($this->dates() as $raw) {
            $date = Carbon::parse($raw);
            if ($year !== null && $date->year !== $year) {
                continue;
            }
            if ($month !== null && $date->month !== $month) {
                continue;
            }
            $buckets[sprintf('%04d-%02d', $date->year, $date->month)] = [$date->year, $date->month];
        }

        krsort($buckets); // newest first

        return array_values(array_map(function (array $ym) {
            [$y, $m] = $ym;
            $start = Carbon::create($y, $m, 1)->startOfDay();
            $end   = $start->copy()->endOfMonth();

            return [
                'key'    => $start->format('Y-m'),
                'label'  => $start->format('F Y'),
                'params' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                'date'   => $start->toDateString(),
            ];
        }, $buckets));
    }

    public function hasData(array $params): bool
    {
        if (empty($params['start']) || empty($params['end'])) {
            return false;
        }

        return ($this->query)()
            ->whereBetween($this->dateColumn, [
                Carbon::parse($params['start'])->toDateString(),
                Carbon::parse($params['end'])->toDateString(),
            ])
            ->exists();
    }

    /** @return iterable<int,mixed> */
    private function dates(): iterable
    {
        return ($this->query)()
            ->whereNotNull($this->dateColumn)
            ->orderBy($this->dateColumn)
            ->pluck($this->dateColumn);
    }
}
