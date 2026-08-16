<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Events\PurchaseOrderConverted;
use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\UpdatePurchaseOrderRequest;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\AuditActivity;
use App\Models\ProgramProjectActivity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderItemCorrection;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use App\Services\Audit\Revisions\AuditRevisionWriter;
use App\Services\FSS\PurchaseOrderAttachmentStorage;
use App\Services\FSS\PurchaseOrderLifecycleService;
use App\Services\FSS\ReceivingService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PurchaseOrderAttachmentStorage $attachmentStorage,
        private readonly AuditRevisionRegistry $revisionRegistry,
        private readonly AuditRevisionWriter $revisionWriter,
    ) {}

    private const RELATIONS = [
        'items.fsItem',
        'items.vendorGroup',
        'attachments.vendorGroup',
        'supplier',
        'shoppingList',
        'vendorGroups.supplier',
        'vendorGroups.items',
        'vendorGroups.attachments',
        'programProjectActivity',
    ];

    public function index(PaginatedRequest $request): AnonymousResourceCollection
    {
        $query = PurchaseOrder::with(self::RELATIONS)->orderByDesc('created_at');
        if ($request->filled('shopping_list_id')) {
            $query->where('shopping_list_id', $request->get('shopping_list_id'));
        }

        return PurchaseOrderResource::collection($query
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString());
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
                ->with(self::RELATIONS)
                ->firstOrFail();
            $before = $this->revisionRegistry->capture($purchaseOrder);
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
                $activity = $receiving->receive($purchaseOrder->load('items'), [...$changedFields, 'received_date']);
                $after = $purchaseOrder->fresh(self::RELATIONS);
                $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));
                $lifecycle->refresh($purchaseOrder);
            } elseif (($validated['status'] ?? null) === 'ordered' && $previousStatus !== 'ordered') {
                $activity = $this->auditLogger->recordMutation(
                    AuditAction::Ordered,
                    AuditDomain::Procurement,
                    $purchaseOrder,
                    $changedFields,
                    context: $purchaseOrder,
                );
                if ($activity !== null) {
                    $after = $purchaseOrder->fresh(self::RELATIONS);
                    $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));
                }
            } else {
                $activity = $this->auditLogger->recordMutation(
                    AuditAction::Updated,
                    AuditDomain::Procurement,
                    $purchaseOrder,
                    $changedFields,
                    context: $purchaseOrder,
                );
                if ($activity !== null) {
                    $after = $purchaseOrder->fresh(self::RELATIONS);
                    $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));
                }
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
                    ->with(self::RELATIONS)
                    ->firstOrFail();
                if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
                    abort(422, 'Completed purchase orders are locked.');
                }
                $before = $this->revisionRegistry->capture($purchaseOrder);
                $moves = $this->attachmentStorage->quarantineMany($purchaseOrder->attachments->pluck('path')->all());
                $activity = $this->auditLogger->record(
                    AuditAction::Deleted,
                    AuditCategory::Operations,
                    AuditDomain::Procurement,
                    subject: $purchaseOrder,
                    context: $purchaseOrder,
                    details: ['status' => $purchaseOrder->status],
                );
                $this->auditLogger->withoutModelEvents(fn () => $purchaseOrder->delete());
                $this->revisionWriter->write($activity, $before, null);
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
        $readiness = $lifecycle->releaseReadiness($shoppingList);
        if (! $readiness['ready']) {
            return response()->json(['message' => 'Shopping list is not ready for release.', 'readiness' => $readiness], 422);
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
            $readiness = $lifecycle->releaseReadiness($shoppingList, true);
            if (! $readiness['ready']) {
                abort(422, 'Shopping list is not ready for release.');
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

                foreach ($shoppingList->items->where('included_in_po', true)->groupBy('supplier_id') as $supplierId => $items) {
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
            $activity = $this->auditLogger->record(
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
            $this->revisionWriter->write($activity, null, $this->revisionRegistry->capture($po->load(self::RELATIONS)));

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
        $fssChangedPlannedValues = Auth::user()->isFss()
            && collect($request->input('items', []))->contains(
                fn (array $line): bool => array_key_exists('unit_price', $line)
                    || array_key_exists('purchase_price', $line),
            );
        if ($fssChangedPlannedValues) {
            return response()->json(['message' => 'FSS users cannot change frozen planned values.'], 403);
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
            'items.*.actual_qty' => ['nullable', 'numeric', 'min:0.001'],
            'items.*.actual_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.receipt_total' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $po = $vendorGroup->purchaseOrder;
        if (in_array($po->lifecycle_status, ['completed', 'archived'], true)) {
            return response()->json(['message' => 'Completed purchase orders are locked.'], 422);
        }

        $this->audited(function () use ($vendorGroup, $data, $receiving, $lifecycle): void {
            DB::transaction(function () use ($vendorGroup, $data, $receiving, $lifecycle) {
                $purchaseOrder = PurchaseOrder::query()
                    ->whereKey($vendorGroup->purchase_order_id)
                    ->lockForUpdate()
                    ->with(self::RELATIONS)
                    ->firstOrFail();
                $before = $this->revisionRegistry->capture($purchaseOrder);
                $vendorGroup = PurchaseOrderVendorGroup::query()
                    ->whereKey($vendorGroup->getKey())
                    ->lockForUpdate()
                    ->with('purchaseOrder')
                    ->firstOrFail();
                if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
                    abort(422, 'Completed purchase orders are locked.');
                }
                $wasReceived = $vendorGroup->status === 'received' || $vendorGroup->received_at !== null;
                if ($wasReceived && (isset($data['items']) || isset($data['status']))) {
                    abort(422, 'Received vendor groups are locked.');
                }
                $vendorGroup->fill(collect($data)->only(['or_number'])->all());
                $vendorGroup->save();
                $vendorChangedFields = array_values(array_intersect(array_keys($vendorGroup->getChanges()), ['or_number']));
                $receivedTransition = false;
                $correctionCount = 0;
                $actualChangeCount = 0;

                foreach ($data['items'] ?? [] as $line) {
                    $item = $vendorGroup->items()->whereKey($line['id'])->firstOrFail();

                    $actualPatch = [];
                    if (array_key_exists('actual_qty', $line)) {
                        $actualPatch['actual_qty'] = (float) $line['actual_qty'];
                    }
                    if (array_key_exists('actual_unit_price', $line)) {
                        $actualPatch['actual_unit_price'] = (float) $line['actual_unit_price'];
                    }
                    if (array_key_exists('receipt_total', $line) && ! array_key_exists('actual_qty', $line)) {
                        $price = (float) ($actualPatch['actual_unit_price']
                            ?? $item->actual_unit_price
                            ?? $item->purchase_price
                            ?? $item->unit_price);
                        if ($price <= 0) {
                            abort(422, 'Actual unit price must be greater than zero to calculate quantity from receipt total.');
                        }
                        $actualPatch['actual_qty'] = round((float) $line['receipt_total'] / $price, 3);
                    }
                    if ($actualPatch !== []) {
                        $item->update($actualPatch);
                        $actualChangeCount++;
                    }

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
                    PurchaseOrderItemCorrection::create([
                        'purchase_order_item_id' => $item->id,
                        'old_unit_price' => $oldUnitPrice,
                        'new_unit_price' => $newUnitPrice ?? $oldUnitPrice,
                        'old_purchase_price' => $oldPurchasePrice,
                        'new_purchase_price' => $newPurchasePrice ?? $oldPurchasePrice,
                        'corrected_by' => Auth::id(),
                        'corrected_at' => now(),
                        'reason' => $line['reason'] ?? null,
                    ]);
                    $correctionCount++;
                }

                $vendorGroup->load('items', 'attachments');
                if (($data['status'] ?? null) === 'received') {
                    if (! $vendorGroup->supplier_id) {
                        abort(422, 'Assign a supplier before marking this vendor received.');
                    }
                    if ($vendorGroup->attachments->where('type', 'receipt')->isEmpty()) {
                        abort(422, 'Upload at least one receipt before marking this vendor received.');
                    }
                    if ($vendorGroup->attachments->where('type', 'proof')->isEmpty()) {
                        abort(422, 'Upload at least one proof of purchase before marking this vendor received.');
                    }
                    if ($vendorGroup->items->contains(fn ($item) => $item->actual_qty === null || $item->actual_unit_price === null)) {
                        abort(422, 'Review actual quantity and unit price for every item before marking this vendor received.');
                    }

                    $vendorGroup->forceFill(['status' => 'received', 'received_at' => now()])->save();
                    $receivedTransition = true;
                }

                $vendorGroup->total_amount = round((float) $vendorGroup->items->sum(
                    fn ($item) => $item->actual_qty !== null && $item->actual_unit_price !== null
                        ? (float) $item->actual_qty * (float) $item->actual_unit_price
                        : (float) $item->total_value,
                ), 2);
                $vendorGroup->save();
                if ($correctionCount > 0) {
                    $this->auditLogger->withoutModelEvents(fn () => $vendorGroup->purchaseOrder->recalcTotal());
                }

                // Only refresh catalog vendor/price from a vendor group that actually has a
                // receipt — "received" without proof must not push prices into the catalog.
                if ($receivedTransition && ! $vendorGroup->stocked_at) {
                    $receiving->receiveVendorGroup($vendorGroup->fresh('items'));
                    $vendorGroup->forceFill(['stocked_at' => now()])->save();
                }

                $activities = [];
                if ($receivedTransition) {
                    $activities[] = $this->recordVendorReceived($vendorGroup, $vendorChangedFields);
                }
                if ($correctionCount > 0) {
                    $activities[] = $this->auditLogger->record(
                        AuditAction::PriceCorrected,
                        AuditCategory::Operations,
                        AuditDomain::Procurement,
                        subject: $purchaseOrder,
                        context: $purchaseOrder,
                        details: [
                            'changed_fields' => collect([...$vendorChangedFields, 'items'])->unique()->sort()->values()->all(),
                            'item_count' => $correctionCount,
                        ],
                    );
                } elseif (! $receivedTransition) {
                    $activity = $this->auditLogger->recordMutation(
                        AuditAction::Updated,
                        AuditDomain::Procurement,
                        $purchaseOrder,
                        $actualChangeCount > 0 ? [...$vendorChangedFields, 'items'] : $vendorChangedFields,
                        context: $purchaseOrder,
                    );
                    if ($activity !== null) {
                        $activities[] = $activity;
                    }
                }
                if ($activities !== []) {
                    $after = $purchaseOrder->fresh(self::RELATIONS);
                    $afterRevision = $this->revisionRegistry->capture($after);
                    foreach ($activities as $activity) {
                        $this->revisionWriter->write($activity, $before, $afterRevision);
                    }
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
                    ->with(self::RELATIONS)
                    ->firstOrFail();
                if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
                    abort(422, 'Completed purchase orders are locked.');
                }
                $before = $this->revisionRegistry->capture($purchaseOrder);
                $attachments = collect($files)->map(function ($file) use ($purchaseOrder, $request, &$storedPaths) {
                    $storedObject = $this->attachmentStorage->store($file);
                    $storedPaths[] = $storedObject;

                    return $purchaseOrder->attachments()->create([
                        'type' => $request->input('type'),
                        'path' => null,
                        'stored_object_id' => $storedObject->id,
                        'caption' => $request->input('caption'),
                    ]);
                });
                $activity = $this->auditLogger->record(
                    AuditAction::Uploaded,
                    AuditCategory::Operations,
                    AuditDomain::Procurement,
                    subject: $purchaseOrder,
                    context: $purchaseOrder,
                    details: [
                        'attachment_type' => $request->input('type'),
                        'attachment_count' => $attachments->count(),
                    ],
                );
                $after = $purchaseOrder->fresh(self::RELATIONS);
                $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));

                return $attachments->map(fn ($attachment): array => [
                    'id' => $attachment->uuid,
                    'type' => $attachment->type,
                    'url' => $attachment->url,
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
            $created = $this->audited(function () use ($files, $vendorGroup, $request, $lifecycle, &$storedPaths): array {
                return DB::transaction(function () use ($files, $vendorGroup, $request, $lifecycle, &$storedPaths): array {
                    $lockedPo = PurchaseOrder::query()
                        ->whereKey($vendorGroup->purchase_order_id)
                        ->lockForUpdate()
                        ->with(self::RELATIONS)
                        ->firstOrFail();
                    $before = $this->revisionRegistry->capture($lockedPo);
                    $vendorGroup = PurchaseOrderVendorGroup::query()
                        ->whereKey($vendorGroup->getKey())
                        ->lockForUpdate()
                        ->with('purchaseOrder')
                        ->firstOrFail();
                    if (in_array($lockedPo->lifecycle_status, ['completed', 'archived'], true)) {
                        abort(422, 'Completed purchase orders are locked.');
                    }
                    $attachments = collect($files)->map(function ($file) use ($vendorGroup, $request, &$storedPaths) {
                        $storedObject = $this->attachmentStorage->store($file);
                        $storedPaths[] = $storedObject;

                        return $vendorGroup->attachments()->create([
                            'purchase_order_id' => $vendorGroup->purchase_order_id,
                            'type' => $request->input('type'),
                            'path' => null,
                            'stored_object_id' => $storedObject->id,
                            'caption' => $request->input('caption'),
                        ]);
                    });

                    $after = $lockedPo->fresh(self::RELATIONS);
                    $afterRevision = $this->revisionRegistry->capture($after);
                    $uploadedActivity = $this->auditLogger->record(
                        AuditAction::Uploaded,
                        AuditCategory::Operations,
                        AuditDomain::Procurement,
                        subject: $after,
                        context: $after,
                        details: [
                            'attachment_type' => $request->input('type'),
                            'attachment_count' => $attachments->count(),
                        ],
                    );
                    $this->revisionWriter->write($uploadedActivity, $before, $afterRevision);
                    $lifecycle->refresh($after);

                    return $attachments->map(fn ($attachment): array => [
                        'id' => $attachment->uuid,
                        'type' => $attachment->type,
                        'url' => $attachment->url,
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
                    ->with(self::RELATIONS)
                    ->firstOrFail();
                $attachment = PurchaseOrderAttachment::query()
                    ->whereKey($attachment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                if (in_array($purchaseOrder->lifecycle_status, ['completed', 'archived'], true)) {
                    abort(422, 'Completed purchase orders are locked.');
                }
                $before = $this->revisionRegistry->capture($purchaseOrder);
                $storedObject = $attachment->storedObject;
                $move = $storedObject === null
                    ? $this->attachmentStorage->quarantine((string) $attachment->path)
                    : null;
                $attachment->delete();
                if ($storedObject !== null) {
                    $this->attachmentStorage->deleteObjectAfterCommit($storedObject);
                }
                $activity = $this->auditLogger->record(
                    AuditAction::Deleted,
                    AuditCategory::Operations,
                    AuditDomain::Procurement,
                    subject: $purchaseOrder,
                    context: $purchaseOrder,
                    details: ['attachment_type' => $attachment->type],
                );
                $after = $purchaseOrder->fresh(self::RELATIONS);
                $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));
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

    public function attachmentFile(PurchaseOrderAttachment $attachment): StreamedResponse
    {
        abort_if($attachment->purchaseOrder === null, 404);
        $stream = $this->attachmentStorage->readStream($attachment);
        $object = $attachment->storedObject;

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $object?->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.($object?->original_name ?? 'attachment').'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
    private function recordVendorReceived(PurchaseOrderVendorGroup $vendorGroup, array $ordinaryFields = []): AuditActivity
    {
        return $this->auditLogger->record(
            AuditAction::Received,
            AuditCategory::Operations,
            AuditDomain::Procurement,
            subject: $vendorGroup->purchaseOrder,
            context: $vendorGroup->purchaseOrder,
            details: [
                'status' => 'received',
                'changed_fields' => collect([...$ordinaryFields, 'status', 'received_at'])->unique()->sort()->values()->all(),
            ],
        );
    }
}
