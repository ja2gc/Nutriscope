<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreMenuCycleRequest;
use App\Http\Requests\FSS\UpdateMenuCycleRequest;
use App\Http\Resources\MenuCycleResource;
use App\Models\FoodServiceSetting;
use App\Models\MenuCycle;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use App\Services\Audit\Revisions\AuditRevisionWriter;
use App\Services\FSS\ShoppingListPopulationService;
use App\Services\MenuCycleCostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MenuCycleController extends Controller
{
    private const DAY_RELATIONS = ['days.recipe', 'days.fsItem'];

    private const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AuditRevisionRegistry $revisionRegistry,
        private readonly AuditRevisionWriter $revisionWriter,
    ) {}

    public function index(): JsonResponse
    {
        $cycles = MenuCycle::with('days')
            ->withCount('days')
            ->orderByDesc('is_active')
            ->orderBy('week_start_date')
            ->get();

        return response()->json(['data' => MenuCycleResource::collection($cycles)]);
    }

    public function store(StoreMenuCycleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $days = $data['days'] ?? null;
        unset($data['days']);

        $data['rnd_user_id'] = Auth::id();
        $data['cycle_days'] = 7;
        $data['week_start_date'] = ! empty($data['week_start_date'])
            ? Carbon::parse($data['week_start_date'])->toDateString()
            : now()->startOfWeek(Carbon::MONDAY)->toDateString();
        // New cycles start as 'upcoming' (states: completed|active|upcoming).
        $data['status'] = $data['status'] ?? 'upcoming';

        $cycle = $this->audited(function () use ($data, $days): MenuCycle {
            $cycle = $this->auditLogger->withoutModelEvents(fn () => DB::transaction(function () use ($data, $days) {
                $cycle = MenuCycle::create($data);
                if ($days !== null) {
                    $this->syncDays($cycle, $days);
                }

                return $cycle;
            }))->fresh(self::DAY_RELATIONS);
            $newValues = $this->auditValues($cycle);
            $fields = array_keys($newValues);
            if ($cycle->days()->exists()) {
                $fields[] = 'days';
            }
            $activity = $this->auditLogger->recordMutation(
                AuditAction::Created,
                AuditDomain::FoodService,
                $cycle,
                $fields,
                newValues: $newValues,
            );
            if ($activity !== null) {
                $this->revisionWriter->write($activity, null, $this->revisionRegistry->capture($cycle));
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
        $data['cycle_days'] = 7;
        if (array_key_exists('week_start_date', $data)) {
            if (! empty($data['week_start_date'])) {
                $data['week_start_date'] = Carbon::parse($data['week_start_date'])->toDateString();
            } else {
                unset($data['week_start_date']);
            }
        }

        if ($days !== null && $this->structuralDaysLocked($menuCycle)) {
            return response()->json([
                'message' => 'Active or snapshotted menu cycles cannot change menu day structure.',
            ], 422);
        }

        $this->audited(function () use ($menuCycle, $data, $days): void {
            $beforeValues = $this->auditValues($menuCycle);
            $revisionEligible = $days !== null
                || array_key_exists('week_start_date', $data)
                || array_key_exists('is_active', $data);
            $before = $revisionEligible
                ? $this->revisionRegistry->capture($menuCycle->load(self::DAY_RELATIONS))
                : null;
            $beforeDays = $days !== null ? $this->daySignature($menuCycle) : [];
            $this->auditLogger->withoutModelEvents(fn () => DB::transaction(function () use ($menuCycle, $data, $days) {
                $menuCycle->update($data);
                if ($days !== null) {
                    $this->syncDays($menuCycle, $days);
                }
            }));
            $after = $menuCycle->fresh(self::DAY_RELATIONS);
            $afterValues = $this->auditValues($after);
            $fields = $this->changedValueKeys($beforeValues, $afterValues);
            $structureChanged = false;
            if ($days !== null) {
                $this->auditLogger->withoutModelEvents(
                    fn () => app(ShoppingListPopulationService::class)->recalculateDraftListsForCycle($after),
                );
                $structureChanged = $beforeDays !== $this->daySignature($after);
                if ($structureChanged) {
                    $fields[] = 'days';
                }
            }
            $activity = $this->auditLogger->recordMutation(
                AuditAction::Updated,
                AuditDomain::FoodService,
                $after,
                $fields,
                oldValues: array_intersect_key($beforeValues, array_flip($fields)),
                newValues: array_intersect_key($afterValues, array_flip($fields)),
            );
            $revisionChanged = $structureChanged
                || collect($fields)->intersect(['week_start_date', 'is_active'])->isNotEmpty();
            if ($activity !== null && $before !== null && $revisionChanged) {
                $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));
            }
        });

        return response()->json(['data' => new MenuCycleResource($menuCycle->fresh()->load(self::DAY_RELATIONS))]);
    }

    public function destroy(MenuCycle $menuCycle): JsonResponse
    {
        $this->audited(function () use ($menuCycle): void {
            $before = $this->revisionRegistry->capture($menuCycle->load(self::DAY_RELATIONS));
            $oldValues = $this->auditValues($menuCycle);
            $this->auditLogger->withoutModelEvents(fn () => $menuCycle->delete());
            $activity = $this->auditLogger->recordMutation(
                AuditAction::Deleted,
                AuditDomain::FoodService,
                $menuCycle,
                [],
                oldValues: $oldValues,
            );
            if ($activity !== null) {
                $this->revisionWriter->write($activity, $before, null);
            }
        });

        return response()->json(null, 204);
    }

    public function activate(MenuCycle $menuCycle): JsonResponse
    {
        $missingDays = $this->missingPlannedWeekdays($menuCycle);
        if ($missingDays !== []) {
            return response()->json([
                'message' => 'Menu cycle must include at least one planned item for every day before activation.',
                'missing_days' => $missingDays,
            ], 422);
        }

        $this->audited(function () use ($menuCycle): void {
            $beforeValues = $this->auditValues($menuCycle);
            $beforeRevision = $this->revisionRegistry->capture($menuCycle->load(self::DAY_RELATIONS));
            $this->auditLogger->withoutModelEvents(fn () => DB::transaction(function () use ($menuCycle) {
                // Retire any currently active cycle before promoting this one — only one
                // cycle may be active at a time (callers do where('is_active', true)->first()).
                // A retired active cycle becomes 'completed' (states: completed|active|upcoming).
                MenuCycle::where('is_active', true)
                    ->where('id', '!=', $menuCycle->id)
                    ->update(['is_active' => false, 'status' => 'completed']);

                $attrs = [
                    'is_active' => true,
                    'status' => 'active',
                    'activation_date' => now()->toDateString(),
                ];

                // Freeze the plan's cost the FIRST time it's activated so past reports keep it
                // (Spec 6 #1). Re-activating (re-promote or double-click) must NOT re-price.
                if ($menuCycle->cost_snapshot === null) {
                    $attrs['cost_snapshot'] = MenuCycleCostService::forCycle($menuCycle);
                    $attrs['cost_snapshot_at'] = now();
                }

                $menuCycle->update($attrs);
            }));
            $after = $menuCycle->fresh(self::DAY_RELATIONS);
            $afterValues = $this->auditValues($after);
            $fields = $this->changedValueKeys($beforeValues, $afterValues);
            if ($fields !== []) {
                $fields[] = 'activation_state';
            }
            $activity = $this->auditLogger->recordMutation(
                AuditAction::Updated,
                AuditDomain::FoodService,
                $after,
                $fields,
                oldValues: array_intersect_key($beforeValues, array_flip($fields)),
                newValues: array_intersect_key($afterValues, array_flip($fields)),
            );
            if ($activity !== null) {
                $this->revisionWriter->write($activity, $beforeRevision, $this->revisionRegistry->capture($after));
            }
        });

        return response()->json(['data' => new MenuCycleResource($menuCycle->fresh())]);
    }

    /**
     * Cost-to-make per head for the active cycle on a given day (default today).
     * This is the REAL menu cost (recipes × ingredient prices ÷ population) for that
     * weekday — not a stored figure — alongside the settable per-head limit.
     */
    public function costToday(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? \Carbon\Carbon::parse($request->get('date')) : now();
        $weekday = $date->format('l');

        $cycle = MenuCycle::with('days.recipe.ingredients.fsItem', 'days.fsItem')
            ->where('is_active', true)
            ->orderByDesc('activation_date')
            ->first();

        if (! $cycle) {
            return response()->json(['data' => null]);
        }

        $cost = MenuCycleCostService::forCycle($cycle);
        $dayCost = $cost['days'][$weekday] ?? null;
        $perHead = $dayCost ? (float) $dayCost['cost_per_head'] : null;
        // Per-head cap is the shared Food Service setting (configured in Settings).
        $setting = FoodServiceSetting::singleton();
        $limit = $setting->per_head_day_limit !== null ? (float) $setting->per_head_day_limit : null;

        // Representative headcount for the weekday = that day's estimate_population.
        $dayPop = (int) ($cycle->days->where('day_of_week', $weekday)->max('estimate_population') ?? 0);

        return response()->json(['data' => [
            'cycle' => $cycle->name,
            'date' => $date->toDateString(),
            'weekday' => $weekday,
            'cost_per_head' => $perHead,         // actual cost to make today's menu, per head
            'limit_per_head' => $limit,           // cap from the Budget covering this date
            'within_budget' => ($limit !== null && $perHead !== null) ? $perHead <= $limit : null,
            'population' => $dayPop,
            'has_menu_today' => $dayCost !== null,
        ]]);
    }

    /**
     * Costing summary for the planner: per-day cost + cost/head, week total, and
     * the per-day budget status (the red/amber/green chip) vs the covering budget.
     */
    public function compute(MenuCycle $menuCycle): JsonResponse
    {
        $result = MenuCycleCostService::forCycle($menuCycle);

        // Per-head cap is the shared Food Service setting (configured in Settings).
        $setting = FoodServiceSetting::singleton();
        $budget = $setting->per_head_day_limit !== null ? (float) $setting->per_head_day_limit : null;
        if ($budget !== null) {
            foreach ($result['days'] as $day => &$d) {
                $d['budget_status'] = $this->budgetStatus($d['cost_per_head'], $budget);
            }
            unset($d);
        }

        $result['budget_per_head_day'] = $budget;
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

    private function missingPlannedWeekdays(MenuCycle $cycle): array
    {
        $planned = $cycle->days()
            ->where(function ($query) {
                $query->whereNotNull('recipe_id')->orWhereNotNull('fs_item_id');
            })
            ->pluck('day_of_week')
            ->unique()
            ->values()
            ->all();

        return array_values(array_diff(self::WEEKDAYS, $planned));
    }

    private function structuralDaysLocked(MenuCycle $cycle): bool
    {
        if ($cycle->is_active) {
            return true;
        }

        return $cycle->days()
            ->where(function ($query) {
                $query->whereNotNull('po_snapshot')
                    ->orWhereNotNull('po_snapshot_at')
                    ->orWhereNotNull('snapshot_purchase_order_id')
                    ->orWhere('po_snapshot_locked', true);
            })
            ->exists();
    }

    /** Replace the cycle's days with the supplied grid (single batch INSERT). */
    private function syncDays(MenuCycle $cycle, array $days): void
    {
        $cycle->days()->delete();

        $now = Carbon::now();
        $rows = [];

        foreach ($days as $d) {
            if (empty($d['recipe_id']) && empty($d['fs_item_id'])) {
                continue;
            }
            $rows[] = [
                'menu_cycle_id' => $cycle->id,
                'day_of_week' => $d['day_of_week'],
                'meal_type' => $d['meal_type'],
                'recipe_id' => $d['recipe_id'] ?? null,
                'fs_item_id' => $d['fs_item_id'] ?? null,
                'quantity' => $d['quantity'] ?? 1,
                'servings_override' => $d['servings_override'] ?? null,
                'estimate_population' => $d['estimate_population'] ?? null,
                'estimate_population_updated_at' => array_key_exists('estimate_population', $d) ? $now : null,
                'is_event' => $d['is_event'] ?? false,
                'event_allocation' => $d['event_allocation'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($rows)) {
            DB::table('menu_cycle_days')->insert($rows);
        }
    }

    /** @return array<string, string|int|bool|null> */
    private function auditValues(MenuCycle $cycle): array
    {
        return [
            'name' => (string) $cycle->name,
            'cycle_days' => (int) $cycle->cycle_days,
            'week_start_date' => $cycle->week_start_date?->toDateString(),
            'status' => (string) $cycle->status,
            'is_active' => (bool) $cycle->is_active,
            'activation_date' => $cycle->activation_date?->toDateString(),
        ];
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @return list<string> */
    private function changedValueKeys(array $before, array $after): array
    {
        return collect($after)
            ->filter(fn (mixed $value, string $key): bool => ($before[$key] ?? null) !== $value)
            ->keys()
            ->values()
            ->all();
    }

    private function daySignature(MenuCycle $cycle): array
    {
        return $cycle->days()->orderBy('day_of_week')->orderBy('meal_type')
            ->get(['day_of_week', 'meal_type', 'recipe_id', 'fs_item_id', 'quantity', 'estimate_population'])
            ->map->toArray()->values()->all();
    }
}
