<?php

namespace App\Services\FSS;

use App\Models\Inventory;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Services\MenuCycleCostService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsumptionService
{
    private const EPS = 1e-6;

    /**
     * Complete a whole service day: deduct every meal slot's planned base-unit
     * ingredients from inventory at stored last-cost, snapshotting each line.
     * Idempotent per (cycle, date); blocks (422) on any shortfall before touching stock.
     */
    public function completeDay(MenuCycle $cycle, string $serviceDate, ?int $populationOverride = null): MealPrepLog
    {
        $weekday = Carbon::parse($serviceDate)->format('l');

        return DB::transaction(function () use ($cycle, $serviceDate, $populationOverride, $weekday) {
            if (MealPrepLog::where('menu_cycle_id', $cycle->id)
                ->where('service_date', $serviceDate)
                ->where('status', 'completed')->exists()) {
                abort(422, "Service day {$serviceDate} is already completed for this cycle.");
            }

            $cycle->loadMissing('days.recipe.ingredients.fsItem', 'days.fsItem');
            $days = $cycle->days->where('day_of_week', $weekday);
            if ($days->isEmpty()) {
                abort(422, "No menu slots planned for {$weekday}.");
            }

            $target = $populationOverride ?? (int) $cycle->population;
            $usage  = MenuCycleCostService::usageForDays($days, $target); // [{fs_item_id,name,unit,quantity,cost}]

            $invByItem = Inventory::whereIn('fs_item_id', array_column($usage, 'fs_item_id'))
                ->lockForUpdate()->get()->keyBy('fs_item_id');

            // Pre-flight cover check — block before any deduction.
            $short = [];
            foreach ($usage as $u) {
                $have = (float) optional($invByItem[$u['fs_item_id']] ?? null)->quantity_in_stock;
                if ($have + self::EPS < (float) $u['quantity']) {
                    $short[] = "{$u['name']}: need " . round($u['quantity'], 2) . " {$u['unit']}, have " . round($have, 2);
                }
            }
            if ($short) {
                abort(422, 'Insufficient stock to serve ' . $weekday . ' — fix upstream (receive the PO or adjust headcount). Short: ' . implode('; ', $short));
            }

            $log = MealPrepLog::create([
                'menu_cycle_id' => $cycle->id,
                'service_date'  => $serviceDate,
                'population'    => $target, // headcount actually served this day
                'status'        => 'completed',
                'completed_by'  => Auth::id(),
                'completed_at'  => now(),
                'total_value'   => 0,
                'has_shortfall' => false,
            ]);

            $total = 0.0;
            foreach ($usage as $u) {
                $inv      = $invByItem[$u['fs_item_id']];
                $unitCost = $inv->unit_price !== null ? (float) $inv->unit_price : ($inv->fsItem?->unit_cost ?? 0.0);
                $qty      = (float) $u['quantity'];

                $inv->quantity_in_stock = (float) $inv->quantity_in_stock - $qty;
                $inv->save();

                $lineValue = round($qty * $unitCost, 2);
                $log->lines()->create([
                    'fs_item_id'    => $u['fs_item_id'],
                    'qty_base'      => $qty,
                    'unit'          => $u['unit'],
                    'unit_cost'     => $unitCost,
                    'line_value'    => $lineValue,
                    'shortfall_qty' => 0,
                ]);
                $total += $lineValue;
            }

            $log->update(['total_value' => round($total, 2)]);

            return $log->load('lines');
        });
    }

    /** Un-complete a day: add back exactly the snapshot quantities (never a recompute). */
    public function reverseDay(MealPrepLog $log): MealPrepLog
    {
        if ($log->status === 'reversed') {
            abort(422, 'This service day is already reversed.');
        }

        return DB::transaction(function () use ($log) {
            foreach ($log->lines as $line) {
                $inv = Inventory::where('fs_item_id', $line->fs_item_id)->lockForUpdate()->first();
                if ($inv) {
                    $inv->quantity_in_stock = (float) $inv->quantity_in_stock + (float) $line->qty_base;
                    $inv->save();
                }
            }
            $log->update(['status' => 'reversed']);

            return $log->fresh('lines');
        });
    }
}
