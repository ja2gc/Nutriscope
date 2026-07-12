<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Events\PurchaseOrderConverted;
use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\ProgramProjectActivity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderItemCorrection;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\FSS\PurchaseOrderAttachmentStorage;
use App\Services\FSS\PurchaseOrderLifecycleService;
use App\Services\FSS\ReceivingService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PurchaseOrderAttachmentStorage $attachmentStorage,
    ) {}

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
        if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)
            && ($validated['lifecycle_status'] ?? null) !== 'archived') {
            return response()->json(['message' => 'Completed purchase orders are locked.'], 422);
        }

        $this->audited(function () use ($purchaseOrder, $validated, $receiving, $lifecycle): void {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->getKey())
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();
            $previousStatus = $purchaseOrder->status;
            if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)
                && ($validated['lifecycle_status'] ?? null) !== 'archived') {
                abort(422, 'Completed purchase orders are locked.');
            }
            if (($validated['lifecycle_status'] ?? null) === 'archived') {
                $lifecycle->archive($purchaseOrder);

                return;
            }

            $this->auditLogger->withoutModelEvents(fn () => $purchaseOrder->update($validated));
            $changedFields = array_keys($purchaseOrder->getChanges());

            if (($validated['status'] ?? null) === 'received' && $previousStatus !== 'received') {
                $this->auditLogger->withoutModelEvents(function () use ($purchaseOrder): void {
                    $purchaseOrder->received_date = now()->toDateString();
                    $purchaseOrder->save();
                });
                $receiving->receive($purchaseOrder->load('items'), [...$changedFields, 'received_date']);
                $lifecycle->refresh($purchaseOrder);
            } elseif (($validated['status'] ?? null) === 'ordered' && $previousStatus !== 'ordered') {
                $this->auditLogger->recordMutation(
                    AuditAction::Ordered,
                    AuditDomain::Procurement,
                    $purchaseOrder,
                    $changedFields,
                    context: $purchaseOrder,
                );
            } else {
                $this->auditLogger->recordMutation(
                    AuditAction::Updated,
                    AuditDomain::Procurement,
                    $purchaseOrder,
                    $changedFields,
                    context: $purchaseOrder,
                );
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

        $moves = [];
        try {
            $this->audited(function () use ($purchaseOrder, &$moves): void {
                $purchaseOrder = PurchaseOrder::query()
                    ->whereKey($purchaseOrder->getKey())
                    ->lockForUpdate()
                    ->with('attachments:id,purchase_order_id,path')
                    ->firstOrFail();
                if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
                    abort(422, 'Completed purchase orders are locked.');
                }
                $moves = $this->attachmentStorage->quarantineMany($purchaseOrder->attachments->pluck('path')->all());
                $this->auditLogger->record(
                    AuditAction::Deleted,
                    AuditCategory::Operations,
                    AuditDomain::Procurement,
                    subject: $purchaseOrder,
                    context: $purchaseOrder,
                    details: ['status' => $purchaseOrder->status],
                );
                $this->auditLogger->withoutModelEvents(fn () => $purchaseOrder->delete());
            });
        } catch (Throwable $exception) {
            $this->attachmentStorage->restoreMany($moves);

            throw $exception;
        }
        $this->attachmentStorage->deleteManyAfterCommit($moves);

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

        $po = $this->audited(function () use ($shoppingList, $lifecycle, $track): PurchaseOrder {
            $shoppingList = ShoppingList::query()
                ->whereKey($shoppingList->getKey())
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();
            if ($shoppingList->items->isEmpty()) {
                abort(422, 'Shopping list has no items.');
            }
            if ($shoppingList->purchaseOrders()->exists()) {
                abort(422, 'This shopping list has already been approved into a purchase.');
            }
            $track = $shoppingList->procurement_track ?? $track;

            $po = $this->auditLogger->withoutModelEvents(fn (): PurchaseOrder => DB::transaction(function () use ($shoppingList, $lifecycle, $track) {
                $po = PurchaseOrder::create([
                    'rnd_user_id' => Auth::id(),
                    'shopping_list_id' => $shoppingList->id,
                    'shopping_list_guard' => $shoppingList->id,
                    'supplier_id' => null,
                    'po_number' => 'PO-'.strtoupper(Str::random(6)).'-'.time(),
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
            }));
            $this->auditLogger->record(
                AuditAction::Approved,
                AuditCategory::Operations,
                AuditDomain::Procurement,
                subject: $po,
                context: $po,
                details: [
                    'procurement_track' => $po->procurement_track,
                    'item_count' => $po->items->count(),
                    'vendor_group_count' => $po->vendorGroups->count(),
                    'status' => $po->status,
                ],
            );

            return $po;
        });

        return response()->json(['data' => [
            'shopping_list_id' => $shoppingList->uuid,
            'purchase_order_id' => $po->uuid,
            'purchase_order_ids' => [$po->uuid],
        ]], 201);
    }

    public function updateVendorGroup(
        Request $request,
        PurchaseOrderVendorGroup $vendorGroup,
        ReceivingService $receiving,
        PurchaseOrderLifecycleService $lifecycle
    ): JsonResponse {
        // FSS may only update or_number; status and item-price corrections are RND-only.
        if (Auth::user()->isFss() && $request->hasAny(['status', 'items'])) {
            return response()->json(['message' => 'FSS users may only update the OR number.'], 403);
        }

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

        if (Auth::user()->isFss()) {
            $data = collect($data)->only('or_number')->all();
        }

        $po = $vendorGroup->purchaseOrder;
        if (in_array($po->lifecycle_status, ['completed', 'archived'], true)) {
            return response()->json(['message' => 'Completed purchase orders are locked.'], 422);
        }

        $this->audited(function () use ($vendorGroup, $data, $receiving, $lifecycle): void {
            DB::transaction(function () use ($vendorGroup, $data, $receiving, $lifecycle) {
                $purchaseOrder = PurchaseOrder::query()->whereKey($vendorGroup->purchase_order_id)->lockForUpdate()->firstOrFail();
                $vendorGroup = PurchaseOrderVendorGroup::query()
                    ->whereKey($vendorGroup->getKey())
                    ->lockForUpdate()
                    ->with('purchaseOrder')
                    ->firstOrFail();
                if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
                    abort(422, 'Completed purchase orders are locked.');
                }
                $wasReceived = $vendorGroup->status === 'received' || $vendorGroup->received_at !== null;
                $vendorGroup->fill(collect($data)->only(['or_number', 'status'])->all());
                if (($data['status'] ?? null) === 'received' && ! $vendorGroup->received_at) {
                    $vendorGroup->received_at = now();
                }
                $vendorGroup->save();
                $vendorChangedFields = array_values(array_intersect(array_keys($vendorGroup->getChanges()), ['or_number']));
                $receivedTransition = ! $wasReceived
                    && ($vendorGroup->status === 'received' || $vendorGroup->received_at !== null);
                if ($receivedTransition) {
                    $this->recordVendorReceived($vendorGroup, $vendorChangedFields);
                }

                foreach ($data['items'] ?? [] as $line) {
                    $item = $vendorGroup->items()->whereKey($line['id'])->firstOrFail();

                    $newUnitPrice = array_key_exists('unit_price', $line) ? (float) $line['unit_price'] : null;
                    $newPurchasePrice = array_key_exists('purchase_price', $line) ? (float) $line['purchase_price'] : null;
                    if ($newUnitPrice === null && $newPurchasePrice === null) {
                        continue;
                    }

                    $oldUnitPrice = (float) $item->unit_price;
                    $oldPurchasePrice = $item->purchase_price !== null ? (float) $item->purchase_price : null;

                    $patch = [];
                    if ($newUnitPrice !== null) {
                        $patch['unit_price'] = $newUnitPrice;
                    }
                    if ($newPurchasePrice !== null) {
                        $patch['purchase_price'] = $newPurchasePrice;
                    }

                    // Recompute the line total from the corrected values (qty stays frozen).
                    if ($item->purchase_qty !== null && array_key_exists('purchase_price', $patch)) {
                        $patch['total_value'] = round((float) $item->purchase_qty * (float) $patch['purchase_price'], 2);
                    } elseif (array_key_exists('unit_price', $patch)) {
                        $patch['total_value'] = round((float) $item->qty * (float) $patch['unit_price'], 2);
                    }

                    $item->update($patch);

                    // Audit every correction with user + timestamp.
                    $correction = PurchaseOrderItemCorrection::create([
                        'purchase_order_item_id' => $item->id,
                        'old_unit_price' => $oldUnitPrice,
                        'new_unit_price' => $newUnitPrice ?? $oldUnitPrice,
                        'old_purchase_price' => $oldPurchasePrice,
                        'new_purchase_price' => $newPurchasePrice ?? $oldPurchasePrice,
                        'corrected_by' => Auth::id(),
                        'corrected_at' => now(),
                        'reason' => $line['reason'] ?? null,
                    ]);
                    $this->auditLogger->record(
                        AuditAction::PriceCorrected,
                        AuditCategory::Operations,
                        AuditDomain::Procurement,
                        subject: $correction,
                        context: $vendorGroup->purchaseOrder,
                        details: [
                            'changed_fields' => array_keys($patch),
                            'item_count' => 1,
                        ],
                    );
                }

                $vendorGroup->total_amount = (float) $vendorGroup->items()->sum('total_value');
                $vendorGroup->save();
                $this->auditLogger->withoutModelEvents(fn () => $vendorGroup->purchaseOrder->recalcTotal());

                // Only refresh catalog vendor/price from a vendor group that actually has a
                // receipt — "received" without proof must not push prices into the catalog.
                $hasReceipt = $vendorGroup->attachments()->where('type', 'receipt')->exists();
                if (($data['status'] ?? null) === 'received' && ! $vendorGroup->stocked_at && $hasReceipt) {
                    $receiving->receiveVendorGroup($vendorGroup->fresh('items'));
                    $vendorGroup->forceFill(['stocked_at' => now()])->save();
                }

                if (! $receivedTransition) {
                    $this->auditLogger->recordMutation(
                        AuditAction::Updated,
                        AuditDomain::Procurement,
                        $vendorGroup,
                        $vendorChangedFields,
                        context: $vendorGroup->purchaseOrder,
                    );
                }

                $lifecycle->refresh($vendorGroup->purchaseOrder);
            });
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

        $storedPaths = [];
        try {
            $created = $this->audited(function () use ($files, $purchaseOrder, $request, &$storedPaths): array {
                $purchaseOrder = PurchaseOrder::query()
                    ->whereKey($purchaseOrder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
                    abort(422, 'Completed purchase orders are locked.');
                }
                $attachments = collect($files)->map(function ($file) use ($purchaseOrder, $request, &$storedPaths) {
                    $path = $this->attachmentStorage->store($file);
                    $storedPaths[] = $path;

                    return $purchaseOrder->attachments()->create([
                        'type' => $request->input('type'),
                        'path' => $path,
                        'caption' => $request->input('caption'),
                    ]);
                });
                $this->auditLogger->record(
                    AuditAction::Uploaded,
                    AuditCategory::Operations,
                    AuditDomain::Procurement,
                    subject: $attachments->first(),
                    context: $purchaseOrder,
                    details: [
                        'attachment_type' => $request->input('type'),
                        'attachment_count' => $attachments->count(),
                    ],
                );

                return $attachments->map(fn ($attachment): array => [
                    'id' => $attachment->uuid,
                    'type' => $attachment->type,
                    'path' => $attachment->path,
                    'caption' => $attachment->caption,
                ])->all();
            });
        } catch (Throwable $exception) {
            $this->attachmentStorage->deleteUploads($storedPaths);

            throw $exception;
        }

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

        $storedPaths = [];
        try {
            $created = $this->audited(function () use ($files, $vendorGroup, $request, $receiving, $lifecycle, $po, &$storedPaths): array {
                return DB::transaction(function () use ($files, $vendorGroup, $request, $receiving, $lifecycle, $po, &$storedPaths): array {
                    $lockedPo = PurchaseOrder::query()->whereKey($vendorGroup->purchase_order_id)->lockForUpdate()->firstOrFail();
                    $vendorGroup = PurchaseOrderVendorGroup::query()
                        ->whereKey($vendorGroup->getKey())
                        ->lockForUpdate()
                        ->with('purchaseOrder')
                        ->firstOrFail();
                    if (in_array($lockedPo->lifecycle_status, ['completed', 'archived'], true)) {
                        abort(422, 'Completed purchase orders are locked.');
                    }
                    $wasReceived = $vendorGroup->status === 'received' || $vendorGroup->received_at !== null;
                    $attachments = collect($files)->map(function ($file) use ($vendorGroup, $request, &$storedPaths) {
                        $path = $this->attachmentStorage->store($file);
                        $storedPaths[] = $path;

                        return $vendorGroup->attachments()->create([
                            'purchase_order_id' => $vendorGroup->purchase_order_id,
                            'type' => $request->input('type'),
                            'path' => $path,
                            'caption' => $request->input('caption'),
                        ]);
                    });

                    if ($request->input('type') === 'receipt') {
                        $vendorGroup->forceFill([
                            'status' => 'received',
                            'received_at' => $vendorGroup->received_at ?? now(),
                        ])->save();
                        if (! $wasReceived) {
                            $this->recordVendorReceived($vendorGroup);
                        }

                        if (! $vendorGroup->stocked_at) {
                            $receiving->receiveVendorGroup($vendorGroup->fresh('items'));
                            $vendorGroup->forceFill(['stocked_at' => now()])->save();
                        }
                    }

                    $lifecycle->refresh($vendorGroup->purchaseOrder);
                    $this->auditLogger->record(
                        AuditAction::Uploaded,
                        AuditCategory::Operations,
                        AuditDomain::Procurement,
                        subject: $attachments->first(),
                        context: $po,
                        details: [
                            'attachment_type' => $request->input('type'),
                            'attachment_count' => $attachments->count(),
                        ],
                    );

                    return $attachments->map(fn ($attachment): array => [
                        'id' => $attachment->uuid,
                        'type' => $attachment->type,
                        'path' => $attachment->path,
                        'caption' => $attachment->caption,
                    ])->all();
                });
            });
        } catch (Throwable $exception) {
            $this->attachmentStorage->deleteUploads($storedPaths);

            throw $exception;
        }

        return response()->json(['data' => $multi ? $created : $created[0]], 201);
    }

    public function destroyAttachment(PurchaseOrderAttachment $attachment): JsonResponse
    {
        if ($attachment->purchaseOrder && in_array($attachment->purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
            return response()->json(['message' => 'Completed purchase orders are locked.'], 422);
        }

        $move = null;
        try {
            $this->audited(function () use ($attachment, &$move): void {
                $purchaseOrder = PurchaseOrder::query()
                    ->whereKey($attachment->purchase_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $attachment = PurchaseOrderAttachment::query()
                    ->whereKey($attachment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
                    abort(422, 'Completed purchase orders are locked.');
                }
                $move = $this->attachmentStorage->quarantine($attachment->path);
                $attachment->delete();
                $this->auditLogger->record(
                    AuditAction::Deleted,
                    AuditCategory::Operations,
                    AuditDomain::Procurement,
                    subject: $attachment,
                    context: $purchaseOrder,
                    details: ['attachment_type' => $attachment->type],
                );
            });
        } catch (Throwable $exception) {
            if ($move !== null) {
                $this->attachmentStorage->restoreMany([$move]);
            }

            throw $exception;
        }
        if ($move !== null) {
            $this->attachmentStorage->deleteAfterCommit($move);
        }

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

    /** @param array<int, string> $ordinaryFields */
    private function recordVendorReceived(PurchaseOrderVendorGroup $vendorGroup, array $ordinaryFields = []): void
    {
        $this->auditLogger->record(
            AuditAction::Received,
            AuditCategory::Operations,
            AuditDomain::Procurement,
            subject: $vendorGroup,
            context: $vendorGroup->purchaseOrder,
            details: [
                'status' => 'received',
                'changed_fields' => collect([...$ordinaryFields, 'status', 'received_at'])->unique()->sort()->values()->all(),
            ],
        );
    }
}
