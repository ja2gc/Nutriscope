<?php

namespace App\Services\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Models\AuditActivity;
use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
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
    public function receive(PurchaseOrder $purchaseOrder, array $changedFields = []): AuditActivity
    {
        $this->auditLogger->assertAvailable();

        return DB::transaction(function () use ($purchaseOrder, $changedFields): AuditActivity {
            $touched = [];

            $this->auditLogger->withoutModelEvents(function () use ($purchaseOrder, &$touched): void {
                foreach ($purchaseOrder->items as $item) {
                    $supplierId = $item->vendorGroup?->supplier_id;
                    $this->receiveLine($item, $touched, $purchaseOrder->id, $supplierId);
                }

                $this->recalculateTouchedRecipes($touched);
            });

            return $this->recordReceipt($purchaseOrder, $purchaseOrder, $purchaseOrder->items->count(), $changedFields);
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

        $fs = FsItem::query()->whereKey($item->fs_item_id)->lockForUpdate()->first();
        if (! $fs) {
            Log::warning('ReceivingService: fs_item missing', ['fs_item_id' => $item->fs_item_id]);

            return;
        }

        $lineQty = (float) $item->qty;
        $lineTotal = (float) $item->total_value;
        $frozenUnitPrice = $lineQty > 0 && $lineTotal > 0
            ? $lineTotal / $lineQty
            : (float) $item->unit_price;
        [, $perBaseCost] = self::normalizeLine(
            $lineQty,
            (string) $item->unit,
            $frozenUnitPrice,
            (string) $fs->base_unit,
        );

        $basePerPurchase = $fs->basePerPurchase();
        if ($basePerPurchase > 0 && $perBaseCost > 0) {
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
    private function recordReceipt(PurchaseOrder|PurchaseOrderVendorGroup $subject, PurchaseOrder $purchaseOrder, int $itemCount, array $changedFields = []): AuditActivity
    {
        return $this->auditLogger->record(
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
