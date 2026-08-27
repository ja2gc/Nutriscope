<?php

namespace App\Services\Reports\Generators;

use App\Models\DietListCount;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\Contracts\ReportGenerator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Accomplishment Report — per-staff semi-monthly duty sheet (FSS §4).
 *
 * Input params (all on report.parameters):
 *   - from         (date string, required) — pay-period start
 *   - to           (date string, required) — pay-period end
 *   - fss_user_id  (int, optional)         — restrict to one staff member
 *   - menu_cycle_id(int, optional)         — restrict to one menu cycle
 *
 * Output (data array):
 *   - from / to           — Carbon instances for the period
 *   - period_label        — human label, e.g. "June 1–15, 2026"
 *   - days                — array of date strings in the range
 *   - staff_sheets        — one entry per FSS user:
 *       user              — User model
 *       rows              — keyed by task slug, each an assoc of date => cell value
 *                           ('✓' | numeric count | 'X' | '')
 *       daily_population  — legacy key for date => distributed-meal total
 *
 * Tasks (7 rows, in order):
 *   helped_food_prep, stored_supplies, collected_diet_list,
 *   apportioned_food (carries distributed-meal count), cleaned_utensils,
 *   assistant_cook, maintained_cleanliness
 */
class AccomplishmentReportGenerator implements ReportGenerator
{
    /** The seven task rows in display order. */
    public const TASKS = [
        'helped_food_prep' => 'Helped in food preparation work.',
        'stored_supplies' => 'Stored food supplies properly.',
        'collected_diet_list' => 'Collected diet list from different wards.',
        'apportioned_food' => 'Apportioned and distributed food to in patient in the different wards.',
        'cleaned_utensils' => 'Collected, cleaned and returned used utensils and other equipment.',
        'assistant_cook' => 'Assumed duties as assistant cook.',
        'maintained_cleanliness' => 'Monitored cleanliness of kitchen, cabinets, refrigerators and freezers.',
    ];

    /** New numeric fields replace legacy boolean task flags for rows 3 and 4. */
    private const NUMERIC_TASK_FIELDS = [
        'collected_diet_list' => 'collected_ward_diet_lists',
        'apportioned_food' => 'apportioned_distributed_meals',
    ];

    public function type(): string
    {
        return 'accomplishment_report';
    }

    public function view(): string
    {
        return 'reports.accomplishment';
    }

    public function paper(): array
    {
        return ['a4', 'landscape'];
    }

