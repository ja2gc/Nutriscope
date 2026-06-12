<?php

namespace App\Services\FSS;

use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Support\UnitConverter;
use Illuminate\Support\Facades\Log;

class ReceivingService
{
    /**
     * Normalize a PO line (qty + per-line-unit price) into base-unit terms.
     * Pure (no DB). Returns [qtyBase, perBaseCost]. Unknown/unconvertible units
     * degrade to "treat the line as base units" rather than throwing.
     *
     * @return array{0:float,1:float}
     */
    public static function normalizeLine(float $qty, string $lineUnit, float $lineUnitPrice, string $baseUnit): array
    {
        $from = UnitConverter::normalize($lineUnit);
        $to   = UnitConverter::normalize($baseUnit);

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

    /**
     * Receive a PO: for each catalog line, add base-unit qty to stock, store the
     * paid ₱/base-unit as last-cost, refresh the catalog purchase_price, then
     * recompute every recipe that uses a touched item. Caller wraps this in a
     * transaction. Free-text lines (no fs_item_id) are skipped + logged.
     */
    public function receive(PurchaseOrder $purchaseOrder): void
    {
        $touched = [];

        foreach ($purchaseOrder->items as $item) {
            if (! $item->fs_item_id) {
                Log::info('ReceivingService: skipped free-text PO line', [
                    'po' => $purchaseOrder->id, 'description' => $item->description,
                ]);
                continue;
            }

            $fs = FsItem::find($item->fs_item_id);
            if (! $fs) {
                Log::warning('ReceivingService: fs_item missing', ['fs_item_id' => $item->fs_item_id]);
                continue;
            }

            [$qtyBase, $perBaseCost] = self::normalizeLine(
                (float) $item->qty, (string) $item->unit, (float) $item->unit_price, (string) $fs->base_unit
            );

            $inv = Inventory::firstOrNew(['fs_item_id' => $fs->id]);
            if (! $inv->exists) {
                $inv->item_type = $fs->kind ?? 'ingredient';
                $inv->quantity_in_stock = 0;
            }
            $inv->unit = $fs->base_unit;
            $inv->quantity_in_stock = (float) $inv->quantity_in_stock + $qtyBase;
            $inv->unit_price = round($perBaseCost, 2); // ₱ per base unit (last cost)
            $inv->save();

            $basePerPurchase = $fs->basePerPurchase();
            if ($basePerPurchase > 0) {
                $fs->purchase_price = round($perBaseCost * $basePerPurchase, 2);
                $fs->save();
            }

            $touched[$fs->id] = true;
        }

        if ($touched) {
            FoodServiceRecipe::recalculateForItems(array_keys($touched));
        }
    }
}
