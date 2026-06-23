<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreShoppingListRequest;
use App\Http\Requests\FSS\UpdateShoppingListRequest;
use App\Http\Resources\ShoppingListResource;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\MenuCycle;
use App\Models\PurchaseOrderItem;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\MenuCycleCostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShoppingListController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ShoppingListResource::collection(ShoppingList::with('items')->orderByDesc('created_at')->get())]);
    }

    public function store(StoreShoppingListRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['rnd_user_id'] = Auth::id();
        $data['list_type'] = $data['list_type'] ?? 'manual';
        $data['status'] = $data['status'] ?? 'draft';
        $data['list_date'] = $data['list_date'] ?? now()->toDateString();

        $shoppingList = ShoppingList::create($data);
        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items'))], 201);
    }

    public function show(ShoppingList $shoppingList): JsonResponse
    {
        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items'))]);
    }

    public function update(UpdateShoppingListRequest $request, ShoppingList $shoppingList): JsonResponse
    {
        $shoppingList->update($request->validated());
        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items'))]);
    }

    public function destroy(ShoppingList $shoppingList): JsonResponse
    {
        $shoppingList->delete();
        return response()->json(null, 204);
    }

    /**
     * Calculated budget-per-head for this procurement span: estimated (planning-time)
     * and actual (procurement cash ÷ derived served population, pending until the span
     * is closed — all POs received and every service day's meal prep completed).
     */
    public function costEfficiency(ShoppingList $shoppingList): JsonResponse
    {
        return response()->json([
            'data' => \App\Services\FSS\ProcurementCostEfficiencyService::forShoppingList($shoppingList),
        ]);
    }

    /**
     * Auto-build a suggested list from a menu cycle for a specific date range.
     * Quantities + costs come from the menu engine summing actual planned weekdays
     * across the range (not a proportional average); the default vendor for each
     * item is the one remembered on the catalog (fs_items.default_supplier_id).
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'menu_cycle_id' => ['required', 'integer', 'exists:menu_cycles,id'],
            'start_date'    => ['required_without:start_weekday', 'date'],
            'end_date'      => ['required_with:start_date', 'date', 'after_or_equal:start_date'],
            'start_weekday' => ['required_without:start_date', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'end_weekday'   => ['required_with:start_weekday', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'name'          => ['nullable', 'string', 'max:255'],
        ]);

        $cycle = MenuCycle::with('days.recipe.ingredients.fsItem', 'days.fsItem')->findOrFail($data['menu_cycle_id']);
        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        if (isset($data['start_weekday'])) {
            $fromIndex = array_search($data['start_weekday'], $weekdays, true);
            $toIndex = array_search($data['end_weekday'], $weekdays, true);
            if ($toIndex < $fromIndex) {
                return response()->json(['message' => 'End weekday must be on or after start weekday.'], 422);
            }
            if (! $cycle->week_start_date) {
                return response()->json(['message' => 'Menu cycle must have a Monday week_start_date.'], 422);
            }
            $startDate = $cycle->week_start_date->copy()->addDays($fromIndex);
            $endDate = $cycle->week_start_date->copy()->addDays($toIndex);
            $data['start_date'] = $startDate->toDateString();
            $data['end_date'] = $endDate->toDateString();
        }

        // Sum the ACTUAL planned days across the range (not a proportional average).
        $acc = []; // fs_item_id => ['name','unit','qty','total']
        $cursor = \Carbon\Carbon::parse($data['start_date']);
        $end    = \Carbon\Carbon::parse($data['end_date']);
        $spanDays = $cursor->diffInDays($end) + 1;

        for (; $cursor->lte($end); $cursor->addDay()) {
            $days = $cycle->days->where('day_of_week', $cursor->format('l'));
            if ($days->isEmpty()) {
                continue;
            }
            // Population is read per-day from each MenuCycleDay (estimate_population).
            foreach (MenuCycleCostService::usageForDays($days) as $u) {
                $id = $u['fs_item_id'];
                $acc[$id] ??= ['name' => $u['name'], 'unit' => $u['unit'], 'qty' => 0.0, 'total' => 0.0];
                $acc[$id]['qty']   += (float) $u['quantity'];
                $acc[$id]['total'] += (float) $u['cost'];
            }
        }

        $ids     = array_keys($acc);
        $fsItems = FsItem::whereIn('id', $ids)->get()->keyBy('id');

        // Net of stock (Spec 6 #2): don't re-buy what's already on hand or already
        // on an open (status='ordered', not-yet-received) purchase order.
        $onHand    = $ids ? Inventory::whereIn('fs_item_id', $ids)->pluck('quantity_in_stock', 'fs_item_id') : collect();
        $inTransit = $ids
            ? PurchaseOrderItem::whereIn('fs_item_id', $ids)
                ->whereHas('purchaseOrder', fn ($q) => $q->where('status', 'ordered'))
                ->selectRaw('fs_item_id, SUM(qty) as q')->groupBy('fs_item_id')->pluck('q', 'fs_item_id')
            : collect();

        $list = DB::transaction(function () use ($data, $cycle, $acc, $fsItems, $spanDays, $onHand, $inTransit) {
            $list = ShoppingList::create([
                'rnd_user_id'   => Auth::id(),
                'menu_cycle_id' => $cycle->id,
                'name'          => $data['name'] ?? "Suggested — {$cycle->name} ({$data['start_date']}→{$data['end_date']})",
                'list_date'     => now()->toDateString(),
                'period_start'  => $data['start_date'],
                'period_end'    => $data['end_date'],
                'days_span'     => $spanDays,
                'list_type'     => 'suggested',
                'status'        => 'draft',
            ]);

            foreach ($acc as $id => $row) {
                $covered = (float) ($onHand[$id] ?? 0) + (float) ($inTransit[$id] ?? 0);
                $net     = max(0.0, (float) $row['qty'] - $covered);
                if ($net <= 0) {
                    continue; // fully covered by stock on hand + open orders → nothing to buy
                }
                $fs  = $fsItems[$id] ?? null;
                $bpp = $fs ? $fs->basePerPurchase() : 0.0;

                if ($fs && $bpp > 0) {
                    // Round UP to whole purchase units (Spec 6 #4). Overage is carried
                    // as leftover stock (Decision B) and netted out next cycle by #2.
                    $packs         = (int) ceil($net / $bpp);
                    $baseBought    = $packs * $bpp;
                    $purchasePrice = (float) $fs->purchase_price;
                    $list->items()->create([
                        'fs_item_id'      => $id,
                        'ingredient_name' => $row['name'],
                        'qty'             => round($baseBought, 2),
                        'unit'            => $fs->base_unit ?? $row['unit'],
                        'supplier_id'     => $fs->default_supplier_id,
                        'unit_price'      => round($purchasePrice / $bpp, 4), // ₱/base
                        'total'           => round($packs * $purchasePrice, 2),
                        'purchase_qty'    => $packs,
                        'purchase_unit'   => $fs->purchase_unit,
                        'purchase_price'  => round($purchasePrice, 2),
                    ]);
                } else {
                    // Degrade: no valid pack conversion — keep base-unit netting.
                    $unitPrice = $row['qty'] > 0 ? round($row['total'] / $row['qty'], 4) : 0;
                    $list->items()->create([
                        'fs_item_id'      => $id,
                        'ingredient_name' => $row['name'],
                        'qty'             => round($net, 2),
                        'unit'            => $row['unit'],
                        'supplier_id'     => $fs?->default_supplier_id,
                        'unit_price'      => $unitPrice,
                        'total'           => round($net * $unitPrice, 2),
                    ]);
                }
            }

            return $list;
        });

        return response()->json(['data' => new ShoppingListResource($list->load('items'))], 201);
    }

    /**
     * Edit one line: vendor / qty / price. Picking a vendor remembers it on the
     * catalog item so it's the default on the next suggested list.
     */
    public function updateItem(Request $request, ShoppingListItem $shoppingListItem): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'qty'         => ['nullable', 'numeric', 'min:0'],
            'unit_price'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $shoppingListItem->fill($data);
        $shoppingListItem->total = round((float) $shoppingListItem->qty * (float) $shoppingListItem->unit_price, 2);
        $shoppingListItem->save();

        if (array_key_exists('supplier_id', $data) && $data['supplier_id'] && $shoppingListItem->fs_item_id) {
            FsItem::whereKey($shoppingListItem->fs_item_id)->update(['default_supplier_id' => $data['supplier_id']]);
        }

        return response()->json(['data' => [
            'id'          => $shoppingListItem->id,
            'supplier_id' => $shoppingListItem->supplier_id,
            'qty'         => $shoppingListItem->qty,
            'unit_price'  => $shoppingListItem->unit_price,
            'total'       => $shoppingListItem->total,
        ]]);
    }

    public function storeItem(Request $request, ShoppingList $shoppingList): JsonResponse
    {
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
        ]], 201);
    }

    public function destroyItem(ShoppingListItem $shoppingListItem): JsonResponse
    {
        $shoppingListItem->delete();
        return response()->json(null, 204);
    }
}
