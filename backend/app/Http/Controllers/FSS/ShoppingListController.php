<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreShoppingListRequest;
use App\Http\Requests\FSS\UpdateShoppingListRequest;
use App\Http\Resources\ShoppingListResource;
use App\Models\FsItem;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\FSS\ShoppingListPopulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShoppingListController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ShoppingListResource::collection(ShoppingList::with('items.fsItem')->orderByDesc('created_at')->get())]);
    }

    public function store(StoreShoppingListRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['rnd_user_id'] = Auth::id();
        $data['list_type'] = $data['list_type'] ?? 'manual';
        $data['status'] = $data['status'] ?? 'draft';
        $data['list_date'] = $data['list_date'] ?? now()->toDateString();
        if (array_key_exists('estimate_population', $data)) {
            $data['estimate_population_updated_at'] = now();
        }

        $shoppingList = ShoppingList::create($data);
        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items.fsItem'))], 201);
    }

    public function show(ShoppingList $shoppingList): JsonResponse
    {
        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items.fsItem'))]);
    }

    public function update(UpdateShoppingListRequest $request, ShoppingList $shoppingList): JsonResponse
    {
        $data = $request->validated();
        if (array_key_exists('estimate_population', $data)) {
            if ($shoppingList->status !== 'draft') {
                return response()->json(['message' => 'Only draft shopping lists can update estimate_population.'], 422);
            }

            app(ShoppingListPopulationService::class)->cascadePopulation($shoppingList, (int) $data['estimate_population']);
            unset($data['estimate_population']);
        }

        if ($data !== []) {
            $shoppingList->update($data);
        }

        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items.fsItem'))]);
    }

    public function destroy(ShoppingList $shoppingList): JsonResponse
    {
        $shoppingList->delete();
        return response()->json(null, 204);
    }

    /**
     * Estimated and actual budget-per-head for a procurement span.
     * Estimated comes from shopping list totals ÷ estimate_population.
     * Actual comes from the linked PO after Phase 3 completion.
     */
    public function costEfficiency(ShoppingList $shoppingList): JsonResponse
    {
        $shoppingList->loadMissing('items', 'purchaseOrder');

        $totalEstimated = (float) $shoppingList->items->sum(fn ($i) => (float) $i->total);
        $population     = (int) ($shoppingList->estimate_population ?? 0);
        $days           = 0;
        if ($shoppingList->period_start && $shoppingList->period_end) {
            $days = (int) $shoppingList->period_start->diffInDays($shoppingList->period_end) + 1;
        }

        $estimatedPerHead = ($population > 0 && $days > 0)
            ? round($totalEstimated / ($population * $days), 2)
            : null;

        $po     = $shoppingList->purchaseOrder;
        $actual = ($po && $po->lifecycle_status !== 'open_execution')
            ? (float) ($po->actual_budget_per_head_per_day ?? 0)
            : null;

        return response()->json(['data' => [
            'estimated_per_head'   => $estimatedPerHead,
            'actual_per_head'      => $actual,
            'pending'              => $actual === null,
            'estimated_total'      => round($totalEstimated, 2),
            'estimate_population'  => $population,
            'span_days'            => $days,
        ]]);
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
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'name'       => ['nullable', 'string', 'max:255'],
        ]);

        $cursor = \Carbon\Carbon::parse($data['start_date'])->startOfDay();
        $end    = \Carbon\Carbon::parse($data['end_date'])->startOfDay();
        $spanDays = $cursor->diffInDays($end) + 1;

        $plan = app(ShoppingListPopulationService::class)->planRange($cursor, $end);
        $uncovered = $plan['uncovered_dates'];

        // All-or-nothing: if ANY date in the span is missing a cycle, missing menu
        // items, or missing population, block creation entirely and report exactly
        // what's missing per date. A partial list is never created.
        $missingDates = array_values(array_unique(array_merge($uncovered, $plan['missing_population_dates'])));
        if ($missingDates !== []) {
            sort($missingDates);
            return response()->json([
                'message'               => 'Shopping list blocked — every date in the span must have a menu cycle, menu items, and an estimated population.',
                'missing_dates'         => $missingDates,
                'missing_items_by_date' => $plan['missing_items_by_date'],
            ], 422);
        }

        $list = DB::transaction(function () use ($data, $plan, $spanDays) {
            $list = ShoppingList::create([
                'rnd_user_id'       => Auth::id(),
                'name'              => $data['name'] ?? "Suggested — {$data['start_date']}→{$data['end_date']}",
                'list_date'         => now()->toDateString(),
                'period_start'      => $data['start_date'],
                'period_end'        => $data['end_date'],
                'days_span'         => $spanDays,
                'list_type'         => 'suggested',
                'procurement_track' => 'food',
                'status'            => 'draft',
                'coverage_status'   => 'full',
                'uncovered_dates'   => null,
            ]);

            foreach ($plan['items'] as $row) {
                $list->items()->create($row);
            }

            return $list;
        });

        return response()->json(['data' => new ShoppingListResource($list->load('items.fsItem'))], 201);
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

        // Unit is NOT editable — it follows the recipe/item creation unit.
        $data = $request->validate([
            'supplier_id'   => ['nullable', 'integer', 'exists:suppliers,id'],
            'qty'           => ['nullable', 'numeric', 'min:0'],
            'unit_price'    => ['nullable', 'numeric', 'min:0'],
            'vendor_locked' => ['nullable', 'boolean'],
        ]);

        $shoppingListItem->fill(collect($data)->only(['supplier_id', 'qty', 'unit_price'])->all());
        $shoppingListItem->total = round((float) $shoppingListItem->qty * (float) $shoppingListItem->unit_price, 2);

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

        $shoppingListItem->save();

        return response()->json(['data' => [
            'id'            => $shoppingListItem->id,
            'supplier_id'   => $shoppingListItem->supplier_id,
            'qty'           => $shoppingListItem->qty,
            'unit_price'    => $shoppingListItem->unit_price,
            'total'         => $shoppingListItem->total,
            'vendor_locked' => $shoppingListItem->vendorLocked(),
            'item_type'     => $shoppingListItem->fsItem?->kind ?? 'ingredient',
        ]]);
    }

    public function storeItem(Request $request, ShoppingList $shoppingList): JsonResponse
    {
        if ($shoppingList->status !== 'draft') {
            return response()->json(['message' => 'Converted shopping list items are read-only.'], 422);
        }

        $data = $request->validate([
            'fs_item_id'      => ['nullable', 'integer', 'exists:fs_items,id'],
            'ingredient_name' => ['nullable', 'string', 'max:255'],
            'qty'             => ['required', 'numeric', 'min:0'],
            'unit'            => ['required', 'string', 'max:50'],
            'supplier_id'     => ['nullable', 'integer', 'exists:suppliers,id'],
            'unit_price'      => ['nullable', 'numeric', 'min:0'],
            'purchase_qty'    => ['nullable', 'numeric', 'min:0'],
            'purchase_unit'   => ['nullable', 'string', 'max:50'],
            'purchase_price'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $fsItem = isset($data['fs_item_id']) ? FsItem::find($data['fs_item_id']) : null;

        // On a supplies list RND adds supply catalog items one by one — reject anything
        // that isn't kind=supply so the two tracks stay independent.
        if ($shoppingList->isSupplies() && $fsItem && $fsItem->kind !== 'supply') {
            return response()->json(['message' => 'Supplies lists only accept supply catalog items.'], 422);
        }

        $qty = (float) $data['qty'];
        $unitPrice = (float) ($data['unit_price'] ?? 0);

        $item = $shoppingList->items()->create([
            'fs_item_id'      => $data['fs_item_id'] ?? null,
            'ingredient_name' => $data['ingredient_name'] ?? $fsItem?->name ?? 'Item',
            'qty'             => $qty,
            'unit'            => $data['unit'],
            'supplier_id'     => $data['supplier_id'] ?? $fsItem?->default_supplier_id,
            'unit_price'      => $unitPrice,
            'total'           => round($qty * $unitPrice, 2),
            'purchase_qty'    => $data['purchase_qty'] ?? null,
            'purchase_unit'   => $data['purchase_unit'] ?? null,
            'purchase_price'  => $data['purchase_price'] ?? null,
        ]);

        return response()->json(['data' => [
            'id'              => $item->id,
            'fs_item_id'      => $item->fs_item_id,
            'ingredient_name' => $item->ingredient_name,
            'qty'             => $item->qty,
            'unit'            => $item->unit,
            'supplier_id'     => $item->supplier_id,
            'unit_price'      => $item->unit_price,
            'total'           => $item->total,
            'purchase_qty'    => $item->purchase_qty,
            'purchase_unit'   => $item->purchase_unit,
            'purchase_price'  => $item->purchase_price,
            'item_type'       => $item->fsItem?->kind ?? 'ingredient',
        ]], 201);
    }

    public function destroyItem(ShoppingListItem $shoppingListItem): JsonResponse
    {
        if ($shoppingListItem->shoppingList->status !== 'draft') {
            return response()->json(['message' => 'Converted shopping list items are read-only.'], 422);
        }

        $shoppingListItem->delete();
        return response()->json(null, 204);
    }
}
