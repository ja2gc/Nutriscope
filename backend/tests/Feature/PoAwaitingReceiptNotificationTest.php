<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PoAwaitingReceiptNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;
    private User $fss1;
    private User $fss2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rnd = User::factory()->create([
            'role'     => 'RND',
            'password' => Hash::make('password'),
        ]);

        $this->fss1 = User::factory()->create([
            'role'     => 'FSS',
            'password' => Hash::make('password'),
        ]);

        $this->fss2 = User::factory()->create([
            'role'     => 'FSS',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_creating_po_with_ordered_status_notifies_all_fss_users(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/purchase-orders', [
                'supplier_id' => $supplier->id,
                'order_date'  => '2026-06-20',
                'status'      => 'ordered',
                'items'       => [
                    ['qty' => 10, 'unit_price' => 5.00, 'description' => 'Rice'],
                ],
            ]);

        $response->assertCreated();
        $poId = $response->json('data.id');

        $notifications = Notification::where('type', 'po_awaiting_receipt')
            ->where('source_module', 'food_service')
            ->where('source_id', $poId)
            ->get();

        $this->assertCount(2, $notifications, 'Expected exactly one notification per FSS user.');

        $notifiedUserIds = $notifications->pluck('user_id')->sort()->values()->all();
        $expectedUserIds = collect([$this->fss1->id, $this->fss2->id])->sort()->values()->all();
        $this->assertEquals($expectedUserIds, $notifiedUserIds);
    }

    public function test_creating_po_as_draft_sends_no_notifications(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/purchase-orders', [
                'supplier_id' => $supplier->id,
                'order_date'  => '2026-06-20',
                'items'       => [
                    ['qty' => 5, 'unit_price' => 3.00, 'description' => 'Eggs'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_updating_po_to_ordered_notifies_fss_users(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft', 'rnd_user_id' => $this->rnd->id]);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->id}", [
                'status' => 'ordered',
            ]);

        $response->assertOk();

        $notifications = Notification::where('type', 'po_awaiting_receipt')
            ->where('source_id', $po->id)
            ->get();

        $this->assertCount(2, $notifications, 'Expected one notification per FSS user on transition to ordered.');
    }

    public function test_updating_already_ordered_po_does_not_send_duplicate_notifications(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'ordered', 'rnd_user_id' => $this->rnd->id]);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->id}", [
                'status'  => 'ordered',
                'or_number' => 'OR-001',
            ]);

        $response->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }
}
