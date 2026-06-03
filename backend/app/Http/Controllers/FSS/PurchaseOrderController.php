<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StorePurchaseOrderRequest;
use App\Http\Requests\FSS\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\FoodItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PurchaseOrderResource::collection(PurchaseOrder::with('items')->get())]);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['fss_user_id'] = Auth::id();
            
            if (empty($data['po_number'])) {
                $data['po_number'] = 'PO-' . strtoupper(Str::random(8)) . '-' . time();
            }
            $data['status'] = $data['status'] ?? 'draft';

            $items = $data['items'];
            unset($data['items']);

            // Calculate total PO amount from items if total_amount is not explicitly set
            $calculatedTotal = 0;
            foreach ($items as $item) {
                $calculatedTotal += $item['quantity'] * $item['unit_price'];
            }
            $data['total_amount'] = $data['total_amount'] ?? $calculatedTotal;

            $purchaseOrder = PurchaseOrder::create($data);

            foreach ($items as $item) {
                $foodItem = FoodItem::find($item['food_item_id']);
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'food_item_id'      => $item['food_item_id'],
                    'description'       => $item['description'] ?? ($foodItem ? $foodItem->name : 'Food Item'),
                    'qty'               => $item['quantity'],
                    'unit'              => $item['unit'] ?? $foodItem?->unit ?? 'unit',
                    'unit_price'        => $item['unit_price'],
                    'total_value'       => $item['quantity'] * $item['unit_price'],
                ]);
            }

            return response()->json(['data' => new PurchaseOrderResource($purchaseOrder->load('items'))], 201);
        });
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder->load('items'))]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validated();
        $previousStatus = $purchaseOrder->status;

        DB::transaction(function () use ($purchaseOrder, $validated, $previousStatus) {
            $purchaseOrder->update($validated);

            // When transitioning to 'received', restock inventory for each PO item
            if (($validated['status'] ?? null) === 'received' && $previousStatus !== 'received') {
                foreach ($purchaseOrder->items as $item) {
                    if ($item->food_item_id) {
                        \App\Models\Inventory::where('food_item_id', $item->food_item_id)
                            ->increment('quantity_in_stock', $item->qty);
                    }
                }
            }
        });

        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder->fresh()->load('items'))]);
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->delete();
        return response()->json(null, 204);
    }
}
