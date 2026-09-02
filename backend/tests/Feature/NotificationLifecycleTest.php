<?php

namespace Tests\Feature;

use App\Models\NcpRecord;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\User;
use App\Services\NotificationLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pruning_deletes_opened_announcements_after_three_days_only(): void
    {
        $this->freezeSecond();

        $user = User::factory()->create();
        $eligible = Notification::factory()->for($user)->create([
            'type' => 'announcement',
            'read' => true,
            'read_at' => now()->subDays(4),
            'opened_at' => now()->subDays(3)->subSecond(),
        ]);
        $boundary = Notification::factory()->for($user)->create([
            'type' => 'announcement',
            'read' => true,
            'read_at' => now()->subDays(3),
            'opened_at' => now()->subDays(3)->addSecond(),
        ]);
        $unopened = Notification::factory()->for($user)->create([
            'type' => 'announcement',
            'read' => true,
            'read_at' => now()->subDays(10),
            'opened_at' => null,
        ]);

        Artisan::call('model:prune', ['--model' => [Notification::class]]);

        $this->assertModelMissing($eligible);
        $this->assertModelExists($boundary);
        $this->assertModelExists($unopened);
    }

    public function test_pruning_keeps_action_notification_until_three_days_after_resolution(): void
    {
        $user = User::factory()->create();
        $eligible = Notification::factory()->for($user)->create([
            'type' => 'po_awaiting_receipt',
            'opened_at' => now()->subDays(20),
            'resolved_at' => now()->subDays(3)->subSecond(),
        ]);
        $unresolved = Notification::factory()->for($user)->create([
            'type' => 'po_awaiting_receipt',
            'opened_at' => now()->subDays(20),
            'resolved_at' => null,
        ]);

        Artisan::call('model:prune', ['--model' => [Notification::class]]);

        $this->assertModelMissing($eligible);
        $this->assertModelExists($unresolved);
    }

    public function test_purchase_order_notification_resolves_only_after_every_vendor_group_has_receipt(): void
    {
        $user = User::factory()->fss()->create();
        $po = PurchaseOrder::factory()->create(['status' => 'ordered']);
        $first = PurchaseOrderVendorGroup::create([
            'purchase_order_id' => $po->id,
            'status' => 'received',
            'total_amount' => 10,
        ]);
        $second = PurchaseOrderVendorGroup::create([
            'purchase_order_id' => $po->id,
            'status' => 'pending',
            'total_amount' => 10,
        ]);
        PurchaseOrderAttachment::create([
            'purchase_order_id' => $po->id,
            'vendor_group_id' => $first->id,
            'type' => 'receipt',
            'path' => 'po-attachments/first.jpg',
        ]);
        $notification = Notification::factory()->for($user)->create([
            'type' => 'po_awaiting_receipt',
            'source_module' => 'food_service',
            'source_id' => $po->id,
        ]);

        app(NotificationLifecycleService::class)->resolvePurchaseOrder($po);
        $this->assertNull($notification->fresh()->resolved_at);

        PurchaseOrderAttachment::create([
            'purchase_order_id' => $po->id,
            'vendor_group_id' => $second->id,
            'type' => 'receipt',
            'path' => 'po-attachments/second.jpg',
        ]);
        app(NotificationLifecycleService::class)->resolvePurchaseOrder($po);

        $this->assertNotNull($notification->fresh()->resolved_at);
    }

    public function test_follow_up_notification_resolves_for_monitoring_created_after_reminder(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');
        $rnd = User::factory()->rnd()->create();
        $ncp = NcpRecord::factory()->create(['rnd_user_id' => $rnd->id]);
        $old = Notification::factory()->for($rnd)->create([
            'type' => 'follow_up',
            'source_module' => 'ncp',
            'source_id' => $ncp->id,
            'created_at' => now()->subDay(),
        ]);
        $future = Notification::factory()->for($rnd)->create([
            'type' => 'follow_up',
            'source_module' => 'ncp',
            'source_id' => $ncp->id,
            'created_at' => now()->addHour(),
        ]);

        app(NotificationLifecycleService::class)->resolveFollowUp($ncp, now());

        $this->assertNotNull($old->fresh()->resolved_at);
        $this->assertNull($future->fresh()->resolved_at);
        Carbon::setTestNow();
    }
}
