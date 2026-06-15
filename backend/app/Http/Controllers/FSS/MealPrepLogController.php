<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Services\FSS\ConsumptionService;
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

    public function complete(Request $request, MenuCycle $menuCycle, ConsumptionService $consumption): JsonResponse
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

        return response()->json(['data' => $log], 201);
    }

    public function reverse(MealPrepLog $mealPrepLog, ConsumptionService $consumption): JsonResponse
    {
        return response()->json(['data' => $consumption->reverseDay($mealPrepLog->load('lines'))]);
    }
}
