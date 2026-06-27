<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Services\FSS\ConsumptionService;
use App\Services\FSS\PurchaseOrderLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPrepLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'          => ['nullable', 'date'],
            'to'            => ['nullable', 'date', 'after_or_equal:from'],
            'menu_cycle_id' => ['nullable', 'integer'],
        ]);

        $logs = MealPrepLog::with('lines', 'menuCycle:id,name', 'completedBy:id,name')
            ->when($data['menu_cycle_id'] ?? null, fn ($q, $id) => $q->where('menu_cycle_id', $id))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('service_date', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('service_date', '<=', $d))
            ->orderByDesc('service_date')->get();

        return response()->json(['data' => $logs]);
    }

    public function complete(Request $request, MenuCycle $menuCycle, ConsumptionService $consumption, PurchaseOrderLifecycleService $lifecycle): JsonResponse
    {
        $data = $request->validate([
            'service_date'      => ['required', 'date'],
            'population'        => ['nullable', 'integer', 'min:1'],
            'served_population' => ['nullable', 'integer', 'min:0'],
            'allow_shortfall'   => ['nullable', 'boolean'],
        ]);

        $log = $consumption->completeDay(
            $menuCycle,
            $data['service_date'],
            $data['population'] ?? null,
            $data['served_population'] ?? null,
            (bool) ($data['allow_shortfall'] ?? false),
        );
        $lifecycle->refreshForServiceDate($data['service_date']);

        return response()->json(['data' => $log], 201);
    }

    public function reverse(MealPrepLog $mealPrepLog, ConsumptionService $consumption): JsonResponse
    {
        return response()->json(['data' => $consumption->reverseDay($mealPrepLog->load('lines'))]);
    }

    /**
     * Set/backfill the served population for one service day of a cycle — for when FSS
     * didn't record the headcount on the day itself. Editable by FSS and RND. Drives the
     * derived budget-per-head once every day in the procurement span is filled.
     */
    public function setServed(Request $request, MenuCycle $menuCycle, PurchaseOrderLifecycleService $lifecycle): JsonResponse
    {
        $data = $request->validate([
            'service_date'      => ['required', 'date'],
            'served_population' => ['required', 'integer', 'min:0'],
        ]);

        $log = MealPrepLog::where('menu_cycle_id', $menuCycle->id)
            ->whereDate('service_date', $data['service_date'])
            ->first();

        // Backfill: when no log exists yet for this cycle-day, create a reconciliation
        // row so FSS can record the actual headcount for ANY day of the cycle without
        // having first run the inventory-deducting "mark served" flow. Population is the
        // weekday's planned estimate; no inventory is touched (this is an after-the-fact
        // census entry, not a prep run).
        if (! $log) {
            $weekday  = \Carbon\Carbon::parse($data['service_date'])->format('l');
            $estimate = (int) ($menuCycle->days()->where('day_of_week', $weekday)->value('estimate_population') ?? 0);

            $log = MealPrepLog::create([
                'menu_cycle_id'     => $menuCycle->id,
                'service_date'      => $data['service_date'],
                'population'        => $estimate ?: null,
                'served_population' => $data['served_population'],
                'status'            => 'completed',
                'completed_by'      => $request->user()?->id,
                'completed_at'      => now(),
                'total_value'       => 0,
                'has_shortfall'     => false,
            ]);
        } else {
            $log->served_population = $data['served_population'];
        }

        if ($log->population !== null) {
            $log->population_variance = $log->population - $data['served_population'];
        }
        $log->save();
        $lifecycle->refreshForServiceDate($data['service_date']);

        return response()->json(['data' => $log]);
    }
}
