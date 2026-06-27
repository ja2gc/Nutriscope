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
            ->where('kind', 'ready_to_eat')
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

    public function update(Request $request, FsItem $fsItem): JsonResponse
    {
        $data = $request->validate([
            'category'           => ['nullable', 'string', 'max:100'],
            'purchase_price'     => ['sometimes', 'numeric', 'min:0'],
            'purchase_unit'      => ['sometimes', 'string', 'max:20'],
            'base_unit'          => ['sometimes', 'string', 'max:20'],
            'units_per_purchase' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        // A price/unit change shifts derived unit_cost → refresh dependent recipe costs.
        $priceTouched = array_intersect(array_keys($data), ['purchase_price', 'purchase_unit', 'base_unit', 'units_per_purchase']) !== [];

        $fsItem->update($data);
        if ($priceTouched) {
            \App\Models\FoodServiceRecipe::recalculateForItems([$fsItem->id]);
        }
        Cache::flush();

        return response()->json(['data' => $fsItem->fresh()]);
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
        if ($fsItem->kind !== 'ready_to_eat') {
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
