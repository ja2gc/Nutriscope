<?php

namespace App\Services\FSS;

use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderVendorGroup;
use App\Support\UnitConverter;
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

    public function receive(PurchaseOrder $purchaseOrder): void
    {
        $touched = [];

        foreach ($purchaseOrder->items as $item) {
            $this->receiveLine($item, $touched, $purchaseOrder->id);
        }

        $this->recalculateTouchedRecipes($touched);
    }

    public function receiveVendorGroup(PurchaseOrderVendorGroup $vendorGroup): void
    {
        $touched = [];

        foreach ($vendorGroup->items as $item) {
            $this->receiveLine($item, $touched, $vendorGroup->purchase_order_id);
        }

        $this->recalculateTouchedRecipes($touched);
    }

    private function receiveLine(PurchaseOrderItem $item, array &$touched, int $purchaseOrderId): void
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

        $inv = Inventory::firstOrNew(['fs_item_id' => $fs->id]);
        if (! $inv->exists) {
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

        $touched[$fs->id] = true;
    }

    private function recalculateTouchedRecipes(array $touched): void
    {
        if ($touched) {
            FoodServiceRecipe::recalculateForItems(array_keys($touched));
        }
    }
}
