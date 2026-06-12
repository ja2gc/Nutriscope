<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreMenuCycleRequest;
use App\Http\Requests\FSS\UpdateMenuCycleRequest;
use App\Http\Resources\MenuCycleResource;
use App\Models\MenuCycle;
use App\Services\MenuCycleCostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MenuCycleController extends Controller
{
    private const DAY_RELATIONS = ['days.recipe', 'days.fsItem'];

    public function index(): JsonResponse
    {
        return response()->json(['data' => MenuCycleResource::collection(MenuCycle::orderByDesc('updated_at')->get())]);
    }

    public function store(StoreMenuCycleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $days = $data['days'] ?? null;
        unset($data['days']);

        $data['rnd_user_id']     = Auth::id();
        $data['week_start_date'] = $data['week_start_date'] ?? now()->toDateString();

        $cycle = DB::transaction(function () use ($data, $days) {
            $cycle = MenuCycle::create($data);
            if ($days !== null) {
                $this->syncDays($cycle, $days);
            }
            return $cycle;
        });

        return response()->json(['data' => new MenuCycleResource($cycle->load(self::DAY_RELATIONS))], 201);
    }

    public function show(MenuCycle $menuCycle): JsonResponse
    {
        return response()->json(['data' => new MenuCycleResource($menuCycle->load(self::DAY_RELATIONS))]);
    }

    public function update(UpdateMenuCycleRequest $request, MenuCycle $menuCycle): JsonResponse
    {
        $data = $request->validated();
        $days = $data['days'] ?? null;
        unset($data['days']);

        DB::transaction(function () use ($menuCycle, $data, $days) {
            $menuCycle->update($data);
            if ($days !== null) {
                $this->syncDays($menuCycle, $days);
            }
        });

        return response()->json(['data' => new MenuCycleResource($menuCycle->fresh()->load(self::DAY_RELATIONS))]);
    }

    public function destroy(MenuCycle $menuCycle): JsonResponse
    {
        $menuCycle->delete();
        return response()->json(null, 204);
    }

    public function activate(MenuCycle $menuCycle): JsonResponse
    {
        $menuCycle->update([
            'is_active'       => true,
            'status'          => 'active',
            'activation_date' => now()->toDateString(),
        ]);
        return response()->json(['data' => new MenuCycleResource($menuCycle)]);
    }

    /**
     * Costing summary for the planner: per-day cost + cost/head, week total, and
     * the per-day budget status (the red/amber/green chip) vs budget_per_head_per_day.
     */
    public function compute(Request $request, MenuCycle $menuCycle): JsonResponse
    {
        $population = $request->filled('population') ? (int) $request->get('population') : null;
        $result     = MenuCycleCostService::forCycle($menuCycle, $population);

        $budget = $menuCycle->budget_per_head_per_day !== null ? (float) $menuCycle->budget_per_head_per_day : null;
        if ($budget !== null) {
            foreach ($result['days'] as $day => &$d) {
                $d['budget_status'] = $this->budgetStatus($d['cost_per_head'], $budget);
            }
            unset($d);
        }

        $result['budget_per_head_per_day'] = $budget;
        $result['within_budget'] = $budget === null ? null : ($result['cost_per_head'] <= $budget);

        return response()->json(['data' => $result]);
    }

    /** green ≤ budget, amber within 10% over, red beyond. */
    private function budgetStatus(float $costPerHead, float $budget): string
    {
        if ($costPerHead <= $budget) {
            return 'ok';
        }
        return $costPerHead <= $budget * 1.10 ? 'warning' : 'over';
    }

    /** Replace the cycle's days with the supplied grid. */
    private function syncDays(MenuCycle $cycle, array $days): void
    {
        $cycle->days()->delete();
        foreach ($days as $d) {
            // Skip empty slots (no recipe and no item).
            if (empty($d['recipe_id']) && empty($d['fs_item_id'])) {
                continue;
            }
            $cycle->days()->create([
                'day_of_week'       => $d['day_of_week'],
                'meal_type'         => $d['meal_type'],
                'recipe_id'         => $d['recipe_id'] ?? null,
                'fs_item_id'        => $d['fs_item_id'] ?? null,
                'quantity'          => $d['quantity'] ?? 1,
                'servings_override' => $d['servings_override'] ?? null,
            ]);
        }
    }
}
