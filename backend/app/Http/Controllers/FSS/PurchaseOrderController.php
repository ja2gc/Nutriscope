<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StorePurchaseOrderRequest;
use App\Http\Requests\FSS\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\ShoppingList;
use App\Services\FSS\ReceivingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    private const RELATIONS = ['items', 'attachments', 'supplier'];

    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(self::RELATIONS)->orderByDesc('created_at');
        if ($request->filled('shopping_list_id')) {
            $query->where('shopping_list_id', $request->get('shopping_list_id'));
        }
        return response()->json(['data' => PurchaseOrderResource::collection($query->get())]);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data  = $request->validated();
            $items = $data['items'] ?? [];
            unset($data['items']);

            $data['fss_user_id'] = Auth::id();
            $data['po_number']   = $data['po_number'] ?? ('PO-' . strtoupper(Str::random(8)) . '-' . time());
            $data['status']      = $data['status'] ?? 'draft';
            $data['total_amount'] = $data['total_amount'] ?? collect($items)->sum(fn ($i) => $i['qty'] * $i['unit_price']);

            $po = PurchaseOrder::create($data);
            foreach ($items as $item) {
                $po->items()->create([
                    'fs_item_id'  => $item['fs_item_id'] ?? null,
                    'description' => $item['description'] ?? 'Item',
                    'qty'         => $item['qty'],
                    'unit'        => $item['unit'] ?? 'unit',
                    'unit_price'  => $item['unit_price'],
                    'total_value' => $item['qty'] * $item['unit_price'],
                    'purchase_qty'   => $item['purchase_qty'] ?? null,
                    'purchase_unit'  => $item['purchase_unit'] ?? null,
                    'purchase_price' => $item['purchase_price'] ?? null,
                ]);
            }
            $po->recalcTotal();

            return response()->json(['data' => new PurchaseOrderResource($po->load(self::RELATIONS))], 201);
        });
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder->load(self::RELATIONS))]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, ReceivingService $receiving): JsonResponse
    {
        $validated = $request->validated();
        $previousStatus = $purchaseOrder->status;

        DB::transaction(function () use ($purchaseOrder, $validated, $previousStatus, $receiving) {
            $purchaseOrder->update($validated);

            if (($validated['status'] ?? null) === 'received' && $previousStatus !== 'received') {
                $purchaseOrder->received_date = now()->toDateString();
                $purchaseOrder->save();
                $receiving->receive($purchaseOrder->load('items'));
            }
        });

        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder->fresh()->load(self::RELATIONS))]);
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->delete();
        return response()->json(null, 204);
    }

    /**
     * Split one shopping list into a draft purchase order per vendor (e.g. one for
     * vegetables, one for meat). Items with no vendor land in an "unassigned" PO.
     */
    public function generatePos(ShoppingList $shoppingList): JsonResponse
    {
        $shoppingList->load('items');
        if ($shoppingList->items->isEmpty()) {
            return response()->json(['message' => 'Shopping list has no items.'], 422);
        }

        $created = [];
        DB::transaction(function () use ($shoppingList, &$created) {
            foreach ($shoppingList->items->groupBy('supplier_id') as $supplierId => $items) {
                $po = PurchaseOrder::create([
                    'fss_user_id'      => Auth::id(),
                    'shopping_list_id' => $shoppingList->id,
                    'supplier_id'      => $supplierId !== '' ? (int) $supplierId : null,
                    'po_number'        => 'PO-' . strtoupper(Str::random(6)) . '-' . time() . '-' . ($supplierId ?: 'NA'),
                    'order_date'       => now()->toDateString(),
                    'total_amount'     => $items->sum(fn ($i) => (float) $i->total),
                    'status'           => 'draft',
                ]);
                foreach ($items as $it) {
                    $po->items()->create([
                        'fs_item_id'  => $it->fs_item_id,
                        'description' => $it->ingredient_name,
                        'qty'         => $it->qty,
                        'unit'        => $it->unit,
                        'unit_price'  => $it->unit_price,
                        'total_value' => $it->total,
                        'purchase_qty'   => $it->purchase_qty,
                        'purchase_unit'  => $it->purchase_unit,
                        'purchase_price' => $it->purchase_price,
                    ]);
                }
                $po->recalcTotal();
                $created[] = $po->id;
            }
            $shoppingList->update(['status' => 'finalized']);
        });

        return response()->json(['data' => ['shopping_list_id' => $shoppingList->id, 'purchase_order_ids' => $created]], 201);
    }

    public function uploadAttachment(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $data = $request->validate([
            'file'    => ['required', 'file', 'image', 'max:8192'],
            'type'    => ['required', 'in:receipt,proof'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $request->file('file')->store('po-attachments', 'public');
        $att  = $purchaseOrder->attachments()->create([
            'type'    => $data['type'],
            'path'    => $path,
            'caption' => $data['caption'] ?? null,
        ]);

        return response()->json(['data' => ['id' => $att->id, 'type' => $att->type, 'path' => $att->path, 'caption' => $att->caption]], 201);
    }

    public function destroyAttachment(PurchaseOrderAttachment $attachment): JsonResponse
    {
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();
        return response()->json(null, 204);
    }
}
