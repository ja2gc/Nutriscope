<?php

namespace App\Services\Reports\Generators;

use App\Models\MenuCycle;
use App\Models\ProgramProjectActivity;
use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Services\MenuCycleCostService;
use App\Services\Reports\Contracts\ReportGenerator;
use Carbon\Carbon;

/**
 * PPA — Program Project Activity ("Food Subsistence for Patients").
 * The weekly menu + total cost + output (headcount) + inclusive dates + signatures.
 *
 * Cost + headcount come straight from {@see MenuCycleCostService} (the single source
 * of truth) — this generator never recomputes ingredient math, it only shapes the
 * menu listing onto the inclusive calendar dates. The menu-shaping + date expansion
 * are pure (and unit-tested); data() is the thin DB loader.
 */
class ProgramProjectActivityGenerator implements ReportGenerator
{
    /** Canonical meal_type key (matches the DB enum) => printable label. */
    public const MEALS = [
        'breakfast' => 'Breakfast',
        'am_snack'  => 'AM Snack',
        'lunch'     => 'Lunch',
        'pm_snack'  => 'PM Snack',
        'dinner'    => 'Dinner',
    ];

    /** Display labels in serving order (used by the calendar grid). */
    public const MEAL_ORDER = ['Breakfast', 'AM Snack', 'Lunch', 'PM Snack', 'Dinner'];

    /** Normalize any meal_type spelling ("AM Snack", "am-snack") to the enum key. */
    public static function mealKey(string $type): string
    {
        return str_replace([' ', '-'], '_', strtolower(trim($type)));
    }

    public static function mealLabel(string $type): string
    {
        return self::MEALS[self::mealKey($type)] ?? $type;
    }

    /** Sort index of a meal in serving order; unknown meals sort last. */
    public static function mealOrder(string $type): int
    {
        $i = array_search(self::mealKey($type), array_keys(self::MEALS), true);
        return $i === false ? 99 : $i;
    }

    public function type(): string
    {
        return 'program_project_activity';
    }

    public function view(): string
    {
        return 'reports.program-project-activity';
    }

