<?php

namespace App\Services\FSS;

use App\Models\Inventory;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use Carbon\Carbon;

class FssDashboardService
{
    /**
     * Return all FSS dashboard KPIs as a plain array. Every figure is a live
     * query so it reflects the current state of the database.
     */
    public function summary(): array
    {
        $today   = now()->toDateString();
        $weekday = Carbon::today()->format('l'); // e.g. "Friday"

        $cycle = MenuCycle::where('is_active', true)
            ->orderByDesc('activation_date')
            ->first();

        return [
            'meals_to_log_today'    => $this->mealsToLogToday($cycle, $weekday, $today),
            'pos_awaiting_receipt'  => $this->posAwaitingReceipt(),
            'inventory_no_stock'    => $this->inventoryNoStock(),
            'today_service'         => $this->todayService($cycle, $weekday, $today),
        ];
    }

    /**
     * Count of the active cycle's service-day slots for today that have no
     * completed MealPrepLog row yet.
     *
     * A "service day" is represented as a distinct (menu_cycle_id, service_date)
     * pair in meal_prep_logs. If there is at least one 'completed' log for today
     * on this cycle, the day is considered logged (count = 0); otherwise 1.
     *
     * We return 0 when no active cycle exists.
     */
    private function mealsToLogToday(?MenuCycle $cycle, string $weekday, string $today): int
    {
        if (! $cycle) {
            return 0;
        }

        // Does any slot exist for today's weekday in this cycle?
        $hasSlots = MenuCycleDay::where('menu_cycle_id', $cycle->id)
            ->where('day_of_week', $weekday)
            ->exists();

        if (! $hasSlots) {
            return 0;
        }

        // Has today already been completed for this cycle?
        $alreadyLogged = MealPrepLog::where('menu_cycle_id', $cycle->id)
            ->where('service_date', $today)
            ->where('status', 'completed')
            ->exists();

        return $alreadyLogged ? 0 : 1;
    }

    /**
     * Count of purchase orders with status 'ordered' that have no attachment
     * of type 'receipt' or 'proof'.
     */
    private function posAwaitingReceipt(): int
    {
        return PurchaseOrder::where('status', 'ordered')
            ->whereDoesntHave('attachments', function ($q) {
                $q->whereIn('type', ['receipt', 'proof']);
            })
            ->count();
    }

    /**
     * Count of inventory rows where quantity_in_stock is at or below zero.
     */
    private function inventoryNoStock(): int
    {
        return Inventory::where('quantity_in_stock', '<=', 0)->count();
    }

    /**
     * Return the active cycle's meal slots for today with their prep readiness.
     * Each element carries the slot identity (meal_type, recipe/item name) and
     * whether the day has been completed (prepped/served) or is still pending.
     *
     * Returns an empty array when no active cycle exists or it has no slots today.
     */
    private function todayService(?MenuCycle $cycle, string $weekday, string $today): array
    {
        if (! $cycle) {
            return [];
        }

        $slots = MenuCycleDay::with('recipe:id,name', 'fsItem:id,name')
            ->where('menu_cycle_id', $cycle->id)
            ->where('day_of_week', $weekday)
            ->orderBy('meal_type')
            ->get();

        if ($slots->isEmpty()) {
            return [];
        }

        $log = MealPrepLog::where('menu_cycle_id', $cycle->id)
            ->where('service_date', $today)
            ->where('status', 'completed')
            ->first();

        $ready = $log !== null;

        return $slots->map(fn ($slot) => [
            'meal_type'  => $slot->meal_type,
            'name'       => $slot->recipe?->name ?? $slot->fsItem?->name ?? 'Unknown',
            'prepped'    => $ready,
            'has_shortfall' => $ready && (bool) $log->has_shortfall,
        ])->values()->all();
    }
}
