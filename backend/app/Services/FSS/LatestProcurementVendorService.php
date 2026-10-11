<?php

namespace App\Services\FSS;

use App\Models\FsItem;

/**
 * Keeps each catalog item's suggested vendor (`fs_items.default_supplier_id`) in
 * sync with the latest completed purchase order.
 *
 * Called only when a purchase order completes.
 */
class LatestProcurementVendorService
{
    /**
     * Point an item's default vendor at the supplier from the completed PO.
     *
     * @return bool true if the vendor was updated.
     */
    public function syncFromReceipt(FsItem $item, ?int $supplierId): bool
    {
        if ($supplierId === null) {
            return false;
        }

        if ((int) $item->default_supplier_id === (int) $supplierId) {
            return false;
        }

        $item->default_supplier_id = $supplierId;
        $item->save();

        return true;
    }
}