    public function data(Report $report): array
    {
        if (($report->snapshot['accomplishment'] ?? null) !== null) {
            return $this->dataFromSnapshot($report->snapshot['accomplishment']);
        }

        $params = $report->parameters ?? [];

        // Accept both 'start'/'end' (PeriodInstanceSource convention used by the
        // render/archive pipeline) and 'from'/'to' (direct/legacy usage).
        // No current-date fallback — period must be explicit so the report is reproducible.
        $startParam = $params['start'] ?? $params['from'] ?? null;
        $endParam = $params['end'] ?? $params['to'] ?? null;
        if ($startParam === null || $endParam === null) {
            throw new \InvalidArgumentException(
                'Accomplishment report requires an explicit start/end (or from/to) period.'
            );
        }
        $from = Carbon::parse($startParam);
        $to = Carbon::parse($endParam);

        // All calendar days in the period.
        $days = collect(CarbonPeriod::create($from, $to))
            ->map(fn (Carbon $d) => $d->toDateString())
            ->values()
            ->all();

        // Load all DietListCount rows for the period (with users eager-loaded).
        $query = DietListCount::with('user')
            ->whereBetween('service_date', [$from->toDateString(), $to->toDateString()]);

        if (! empty($params['fss_user_id'])) {
            $query->where('fss_user_id', (int) $params['fss_user_id']);
        }
        if (! empty($params['menu_cycle_id'])) {
            $query->where('menu_cycle_id', (int) $params['menu_cycle_id']);
        }

        /** @var Collection<int, DietListCount> $counts */
        $counts = $query->orderBy('service_date')->get();

        // Day-level distributed meal count. Kept under the legacy key for archived
        // report compatibility, but it is not actual served population.
        $dailyPopulation = $counts
            ->groupBy(fn (DietListCount $r) => $r->service_date->toDateString())
            ->map(fn (Collection $rows) => $rows->sum('population'))
            ->all();

        // Build per-staff sheets.
        $staffSheets = $counts
            ->groupBy('fss_user_id')
            ->map(function (Collection $staffRows) use ($days) {
                /** @var User $user */
                $user = $staffRows->first()->user;

                // Keep legacy ward rows together while current form dates use one row.
                $byDate = $staffRows->groupBy(fn (DietListCount $r) => $r->service_date->toDateString());

                $taskRows = [];
                foreach (array_keys(self::TASKS) as $task) {
                    $cells = [];
                    foreach ($days as $date) {
                        /** @var Collection<int, DietListCount>|null $dateRows */
                        $dateRows = $byDate->get($date);

                        if ($dateRows === null) {
                            $cells[$date] = '';
                        } elseif ($dateRows->every(fn (DietListCount $row): bool => $row->off_duty)) {
                            $cells[$date] = 'X';
                        } elseif (array_key_exists($task, self::NUMERIC_TASK_FIELDS)) {
                            $numericField = self::NUMERIC_TASK_FIELDS[$task];
                            $numericRows = $dateRows->filter(fn (DietListCount $row): bool => ! $row->off_duty && $row->{$numericField} !== null)->values();
                            if ($numericRows->isNotEmpty()) {
                                $cells[$date] = $numericRows->sum(fn (DietListCount $row): int => (int) $row->{$numericField});
                            } else {
                                $legacyRows = $dateRows->filter(fn (DietListCount $row): bool => ! $row->off_duty && (bool) $row->{$task})->values();
                                $cells[$date] = $legacyRows->isNotEmpty()
                                    ? ($task === 'apportioned_food' ? $legacyRows->sum('population') : '✓')
                                    : '';
                            }
                        } else {
                            $cells[$date] = $dateRows->contains(fn (DietListCount $row): bool => ! $row->off_duty && $row->$task)
                                ? '✓'
                                : '';
                        }
                    }
                    $taskRows[$task] = $cells;
                }

                return [
                    'user' => $user,
                    'task_rows' => $taskRows,
                ];
            })
            ->values()
            ->all();

        $periodLabel = $from->format('F j').'–'.$to->format('j, Y');
        if ($from->month !== $to->month) {
            $periodLabel = $from->format('F j').' – '.$to->format('F j, Y');
        }

        return [
            'from' => $from,
            'to' => $to,
            'period_label' => $periodLabel,
            'days' => $days,
            'tasks' => self::TASKS,
            'numeric_task' => 'apportioned_food',
            'numeric_tasks' => array_keys(self::NUMERIC_TASK_FIELDS),
            'staff_sheets' => $staffSheets,
            'daily_population' => $dailyPopulation,
        ];
    }

    private function dataFromSnapshot(array $snapshot): array
    {
        $staffSheets = collect($snapshot['staff_sheets'] ?? [])->map(function (array $sheet) {
            $user = new User($sheet['user'] ?? []);
            if (isset($sheet['user']['id'])) {
                $user->id = $sheet['user']['id'];
            }

            return [
                'user' => $user,
                'task_rows' => $sheet['task_rows'] ?? [],
            ];
        })->values()->all();

        return [
            'from' => Carbon::parse($snapshot['from']),
            'to' => Carbon::parse($snapshot['to']),
            'period_label' => $snapshot['period_label'],
            'days' => $snapshot['days'],
            'tasks' => $snapshot['tasks'],
            'numeric_tasks' => $snapshot['numeric_tasks'] ?? [$snapshot['numeric_task'] ?? 'apportioned_food'],
            'staff_sheets' => $staffSheets,
            'daily_population' => $snapshot['daily_population'],
        ];
    }
}
