<?php

namespace App\Services;

use App\Models\NcpRecord;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use Illuminate\Support\Carbon;

class NotificationLifecycleService
{
    public function resolvePurchaseOrder(PurchaseOrder $purchaseOrder): int
    {
        $purchaseOrder->load('vendorGroups.attachments');
        $groups = $purchaseOrder->vendorGroups;
        $hasEveryReceipt = $groups->isNotEmpty()
            && $groups->every(fn ($group): bool => $group->attachments->contains('type', 'receipt'));

        if (! $hasEveryReceipt) {
            return 0;
        }

        return Notification::query()
            ->where('type', 'po_awaiting_receipt')
            ->where('source_module', 'food_service')
            ->where('source_id', $purchaseOrder->id)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    public function resolveFollowUp(NcpRecord $ncpRecord, Carbon $completedAt): int
    {
        return Notification::query()
            ->where('type', 'follow_up')
            ->where('source_module', 'ncp')
            ->where('source_id', $ncpRecord->id)
            ->where('created_at', '<=', $completedAt)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => $completedAt]);
    }
}
