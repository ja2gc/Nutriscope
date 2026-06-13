<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\MealPrepLog;
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
        $consumptionByDay = MealPrepLog::where('status', 'completed')
            ->whereBetween('service_date', [$startStr, $endStr])
            ->selectRaw('DATE(service_date) as d, SUM(total_value) as t')
            ->groupByRaw('DATE(service_date)')->pluck('t', 'd');

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

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $ds = $d->toDateString();
            $actual = $source === 'consumption'
                ? (float) ($consumptionByDay[$ds] ?? 0) + (float) ($logByDay[$ds] ?? 0)
                : (float) ($poByDay[$ds] ?? 0) + (float) ($logByDay[$ds] ?? 0);

            $days[] = ['date' => $ds, 'planned' => $cap, 'actual' => $actual];
        }

        return [
            'days'        => $days,
            'source'      => $source,
            'cash_flow'   => round($cashFlow, 2),
            'days_served' => $consumptionByDay->count(), // distinct served service_dates in range
        ];
    }
}
