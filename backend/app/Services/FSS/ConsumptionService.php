<?php

namespace App\Services\FSS;

use App\Models\DietListCount;
use App\Models\Inventory;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\Notification;
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
     * Idempotent per (cycle, date).
     *
     * Stock handling:
     *  - default: blocks (422) on any ingredient shortfall before touching stock.
     *  - $allowShortfall: serves what stock covers, records shortfall_qty per line,
     *    flags has_shortfall, and notifies the RND so they can substitute.
     *
     * Population: $populationOverride is the headcount PREPARED for (drives usage).
     * $servedPopulation is who was actually FED; the prepared−served gap is stored as
     * population_variance (+ = cooked too much / surplus, − = cooked too little) and
     * also alerts the RND.
     */
    public function completeDay(
        MenuCycle $cycle,
        string $serviceDate,
        ?int $populationOverride = null,
        ?int $servedPopulation = null,
        bool $allowShortfall = false
    ): MealPrepLog {
        $weekday = Carbon::parse($serviceDate)->format('l');

        // Prefer diet-list summed population as served_population when rows exist for this date.
        // Rows scoped to this cycle_id take precedence; fall back to unscoped rows for the date.
        $dietQuery = DietListCount::where('service_date', $serviceDate)
            ->where(function ($q) use ($cycle) {
                $q->where('menu_cycle_id', $cycle->id)->orWhereNull('menu_cycle_id');
            });

        if ($dietQuery->exists()) {
            $servedPopulation = (int) $dietQuery->sum('population');
        }

        return DB::transaction(function () use ($cycle, $serviceDate, $populationOverride, $servedPopulation, $allowShortfall, $weekday) {
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

            // Ingredient usage is driven by each day's planned estimate_population — the
            // headcount that drove the purchase. Actual/served (recorded below) never
            // rescale the math (they're only known after the fact). $populationOverride
            // records the prepared-for headcount; it defaults to the day's estimate.
            $dayEstimate = (int) ($days->first()->estimate_population ?? 0);
            $target = $populationOverride ?? $dayEstimate;
            $usage  = MenuCycleCostService::usageForDays($days); // [{fs_item_id,name,unit,quantity,cost}]

            $invByItem = Inventory::whereIn('fs_item_id', array_column($usage, 'fs_item_id'))
                ->lockForUpdate()->get()->keyBy('fs_item_id');

            // Cover check. Without $allowShortfall this is a hard block (422) before any
            // deduction; with it, the gap is served short and recorded instead.
            $short = [];
            foreach ($usage as $u) {
                $have = (float) optional($invByItem[$u['fs_item_id']] ?? null)->quantity_in_stock;
                if ($have + self::EPS < (float) $u['quantity']) {
                    $short[] = "{$u['name']}: need " . round($u['quantity'], 2) . " {$u['unit']}, have " . round($have, 2);
                }
            }
            if ($short && ! $allowShortfall) {
                abort(422, 'Insufficient stock to serve ' . $weekday . ' — fix upstream (receive the PO or adjust headcount), or complete with allow_shortfall. Short: ' . implode('; ', $short));
            }

            $variance = $servedPopulation === null ? null : ($target - $servedPopulation);

            $log = MealPrepLog::create([
                'menu_cycle_id'       => $cycle->id,
                'service_date'        => $serviceDate,
                'population'          => $target,            // prepared-for headcount
                'served_population'   => $servedPopulation,  // actually fed (nullable)
                'population_variance' => $variance,
                'status'              => 'completed',
                'completed_by'        => Auth::id(),
                'completed_at'        => now(),
                'total_value'         => 0,
                'has_shortfall'       => false,
            ]);

            $total          = 0.0;
            $hasShortfall   = false;
            $shortfallLines = [];
            foreach ($usage as $u) {
                $inv      = $invByItem[$u['fs_item_id']];
                $unitCost = $inv->unit_price !== null ? (float) $inv->unit_price : ($inv->fsItem?->unit_cost ?? 0.0);
                $need     = (float) $u['quantity'];
                $have     = (float) ($inv->quantity_in_stock ?? 0);

                // Serve what stock covers; the uncovered remainder is the shortfall.
                $served       = $allowShortfall ? min($have, $need) : $need;
                $shortfallQty = round(max(0, $need - $served), 4);
                if ($shortfallQty > self::EPS) {
                    $hasShortfall     = true;
                    $shortfallLines[] = "{$u['name']}: short " . $shortfallQty . " {$u['unit']}";
                }

                $inv->quantity_in_stock = $have - $served;
                $inv->save();

                $lineValue = round($served * $unitCost, 2);
                $log->lines()->create([
                    'fs_item_id'    => $u['fs_item_id'],
                    'qty_base'      => $served,
                    'unit'          => $u['unit'],
                    'unit_cost'     => $unitCost,
                    'line_value'    => $lineValue,
                    'shortfall_qty' => $shortfallQty,
                ]);
                $total += $lineValue;
            }

            $log->update([
                'total_value'   => round($total, 2),
                'has_shortfall' => $hasShortfall,
            ]);

            $this->alertRndOfVariance($cycle, $log, $shortfallLines, $variance);

            return $log->load('lines');
        });
    }

    /**
     * Tell the menu cycle's RND when a service day didn't go to plan, so they can
     * substitute (ingredient ran short) or right-size the cycle (over/under prep).
     */
    private function alertRndOfVariance(MenuCycle $cycle, MealPrepLog $log, array $shortfallLines, ?int $variance): void
    {
        if (! $cycle->rnd_user_id) {
            return;
        }
        if (! $shortfallLines && (int) $variance === 0) {
            return; // day went to plan — nothing to flag
        }

        $parts = [];
        if ($shortfallLines) {
            $parts[] = 'Ingredient shortfall — ' . implode('; ', $shortfallLines) . '. A substitute may be needed.';
        }
        if ($variance !== null && $variance !== 0) {
            $parts[] = $variance > 0
                ? "Prepared for {$log->population} but fed {$log->served_population} — {$variance} portions surplus."
                : "Prepared for {$log->population} but fed {$log->served_population} — " . abs($variance) . ' more than prepped.';
        }

        Notification::create([
            'user_id'       => $cycle->rnd_user_id,
            'title'         => 'Meal prep variance on ' . Carbon::parse($log->service_date)->toFormattedDateString(),
            'message'       => implode(' ', $parts),
            'type'          => $shortfallLines ? 'meal_prep_shortfall' : 'meal_prep_variance',
            'source_module' => 'food_service',
            'source_id'     => $log->id,
            'read'          => false,
        ]);
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
