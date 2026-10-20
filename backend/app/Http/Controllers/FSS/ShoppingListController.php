<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreShoppingListRequest;
use App\Http\Requests\FSS\UpdateShoppingListRequest;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\ShoppingListResource;
use App\Models\FsItem;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Supplier;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use App\Services\Audit\Revisions\AuditRevisionWriter;
use App\Services\FSS\MaintainMenuCycles;
use App\Services\FSS\ShoppingListPopulationService;
use App\Support\UnitConverter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShoppingListController extends Controller
{
    private const ITEM_RELATIONS = ['items.fsItem', 'items.supplier'];

    public static function draftItemRules(): array
    {
        return [
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['nullable', 'string', 'exists:shopping_list_items,uuid'],
            'items.*.fs_item_uuid' => ['required', 'string', 'exists:fs_items,uuid'],
            'items.*.source' => ['required', 'in:generated,manual'],
            'items.*.purchase_unit' => ['required', 'string', 'max:50'],
            'items.*.purchase_qty' => ['required', 'numeric', 'min:0'],
            'items.*.purchase_price' => ['required', 'numeric', 'min:0'],
            'items.*.supplier_id' => ['nullable', 'string', 'exists:suppliers,uuid'],
            'items.*.quantity_mode' => ['required', 'in:calculated,rounded,manual'],
            'items.*.included_in_po' => ['required', 'boolean'],
            'items.*.exclusion_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AuditRevisionRegistry $revisionRegistry,
        private readonly AuditRevisionWriter $revisionWriter,
    ) {}

    public function index(PaginatedRequest $request): AnonymousResourceCollection
    {
        return ShoppingListResource::collection(ShoppingList::with('items.fsItem', 'items.supplier:id,uuid', 'purchaseOrders')
            ->orderByRaw('COALESCE(period_start, list_date) DESC')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString());
    }

    public function store(StoreShoppingListRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['rnd_user_id'] = Auth::id();
        $data['list_type'] = $data['list_type'] ?? 'manual';
        $data['status'] = $data['status'] ?? 'draft';
        if (array_key_exists('estimate_population', $data)) {
            $data['estimate_population_updated_at'] = now();
        }

        $shoppingList = $this->audited(function () use ($data): ShoppingList {
            $list = $this->auditLogger->withoutModelEvents(fn (): ShoppingList => ShoppingList::create($data));
            $activity = $this->auditLogger->recordMutation(AuditAction::Created, AuditDomain::Procurement, $list, array_keys($list->getAttributes()));
            if ($activity !== null) {
                $this->revisionWriter->write($activity, null, $this->revisionRegistry->capture($list->load(self::ITEM_RELATIONS)));
            }

            return $list;
        });

        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items.fsItem', 'items.supplier:id,uuid'))], 201);
    }

    public function show(ShoppingList $shoppingList): JsonResponse
    {
        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items.fsItem', 'items.supplier:id,uuid'))]);
    }

    public function update(UpdateShoppingListRequest $request, ShoppingList $shoppingList): JsonResponse
    {
        $data = $request->validated();
        if ($shoppingList->status !== 'draft') {
            return response()->json(['message' => 'Shopping Lists are read-only after Turn Into PO.'], 422);
        }
        $this->audited(function () use ($shoppingList, &$data): void {
            $before = $this->revisionRegistry->capture($shoppingList->load(self::ITEM_RELATIONS));
            $fields = [];
            $items = $data['items'] ?? null;
            unset($data['items']);
            if (array_key_exists('estimate_population', $data) && (int) $data['estimate_population'] !== (int) $shoppingList->estimate_population) {
                $beforePopulation = $shoppingList->estimate_population;
                $this->auditLogger->withoutModelEvents(
                    fn () => app(ShoppingListPopulationService::class)->cascadePopulation($shoppingList, (int) $data['estimate_population']),
                );
                $shoppingList->refresh();
                if ($beforePopulation !== $shoppingList->estimate_population) {
                    $fields[] = 'estimate_population';
                }
            }
            unset($data['estimate_population']);
            if ($data !== []) {
                $this->auditLogger->withoutModelEvents(fn () => $shoppingList->update($data));
                $fields = [...$fields, ...array_keys($shoppingList->getChanges())];
            }
            if ($items !== null) {
                $this->auditLogger->withoutModelEvents(fn () => $this->applyDraftItems($shoppingList, $items));
                $fields[] = 'items';
            }

            $after = $shoppingList->fresh(self::ITEM_RELATIONS);
            $activity = $this->auditLogger->recordMutation(AuditAction::Updated, AuditDomain::Procurement, $after, $fields);
            if ($activity !== null) {
                $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));
            }
        });

        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items.fsItem', 'items.supplier:id,uuid'))]);
    }

    public function destroy(ShoppingList $shoppingList): JsonResponse
    {
        $this->audited(function () use ($shoppingList): void {
            $before = $this->revisionRegistry->capture($shoppingList->load(self::ITEM_RELATIONS));
            $this->auditLogger->withoutModelEvents(fn () => $shoppingList->delete());
            $activity = $this->auditLogger->recordMutation(AuditAction::Deleted, AuditDomain::Procurement, $shoppingList, []);
            if ($activity !== null) {
                $this->revisionWriter->write($activity, $before, null);
            }
        });

        return response()->json(null, 204);
    }

    /**
     * Auto-build a suggested list for a date RANGE. The owning menu cycle is resolved
     * per date (MenuCycle::coveringDate), so a span that crosses a week boundary —
     * e.g. the client's Fri→Mon run — pulls each day from the correct weekly cycle.
     * Quantities + costs come from the menu engine summing the actual planned weekdays
     * across the range; the default vendor for each item is the one remembered on the
     * catalog (fs_items.default_supplier_id).
     *
     * Dates with no covering cycle, no plan for that weekday, or no population are
     * recorded as "uncovered": the list is still built for the covered dates and marked
     * `partial` so the UI can warn about the gaps. Only a fully-uncovered span is a 422.
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'estimate_population' => ['required', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:255'],
            'preview' => ['sometimes', 'boolean'],
            ...self::draftItemRules(),
        ]);

        $cursor = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $spanDays = $cursor->diffInDays($end) + 1;
        abort_if($spanDays > 99, 422, 'Choose a span from 1 to 99 days.');

        $plan = app(ShoppingListPopulationService::class)->planRange($cursor, $end, $data['estimate_population']);

        // All-or-nothing: if any date in the span is missing a cycle or menu
        // items, block creation entirely. Estimate population is list-level only.
        $missingDates = array_values(array_unique([...$plan['uncovered_dates'], ...$plan['missing_population_dates']]));
        if ($missingDates !== []) {
            sort($missingDates);

            return response()->json([
                'message' => 'Shopping list blocked. Each service date needs menu items and an estimated population.',
                'missing_dates' => $missingDates,
                'missing_population_dates' => $plan['missing_population_dates'],
                'missing_items_by_date' => $plan['missing_items_by_date'],
            ], 422);
        }

        $conflicts = ShoppingList::query()
            ->where('list_type', 'suggested')
            ->whereDate('period_start', '<=', $data['end_date'])
            ->whereDate('period_end', '>=', $data['start_date'])
            ->get(['period_start', 'period_end']);
        $conflictingDates = [];
        foreach ($conflicts as $conflict) {
            $first = Carbon::parse($conflict->period_start)->max($cursor);
            $last = Carbon::parse($conflict->period_end)->min($end);
            for ($date = $first->copy(); $date->lte($last); $date->addDay()) {
                $conflictingDates[$date->toDateString()] = true;
            }
        }
        if ($conflictingDates !== []) {
            $dates = array_keys($conflictingDates);
            sort($dates);

            return response()->json([
                'message' => 'These service dates are already in a saved Suggested Shopping List.',
                'conflicting_dates' => $dates,
            ], 422);
        }

        if ($data['preview'] ?? false) {
            $itemUuids = FsItem::query()->whereIn('id', array_column($plan['items'], 'fs_item_id'))
                ->get(['id', 'uuid', 'base_unit'])->keyBy('id');
            $supplierUuids = Supplier::query()->whereIn('id', array_column($plan['items'], 'supplier_id'))
                ->get(['id', 'uuid'])->keyBy('id');
            $estimatedTotal = round(array_sum(array_column($plan['items'], 'total')), 2);

            return response()->json(['data' => [
                'id' => 'preview',
                'name' => $data['name'] ?? "Suggested — {$data['start_date']}→{$data['end_date']}",
                'list_date' => $data['start_date'],
                'list_type' => 'suggested',
                'procurement_track' => 'food',
                'status' => 'draft',
                'coverage_status' => 'full',
                'uncovered_dates' => [],
                'period_start' => $data['start_date'],
                'period_end' => $data['end_date'],
                'days_span' => $spanDays,
                'estimate_population' => $data['estimate_population'],
                'estimated_total' => $estimatedTotal,
                'estimated_budget_per_head_per_day' => round($estimatedTotal / ($spanDays * $data['estimate_population']), 2),
                'items' => array_map(fn (array $item): array => array_merge($item, [
                    'id' => 'preview:'.$itemUuids->get($item['fs_item_id'])?->uuid,
                    'fs_item_uuid' => $itemUuids->get($item['fs_item_id'])?->uuid,
                    'inventory_unit' => $itemUuids->get($item['fs_item_id'])?->base_unit,
                    'supplier_id' => $supplierUuids->get($item['supplier_id'])?->uuid,
                    'source' => 'generated',
                    'generation_baseline' => app(ShoppingListPopulationService::class)->generationBaseline($item, $data['estimate_population']),
                    'included_in_po' => true,
                    'exclusion_note' => null,
                    'quantity_mode' => 'calculated',
                    'item_type' => 'ingredient',
                ]), $plan['items']),
            ]]);
        }

        $list = $this->audited(function () use ($data, $plan, $spanDays): ShoppingList {
            MaintainMenuCycles::lockCreationOwner();
            abort_if(ShoppingList::query()->where('list_type', 'suggested')
                ->whereDate('period_start', '<=', $data['end_date'])->whereDate('period_end', '>=', $data['start_date'])->exists(),
                422, 'These service dates already have a saved Suggested Shopping List.');
            $list = $this->auditLogger->withoutModelEvents(fn () => DB::transaction(function () use ($data, $plan, $spanDays) {
                $list = ShoppingList::create([
                    'rnd_user_id' => Auth::id(),
                    'name' => $data['name'] ?? "Suggested — {$data['start_date']}→{$data['end_date']}",
                    'list_date' => $data['start_date'],
                    'period_start' => $data['start_date'],
                    'period_end' => $data['end_date'],
                    'days_span' => $spanDays,
                    'list_type' => 'suggested',
                    'procurement_track' => 'food',
                    'status' => 'draft',
                    'estimate_population' => $data['estimate_population'],
                    'estimate_population_updated_at' => now(),
                    'coverage_status' => 'full',
                    'uncovered_dates' => null,
                ]);

                foreach ($plan['items'] as $row) {
                    $list->items()->create($row + ['source' => 'generated', 'generation_baseline' => app(ShoppingListPopulationService::class)->generationBaseline($row, $data['estimate_population'])]);
                }

                if (isset($data['items'])) {
                    $this->applyDraftItems($list, $data['items']);
                }

                app(ShoppingListPopulationService::class)->cascadeMenuDays(
                    $data['start_date'], $data['end_date'], $data['estimate_population'],
                );

                return $list;
            }));
            $activity = $this->auditLogger->recordMutation(AuditAction::Created, AuditDomain::Procurement, $list, array_keys($list->getAttributes()));
            if ($activity !== null) {
                $this->revisionWriter->write($activity, null, $this->revisionRegistry->capture($list->load(self::ITEM_RELATIONS)));
            }

            return $list;
        });

        return response()->json(['data' => new ShoppingListResource($list->load('items.fsItem', 'items.supplier:id,uuid'))], 201);
    }

    private function applyDraftItems(ShoppingList $list, array $lines): void
    {
        $seen = [];
        foreach ($lines as $line) {
            $fsItem = FsItem::query()->where('uuid', $line['fs_item_uuid'])->firstOrFail();
            abort_if($fsItem->kind !== ($list->isSupplies() ? 'supply' : 'ingredient'), 422, 'Choose an Inventory item that matches this list type.');
            $unit = (string) $line['purchase_unit'];
            $physical = UnitConverter::isCompatible((string) $fsItem->base_unit, $unit)
                && (! $fsItem->include_in_generated_lists || ! $fsItem->hasIncompatibleRecipeUnit($unit));
            $savedUnit = isset($line['id']) && $list->items()->where('uuid', $line['id'])->where('purchase_unit', $unit)->exists();
            if (! $physical && ! $savedUnit) {
                abort(422, 'Choose a unit that matches the Inventory item and its Auto Grocery recipes.');
            }
            if (isset($line['id'])) {
                $item = $list->items()->where('uuid', $line['id'])->firstOrFail();
                if ($item->fs_item_id !== $fsItem->id || $item->source !== $line['source']) {
                    abort(422, 'Shopping List item does not match its saved source.');
                }
            } elseif ($line['source'] === 'generated') {
                $item = $list->items()->where('source', 'generated')->where('fs_item_id', $fsItem->id)->first();
                if (! $item) {
                    abort(422, 'Generated item is not part of the selected Menu Calendar days.');
                }
            } else {
                $item = $list->items()->create([
                    'fs_item_id' => $fsItem->id,
                    'ingredient_name' => $fsItem->name,
                    'source' => 'manual',
                    'qty' => UnitConverter::convert((float) $line['purchase_qty'], $unit, (string) $fsItem->base_unit),
                    'unit' => $fsItem->base_unit,
                    'unit_price' => $line['purchase_price'],
                    'total' => 0,
                ]);
            }
            if (isset($seen[$item->id])) {
                abort(422, 'Shopping List item was submitted twice.');
            }
            $seen[$item->id] = true;
            $mode = $line['quantity_mode'];
            $calculatedQty = UnitConverter::isCompatible((string) $item->unit, $unit)
                ? UnitConverter::convert((float) $item->qty, (string) $item->unit, $unit)
                : (float) $item->purchase_qty;
            $purchaseQty = match ($mode) {
                'rounded' => round($calculatedQty),
                'manual' => (float) $line['purchase_qty'],
                default => round($calculatedQty, 6),
            };
            $item->update([
                'purchase_unit' => $unit,
                'purchase_qty' => $purchaseQty,
                'purchase_price' => $line['purchase_price'],
                'supplier_id' => isset($line['supplier_id']) ? Supplier::idFromUuid($line['supplier_id']) : null,
                'quantity_mode' => $mode,
                'included_in_po' => $line['included_in_po'],
                'exclusion_note' => $line['exclusion_note'] ?? null,
                'total' => round($purchaseQty * (float) $line['purchase_price'], 2),
            ]);
        }
        abort_if($list->items()->where('source', 'generated')->whereNotIn('id', array_keys($seen))->exists(), 422, 'Generated ingredients cannot be deleted. Uncheck items you do not need to purchase.');
        $list->items()->where('source', 'manual')->whereNotIn('id', array_keys($seen))->delete();
    }

    /**
     * Edit one line: vendor / qty / price. Picking a vendor remembers it on the
     * catalog item so it's the default on the next suggested list.
     */
    public function updateItem(Request $request, ShoppingListItem $shoppingListItem): JsonResponse
    {
        if ($shoppingListItem->shoppingList->status !== 'draft') {
            return response()->json(['message' => 'Converted shopping list items are read-only.'], 422);
        }

        $data = $request->validate([
            'supplier_id' => ['nullable', 'string', 'exists:suppliers,uuid'],
            'qty' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'purchase_qty' => ['nullable', 'numeric', 'min:0'],
            'purchase_unit' => ['nullable', 'string', 'max:50'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'included_in_po' => ['nullable', 'boolean'],
            'exclusion_note' => ['nullable', 'string', 'max:255'],
            'vendor_locked' => ['nullable', 'boolean'],
            'quantity_mode' => ['sometimes', 'in:calculated,rounded,manual'],
        ]);

        if (! empty($data['supplier_id'])) {
            $data['supplier_id'] = Supplier::idFromUuid($data['supplier_id']);
        }

        if (array_key_exists('qty', $data)
            && ! $shoppingListItem->shoppingList->isSupplies()
            && $shoppingListItem->source === 'generated') {
            return response()->json(['message' => 'Calculated requirements are read-only. Edit planned purchase quantity instead.'], 422);
        }

        $explicitPurchaseQty = array_key_exists('purchase_qty', $data);

        if (isset($data['purchase_unit']) && UnitConverter::normalize($data['purchase_unit']) !== UnitConverter::normalize((string) ($shoppingListItem->purchase_unit ?? $shoppingListItem->unit))) {
            $from = (string) ($shoppingListItem->purchase_unit ?? $shoppingListItem->unit);
            $to = (string) $data['purchase_unit'];
            $fsItem = $shoppingListItem->fsItem;
            if (! UnitConverter::isCompatible($from, $to)
                || ($fsItem && ! UnitConverter::isCompatible((string) $fsItem->base_unit, $to))
                || ($fsItem && $fsItem->include_in_generated_lists
                    && $fsItem->hasIncompatibleRecipeUnit($to))) {
                return response()->json(['message' => 'Choose a unit that matches the Inventory item and its Auto Grocery recipes.'], 422);
            }
            $oldQty = (float) ($shoppingListItem->purchase_qty ?? $shoppingListItem->qty);
            $convertedQty = UnitConverter::convert($oldQty, $from, $to);
            $data['purchase_qty'] ??= round($convertedQty, 6);
            if (! array_key_exists('purchase_price', $data)) {
                $data['purchase_price'] = $convertedQty > 0
                    ? round((float) $shoppingListItem->total / $convertedQty, 6)
                    : 0;
            }
        }

        if ($explicitPurchaseQty && ! array_key_exists('quantity_mode', $data)) {
            $data['quantity_mode'] = 'manual';
        }
        if (isset($data['quantity_mode']) && $data['quantity_mode'] !== 'manual'
            && ! array_key_exists('purchase_qty', $data)) {
            $unit = (string) ($data['purchase_unit'] ?? $shoppingListItem->purchase_unit ?? $shoppingListItem->unit);
            if (! UnitConverter::isCompatible((string) $shoppingListItem->unit, $unit)) {
                return response()->json(['message' => 'Choose a unit that matches the calculated requirement.'], 422);
            }
            $calculated = UnitConverter::convert((float) $shoppingListItem->qty, (string) $shoppingListItem->unit, $unit);
            $data['purchase_qty'] = $data['quantity_mode'] === 'rounded' ? round($calculated) : round($calculated, 6);
        }

        $shoppingListItem->fill(collect($data)->only([
            'supplier_id', 'qty', 'unit_price', 'purchase_qty', 'purchase_unit',
            'purchase_price', 'included_in_po', 'exclusion_note', 'quantity_mode',
        ])->all());
        $shoppingListItem->total = $shoppingListItem->purchase_qty !== null && $shoppingListItem->purchase_price !== null
            ? round((float) $shoppingListItem->purchase_qty * (float) $shoppingListItem->purchase_price, 2)
            : round((float) $shoppingListItem->qty * (float) $shoppingListItem->unit_price, 2);

        // Manual vendor lock on this line — does NOT touch the catalog default.
        // The catalog vendor auto-updates from the LATEST PROCUREMENT (receiving),
        // not from a draft shopping-list edit.
        if (array_key_exists('vendor_locked', $data)) {
            if ($data['vendor_locked']) {
                $shoppingListItem->vendor_locked_at = now();
                $shoppingListItem->vendor_locked_by = Auth::id();
            } else {
                $shoppingListItem->vendor_locked_at = null;
                $shoppingListItem->vendor_locked_by = null;
            }
        }

        $this->audited(function () use ($shoppingListItem): void {
            if ($shoppingListItem->isClean()) {
                return;
            }
            $list = ShoppingList::query()->with(self::ITEM_RELATIONS)->lockForUpdate()->findOrFail($shoppingListItem->shopping_list_id);
            $before = $this->revisionRegistry->capture($list);
            $this->auditLogger->withoutModelEvents(fn () => $shoppingListItem->save());
            $after = $list->fresh(self::ITEM_RELATIONS);
            $activity = $this->auditLogger->recordMutation(
                AuditAction::Updated,
                AuditDomain::Procurement,
                $after,
                ['items'],
            );
            if ($activity !== null) {
                $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));
            }
        });

        return response()->json(['data' => [
            'id' => $shoppingListItem->uuid,
            // Public uuid — the procurement UI matches this against uuid-valued <option>s.
            'supplier_id' => $shoppingListItem->supplier?->uuid,
            'qty' => $shoppingListItem->qty,
            'unit_price' => $shoppingListItem->unit_price,
            'total' => $shoppingListItem->total,
            'purchase_qty' => $shoppingListItem->purchase_qty,
            'purchase_unit' => $shoppingListItem->purchase_unit,
            'purchase_price' => $shoppingListItem->purchase_price,
            'quantity_mode' => $shoppingListItem->quantity_mode,
            'source' => $shoppingListItem->source,
            'included_in_po' => $shoppingListItem->included_in_po,
            'exclusion_note' => $shoppingListItem->exclusion_note,
            'vendor_locked' => $shoppingListItem->vendorLocked(),
            'item_type' => $shoppingListItem->fsItem?->kind ?? 'ingredient',
        ]]);
    }

    public function storeItem(Request $request, ShoppingList $shoppingList): JsonResponse
    {
        if ($shoppingList->status !== 'draft') {
            return response()->json(['message' => 'Converted shopping list items are read-only.'], 422);
        }

        $isSupplies = $shoppingList->isSupplies();

        $data = $request->validate([
            'fs_item_id' => [$isSupplies ? 'required' : 'nullable', 'string', 'exists:fs_items,uuid'],
            'ingredient_name' => ['nullable', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'min:0'],
            'unit' => [$isSupplies ? 'nullable' : 'required', 'string', 'max:50'],
            'supplier_id' => ['nullable', 'string', 'exists:suppliers,uuid'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'purchase_qty' => [$isSupplies ? 'prohibited' : 'nullable', 'numeric', 'min:0'],
            'purchase_unit' => [$isSupplies ? 'prohibited' : 'nullable', 'string', 'max:50'],
            'purchase_price' => [$isSupplies ? 'prohibited' : 'nullable', 'numeric', 'min:0'],
        ]);

        if (! empty($data['fs_item_id'])) {
            $data['fs_item_id'] = FsItem::idFromUuid($data['fs_item_id']);
        }
        if (! empty($data['supplier_id'])) {
            $data['supplier_id'] = Supplier::idFromUuid($data['supplier_id']);
        }

        $fsItem = isset($data['fs_item_id']) ? FsItem::find($data['fs_item_id']) : null;

        // On a supplies list RND adds supply catalog items one by one — reject anything
        // that isn't kind=supply so the two tracks stay independent.
        if ($isSupplies) {
            if (! $fsItem || $fsItem->kind !== 'supply') {
                return response()->json(['message' => 'Supplies lists only accept supply catalog items.'], 422);
            }

            $qty = (float) $data['qty'];
            $unitPrice = array_key_exists('unit_price', $data) ? (float) $data['unit_price'] : (float) $fsItem->unit_cost;

            $item = $this->audited(function () use ($shoppingList, $fsItem, $qty, $unitPrice, $data): ShoppingListItem {
                $list = ShoppingList::query()->with(self::ITEM_RELATIONS)->lockForUpdate()->findOrFail($shoppingList->id);
                $before = $this->revisionRegistry->capture($list);
                $item = $this->auditLogger->withoutModelEvents(fn () => $list->items()->create([
                    'fs_item_id' => $fsItem->id,
                    'ingredient_name' => $fsItem->name,
                    'qty' => $qty,
                    'unit' => $fsItem->base_unit,
                    'supplier_id' => $data['supplier_id'] ?? $fsItem->default_supplier_id,
                    'unit_price' => $unitPrice,
                    'total' => round($qty * $unitPrice, 2),
                    'purchase_qty' => $qty,
                    'purchase_unit' => $fsItem->base_unit,
                    'purchase_price' => $unitPrice,
                    'source' => 'manual',
                ]));
                $after = $list->fresh(self::ITEM_RELATIONS);
                $activity = $this->auditLogger->recordMutation(AuditAction::Updated, AuditDomain::Procurement, $after, ['items']);
                if ($activity !== null) {
                    $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));
                }

                return $item;
            });

            return response()->json(['data' => [
                'id' => $item->uuid,
                // fs_item_id stays the raw FK (not consumed for routing); supplier_id is
                // the public uuid so it matches the vendor <option>s in the procurement UI.
                'fs_item_id' => $item->fs_item_id,
                'ingredient_name' => $item->ingredient_name,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'supplier_id' => $item->supplier?->uuid,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
                'purchase_qty' => $item->purchase_qty,
                'purchase_unit' => $item->purchase_unit,
                'purchase_price' => $item->purchase_price,
                'item_type' => 'supply',
                'source' => $item->source,
                'included_in_po' => $item->included_in_po,
            ]], 201);
        }

        if ($fsItem && $fsItem->kind !== 'ingredient') {
            return response()->json(['message' => 'Food shopping lists only accept ingredient catalog items.'], 422);
        }

        $qty = (float) $data['qty'];
        $unitPrice = (float) ($data['unit_price'] ?? 0);

        $item = $this->audited(function () use ($shoppingList, $data, $fsItem, $qty, $unitPrice): ShoppingListItem {
            $list = ShoppingList::query()->with(self::ITEM_RELATIONS)->lockForUpdate()->findOrFail($shoppingList->id);
            $before = $this->revisionRegistry->capture($list);
            $item = $this->auditLogger->withoutModelEvents(fn () => $list->items()->create([
                'fs_item_id' => $data['fs_item_id'] ?? null,
                'ingredient_name' => $data['ingredient_name'] ?? $fsItem?->name ?? 'Item',
                'qty' => $qty,
                'unit' => $data['unit'],
                'supplier_id' => $data['supplier_id'] ?? $fsItem?->default_supplier_id,
                'unit_price' => $unitPrice,
                'total' => round($qty * $unitPrice, 2),
                'purchase_qty' => $data['purchase_qty'] ?? null,
                'purchase_unit' => $data['purchase_unit'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
                'source' => 'manual',
            ]));
            $after = $list->fresh(self::ITEM_RELATIONS);
            $activity = $this->auditLogger->recordMutation(AuditAction::Updated, AuditDomain::Procurement, $after, ['items']);
            if ($activity !== null) {
                $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));
            }

            return $item;
        });

        return response()->json(['data' => [
            'id' => $item->uuid,
            // fs_item_id stays the raw FK; supplier_id is the public uuid (see supplies branch).
            'fs_item_id' => $item->fs_item_id,
            'ingredient_name' => $item->ingredient_name,
            'qty' => $item->qty,
            'unit' => $item->unit,
            'supplier_id' => $item->supplier?->uuid,
            'unit_price' => $item->unit_price,
            'total' => $item->total,
            'purchase_qty' => $item->purchase_qty,
            'purchase_unit' => $item->purchase_unit,
            'purchase_price' => $item->purchase_price,
            'item_type' => $item->fsItem?->kind ?? 'ingredient',
            'source' => $item->source,
            'included_in_po' => $item->included_in_po,
        ]], 201);
    }

    public function destroyItem(ShoppingListItem $shoppingListItem): JsonResponse
    {
        abort_if($shoppingListItem->source === 'generated', 422, 'Generated ingredients cannot be deleted. Uncheck items you do not need to purchase.');
        if ($shoppingListItem->shoppingList->status !== 'draft') {
            return response()->json(['message' => 'Converted shopping list items are read-only.'], 422);
        }

        $this->audited(function () use ($shoppingListItem): void {
            $list = ShoppingList::query()->with(self::ITEM_RELATIONS)->lockForUpdate()->findOrFail($shoppingListItem->shopping_list_id);
            $before = $this->revisionRegistry->capture($list);
            $this->auditLogger->withoutModelEvents(fn () => $shoppingListItem->delete());
            $after = $list->fresh(self::ITEM_RELATIONS);
            $activity = $this->auditLogger->recordMutation(AuditAction::Updated, AuditDomain::Procurement, $after, ['items']);
            if ($activity !== null) {
                $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));
            }
        });

        return response()->json(null, 204);
    }
}
