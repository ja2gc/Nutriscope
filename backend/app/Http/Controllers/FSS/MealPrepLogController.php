<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Services\Audit\AuditLogger;
use App\Services\FSS\ConsumptionService;
use App\Services\FSS\PurchaseOrderLifecycleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPrepLogController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'menu_cycle_id' => ['nullable', 'integer'],
        ]);

        $logs = MealPrepLog::with('lines', 'menuCycle:id,uuid,name', 'completedBy:id,uuid,name,first_name,last_name')
            ->when($data['menu_cycle_id'] ?? null, fn ($q, $id) => $q->where('menu_cycle_id', $id))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('service_date', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('service_date', '<=', $d))
            ->orderByDesc('service_date')->get();

        return response()->json(['data' => $logs->map(fn (MealPrepLog $l) => array_merge($l->toArray(), ['id' => $l->uuid]))]);
    }

    public function complete(Request $request, MenuCycle $menuCycle, ConsumptionService $consumption, PurchaseOrderLifecycleService $lifecycle): JsonResponse
    {
        $data = $request->validate([
            'service_date' => ['required', 'date'],
            'population' => ['nullable', 'integer', 'min:1'],
            'served_population' => ['nullable', 'integer', 'min:0'],
        ]);

        $log = $this->audited(function () use ($consumption, $menuCycle, $data, $lifecycle): MealPrepLog {
            $existing = MealPrepLog::query()
                ->where('menu_cycle_id', $menuCycle->id)
                ->whereDate('service_date', $data['service_date'])
                ->first();
            $before = $existing === null ? [] : $this->auditValues($existing);
            $log = $this->auditLogger->withoutModelEvents(fn (): MealPrepLog => $consumption->completeDay(
                $menuCycle,
                $data['service_date'],
                $data['population'] ?? null,
                $data['served_population'] ?? null,
            ));
            $after = $this->auditValues($log);
            $fields = $this->changedValueKeys($before, $after);
            $before = $before === [] ? array_fill_keys(array_keys($after), null) : $before;
            $fieldMap = array_flip($fields);
            $this->recordServiceEvent(
                AuditAction::Completed,
                $log,
                $data['service_date'],
                $fields,
                array_intersect_key($before, $fieldMap),
                array_intersect_key($after, $fieldMap),
            );
            $lifecycle->refreshForServiceDate($data['service_date']);

            return $log;
        });

        return response()->json(['data' => array_merge($log->toArray(), ['id' => $log->uuid])], 201);
    }

    public function reverse(MealPrepLog $mealPrepLog, ConsumptionService $consumption): JsonResponse
    {
        $log = $this->audited(function () use ($consumption, $mealPrepLog): MealPrepLog {
            $before = $this->auditValues($mealPrepLog);
            $log = $this->auditLogger->withoutModelEvents(
                fn (): MealPrepLog => $consumption->reverseDay($mealPrepLog->load('lines')),
            );
            $after = $this->auditValues($log);
            $fields = $this->changedValueKeys($before, $after);
            $fieldMap = array_flip($fields);
            $this->recordServiceEvent(
                AuditAction::Reversed,
                $log,
                $log->service_date->toDateString(),
                $fields,
                array_intersect_key($before, $fieldMap),
                array_intersect_key($after, $fieldMap),
            );

            return $log;
        });

        return response()->json(['data' => array_merge($log->toArray(), ['id' => $log->uuid])]);
    }

    /**
     * Set/backfill the served population for one service day of a cycle — for when FSS
     * didn't record the headcount on the day itself. Editable by FSS and RND. Drives the
     * derived budget-per-head once every day in the procurement span is filled.
     */
    public function setServed(Request $request, MenuCycle $menuCycle, PurchaseOrderLifecycleService $lifecycle): JsonResponse
    {
        $data = $request->validate([
            'service_date' => ['required', 'date'],
            'served_population' => ['required', 'integer', 'min:0'],
        ]);

        // Served population can be backfilled anytime BEFORE the related food PO
        // completes. Once a food PO covering this date is completed, it's locked.
        $lockedByCompletedPo = PurchaseOrder::where('procurement_track', 'food')
            ->where('lifecycle_status', 'completed')
            ->whereHas('shoppingList', fn ($q) => $q
                ->whereDate('period_start', '<=', $data['service_date'])
                ->whereDate('period_end', '>=', $data['service_date']))
            ->exists();

        if ($lockedByCompletedPo) {
            return response()->json([
                'message' => 'Served population is locked — the food purchase order covering this date is already completed.',
            ], 422);
        }

        $log = $this->audited(function () use ($menuCycle, $data, $request, $lifecycle): MealPrepLog {
            $log = MealPrepLog::where('menu_cycle_id', $menuCycle->id)
                ->whereDate('service_date', $data['service_date'])
                ->first();
            $before = $log === null ? [] : $this->auditValues($log);

            // Backfill: when no log exists yet for this cycle-day, create a reconciliation
            // row so FSS can record the actual headcount for ANY day of the cycle without
            // having first run the inventory-deducting "mark served" flow. Population is the
            // weekday's planned estimate; no inventory is touched (this is an after-the-fact
            // census entry, not a prep run).
            if (! $log) {
                $weekday = Carbon::parse($data['service_date'])->format('l');
                $estimate = (int) ($menuCycle->days()->where('day_of_week', $weekday)->value('estimate_population') ?? 0);

                $log = $this->auditLogger->withoutModelEvents(fn (): MealPrepLog => MealPrepLog::create([
                    'menu_cycle_id' => $menuCycle->id,
                    'service_date' => $data['service_date'],
                    'population' => $estimate ?: null,
                    'served_population' => $data['served_population'],
                    'status' => 'completed',
                    'completed_by' => $request->user()?->id,
                    'completed_at' => now(),
                    'total_value' => 0,
                    'has_shortfall' => false,
                ]));
            } else {
                $log->served_population = $data['served_population'];
            }

            if ($log->population !== null) {
                $log->population_variance = $log->population - $data['served_population'];
            }
            $this->auditLogger->withoutModelEvents(fn () => $log->save());
            $after = $this->auditValues($log);
            $fields = $this->changedValueKeys($before, $after);
            $before = $before === [] ? array_fill_keys(array_keys($after), null) : $before;
            $fieldMap = array_flip($fields);
            $this->recordServiceEvent(
                AuditAction::Adjusted,
                $log,
                $data['service_date'],
                $fields,
                array_intersect_key($before, $fieldMap),
                array_intersect_key($after, $fieldMap),
            );
            $lifecycle->refreshForServiceDate($data['service_date']);

            return $log;
        });

        return response()->json(['data' => array_merge($log->toArray(), ['id' => $log->uuid])]);
    }

    /**
     * @param  array<int, string>  $changedFields
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function recordServiceEvent(
        AuditAction $action,
        MealPrepLog $log,
        string $serviceDate,
        array $changedFields,
        array $oldValues,
        array $newValues,
    ): void {
        $purchaseOrder = $this->purchaseOrderRoot($log, $serviceDate);
        if ($purchaseOrder === null) {
            $this->auditLogger->record(
                $action,
                AuditCategory::Operations,
                AuditDomain::FoodService,
                subject: $log,
                details: ['changed_fields' => $changedFields, 'service_date' => $serviceDate],
                oldValues: $oldValues,
                newValues: $newValues,
            );

            return;
        }

        $this->auditLogger->record(
            $action,
            AuditCategory::Operations,
            AuditDomain::FoodService,
            subject: $log,
            context: $purchaseOrder,
            details: ['changed_fields' => $changedFields, 'service_date' => $serviceDate],
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }

    /** @return array<string, string|int|float|bool|null> */
    private function auditValues(MealPrepLog $log): array
    {
        return [
            'service_date' => $log->service_date->toDateString(),
            'estimated_population' => $log->population,
            'served_population' => $log->served_population,
            'population_variance' => $log->population_variance,
            'status' => $log->status,
            'total_value' => $log->total_value === null ? null : (float) $log->total_value,
            'has_shortfall' => (bool) $log->has_shortfall,
        ];
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @return list<string> */
    private function changedValueKeys(array $before, array $after): array
    {
        if ($before === []) {
            return array_keys(array_filter($after, fn (mixed $value): bool => $value !== null));
        }

        return collect(array_keys($after))
            ->filter(fn (string $field): bool => $before[$field] !== $after[$field])
            ->values()->all();
    }

    private function purchaseOrderRoot(MealPrepLog $log, string $serviceDate): ?PurchaseOrder
    {
        if ($log->purchase_order_id !== null) {
            return PurchaseOrder::query()->find($log->purchase_order_id, ['id', 'uuid']);
        }

        $weekday = Carbon::parse($serviceDate)->format('l');
        $rootIds = MenuCycleDay::query()
            ->where('menu_cycle_id', $log->menu_cycle_id)
            ->where('day_of_week', $weekday)
            ->whereNotNull('snapshot_purchase_order_id')
            ->distinct()
            ->pluck('snapshot_purchase_order_id');

        if ($rootIds->count() !== 1) {
            return null;
        }

        $purchaseOrder = PurchaseOrder::query()->find($rootIds->first(), ['id', 'uuid']);
        if ($purchaseOrder !== null) {
            $this->auditLogger->withoutModelEvents(fn () => $log->forceFill(['purchase_order_id' => $purchaseOrder->id])->save());
        }

        return $purchaseOrder;
    }
}
