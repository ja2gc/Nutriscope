<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\FsItem;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
