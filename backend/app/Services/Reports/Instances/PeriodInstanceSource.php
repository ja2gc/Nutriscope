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
        private bool $semiMonthly = false,
    ) {}

    public function axis(): string
    {
        return 'period';
    }

    public function instances(array $filters): array
    {
        $year = ! empty($filters['year']) ? (int) $filters['year'] : null;
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
            [$start, $end] = $this->bounds($date);
            $buckets[$start->toDateString().'_'.$end->toDateString()] = [$start, $end];
        }

        krsort($buckets); // newest first

        return array_values(array_map(function (array $period) {
            [$start, $end] = $period;

            return [
                'key' => $this->semiMonthly
                    ? $start->toDateString().'_'.$end->toDateString()
                    : $start->format('Y-m'),
                'label' => $this->semiMonthly
                    ? $start->format('F j').'–'.$end->format('j, Y')
                    : $start->format('F Y'),
                'params' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                'date' => $start->toDateString(),
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

    /** @return array{Carbon, Carbon} */
    private function bounds(Carbon $date): array
    {
        $start = $date->copy()->startOfMonth();
        if (! $this->semiMonthly) {
            return [$start, $start->copy()->endOfMonth()];
        }

        return $date->day <= 15
            ? [$start, $start->copy()->day(15)]
            : [$start->copy()->day(16), $start->copy()->endOfMonth()];
    }
}
