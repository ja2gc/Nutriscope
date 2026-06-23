<?php

namespace App\Services\FSS;

use App\Models\Budget;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Services\MenuCycleCostService;
use Carbon\Carbon;

/**
 * Calculated budget-per-head-per-day for a procurement span (one shopping list).
 *
 * Two figures, because the real served population is only known after the span runs:
 *
 *   ESTIMATED — available at planning time. Planned menu-cycle ingredient cost over
 *   the span ÷ Σ estimate_population across those days (head-days). Reuses
 *   {@see MenuCycleCostService} so it can never diverge from the planner.
 *
 *   ACTUAL — the real average meal cost: total procurement CASH (received POs) ÷ the
 *   total served population DERIVED from completed meal-prep logs over the span (each
 *   service day records ONE served_population — a per-day ward headcount, never a sum
 *   of breakfast/lunch/dinner, so summing days does not triple-count). It stays PENDING
 *   until BOTH every PO is received AND every service day in the span has a completed
 *   meal-prep log — only then is the procurement span truly closed.
 *
 * "Per day" is the span average (total cost ÷ total served) — no extra divide-by-days.
 */
class ProcurementCostEfficiencyService
{
    /**
     * @return array{estimated: ?float, actual: ?float, pending: bool, pending_reason: ?string,
     *               procurement_cost: float, served_population: int, service_days_done: int,
     *               service_days_expected: int}
     */
    public static function forShoppingList(ShoppingList $list): array
    {
        return [
            'estimated' => self::estimated($list),
            ...self::actual($list),
        ];
    }

    /**
     * Planned cost ÷ planned head-days over the span. Per-date aggregation so a span
     * crossing multiple weeks counts each occurrence of a weekday once. Null when the
     * span has no menu cycle or no day carries an estimate_population.
     */
    public static function estimated(ShoppingList $list): ?float
    {
        if (! $list->menu_cycle_id || ! $list->period_start || ! $list->period_end) {
            return null;
        }

        $cycle = $list->menuCycle;
        if (! $cycle) {
            return null;
        }
        $cycle->loadMissing('days.recipe.ingredients.fsItem', 'days.fsItem');

        $totalCost = 0.0;
        $headDays  = 0;

        for ($d = Carbon::parse($list->period_start); $d->lte(Carbon::parse($list->period_end)); $d->addDay()) {
            $daysForWeekday = $cycle->days->where('day_of_week', $d->format('l'));
            if ($daysForWeekday->isEmpty()) {
                continue;
            }
            $agg = MenuCycleCostService::aggregate(MenuCycleCostService::entriesForDays($daysForWeekday));
            $totalCost += (float) $agg['total_cost'];
            $headDays  += (int) $agg['population'];
        }

        return $headDays > 0 ? round($totalCost / $headDays, 2) : null;
    }

    /**
     * Actual procurement cash ÷ derived served population, gated on full span closure
     * (all POs received AND every service day's meal prep completed).
     *
     * @return array{actual: ?float, pending: bool, pending_reason: ?string, procurement_cost: float,
     *               served_population: int, service_days_done: int, service_days_expected: int}
     */
    public static function actual(ShoppingList $list): array
    {
        $orders = $list->purchaseOrders()->get(['id', 'status', 'total_amount']);
        $procurementCost = (float) $orders->where('status', 'received')->sum('total_amount');
        $awaitingReceipts = $orders->isEmpty() || $orders->contains(fn ($po) => $po->status !== 'received');

        $prep = self::mealPrepProgress($list->menuCycle, $list->period_start, $list->period_end);

        return self::compose(
            $procurementCost,
            $awaitingReceipts,
            $prep['done'],
            $prep['expected'],
            $prep['served'],
        );
    }

    /**
     * Budget-level average meal cost over its period, for the budget's matched menu
     * cycle. Same derived-and-gated logic as a single span, rolled up to the period.
     *
     * @return array{actual: ?float, pending: bool, pending_reason: ?string, procurement_cost: float,
     *               served_population: int, service_days_done: int, service_days_expected: int}
     */
    public static function actualForBudget(Budget $budget, Carbon $start, Carbon $end): array
    {
        $procurementCost = (float) PurchaseOrder::where('status', 'received')
            ->whereRaw('COALESCE(received_date, order_date) BETWEEN ? AND ?', [$start->toDateString(), $end->toDateString()])
            ->sum('total_amount');

        // POs received for the budget's cycle: gate on the cycle's lists being all received.
        $awaitingReceipts = false;
        if ($budget->menu_cycle_id) {
            $awaitingReceipts = PurchaseOrder::whereHas('shoppingList', fn ($q) => $q->where('menu_cycle_id', $budget->menu_cycle_id))
                ->where('status', '!=', 'received')
                ->exists();
        }

        $prep = self::mealPrepProgress($budget->menuCycle, $start->toDateString(), $end->toDateString());

        return self::compose($procurementCost, $awaitingReceipts, $prep['done'], $prep['expected'], $prep['served']);
    }

    /**
     * Service-day progress for a cycle over a span: how many days the cycle is meant to
     * serve, how many have a completed meal-prep log, and the Σ served population of
     * those completed days.
     *
     * @return array{expected: int, done: int, served: int}
     */
    private static function mealPrepProgress(?MenuCycle $cycle, ?string $start, ?string $end): array
    {
        if (! $cycle || ! $start || ! $end) {
            return ['expected' => 0, 'done' => 0, 'served' => 0];
        }

        $cycle->loadMissing('days');
        $weekdaysServed = $cycle->days->pluck('day_of_week')->unique();

        $expected = 0;
        for ($d = Carbon::parse($start); $d->lte(Carbon::parse($end)); $d->addDay()) {
            if ($weekdaysServed->contains($d->format('l'))) {
                $expected++;
            }
        }

        $logs = MealPrepLog::where('menu_cycle_id', $cycle->id)
            ->where('status', 'completed')
            ->whereBetween('service_date', [$start, $end])
            ->get(['service_date', 'served_population']);

        return [
            'expected' => $expected,
            'done'     => $logs->pluck('service_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->count(),
            'served'   => (int) $logs->whereNotNull('served_population')->sum('served_population'),
        ];
    }

    /**
     * Build the result, applying the two gates: all POs received AND the whole span's
     * meal prep completed. Until both are met the actual is null + pending with the
     * specific reason.
     *
     * @return array{actual: ?float, pending: bool, pending_reason: ?string, procurement_cost: float,
     *               served_population: int, service_days_done: int, service_days_expected: int}
     */
    private static function compose(float $procurementCost, bool $awaitingReceipts, int $done, int $expected, int $served): array
    {
        $mealPrepIncomplete = $expected === 0 || $done < $expected;

        $base = [
            'procurement_cost'      => round($procurementCost, 2),
            'served_population'     => $served,
            'service_days_done'     => $done,
            'service_days_expected' => $expected,
        ];

        if ($awaitingReceipts || $mealPrepIncomplete || $served <= 0) {
            $reasons = [];
            if ($awaitingReceipts) {
                $reasons[] = 'PO receipts';
            }
            if ($mealPrepIncomplete) {
                $reasons[] = "meal prep ({$done}/{$expected} days)";
            } elseif ($served <= 0) {
                $reasons[] = 'served population';
            }

            return $base + [
                'actual'         => null,
                'pending'        => true,
                'pending_reason' => 'Awaiting ' . implode(' + ', $reasons),
            ];
        }

        return $base + [
            'actual'         => round($procurementCost / $served, 2),
            'pending'        => false,
            'pending_reason' => null,
        ];
    }
}
