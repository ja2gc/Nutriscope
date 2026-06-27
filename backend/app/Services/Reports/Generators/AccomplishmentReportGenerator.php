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
 * Accomplishment Report — per-staff weekly/pay-period duty sheet (FSS §4).
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
 *                           ('✓' | population int | 'off-duty' | '–')
 *       daily_population  — date => int (served total for that day across all staff)
 *
 * Tasks (7 rows, in order):
 *   helped_food_prep, stored_supplies, collected_diet_list,
 *   apportioned_food (carries population number), cleaned_utensils,
 *   assistant_cook, maintained_cleanliness
 */
class AccomplishmentReportGenerator implements ReportGenerator
{
    /** The seven task rows in display order. */
    public const TASKS = [
        'helped_food_prep'       => 'Helped in food preparation work',
        'stored_supplies'        => 'Stored food supplies properly',
        'collected_diet_list'    => 'Collected diet list from different wards',
        'apportioned_food'       => 'Apportioned and distributed food to in-patients in different wards',
        'cleaned_utensils'       => 'Collected, cleaned and returned used utensils and dining equipment',
        'assistant_cook'         => 'Assumed duties as assistant cook',
        'maintained_cleanliness' => 'Maintained cleanliness of kitchen, cabinets, refrigerators and freezers',
    ];

    /** Row 4 carries the numeric headcount; the rest are checkmark rows. */
    private const NUMERIC_TASK = 'apportioned_food';

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
        $endParam   = $params['end']   ?? $params['to']   ?? null;
        if ($startParam === null || $endParam === null) {
            throw new \InvalidArgumentException(
                'Accomplishment report requires an explicit start/end (or from/to) period.'
            );
        }
        $from = Carbon::parse($startParam);
        $to   = Carbon::parse($endParam);

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

        // Day-level served population (sum of all staff rows per day).
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

                // Index rows by date for O(1) lookup.
                $byDate = $staffRows->keyBy(fn (DietListCount $r) => $r->service_date->toDateString());

                $taskRows = [];
                foreach (array_keys(self::TASKS) as $task) {
                    $cells = [];
                    foreach ($days as $date) {
                        /** @var DietListCount|null $row */
                        $row = $byDate->get($date);

                        if ($row === null) {
                            $cells[$date] = '–';
                        } elseif ($row->off_duty) {
                            $cells[$date] = 'X';
                        } elseif ($task === self::NUMERIC_TASK) {
                            // Row 4: show the numeric headcount this staff distributed.
                            $cells[$date] = $row->population ?? '–';
                        } else {
                            $cells[$date] = $row->$task ? '✓' : '–';
                        }
                    }
                    $taskRows[$task] = $cells;
                }

                return [
                    'user'     => $user,
                    'task_rows'=> $taskRows,
                ];
            })
            ->values()
            ->all();

        $periodLabel = $from->format('F j') . '–' . $to->format('j, Y');
        if ($from->month !== $to->month) {
            $periodLabel = $from->format('F j') . ' – ' . $to->format('F j, Y');
        }

        return [
            'from'             => $from,
            'to'               => $to,
            'period_label'     => $periodLabel,
            'days'             => $days,
            'tasks'            => self::TASKS,
            'numeric_task'     => self::NUMERIC_TASK,
            'staff_sheets'     => $staffSheets,
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
            'from'             => Carbon::parse($snapshot['from']),
            'to'               => Carbon::parse($snapshot['to']),
            'period_label'     => $snapshot['period_label'],
            'days'             => $snapshot['days'],
            'tasks'            => $snapshot['tasks'],
            'numeric_task'     => $snapshot['numeric_task'],
            'staff_sheets'     => $staffSheets,
            'daily_population' => $snapshot['daily_population'],
        ];
    }
}
