<?php

namespace App\Services\FSS;

use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderVendorGroup;
use Carbon\Carbon;

class FssDashboardService
{
    public function __construct(
        private PurchaseOrderLifecycleService $lifecycle = new PurchaseOrderLifecycleService(),
    ) {
    }

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

        $pendingPos = $this->pendingPos();

        return [
            'meals_to_log_today'    => $this->mealsToLogToday($cycle, $weekday, $today),
            'pending_pos'           => $pendingPos,
            'pending_pos_count'     => count($pendingPos),
            'today_service'         => $this->todayService($cycle, $weekday, $today),
            'active_cycle'          => $this->activeCycleInfo($cycle),
        ];
    }

    /**
     * Active menu cycle summary for the dashboard card.
     */
    private function activeCycleInfo(?MenuCycle $cycle): ?array
    {
        if (! $cycle) {
            return null;
        }

        $serviceDayCount = MenuCycleDay::where('menu_cycle_id', $cycle->id)
            ->distinct('day_of_week')
            ->count('day_of_week');

        return [
            'id'               => $cycle->id,
            'name'             => $cycle->name,
            'activation_date'  => $cycle->activation_date
                ? Carbon::parse($cycle->activation_date)->toDateString()
                : null,
            'service_day_count' => $serviceDayCount,
        ];
    }

    /**
     * Open-execution POs and what each is waiting on. Food POs wait on receipts
     * and/or served population; supplies POs wait on receipts only.
     *
     * @return array<int,array{id:int,po_number:?string,procurement_track:string,waiting_on:array<int,string>}>
     */
    private function pendingPos(): array
    {
        $pos = PurchaseOrder::with(['vendorGroups.attachments', 'shoppingList'])
            ->where('lifecycle_status', 'open_execution')
            ->orderByDesc('converted_at')
            ->get();

        return $pos->map(function (PurchaseOrder $po) {
            $waiting = [];

            $needsReceipts = $po->vendorGroups->isEmpty()
                || $po->vendorGroups->contains(
                    fn (PurchaseOrderVendorGroup $g) => $g->attachments->where('type', 'receipt')->isEmpty()
                );
            if ($needsReceipts) {
                $waiting[] = 'receipts';
            }

            if ($po->isFoodTrack()) {
                $prep = $this->lifecycle->servedPopulationProgress($po->shoppingList);
                if ($prep['expected'] === 0 || $prep['done'] < $prep['expected'] || $prep['served'] <= 0) {
                    $waiting[] = 'served_population';
                }
            }

            return [
                'id'                => $po->id,
                'po_number'         => $po->po_number,
                'procurement_track' => $po->procurement_track,
                'waiting_on'        => $waiting,
            ];
        })->filter(fn ($p) => $p['waiting_on'] !== [])->values()->all();
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