    public function paper(): array
    {
        return ['a4', 'portrait'];
    }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];
        if (! empty($params['purchase_order_id'])) {
            $po = PurchaseOrder::with(['programProjectActivity', 'shoppingList'])
                ->where('procurement_track', 'food')
                ->whereIn('lifecycle_status', ['completed', 'archived'])
                ->findOrFail($params['purchase_order_id']);

            $ppa = $po->programProjectActivity;
            abort_unless($ppa, 404, 'No PPA snapshot exists for this purchase order.');

            $start = optional($ppa->period_start)->toDateString()
                ?? optional($po->shoppingList?->period_start)->toDateString();
            $end = optional($ppa->period_end)->toDateString()
                ?? optional($po->shoppingList?->period_end)->toDateString();
            abort_unless($start && $end, 404, 'Purchase order has no procurement span.');

            $output = (int) ($ppa->actual_output_patients ?? 0);

            return [
                'activity'        => $ppa->activity ?: 'Food Subsistence for Patients',
                'cycle'           => null,
                'menu_days'       => $this->menuDaysForRange($start, $end),
                'total_cost'      => round((float) ($ppa->actual_total_cost ?? $po->total_amount ?? 0), 2),
                'population'      => $output,
                'output_label'    => number_format($output) . ' Patients (actual served)',
                'inclusive_start' => $start,
                'inclusive_end'   => $end,
                'inclusive_label' => Carbon::parse($start)->format('m/d/y') . '-' . Carbon::parse($end)->format('m/d/y'),
            ];
        }

        $cycle  = MenuCycle::with('days.recipe', 'days.fsItem')->findOrFail($params['menu_cycle_id']);

        [$start, $end] = $this->resolveRange($cycle, $params);
        $calendar      = self::calendarDates($start, $end);
        $entries       = self::groupEntries($cycle);
        $menuDays      = self::buildMenuDays($entries, $calendar);

        // A PPA is bound to a purchase order: once a food shopping list is converted, the
        // PPA carries frozen totals + output that finalize when the PO completes. The
        // report reads that snapshot first so it stays reproducible and PO-independent,
        // and never drifts if the cycle is edited afterwards.
        $ppa = ProgramProjectActivity::query()
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start)
            ->whereHas('purchaseOrder', fn ($q) => $q->where('procurement_track', 'food'))
            ->latest('id')
            ->first();

        if ($ppa) {
            $isFinal  = $ppa->execution_frozen_at !== null;
            $total    = (float) ($isFinal ? $ppa->actual_total_cost : $ppa->estimated_total_cost);
            $output   = (int) ($isFinal ? $ppa->actual_output_patients : $ppa->estimated_output_patients);
            $outLabel = number_format($output) . ' Patients' . ($isFinal ? ' (actual served)' : ' (estimated)');
        } else {
            // No PO yet — fall back to the cycle's frozen cost snapshot (never a live
            // recompute that drifts when catalog prices change) and planning estimate.
            $cost     = $cycle->cost_snapshot ?: MenuCycleCostService::forReport($cycle);
            $dayCosts = $cost['days'] ?? [];
            $total    = 0.0;
            foreach ($calendar as $c) {
                $total += $dayCosts[$c['day_of_week']]['cost'] ?? 0.0;
            }
            $output   = (int) round($cycle->days->whereNotNull('estimate_population')->avg('estimate_population') ?? 0);
            $outLabel = number_format($output) . ' Patients (estimated)';
        }

        return [
            'activity'        => 'Food Subsistence for Patients',
            'cycle'           => $cycle,
            'menu_days'       => $menuDays,
            'total_cost'      => round($total, 2),
            'population'      => $output,
            'output_label'    => $outLabel,
            'inclusive_start' => $start,
            'inclusive_end'   => $end,
            'inclusive_label' => Carbon::parse($start)->format('m/d/y') . '-' . Carbon::parse($end)->format('m/d/y'),
        ];
    }

    /** @return array{0:string,1:string} [start, end] Y-m-d */
    private function resolveRange(MenuCycle $cycle, array $params): array
    {
        if (! empty($params['start']) && ! empty($params['end'])) {
            return [$params['start'], $params['end']];
        }

        $start = $cycle->week_start_date
            ? Carbon::parse($cycle->week_start_date)
            : Carbon::now()->startOfWeek();
        $len = max(1, (int) ($cycle->cycle_days ?: 7));

        return [$start->toDateString(), $start->copy()->addDays($len - 1)->toDateString()];
    }

    private function menuDaysForRange(string $start, string $end): array
    {
        return array_map(function (array $date) {
            $cycle = MenuCycle::coveringDate($date['date']);
            if (! $cycle) {
                return [
                    'date'        => $date['date'],
                    'label'       => Carbon::parse($date['date'])->format('d'),
                    'day_of_week' => $date['day_of_week'],
                    'meals'       => [],
                ];
            }

            $cycle->loadMissing('days.recipe', 'days.fsItem');
            $entries = self::groupEntries($cycle);
            return self::buildMenuDays($entries, [$date])[0];
        }, self::calendarDates($start, $end));
    }

    /**
     * Group a cycle's days into day_of_week => [{meal_type, name}], reading the
     * recipe name (or single fs_item name) for each planned slot.
     *
     * @return array<string,array<int,array{meal_type:string,name:string}>>
     */
    public static function groupEntries(MenuCycle $cycle): array
    {
        $entries = [];
        foreach ($cycle->days as $day) {
            // Frozen-first: a converted cell carries a po_snapshot with the dish name
            // captured at conversion, so the listing can't drift if the cycle is edited.
            $snapshotName = is_array($day->po_snapshot) ? ($day->po_snapshot['name'] ?? null) : null;
            $name = $snapshotName ?? $day->recipe?->name ?? $day->fsItem?->name;
            if (! $name) {
                continue;
            }
            $entries[$day->day_of_week][] = [
                'meal_type' => (string) $day->meal_type,
                'name'      => $name,
            ];
        }
        return $entries;
    }

    /**
     * Expand an inclusive date range into [{date, day_of_week}] rows.
     *
     * @return array<int,array{date:string,day_of_week:string}>
     */
    public static function calendarDates(string $start, string $end): array
    {
        $out    = [];
        $cursor = Carbon::parse($start);
        $last   = Carbon::parse($end);
        while ($cursor->lte($last)) {
            $out[] = ['date' => $cursor->toDateString(), 'day_of_week' => $cursor->format('l')];
            $cursor->addDay();
        }
        return $out;
    }

    /**
     * Map the cycle's per-weekday entries onto calendar dates, meals in canonical
     * order. Dates with no plan come back with an empty meals list.
     *
     * @param  array<string,array<int,array{meal_type:string,name:string}>> $entries
     * @param  array<int,array{date:string,day_of_week:string}> $calendar
     * @return array<int,array{date:string,label:string,day_of_week:string,meals:array}>
     */
    public static function buildMenuDays(array $entries, array $calendar): array
    {
        return array_map(function ($c) use ($entries) {
            $meals = $entries[$c['day_of_week']] ?? [];
            usort($meals, fn ($a, $b) => self::mealOrder($a['meal_type']) <=> self::mealOrder($b['meal_type']));

            return [
                'date'        => $c['date'],
                'label'       => Carbon::parse($c['date'])->format('d'),
                'day_of_week' => $c['day_of_week'],
                'meals'       => array_values($meals),
            ];
        }, $calendar);
    }
}
