<?php

namespace App\Services\FSS;

    use App\Models\Budget;
    use App\Models\MealPrepLog;
    use App\Models\PurchaseOrder;
    use Carbon\Carbon;

    /**
     * Span-level procurement cost-efficiency: real cash paid to suppliers over a
     * budget span ÷ average headcount actually served across that span's days.
     *
     * NOT per_head_actual. That figure (BudgetActualService::dailySeries) divides
     * food-SERVED VALUE (MealPrepLog.total_value) by served heads — operational
     * dispensing, accrued per day. THIS divides procurement CASH-OUT
     * (PurchaseOrder.total_amount) by served heads — supplier spend efficiency,
     * computed once per span. They diverge on purpose: POs buy stock used outside
     * the span, and served value can draw on prior stock. Kept in a separate class
     * so the two are never folded together by proximity.
     */
    class ProcurementCostEfficiencyService
    {
        /**
         * Real cost per head for a procurement span. Null when no served day in the
         * span recorded a served_population — no silent estimate substitution (per
         * population-redesign: actuals are reporting-only, never back-filled).
         */
        public static function forSpan(Budget $budget, Carbon $start, Carbon $end): ?float
        {
            $startStr = $start->toDateString();
            $endStr   = $end->toDateString();

            // Average served headcount across the span. Explicit count/avg — never
            // rely on the aggregate's empty-set behaviour (AVG of nothing is NULL in
            // SQL but 0 once cast to float). Scope to the budget's cycle when set.
            $servedQuery = MealPrepLog::where('status', 'completed')
                ->whereBetween('service_date', [$startStr, $endStr])
                ->whereNotNull('served_population')
                ->when($budget->menu_cycle_id, fn ($q) => $q->where('menu_cycle_id', $budget->menu_cycle_id));

            if ((clone $servedQuery)->count() === 0) {
                return null;
            }

            $avgServed = (float) (clone $servedQuery)->avg('served_population');
            if ($avgServed <= 0) {
                return null;
            }

            $totalProcurementCost = (float) PurchaseOrder::where('status', 'received')
                ->whereRaw('COALESCE(received_date, order_date) BETWEEN ? AND ?', [$startStr, $endStr])
                ->sum('total_amount');

            return round($totalProcurementCost / $avgServed, 2);
        }
    }
