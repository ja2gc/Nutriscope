<?php

namespace App\Http\Controllers\FSS;

use App\Events\PurchaseOrderConverted;
use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\ProgramProjectActivity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\FSS\PurchaseOrderLifecycleService;
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
    private const RELATIONS = [
        'items',
        'attachments',
        'supplier',
        'shoppingList',
        'vendorGroups.supplier',
        'vendorGroups.items',
        'vendorGroups.attachments',
        'programProjectActivity',
    ];

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

    public function update(
        UpdatePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        ReceivingService $receiving,
        PurchaseOrderLifecycleService $lifecycle
    ): JsonResponse {
        $validated = $request->validated();
        $previousStatus = $purchaseOrder->status;

        if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)
            && ($validated['lifecycle_status'] ?? null) !== 'archived') {
            return response()->json(['message' => 'Completed purchase orders are locked.'], 422);
        }

        DB::transaction(function () use ($purchaseOrder, $validated, $previousStatus, $receiving, $lifecycle) {
            if (($validated['lifecycle_status'] ?? null) === 'archived') {
                $lifecycle->archive($purchaseOrder);
                return;
            }

            $purchaseOrder->update($validated);

            if (($validated['status'] ?? null) === 'received' && $previousStatus !== 'received') {
                $purchaseOrder->received_date = now()->toDateString();
                $purchaseOrder->save();
                $receiving->receive($purchaseOrder->load('items'));
                $lifecycle->refresh($purchaseOrder);
            }

            if (($validated['status'] ?? null) === 'ordered' && $previousStatus !== 'ordered') {
                $this->notifyFssIfOrdered($purchaseOrder->id, 'ordered');
            }
        });

        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder->fresh()->load(self::RELATIONS))]);
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
            return response()->json(['message' => 'Completed purchase orders are locked.'], 422);
        }

        $purchaseOrder->delete();

        return response()->json(null, 204);
    }

    public function approve(ShoppingList $shoppingList, PurchaseOrderLifecycleService $lifecycle): JsonResponse
    {
        $shoppingList->load('items');
        if ($shoppingList->items->isEmpty()) {
            return response()->json(['message' => 'Shopping list has no items.'], 422);
        }
        if ($shoppingList->purchaseOrders()->exists()) {
            return response()->json(['message' => 'This shopping list has already been approved into a purchase.'], 422);
        }

        $track = $shoppingList->procurement_track ?? 'food';

        $po = DB::transaction(function () use ($shoppingList, $lifecycle, $track) {
            $po = PurchaseOrder::create([
                'rnd_user_id' => Auth::id(),
                'shopping_list_id' => $shoppingList->id,
                'supplier_id' => null,
                'po_number' => 'PO-' . strtoupper(Str::random(6)) . '-' . time(),
                'order_date' => now()->toDateString(),
                'total_amount' => $shoppingList->items->sum(fn ($i) => (float) $i->total),
                'status' => 'draft',
                'lifecycle_status' => 'open_execution',
                'procurement_track' => $track,
                'converted_at' => now(),
                // All structural data freezes at the moment of conversion.
                'structural_locked_at' => now(),
            ]);

            foreach ($shoppingList->items->groupBy('supplier_id') as $supplierId => $items) {
                $group = $po->vendorGroups()->create([
                    'supplier_id' => $supplierId !== '' ? (int) $supplierId : null,
                    'status' => 'pending',
                    'total_amount' => $items->sum(fn ($i) => (float) $i->total),
                ]);

                foreach ($items as $it) {
                    $po->items()->create([
                        'vendor_group_id' => $group->id,
                        'fs_item_id' => $it->fs_item_id,
                        'description' => $it->ingredient_name,
                        'qty' => $it->qty,
                        'unit' => $it->unit,
                        'unit_price' => $it->unit_price,
                        'total_value' => $it->total,
                        'purchase_qty' => $it->purchase_qty,
                        'purchase_unit' => $it->purchase_unit,
                        'purchase_price' => $it->purchase_price,
                    ]);
                }
            }

            $po->recalcTotal();
            $lifecycle->createPpaSnapshot($po, $shoppingList);

            // Food POs freeze the scaled snapshot onto each menu-cycle day cell.
            if ($track === 'food') {
                $lifecycle->writeMenuCycleSnapshots($po->fresh('items'), $shoppingList);
            }

            $shoppingList->update(['status' => 'converted']);

            event(new PurchaseOrderConverted($po->fresh(self::RELATIONS)));

            return $po->fresh(self::RELATIONS);
        });

        return response()->json(['data' => [
            'shopping_list_id' => $shoppingList->id,
            'purchase_order_id' => $po->id,
            'purchase_order_ids' => [$po->id],
        ]], 201);
    }

    public function updateVendorGroup(
        Request $request,
        PurchaseOrderVendorGroup $vendorGroup,
        ReceivingService $receiving,
        PurchaseOrderLifecycleService $lifecycle
    ): JsonResponse {
        // During open execution the ONLY structural edit allowed is a unit cost /
        // purchase price correction. purchase_qty and purchase_unit are frozen.
        $data = $request->validate([
            'or_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:pending,received'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required_with:items', 'integer', 'exists:purchase_order_items,id'],
            'items.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $po = $vendorGroup->purchaseOrder;
        if (in_array($po->lifecycle_status, ['completed', 'archived'], true)) {
            return response()->json(['message' => 'Completed purchase orders are locked.'], 422);
        }

        DB::transaction(function () use ($vendorGroup, $data, $receiving, $lifecycle) {
            $vendorGroup->fill(collect($data)->only(['or_number', 'status'])->all());
            if (($data['status'] ?? null) === 'received' && ! $vendorGroup->received_at) {
                $vendorGroup->received_at = now();
            }
            $vendorGroup->save();

            foreach ($data['items'] ?? [] as $line) {
                $item = $vendorGroup->items()->whereKey($line['id'])->firstOrFail();

                $newUnitPrice     = array_key_exists('unit_price', $line) ? (float) $line['unit_price'] : null;
                $newPurchasePrice = array_key_exists('purchase_price', $line) ? (float) $line['purchase_price'] : null;
                if ($newUnitPrice === null && $newPurchasePrice === null) {
                    continue;
                }

                $oldUnitPrice     = (float) $item->unit_price;
                $oldPurchasePrice = $item->purchase_price !== null ? (float) $item->purchase_price : null;

                $patch = [];
                if ($newUnitPrice !== null)     { $patch['unit_price'] = $newUnitPrice; }
                if ($newPurchasePrice !== null) { $patch['purchase_price'] = $newPurchasePrice; }

                // Recompute the line total from the corrected values (qty stays frozen).
                if ($item->purchase_qty !== null && array_key_exists('purchase_price', $patch)) {
                    $patch['total_value'] = round((float) $item->purchase_qty * (float) $patch['purchase_price'], 2);
                } elseif (array_key_exists('unit_price', $patch)) {
                    $patch['total_value'] = round((float) $item->qty * (float) $patch['unit_price'], 2);
                }

                $item->update($patch);

                // Audit every correction with user + timestamp.
                \App\Models\PurchaseOrderItemCorrection::create([
                    'purchase_order_item_id' => $item->id,
                    'old_unit_price'         => $oldUnitPrice,
                    'new_unit_price'         => $newUnitPrice ?? $oldUnitPrice,
                    'old_purchase_price'     => $oldPurchasePrice,
                    'new_purchase_price'     => $newPurchasePrice ?? $oldPurchasePrice,
                    'corrected_by'           => Auth::id(),
                    'corrected_at'           => now(),
                    'reason'                 => $line['reason'] ?? null,
                ]);
            }

            $vendorGroup->total_amount = (float) $vendorGroup->items()->sum('total_value');
            $vendorGroup->save();
            $vendorGroup->purchaseOrder->recalcTotal();

            // Only refresh catalog vendor/price from a vendor group that actually has a
            // receipt — "received" without proof must not push prices into the catalog.
            $hasReceipt = $vendorGroup->attachments()->where('type', 'receipt')->exists();
            if (($data['status'] ?? null) === 'received' && ! $vendorGroup->stocked_at && $hasReceipt) {
                $receiving->receiveVendorGroup($vendorGroup->fresh('items'));
                $vendorGroup->forceFill(['stocked_at' => now()])->save();
            }

            $lifecycle->refresh($vendorGroup->purchaseOrder);
        });

        return response()->json(['data' => new PurchaseOrderResource($vendorGroup->purchaseOrder->fresh()->load(self::RELATIONS))]);
    }

    public function uploadAttachment(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'in:receipt,proof'],
            'caption' => ['nullable', 'string', 'max:255'],
            'file' => ['sometimes', 'file', 'image', 'max:8192'],
            'files' => ['sometimes', 'array', 'max:20'],
            'files.*' => ['file', 'image', 'max:8192'],
        ]);

        if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
            return response()->json(['message' => 'Completed purchase orders are locked.'], 422);
        }

        $multi = $request->hasFile('files');
        $files = $multi ? $request->file('files') : array_values(array_filter([$request->file('file')]));

        if (empty($files)) {
            return response()->json(['message' => 'At least one image file is required.'], 422);
        }

        $created = collect($files)->map(function ($f) use ($purchaseOrder, $request) {
            $att = $purchaseOrder->attachments()->create([
                'type' => $request->input('type'),
                'path' => $f->store('po-attachments', 'public'),
                'caption' => $request->input('caption'),
            ]);

            return ['id' => $att->id, 'type' => $att->type, 'path' => $att->path, 'caption' => $att->caption];
        })->all();

        return response()->json(['data' => $multi ? $created : $created[0]], 201);
    }

    public function uploadVendorGroupAttachment(
        Request $request,
        PurchaseOrderVendorGroup $vendorGroup,
        ReceivingService $receiving,
        PurchaseOrderLifecycleService $lifecycle
    ): JsonResponse {
        $request->validate([
            'type' => ['required', 'in:receipt,proof'],
            'caption' => ['nullable', 'string', 'max:255'],
            'file' => ['sometimes', 'file', 'image', 'max:8192'],
            'files' => ['sometimes', 'array', 'max:20'],
            'files.*' => ['file', 'image', 'max:8192'],
        ]);

        $po = $vendorGroup->purchaseOrder;
        if (in_array($po->lifecycle_status, ['completed', 'archived'], true)) {
            return response()->json(['message' => 'Completed purchase orders are locked.'], 422);
        }

        $multi = $request->hasFile('files');
        $files = $multi ? $request->file('files') : array_values(array_filter([$request->file('file')]));
        if (empty($files)) {
            return response()->json(['message' => 'At least one image file is required.'], 422);
        }

        $created = DB::transaction(function () use ($files, $vendorGroup, $request, $receiving, $lifecycle) {
            $created = collect($files)->map(function ($f) use ($vendorGroup, $request) {
                $att = $vendorGroup->attachments()->create([
                    'purchase_order_id' => $vendorGroup->purchase_order_id,
                    'type' => $request->input('type'),
                    'path' => $f->store('po-attachments', 'public'),
                    'caption' => $request->input('caption'),
                ]);

                return ['id' => $att->id, 'type' => $att->type, 'path' => $att->path, 'caption' => $att->caption];
            })->all();

            if ($request->input('type') === 'receipt') {
                $vendorGroup->forceFill([
                    'status' => 'received',
                    'received_at' => $vendorGroup->received_at ?? now(),
                ])->save();

                if (! $vendorGroup->stocked_at) {
                    $receiving->receiveVendorGroup($vendorGroup->fresh('items'));
                    $vendorGroup->forceFill(['stocked_at' => now()])->save();
                }
            }

            $lifecycle->refresh($vendorGroup->purchaseOrder);

            return $created;
        });

        return response()->json(['data' => $multi ? $created : $created[0]], 201);
    }

    public function destroyAttachment(PurchaseOrderAttachment $attachment): JsonResponse
    {
        if ($attachment->purchaseOrder && in_array($attachment->purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
            return response()->json(['message' => 'Completed purchase orders are locked.'], 422);
        }

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->json(null, 204);
    }

    public function ppa(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $ppa = $purchaseOrder->programProjectActivity ?: ProgramProjectActivity::where('purchase_order_id', $purchaseOrder->id)->first();
        abort_unless($ppa, 404, 'PPA snapshot not found.');

        return response()->json(['data' => $ppa]);
    }

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
                "PO #{$poId} is ordered - upload proof of purchase.",
                'po_awaiting_receipt',
                'food_service',
                $poId,
            );
        });
    }
}
