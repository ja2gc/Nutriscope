<?php

namespace App\Services\FSS;

use App\Models\FsItem;

/**
 * Keeps each catalog item's suggested vendor (`fs_items.default_supplier_id`) in
 * sync with the latest procurement that actually received it — UNLESS the user has
 * manually locked the vendor, in which case it stays fixed until unlocked.
 *
 * Called from the receiving flow when a vendor group's receipts land, so the
 * "latest procurement" always means the most recently received PO line.
 */
class LatestProcurementVendorService
{
    /**
     * Point an item's default vendor at the supplier it was just received from,
     * unless the item's vendor suggestion is locked.
     *
     * @return bool true if the vendor was updated, false if locked/no-op.
     */
    public function syncFromReceipt(FsItem $item, ?int $supplierId): bool
    {
        if ($supplierId === null) {
            return false;
        }

        // Respect a manual lock — locked vendors never auto-update.
        if ($item->vendorLocked()) {
            return false;
        }

        if ((int) $item->default_supplier_id === (int) $supplierId) {
            return false;
        }

        $item->default_supplier_id = $supplierId;
        $item->save();

        return true;
    }

    /** Lock the vendor suggestion so it stops auto-updating. */
    public function lock(FsItem $item, ?int $userId): void
    {
        $item->forceFill([
            'default_supplier_locked_at' => now(),
            'default_supplier_locked_by' => $userId,
        ])->save();
    }

    /** Unlock the vendor suggestion so it resumes auto-updating. */
    public function unlock(FsItem $item): void
    {
        $item->forceFill([
            'default_supplier_locked_at' => null,
            'default_supplier_locked_by' => null,
        ])->save();
    }
}
