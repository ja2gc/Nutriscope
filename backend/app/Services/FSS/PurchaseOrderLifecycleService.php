<?php

namespace App\Services\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Events\PurchaseOrderCompleted;
use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\ProgramProjectActivity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use App\Services\Audit\Revisions\AuditRevisionWriter;
use App\Services\MenuCycleCostService;
use App\Services\NotificationLifecycleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseOrderLifecycleService
{
    private const REVISION_RELATIONS = [
        'items.fsItem',
        'items.vendorGroup',
        'attachments.vendorGroup',
        'supplier',
        'shoppingList',
        'vendorGroups.supplier',
        'vendorGroups.items',
        'vendorGroups.attachments',
        'programProjectActivity',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AuditRevisionRegistry $revisionRegistry,
        private readonly AuditRevisionWriter $revisionWriter,
        private readonly NotificationLifecycleService $notificationLifecycle,
    ) {}

    /**
     * @return array{ready: bool, blockers: list<array{code: string, message: string}>, planned_total: float, available_budget: float|null, fiscal_year: int}
     */
    public function releaseReadiness(ShoppingList $shoppingList, bool $lockBudget = false): array
    {
        $shoppingList->loadMissing('items');
        $included = $shoppingList->items->where('included_in_po', true);
        $plannedTotal = round((float) $included->sum(fn ($item) => (float) $item->total), 2);
        $blockers = [];

        if ($included->isEmpty()) {
            $blockers[] = ['code' => 'items_missing', 'message' => 'Include at least one shopping-list item.'];
        }
        if ($included->contains(fn ($item) => $item->supplier_id === null)) {
            $blockers[] = ['code' => 'supplier_missing', 'message' => 'Assign a supplier to every included item.'];
        }

        $suggestedFood = ! $shoppingList->isSupplies() && $shoppingList->list_type === 'suggested';
        if ($suggestedFood && (int) $shoppingList->estimate_population < 1) {
            $blockers[] = ['code' => 'estimate_missing', 'message' => 'Set one estimated serving count for the selected span.'];
        }
        if ($suggestedFood && (
            $shoppingList->coverage_status !== 'full'
            || ! $shoppingList->period_start
            || ! $shoppingList->period_end
        )) {
            $blockers[] = ['code' => 'coverage_incomplete', 'message' => 'Every selected service date needs menu items.'];
        }

        $year = $this->fiscalYear($shoppingList);
        $budgetQuery = Budget::query()->where('fiscal_year', $year);
        if ($lockBudget) {
            $budgetQuery->lockForUpdate();
        }
        $budget = $budgetQuery->first();
        $available = null;
        if (! $budget) {
            $blockers[] = ['code' => 'budget_missing', 'message' => "Set up the {$year} fiscal-year budget."];
        } else {
            $reserved = PurchaseOrder::query()
                ->where('lifecycle_status', 'open_execution')
                ->with('shoppingList')
                ->get()
                ->filter(fn (PurchaseOrder $po) => $po->shoppingList && $this->fiscalYear($po->shoppingList) === $year)
                ->sum(fn (PurchaseOrder $po) => (float) $po->total_amount);
            $available = round($budget->remainingBalance() - (float) $reserved, 2);
            if ($plannedTotal > $available) {
                $blockers[] = ['code' => 'budget_exceeded', 'message' => 'Planned total exceeds the remaining available budget.'];
            }
        }

        return [
            'ready' => $blockers === [],
            'blockers' => $blockers,
            'planned_total' => $plannedTotal,
            'available_budget' => $available,
            'fiscal_year' => $year,
        ];
    }

    public function reassignVendor(
        PurchaseOrderVendorGroup $sourceGroup,
        Supplier $supplier,
        ?int $itemId = null,
    ): PurchaseOrder {
        return DB::transaction(function () use ($sourceGroup, $supplier, $itemId): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($sourceGroup->purchase_order_id)
                ->lockForUpdate()
                ->with(self::REVISION_RELATIONS)
                ->firstOrFail();
            $before = $this->revisionRegistry->capture($purchaseOrder);
            $sourceGroup = PurchaseOrderVendorGroup::query()
                ->whereKey($sourceGroup->getKey())
                ->lockForUpdate()
                ->with(['items', 'attachments'])
                ->firstOrFail();

            if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
                abort(422, 'Completed purchase orders are locked.');
            }
            if ($sourceGroup->status === 'received' || $sourceGroup->received_at !== null) {
                abort(422, 'Received vendor groups are locked.');
            }
            $movingItem = $itemId !== null
                ? $sourceGroup->items()->whereKey($itemId)->lockForUpdate()->first()
                : null;
            if ($itemId !== null && ! $movingItem) {
                abort(422, 'The selected item does not belong to this vendor group.');
            }
            if ((int) $sourceGroup->supplier_id === (int) $supplier->id) {
                return $purchaseOrder->fresh(self::REVISION_RELATIONS);
            }
            if ($sourceGroup->attachments->isNotEmpty()) {
                abort(422, 'Remove this vendor group\'s receipt and proof before changing its vendor.');
            }

            $destination = PurchaseOrderVendorGroup::query()
                ->where('purchase_order_id', $purchaseOrder->id)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->with(['items', 'attachments'])
                ->first();

            if ($destination) {
                if ($destination->status === 'received' || $destination->received_at !== null) {
                    abort(422, 'The selected vendor group is already received.');
                }
                if ($destination->attachments->isNotEmpty()) {
                    abort(422, 'Remove the selected vendor group\'s receipt and proof before adding items to it.');
                }
            } else {
                $destination = $purchaseOrder->vendorGroups()->create([
                    'supplier_id' => $supplier->id,
                    'status' => 'pending',
                    'total_amount' => 0,
                ]);
            }

            if ($movingItem) {
                $movingItem->update(['vendor_group_id' => $destination->id]);
            } else {
                $sourceGroup->items()->update(['vendor_group_id' => $destination->id]);
            }

            $this->recalculateVendorGroupTotal($destination);
            if ($sourceGroup->items()->exists()) {
                $this->recalculateVendorGroupTotal($sourceGroup);
            } else {
                $sourceGroup->delete();
            }
            $this->auditLogger->withoutModelEvents(fn () => $purchaseOrder->recalcTotal());

            $after = $purchaseOrder->fresh(self::REVISION_RELATIONS);
            $activity = $this->auditLogger->record(
                AuditAction::Updated,
                AuditCategory::Operations,
                AuditDomain::Procurement,
                subject: $after,
                context: $after,
                details: ['changed_fields' => ['vendor_groups', 'items']],
            );
            $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));

            return $after;
        });
    }

    private function recalculateVendorGroupTotal(PurchaseOrderVendorGroup $vendorGroup): void
    {
        $vendorGroup->load('items');
        $vendorGroup->total_amount = round((float) $vendorGroup->items->sum(
            fn ($item) => $item->actual_qty !== null && $item->actual_unit_price !== null
                ? (float) $item->actual_qty * (float) $item->actual_unit_price
                : (float) $item->total_value,
        ), 2);
        $vendorGroup->save();
    }

    private function fiscalYear(ShoppingList $shoppingList): int
    {
        return ($shoppingList->period_start ?? $shoppingList->list_date ?? now())->year;
    }

    /**
     * Re-evaluate every PO whose procurement span includes a service date.
     */
    public function refreshForServiceDate(string $serviceDate): void
    {
        $date = Carbon::parse($serviceDate)->toDateString();

        PurchaseOrder::query()
            ->whereHas('shoppingList', fn ($q) => $q
                ->whereDate('period_start', '<=', $date)
                ->whereDate('period_end', '>=', $date))
            ->with('shoppingList')
            ->get()
            ->each(fn (PurchaseOrder $po) => $this->refresh($po));
    }

    public function refresh(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $po = PurchaseOrder::query()
                ->whereKey($purchaseOrder->getKey())
                ->lockForUpdate()
                ->with(self::REVISION_RELATIONS)
                ->first();
            if (! $po || in_array($po->lifecycle_status, ['completed', 'archived'], true)) {
                return $purchaseOrder->fresh();
            }

            $groups = $po->vendorGroups;
            $vendorsReceived = $groups->isNotEmpty()
                && $groups->every(fn (PurchaseOrderVendorGroup $group) => $group->status === 'received'
                    && $group->received_at !== null
                    && $group->attachments->where('type', 'receipt')->isNotEmpty()
                    && $group->attachments->where('type', 'proof')->isNotEmpty()
                    && $group->items->isNotEmpty()
                    && $group->items->every(fn ($item) => $item->actual_qty !== null && $item->actual_unit_price !== null));

            if ($vendorsReceived) {
                $this->notificationLifecycle->resolvePurchaseOrder($po);
            }

            $isFood = $po->isFoodTrack();
            $actualTotal = round((float) $groups->sum('total_amount'), 2);

            // Supplies POs complete on receipts alone — no served population, no per-head.
            if (! $isFood) {
                if (! $vendorsReceived) {
                    return $po;
                }

                return $this->completeWithoutPopulation($po, $actualTotal);
            }

            if ($po->shoppingList?->list_type === 'manual') {
                if (! $vendorsReceived) {
                    return $po;
                }

                return $this->completeWithoutPopulation($po, $actualTotal);
            }

            // Suggested food PO: complete vendor evidence and served population for every span date.
            $prep = $this->servedPopulationProgress($po->shoppingList);
            if (! $vendorsReceived || $prep['expected'] === 0 || $prep['done'] < $prep['expected'] || $prep['served'] <= 0) {
                return $po;
            }

            $actualPerHead = round($actualTotal / $prep['served'], 2);

            // Block completion if no fiscal year allocation exists for the procurement year.
            // BudgetController::store() re-calls refresh() for blocked POs when allocation is set up.
            $procurementYear = optional($po->shoppingList?->period_start)->year ?? now()->year;
            $budget = Budget::query()->where('fiscal_year', $procurementYear)->first();
            if (! $budget || $actualTotal > $budget->remainingBalance()) {
                return $po;
            }

            $this->auditLogger->assertAvailable();
            $before = $this->revisionRegistry->capture($po);

            $this->auditLogger->withoutModelEvents(function () use ($po, $actualPerHead, $actualTotal): void {
                $po->forceFill([
                    'lifecycle_status' => 'completed',
                    'completed_at' => now(),
                    'final_locked_at' => now(),
                    'actual_budget_per_head_per_day' => $actualPerHead,
                    'total_amount' => $actualTotal,
                    'status' => 'received',
                    'received_date' => now()->toDateString(),
                ])->save();
            });

            // Permanently lock the menu-cycle day cells this PO snapshotted.
            MenuCycleDay::where('snapshot_purchase_order_id', $po->id)
                ->update(['po_snapshot_locked' => true]);

            if ($po->programProjectActivity) {
                $po->programProjectActivity->update([
                    'actual_total_cost' => $actualTotal,
                    'actual_output_patients' => $prep['served'],
                    'execution_frozen_at' => now(),
                    'execution_payload' => [
                        'formula' => 'actual_budget_per_head_per_day = actual_total_cost / actual_output_patients',
                        'actual_budget_per_head_per_day' => $actualPerHead,
                        'service_days_done' => $prep['done'],
                        'service_days_expected' => $prep['expected'],
                    ],
                ]);
            }

            event(new PurchaseOrderCompleted($po->fresh(['vendorGroups', 'shoppingList', 'programProjectActivity'])));
            $activity = $this->recordLifecycle(AuditAction::Completed, $po);
            $after = $po->fresh(self::REVISION_RELATIONS);
            $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));

            return $after;
        });
    }

    private function completeWithoutPopulation(PurchaseOrder $po, float $actualTotal): PurchaseOrder
    {
        $year = $po->shoppingList ? $this->fiscalYear($po->shoppingList) : now()->year;
        $budget = Budget::query()->where('fiscal_year', $year)->first();
        if (! $budget || $actualTotal > $budget->remainingBalance()) {
            return $po;
        }

        $this->auditLogger->assertAvailable();
        $before = $this->revisionRegistry->capture($po);
        $this->auditLogger->withoutModelEvents(function () use ($po, $actualTotal): void {
            $po->forceFill([
                'lifecycle_status' => 'completed',
                'completed_at' => now(),
                'final_locked_at' => now(),
                'total_amount' => $actualTotal,
                'status' => 'received',
                'received_date' => now()->toDateString(),
            ])->save();
        });

        event(new PurchaseOrderCompleted($po->fresh(['vendorGroups', 'shoppingList', 'programProjectActivity'])));
        $activity = $this->recordLifecycle(AuditAction::Completed, $po);
        $after = $po->fresh(self::REVISION_RELATIONS);
        $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));

        return $after;
    }

    public function archive(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->lifecycle_status !== 'completed') {
            abort(422, 'Only completed purchase orders can be archived.');
        }

        $this->auditLogger->assertAvailable();

        return DB::transaction(function () use ($purchaseOrder): PurchaseOrder {
            $po = PurchaseOrder::query()
                ->whereKey($purchaseOrder->getKey())
                ->lockForUpdate()
                ->with(self::REVISION_RELATIONS)
                ->firstOrFail();
            if ($po->lifecycle_status !== 'completed') {
                abort(422, 'Only completed purchase orders can be archived.');
            }
            $before = $this->revisionRegistry->capture($po);
            $this->auditLogger->withoutModelEvents(function () use ($po): void {
                $po->forceFill([
                    'lifecycle_status' => 'archived',
                    'archived_at' => now(),
                ])->save();
            });
            $activity = $this->recordLifecycle(AuditAction::Archived, $po);
            $after = $po->fresh(self::REVISION_RELATIONS);
            $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));

            return $after;
        });
    }

    private function recordLifecycle(AuditAction $action, PurchaseOrder $purchaseOrder): AuditActivity
    {
        return $this->auditLogger->record(
            $action,
            AuditCategory::Operations,
            AuditDomain::Procurement,
            subject: $purchaseOrder,
            context: $purchaseOrder,
            details: ['status' => $purchaseOrder->status, 'lifecycle_status' => $purchaseOrder->lifecycle_status],
            systemActor: auth()->user() === null ? 'purchase-order-lifecycle' : null,
        );
    }

    public function createPpaSnapshot(PurchaseOrder $purchaseOrder, ShoppingList $shoppingList): ProgramProjectActivity
    {
        $shoppingList->loadMissing('items.fsItem');

        $menuSnapshot = $this->menuSnapshot($shoppingList);
        $includedItems = $shoppingList->items->where('included_in_po', true);
        $estimatedTotal = round((float) $includedItems->sum(fn ($item) => (float) $item->total), 2);
        $estimatedPatients = $this->estimatedOutputPatients($shoppingList);
        $activity = $shoppingList->isSupplies()
            ? 'Food Service Supplies'
            : 'Food Subsistence for Patients';

        return ProgramProjectActivity::create([
            'purchase_order_id' => $purchaseOrder->id,
            'activity' => $activity,
            'menu_snapshot' => $menuSnapshot,
            'target_date_range' => $this->targetDateRange($shoppingList),
            'period_start' => $shoppingList->period_start,
            'period_end' => $shoppingList->period_end,
            'estimated_total_cost' => $estimatedTotal,
            'estimated_output_patients' => $estimatedPatients,
            'planning_payload' => [
                'source' => 'shopping_list_conversion',
                'shopping_list_id' => $shoppingList->id,
                'items_count' => $includedItems->count(),
                'formula' => 'estimated_total_cost = included frozen shopping_list_items.total sum',
                'estimated_output_patients_formula' => 'shopping_list.estimate_population * planned service dates in procurement span',
            ],
        ]);
    }

    /**
     * @return array{expected:int, done:int, served:int}
     */
    public function servedPopulationProgress(?ShoppingList $shoppingList): array
    {
        if (! $shoppingList?->period_start || ! $shoppingList?->period_end) {
            return ['expected' => 0, 'done' => 0, 'served' => 0];
        }

        $cycleIds = [];
        $expectedDates = [];

        for ($d = Carbon::parse($shoppingList->period_start); $d->lte(Carbon::parse($shoppingList->period_end)); $d->addDay()) {
            $cycle = MenuCycle::coveringDate($d);
            if (! $cycle) {
                continue;
            }

            $cycle->loadMissing('days');
            $hasPlan = $cycle->days
                ->where('day_of_week', $d->format('l'))
                ->contains(fn ($day) => $day->recipe_id || $day->fs_item_id);

            if ($hasPlan) {
                $cycleIds[$cycle->id] = true;
                $expectedDates[$d->toDateString()] = true;
            }
        }

        if (! $expectedDates) {
            return ['expected' => 0, 'done' => 0, 'served' => 0];
        }

        $logs = MealPrepLog::query()
            ->whereIn('menu_cycle_id', array_keys($cycleIds))
            ->where('status', 'completed')
            ->whereBetween('service_date', [
                $shoppingList->period_start->toDateString(),
                $shoppingList->period_end->toDateString(),
            ])
            ->whereNotNull('served_population')
            ->get(['service_date', 'served_population']);

        return [
            'expected' => count($expectedDates),
            'done' => $logs->pluck('service_date')->map(fn ($date) => Carbon::parse($date)->toDateString())->unique()->count(),
            'served' => (int) $logs->sum('served_population'),
        ];
    }

    private function estimatedOutputPatients(ShoppingList $shoppingList): int
    {
        if ($shoppingList->isSupplies()) {
            return 0;
        }

        if (! $shoppingList->period_start || ! $shoppingList->period_end) {
            return (int) ($shoppingList->estimate_population ?? 0);
        }

        $total = 0;
        $population = (int) ($shoppingList->estimate_population ?? 0);
        for ($d = Carbon::parse($shoppingList->period_start); $d->lte(Carbon::parse($shoppingList->period_end)); $d->addDay()) {
            $cycle = MenuCycle::coveringDate($d);
            if (! $cycle) {
                continue;
            }
            $cycle->loadMissing('days');
            $day = $cycle->days->firstWhere('day_of_week', $d->format('l'));
            if ($day && ($day->recipe_id || $day->fs_item_id)) {
                $total += $population;
            }
        }

        return $total;
    }

    private function menuSnapshot(ShoppingList $shoppingList): ?string
    {
        if (! $shoppingList->period_start || ! $shoppingList->period_end) {
            return $shoppingList->items->pluck('ingredient_name')->unique()->values()->implode(', ');
        }

        $lines = [];
        for ($d = Carbon::parse($shoppingList->period_start); $d->lte(Carbon::parse($shoppingList->period_end)); $d->addDay()) {
            $cycle = MenuCycle::coveringDate($d);
            if (! $cycle) {
                continue;
            }
            $cycle->loadMissing('days.recipe', 'days.fsItem');
            $names = $cycle->days
                ->where('day_of_week', $d->format('l'))
                ->map(fn ($day) => $day->recipe?->name ?? $day->fsItem?->name)
                ->filter()
                ->unique()
                ->values();
            if ($names->isNotEmpty()) {
                $lines[] = $d->format('d').': '.$names->implode(', ');
            }
        }

        return $lines ? implode("\n", $lines) : null;
    }

    private function targetDateRange(ShoppingList $shoppingList): ?string
    {
        if (! $shoppingList->period_start || ! $shoppingList->period_end) {
            return null;
        }

        return $shoppingList->period_start->format('m/d/y').' - '.$shoppingList->period_end->format('m/d/y');
    }

    /**
     * Freeze the scaled values onto each menu-cycle day cell covered by a food PO's
     * procurement span. The snapshot is the cell's recipe/item profile scaled to the
     * shopping list's estimated population, captured permanently at conversion time.
     */
    public function writeMenuCycleSnapshots(PurchaseOrder $purchaseOrder, ShoppingList $shoppingList): void
    {
        if (! $shoppingList->period_start || ! $shoppingList->period_end) {
            return;
        }

        $population = (int) ($shoppingList->estimate_population ?? 0);

        for ($d = Carbon::parse($shoppingList->period_start); $d->lte(Carbon::parse($shoppingList->period_end)); $d->addDay()) {
            $cycle = MenuCycle::coveringDate($d);
            if (! $cycle) {
                continue;
            }

            $cycle->loadMissing('days.recipe.ingredients.fsItem', 'days.fsItem');
            $cells = $cycle->days->where('day_of_week', $d->format('l'))
                ->filter(fn ($day) => $day->recipe_id || $day->fs_item_id);

            foreach ($cells as $cell) {
                // Locked cells never re-snapshot.
                if ($cell->po_snapshot_locked) {
                    continue;
                }

                $pop = $population;

                if ($cell->recipe_id && $cell->recipe) {
                    $snapshot = MenuCycleCostService::recipeProfileForDay($cell, $pop);
                } elseif ($cell->fs_item_id && $cell->fsItem) {
                    $item = $cell->fsItem;
                    $qty = (float) ($cell->quantity ?? 1);
                    $totalQty = $pop * $qty;
                    $totalCost = round($totalQty * $item->unit_cost, 2);
                    $snapshot = [
                        'fs_item_id' => $item->id,
                        'name' => $item->name,
                        'unit' => $item->base_unit,
                        'unit_cost' => round($item->unit_cost, 2),
                        'quantity' => round($qty, 2),
                        'population' => $pop,
                        'total_quantity' => round($totalQty, 2),
                        'total_cost' => $totalCost,
                    ];
                } else {
                    continue;
                }

                $cell->forceFill([
                    'snapshot_purchase_order_id' => $purchaseOrder->id,
                    'po_snapshot' => $snapshot,
                    'po_snapshot_at' => now(),
                ])->save();
            }
        }
    }
}
