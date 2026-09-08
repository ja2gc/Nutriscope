<?php

namespace App\Services;

use App\Models\NcpAppointment;
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

    public function resolveAppointment(NcpAppointment $appointment): int
    {
        return Notification::query()
            ->where('type', 'appointment_due')
            ->where('source_module', 'ncp_appointment')
            ->where('source_id', $appointment->id)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    public function reopenAppointment(NcpAppointment $appointment): int
    {
        return Notification::query()
            ->where('type', 'appointment_due')
            ->where('source_module', 'ncp_appointment')
            ->where('source_id', $appointment->id)
            ->update(['resolved_at' => null, 'dismissed_at' => null]);
    }
}
