<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\FSS\ReceivingService;
use App\Services\NotificationService;
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

            if (($validated['status'] ?? null) === 'ordered' && $previousStatus !== 'ordered') {
                $this->notifyFssIfOrdered($purchaseOrder->id, 'ordered');
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
     * Approve a shopping list: it BECOMES the purchase. One purchase order is created
     * per vendor (e.g. one for vegetables, one for meat) so each vendor carries its own
     * OR number, receipts, and proof of purchase; items with no vendor land in an
     * "unassigned" order. The list's per-vendor orders together are the single purchase
     * for that list (grouped by shopping_list_id). Approval is one-shot — a list that
     * already produced orders cannot be re-approved (delete its orders first to redo).
     */
    public function approve(ShoppingList $shoppingList): JsonResponse
    {
        $shoppingList->load('items');
        if ($shoppingList->items->isEmpty()) {
            return response()->json(['message' => 'Shopping list has no items.'], 422);
        }
        if ($shoppingList->purchaseOrders()->exists()) {
            return response()->json(['message' => 'This shopping list has already been approved into a purchase.'], 422);
        }

        $created = [];
        DB::transaction(function () use ($shoppingList, &$created) {
            foreach ($shoppingList->items->groupBy('supplier_id') as $supplierId => $items) {
                $po = PurchaseOrder::create([
                    'rnd_user_id'      => Auth::id(),
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
        $request->validate([
            'type'    => ['required', 'in:receipt,proof'],
            'caption' => ['nullable', 'string', 'max:255'],
            'file'    => ['sometimes', 'file', 'image', 'max:8192'],       // single (back-compat)
            'files'   => ['sometimes', 'array', 'max:20'],                 // multiple
            'files.*' => ['file', 'image', 'max:8192'],
        ]);

        $multi = $request->hasFile('files');
        $files = $multi ? $request->file('files') : array_values(array_filter([$request->file('file')]));

        if (empty($files)) {
            return response()->json(['message' => 'At least one image file is required.'], 422);
        }

        $created = collect($files)->map(function ($f) use ($purchaseOrder, $request) {
            $att = $purchaseOrder->attachments()->create([
                'type'    => $request->input('type'),
                'path'    => $f->store('po-attachments', 'public'),
                'caption' => $request->input('caption'),
            ]);
            return ['id' => $att->id, 'type' => $att->type, 'path' => $att->path, 'caption' => $att->caption];
        })->all();

        // Single-file callers still get a single object; multi-file callers get an array.
        return response()->json(['data' => $multi ? $created : $created[0]], 201);
    }

    public function destroyAttachment(PurchaseOrderAttachment $attachment): JsonResponse
    {
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();
        return response()->json(null, 204);
    }

    /**
     * Notify all FSS users that a PO is ordered and awaiting proof of purchase.
     * Scheduled after the current DB transaction commits so a rollback sends nothing.
     */
    private function notifyFssIfOrdered(int $poId, string $status): void
    {
        if ($status !== 'ordered') {
            return;
        }

        DB::afterCommit(function () use ($poId) {
            $fssUsers = User::where('role', 'FSS')->get(['id']);
            if ($fssUsers->isEmpty()) {
                return;
            }
            app(NotificationService::class)->notify(
                $fssUsers,
                'PO Awaiting Receipt',
                "PO #{$poId} is ordered — upload proof of purchase.",
                'po_awaiting_receipt',
                'food_service',
                $poId,
            );
        });
    }
}
