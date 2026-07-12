<?php

namespace App\Services\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderVendorGroup;
use App\Services\Audit\AuditLogger;
use App\Support\UnitConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceivingService
{
    /**
     * @return array{0:float,1:float}
     */
    public static function normalizeLine(float $qty, string $lineUnit, float $lineUnitPrice, string $baseUnit): array
    {
        $from = UnitConverter::normalize($lineUnit);
        $to = UnitConverter::normalize($baseUnit);

        if ($from === '' || $to === '' || $from === $to
            || ! UnitConverter::isKnown($from) || ! UnitConverter::isKnown($to)) {
            return [$qty, $lineUnitPrice];
        }

        $basePerLine = UnitConverter::convert(1, $from, $to);
        if ($basePerLine <= 0) {
            return [$qty, $lineUnitPrice];
        }

        return [$qty * $basePerLine, $lineUnitPrice / $basePerLine];
    }

    public function __construct(
        private readonly LatestProcurementVendorService $vendorSync,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param array<int, string> $changedFields */
    public function receive(PurchaseOrder $purchaseOrder, array $changedFields = []): void
    {
        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($purchaseOrder, $changedFields): void {
            $touched = [];

            $this->auditLogger->withoutModelEvents(function () use ($purchaseOrder, &$touched): void {
                foreach ($purchaseOrder->items as $item) {
                    $supplierId = $item->vendorGroup?->supplier_id;
                    $this->receiveLine($item, $touched, $purchaseOrder->id, $supplierId);
                }

                $this->recalculateTouchedRecipes($touched);
            });
            $this->recordReceipt($purchaseOrder, $purchaseOrder, $purchaseOrder->items->count(), $changedFields);
        });
    }

    public function receiveVendorGroup(PurchaseOrderVendorGroup $vendorGroup): void
    {
        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($vendorGroup): void {
            $touched = [];

            $this->auditLogger->withoutModelEvents(function () use ($vendorGroup, &$touched): void {
                foreach ($vendorGroup->items as $item) {
                    $this->receiveLine($item, $touched, $vendorGroup->purchase_order_id, $vendorGroup->supplier_id);
                }

                $this->recalculateTouchedRecipes($touched);
            });
        });
    }

    private function receiveLine(PurchaseOrderItem $item, array &$touched, int $purchaseOrderId, ?int $supplierId = null): void
    {
        if (! $item->fs_item_id) {
            Log::info('ReceivingService: skipped free-text PO line', [
                'po' => $purchaseOrderId,
                'description' => $item->description,
            ]);

            return;
        }

        $fs = FsItem::find($item->fs_item_id);
        if (! $fs) {
            Log::warning('ReceivingService: fs_item missing', ['fs_item_id' => $item->fs_item_id]);

            return;
        }

        $basePerPurchase = $fs->basePerPurchase();
        if ($item->purchase_qty !== null && $basePerPurchase > 0) {
            $qtyBase = (float) $item->purchase_qty * $basePerPurchase;
            $perBaseCost = (float) $item->purchase_price / $basePerPurchase;
        } else {
            [$qtyBase, $perBaseCost] = self::normalizeLine(
                (float) $item->qty,
                (string) $item->unit,
                (float) $item->unit_price,
                (string) $fs->base_unit
            );
        }

        $inv = Inventory::query()->where('fs_item_id', $fs->id)->lockForUpdate()->first();
        if ($inv === null) {
            $inv = new Inventory(['fs_item_id' => $fs->id]);
            $inv->item_type = $fs->kind ?? 'ingredient';
            $inv->quantity_in_stock = 0;
        }
        $inv->unit = $fs->base_unit;
        $inv->quantity_in_stock = (float) $inv->quantity_in_stock + $qtyBase;
        $inv->unit_price = round($perBaseCost, 2);
        $inv->save();

        if ($basePerPurchase > 0) {
            $fs->purchase_price = round($perBaseCost * $basePerPurchase, 2);
            $fs->save();
        }

        // Vendor auto-updates from the latest procurement unless manually locked.
        $this->vendorSync->syncFromReceipt($fs, $supplierId);

        $touched[$fs->id] = true;
    }

    private function recalculateTouchedRecipes(array $touched): void
    {
        if ($touched) {
            FoodServiceRecipe::recalculateForItems(array_keys($touched));
        }
    }

    /** @param array<int, string> $changedFields */
    private function recordReceipt(PurchaseOrder|PurchaseOrderVendorGroup $subject, PurchaseOrder $purchaseOrder, int $itemCount, array $changedFields = []): void
    {
        $this->auditLogger->record(
            AuditAction::Received,
            AuditCategory::Operations,
            AuditDomain::Procurement,
            subject: $subject,
            context: $purchaseOrder,
            details: [
                'item_count' => $itemCount,
                'status' => 'received',
                'changed_fields' => collect($changedFields)
                    ->reject(fn (string $field): bool => in_array($field, ['id', 'uuid', 'created_at', 'updated_at'], true))
                    ->unique()->sort()->values()->all(),
            ],
            systemActor: auth()->user() === null ? 'purchase-order-receiving' : null,
        );
    }
}
