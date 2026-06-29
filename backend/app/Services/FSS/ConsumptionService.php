<?php

namespace App\Services\FSS;

use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsumptionService
{
    /**
     * Mark a service day complete. This is a formal service/accomplishment marker;
     * it does not check stock, deduct inventory, create shortfall lines, or notify RND.
     */
    public function completeDay(
        MenuCycle $cycle,
        string $serviceDate,
        ?int $populationOverride = null,
        ?int $servedPopulation = null
    ): MealPrepLog {
        $weekday = Carbon::parse($serviceDate)->format('l');

        return DB::transaction(function () use ($cycle, $serviceDate, $populationOverride, $servedPopulation, $weekday) {
            $cycle->loadMissing('days');
            $days = $cycle->days->where('day_of_week', $weekday);

            if ($days->isEmpty()) {
                abort(422, "No menu slots planned for {$weekday}.");
            }

            $target = $populationOverride ?? (int) ($days->first()->estimate_population ?? 0);
            $log = MealPrepLog::where('menu_cycle_id', $cycle->id)
                ->whereDate('service_date', $serviceDate)
                ->first();
            $actualServed = $servedPopulation ?? $log?->served_population;

            $values = [
                'population'          => $target,
                'served_population'   => $actualServed,
                'population_variance' => $actualServed === null ? null : $target - $actualServed,
                'status'              => 'completed',
                'completed_by'        => Auth::id(),
                'completed_at'        => now(),
                'total_value'         => 0,
                'has_shortfall'       => false,
            ];

            if ($log) {
                $log->forceFill($values)->save();
            } else {
                $log = MealPrepLog::create([
                    'menu_cycle_id' => $cycle->id,
                    'service_date'  => $serviceDate,
                    ...$values,
                ]);
            }

            return $log->fresh('lines');
        });
    }

    /**
     * Reverse the formal completion marker. No inventory is restored because mark-served
     * no longer deducts inventory.
     */
    public function reverseDay(MealPrepLog $log): MealPrepLog
    {
        if ($log->status === 'reversed') {
            abort(422, 'This service day is already reversed.');
        }

        return DB::transaction(function () use ($log) {
            $log->update(['status' => 'reversed']);

            return $log->fresh('lines');
        });
    }
}
