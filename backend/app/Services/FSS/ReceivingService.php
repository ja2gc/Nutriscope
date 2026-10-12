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
    public function receive(PurchaseOrder $purchaseOrder, array $changedFields = []): ?AuditActivity
    {
        $this->auditLogger->assertAvailable();

        return $this->recordReceipt($purchaseOrder, $purchaseOrder, $purchaseOrder->items->count(), $changedFields);
    }

    public function receiveVendorGroup(PurchaseOrderVendorGroup $vendorGroup): void
    {
        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($vendorGroup): void {
            $touched = [];
            $catalogChanges = [];

            $this->auditLogger->withoutModelEvents(function () use ($vendorGroup, &$touched, &$catalogChanges): void {
                foreach ($vendorGroup->items as $item) {
                    $catalogChange = $this->receiveLine($item, $touched, $vendorGroup->purchase_order_id, $vendorGroup->supplier_id);
                    if ($catalogChange !== null) {
                        $catalogChanges[] = $catalogChange;
                    }
                }

                $this->recalculateTouchedRecipes($touched);
            });

            if (! config('audit.seeding.suppress_model_events', false)) {
                foreach ($catalogChanges as $change) {
                    $this->auditLogger->recordMutation(
                        AuditAction::Updated,
                        AuditDomain::FoodService,
                        $change['subject'],
                        $change['changed_fields'],
                        context: $vendorGroup->purchaseOrder,
                        oldValues: $change['old_values'],
                        newValues: $change['new_values'],
                    );
                }
            }
        });
    }

    /**
     * @param  array<int, true>  $touched
     * @return array{subject: FsItem, changed_fields: list<string>, old_values: array<string, mixed>, new_values: array<string, mixed>}|null
     */
    private function receiveLine(PurchaseOrderItem $item, array &$touched, int $purchaseOrderId, ?int $supplierId = null): ?array
    {
        if (! $item->fs_item_id) {
            Log::info('ReceivingService: skipped free-text PO line', [
                'po' => $purchaseOrderId,
                'description' => $item->description,
            ]);

            return null;
        }

        $fs = FsItem::query()->whereKey($item->fs_item_id)->lockForUpdate()->first();
        if (! $fs) {
            Log::warning('ReceivingService: fs_item missing', ['fs_item_id' => $item->fs_item_id]);

            return null;
        }

        $trackedFields = ['base_unit', 'purchase_unit', 'units_per_purchase', 'purchase_price', 'default_supplier_id'];
        $before = $fs->only($trackedFields);
        $receivedUnit = (string) ($item->purchase_unit ?: $item->unit);
        $receivedUnitPrice = (float) ($item->actual_unit_price ?? $item->purchase_price ?? $item->unit_price);
        if ($fs->include_in_generated_lists && $fs->hasIncompatibleRecipeUnit($receivedUnit)) {
            abort(422, "The completed PO unit for '{$fs->name}' does not match a linked recipe.");
        }
        $fs->base_unit = $receivedUnit;
        $fs->purchase_unit = $receivedUnit;
        $fs->units_per_purchase = 1;
        $fs->purchase_price = round($receivedUnitPrice, 6);
        $fs->save();

        // Vendor auto-updates from the latest procurement unless manually locked.
        $this->vendorSync->syncFromReceipt($fs, $supplierId);

        $touched[$fs->id] = true;

        $after = $fs->fresh();
        $afterValues = $after->only($trackedFields);
        $changedFields = array_values(array_filter(
            $trackedFields,
            fn (string $field): bool => $before[$field] !== $afterValues[$field],
        ));

        if ($changedFields === []) {
            return null;
        }

        return [
            'subject' => $after,
            'changed_fields' => $changedFields,
            'old_values' => array_intersect_key($before, array_flip($changedFields)),
            'new_values' => array_intersect_key($afterValues, array_flip($changedFields)),
        ];
    }

    private function recalculateTouchedRecipes(array $touched): void
    {
        if ($touched) {
            FoodServiceRecipe::recalculateForItems(array_keys($touched));
        }
    }

    /** @param array<int, string> $changedFields */
    private function recordReceipt(PurchaseOrder|PurchaseOrderVendorGroup $subject, PurchaseOrder $purchaseOrder, int $itemCount, array $changedFields = []): ?AuditActivity
    {
        $actor = auth()->user();
        if ($actor === null) {
            return null;
        }

        return $this->auditLogger->record(
            AuditAction::Updated,
            AuditCategory::Operations,
            AuditDomain::Procurement,
            subject: $subject,
            context: $purchaseOrder,
            details: [
                'subject_label' => 'Goods received',
                'item_count' => $itemCount,
                'status' => 'received',
                'changed_fields' => collect($changedFields)
                    ->reject(fn (string $field): bool => in_array($field, ['id', 'uuid', 'created_at', 'updated_at'], true))
                    ->unique()->sort()->values()->all(),
            ],
            actor: $actor,
        );
    }
}
