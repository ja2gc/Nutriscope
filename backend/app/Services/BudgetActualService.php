<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\MealPrepLog;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use Carbon\Carbon;

/**
 * Single source of truth for a budget's daily planned-vs-actual series.
 *
 * planned = the daily cap (per-head/day × population, else allocated / range-days).
 * actual  = per a range-level switch (Spec 6 §5-D):
 *   - consumption mode (≥1 completed MealPrepLog in range): Σ completed
 *     MealPrepLog.total_value by service_date + manual budget_daily_logs.spent;
 *     received POs are NOT counted here — they are returned separately as
 *     cash_flow (the Dietary Cash Book disbursements).
 *   - purchases mode (no served days in range): received-PO totals by date +
 *     manual logs — the legacy estimate, labelled 'purchases'.
 *
 * Consumed by BudgetController::summary and BudgetReportGenerator so the live
 * dashboard and the printed report can never drift apart.
 *
 * SCOPING NOTE: consumption is read FACILITY-WIDE (every completed MealPrepLog in
 * the range, across all menu cycles) — it is NOT scoped to this budget's cycle or
 * population. This is correct for a single-facility / one-active-budget-per-period
 * model. If budgets can ever overlap in time, two budgets would report the same
 * consumption "actual"; scope by menu_cycle_id/period before relying on that.
 */
class BudgetActualService
{
    /**
     * @return array{days: array<int,array{date:string,planned:float,actual:float}>, source: string, cash_flow: float, days_served: int}
     */
    public static function dailySeries(Budget $budget, Carbon $start, Carbon $end): array
    {
        $startStr = $start->toDateString();
        $endStr   = $end->toDateString();

        // Consumption: facility-wide food served per day (completed logs only).
        // DATE(...) normalises keys to Y-m-d across sqlite (which stores datetimes
        // on date columns) and MySQL, so per-day lookups below always match.
        $mealPrepQuery = MealPrepLog::where('status', 'completed')
            ->whereBetween('service_date', [$startStr, $endStr])
            ->when($budget->menu_cycle_id, fn ($query) => $query->where('menu_cycle_id', $budget->menu_cycle_id));

        $consumptionByDay = (clone $mealPrepQuery)
            ->selectRaw('DATE(service_date) as d, SUM(total_value) as t')
            ->groupByRaw('DATE(service_date)')->pluck('t', 'd');

        // Headcount actually served per day (FSS-reported; actual fed, not prepared-for).
        // Excludes days where served_population hasn't been reported yet — no fallback to
        // prepared estimates, as per population-redesign doc.
        $populationByDay = (clone $mealPrepQuery)
            ->whereNotNull('served_population')
            ->selectRaw('DATE(service_date) as d, SUM(served_population) as p')
            ->groupByRaw('DATE(service_date)')->pluck('p', 'd');

        // Manual non-PO cash logs entered by hand.
        $logByDay = $budget->dailyLogs()
            ->whereBetween('log_date', [$startStr, $endStr])
            ->selectRaw('DATE(log_date) as d, SUM(spent) as t')
            ->groupByRaw('DATE(log_date)')->pluck('t', 'd');

        // Received POs: cash disbursed (Dietary Cash Book) — also the legacy
        // actual fallback when no day has been served yet.
        $poByDay = PurchaseOrder::where('status', 'received')
            ->whereRaw('COALESCE(received_date, order_date) BETWEEN ? AND ?', [$startStr, $endStr])
            ->selectRaw('DATE(COALESCE(received_date, order_date)) as d, SUM(total_amount) as t')
            ->groupByRaw('DATE(COALESCE(received_date, order_date))')->pluck('t', 'd');

        $source   = $consumptionByDay->isNotEmpty() ? 'consumption' : 'purchases';
        $cashFlow = (float) $poByDay->sum();

        $cap = ($budget->budget_per_head_day && $budget->population)
            ? (float) $budget->budget_per_head_day * (int) $budget->population
            : ((float) ($budget->allocated_amount ?? 0) / max(1, $start->diffInDays($end) + 1));

        $eventCapsByDate = collect();
        if ($budget->menu_cycle_id && $budget->menuCycle?->week_start_date) {
            $weekStart = $budget->menuCycle->week_start_date;
            $dayOffsets = ['Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2, 'Thursday' => 3, 'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6];
            $eventCapsByDate = MenuCycleDay::query()
                ->where('menu_cycle_id', $budget->menu_cycle_id)
                ->where('is_event', true)
                ->whereNotNull('event_allocation')
                ->get(['day_of_week', 'event_allocation'])
                ->mapWithKeys(fn (MenuCycleDay $day) => [
                    $weekStart->copy()->addDays($dayOffsets[$day->day_of_week] ?? 0)->toDateString() => (float) $day->event_allocation,
                ]);
        }

        $days = [];
        $popSum = 0;       // Σ headcount over served days (with a recorded population)
        $popDays = 0;      // count of served days that recorded a population
        $servedValue = 0.0; // Σ food-served value on those same days (per-head numerator)
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $ds = $d->toDateString();
            $actual = $source === 'consumption'
                ? (float) ($consumptionByDay[$ds] ?? 0) + (float) ($logByDay[$ds] ?? 0)
                : (float) ($poByDay[$ds] ?? 0) + (float) ($logByDay[$ds] ?? 0);

            $pop = isset($populationByDay[$ds]) ? (int) $populationByDay[$ds] : null;
            $dayConsumption = (float) ($consumptionByDay[$ds] ?? 0);
            if ($pop !== null && $pop > 0) {
                $popSum += $pop;
                $popDays++;
                $servedValue += $dayConsumption;
            }
            // Realized cost per head that day = value of food served ÷ that day's headcount.
            $perHead = ($pop !== null && $pop > 0) ? round($dayConsumption / $pop, 2) : null;

            $isEvent = $eventCapsByDate->has($ds);
            $days[] = [
                'date' => $ds,
                'planned' => $isEvent ? (float) $eventCapsByDate[$ds] : $cap,
                'actual' => $actual,
                'population' => $pop,
                'per_head' => $perHead,
                'event' => $isEvent,
            ];
        }

        return [
            'days'           => $days,
            'source'         => $source,
            'cash_flow'      => round($cashFlow, 2),
            'days_served'    => $consumptionByDay->count(), // distinct served service_dates in range
            // Daily-headcount roll-ups (null when no served day recorded a population):
            'avg_population' => $popDays > 0 ? round($popSum / $popDays, 2) : null,
            'per_head_actual' => $popSum > 0 ? round($servedValue / $popSum, 2) : null,
        ];
    }
}
