<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\FsItem;
use App\Models\PurchaseOrder;
use App\Services\FSS\LatestProcurementVendorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FsItemController extends Controller
{
    /**
     * Ready-to-eat catalog items usable as standalone menu entries (e.g. a banana or
     * Yakult snack placed directly in any meal slot). Raw ingredients and non-food
     * supplies are excluded — those are only used inside recipes.
     */
    public function index(Request $request): JsonResponse
    {
        $items = FsItem::query()
            ->where('is_active', true)
            ->where('kind', 'ingredient')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'base_unit', 'purchase_price', 'purchase_unit', 'units_per_purchase'])
            ->map(fn (FsItem $i) => [
                'id'        => $i->id,
                'name'      => $i->name,
                'category'  => $i->category,
                'unit'      => $i->base_unit,
                'unit_cost' => $i->unit_cost,
            ]);

        return response()->json(['data' => $items]);
    }

    /**
     * The reference catalog — the backend list of ingredients/supplies that recipes
     * and procurement are built from. NOT a stock ledger: items carry no quantity, only
     * how they're bought (unit + cost) and their auto-suggested vendor. Optionally
     * filtered by kind (ingredient | supply | ready_to_eat).
     */
    public function catalog(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind'   => ['nullable', 'in:ingredient,supply'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $items = FsItem::query()
            ->with('defaultSupplier:id,name')
            ->where('is_active', true)
            ->when($data['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->get()
            ->map(fn (FsItem $i) => $this->catalogRow($i));

        return response()->json(['data' => $items]);
    }

    /** @return array<string,mixed> */
    private function catalogRow(FsItem $i): array
    {
        return [
            'id'                  => $i->id,
            'name'                => $i->name,
            'kind'                => $i->kind,
            'category'            => $i->category,
            'base_unit'           => $i->base_unit,
            'purchase_unit'       => $i->purchase_unit,
            'purchase_price'      => $i->purchase_price,
            'units_per_purchase'  => $i->units_per_purchase,
            'unit_cost'           => round($i->unit_cost, 4),
            'default_supplier_id' => $i->default_supplier_id,
            'vendor'              => $i->defaultSupplier?->name,
            'vendor_locked'       => $i->vendorLocked(),
        ];
    }

    /**
     * Create a reference catalog item. Ingredients carry a base/purchase unit and a
     * purchase price (→ unit cost). Supplies have no qty — only a cost per unit — so the
     * base and purchase unit are the same and one purchase unit is one base unit.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255', 'unique:fs_items,name'],
            'kind'                => ['required', 'in:ingredient,supply'],
            'category'            => ['nullable', 'string', 'max:100'],
            'base_unit'           => ['required', 'string', 'max:20'],
            'purchase_price'      => ['required', 'numeric', 'min:0'],
            'default_supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ]);

        // Catalog items carry a single unit and a cost per that unit (plan: ingredient =
        // name, category, vendor, unit, unit/cost; supply = name, category, vendor, cost).
        // purchase_unit == base_unit and 1 base unit per purchase ⇒ unit_cost == cost entered.
        $data['purchase_unit']      = $data['base_unit'];
        $data['units_per_purchase'] = 1;

        $item = FsItem::create($data + ['is_active' => true]);
        Cache::flush();

        return response()->json(['data' => $this->catalogRow($item->fresh('defaultSupplier'))], 201);
    }

    public function update(Request $request, FsItem $fsItem): JsonResponse
    {
        $data = $request->validate([
            'name'                => ['sometimes', 'string', 'max:255', 'unique:fs_items,name,' . $fsItem->id],
            'category'            => ['nullable', 'string', 'max:100'],
            'purchase_price'      => ['sometimes', 'numeric', 'min:0'],
            'base_unit'           => ['sometimes', 'string', 'max:20'],
            'default_supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
        ]);

        // Single unit + cost per unit: keep purchase unit aligned to the base unit so
        // unit_cost stays equal to the entered cost.
        if (array_key_exists('base_unit', $data)) {
            $data['purchase_unit']      = $data['base_unit'];
            $data['units_per_purchase'] = 1;
        }

        // A price/unit change shifts derived unit_cost → refresh dependent recipe costs.
        $priceTouched = array_intersect(array_keys($data), ['purchase_price', 'base_unit']) !== [];

        $fsItem->update($data);
        if ($priceTouched) {
            \App\Models\FoodServiceRecipe::recalculateForItems([$fsItem->id]);
        }
        Cache::flush();

        return response()->json(['data' => $this->catalogRow($fsItem->fresh('defaultSupplier'))]);
    }

    /** Remove a catalog item — blocked while any recipe still references it. */
    public function destroy(FsItem $fsItem): JsonResponse
    {
        $usedBy = \App\Models\FoodServiceRecipeIngredient::where('fs_item_id', $fsItem->id)->count();
        if ($usedBy > 0) {
            return response()->json([
                'message' => "Can't delete: this item is used by {$usedBy} recipe ingredient(s). Remove it from those recipes first.",
            ], 409);
        }

        $fsItem->delete();
        Cache::flush();

        return response()->json(null, 204);
    }

    /**
     * Lock or unlock the catalog item's suggested vendor. While locked, the vendor
     * stops auto-updating from the latest procurement until explicitly unlocked.
     */
    public function toggleDefaultSupplierLock(Request $request, FsItem $fsItem, LatestProcurementVendorService $vendorSync): JsonResponse
    {
        $data = $request->validate([
            'locked' => ['required', 'boolean'],
        ]);

        if ($data['locked']) {
            $vendorSync->lock($fsItem, Auth::id());
        } else {
            $vendorSync->unlock($fsItem);
        }

        $fsItem->refresh();

        return response()->json(['data' => [
            'id'                  => $fsItem->id,
            'default_supplier_id' => $fsItem->default_supplier_id,
            'vendor_locked'       => $fsItem->vendorLocked(),
            'locked_at'           => $fsItem->default_supplier_locked_at?->toDateTimeString(),
            'locked_by'           => $fsItem->defaultSupplierLockedBy?->name,
        ]]);
    }

    /**
     * Cost profile for a ready-to-serve item placed directly in a menu-cycle slot.
     * Quantity is per head, so total = quantity x population x unit cost.
     */
    public function profile(Request $request, FsItem $fsItem): JsonResponse
    {
        if ($fsItem->kind !== 'ingredient') {
            abort(404);
        }

        $data = $request->validate([
            'population' => ['nullable', 'integer', 'min:0'],
            'quantity'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $population = (int) ($data['population'] ?? 0);
        $quantity = (float) ($data['quantity'] ?? 1);
        $totalQuantity = $population * $quantity;
        $unitCost = $fsItem->unit_cost;
        $totalCost = round($totalQuantity * $unitCost, 2);

        return response()->json(['data' => [
            'id' => $fsItem->id,
            'fs_item_id' => $fsItem->id,
            'name' => $fsItem->name,
            'kind' => $fsItem->kind,
            'category' => $fsItem->category,
            'unit' => $fsItem->base_unit,
            'unit_cost' => $unitCost,
            'quantity' => $quantity,
            'population' => $population,
            'servings' => $population,
            'total_quantity' => round($totalQuantity, 2),
            'total_cost' => $totalCost,
            'cost_per_head' => $population > 0 ? round($totalCost / $population, 2) : 0.0,
            'prep_notes' => $fsItem->notes,
            'formula' => 'total_cost = quantity_per_head * population * unit_cost',
            'ingredient_usage' => [[
                'fs_item_id' => $fsItem->id,
                'name' => $fsItem->name,
                'unit' => $fsItem->base_unit,
                'quantity' => round($totalQuantity, 2),
                'cost' => $totalCost,
            ]],
        ]]);
    }

    /** @param array<int,array{date:string,unit_price:float}> $points */
    public static function summarizeTrend(array $points): array
    {
        if (! $points) {
            return ['min' => 0.0, 'max' => 0.0, 'latest' => 0.0, 'avg' => 0.0];
        }
        $last   = $points[array_key_last($points)];
        $prices = array_map(fn ($p) => (float) $p['unit_price'], $points);

        return [
            'min'    => min($prices),
            'max'    => max($prices),
            'latest' => (float) $last['unit_price'],
            'avg'    => round(array_sum($prices) / count($prices), 6),
        ];
    }

    /** Purchase-price trend for one catalog item, derived from frozen received-PO lines. */
    public function priceTrend(Request $request, FsItem $fsItem): JsonResponse
    {
        $data = $request->validate([
            'start' => ['nullable', 'date'],
            'end'   => ['nullable', 'date', 'after_or_equal:start'],
        ]);
        $start = $data['start'] ?? now()->subMonths(6)->toDateString();
        $end   = $data['end'] ?? now()->toDateString();

        $rows = PurchaseOrder::query()
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.status', 'received')
            ->where('purchase_order_items.fs_item_id', $fsItem->id)
            ->whereRaw('COALESCE(purchase_orders.received_date, purchase_orders.order_date) BETWEEN ? AND ?', [$start, $end])
            ->orderByRaw('COALESCE(purchase_orders.received_date, purchase_orders.order_date)')
            ->get([
                DB::raw('COALESCE(purchase_orders.received_date, purchase_orders.order_date) as date'),
                'purchase_order_items.unit_price as unit_price',
            ])
            ->map(fn ($r) => ['date' => (string) $r->date, 'unit_price' => (float) $r->unit_price])
            ->all();

        return response()->json(['data' => ['points' => $rows] + self::summarizeTrend($rows)]);
    }
}
