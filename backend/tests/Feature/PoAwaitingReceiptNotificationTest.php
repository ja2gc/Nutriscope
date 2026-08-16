<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
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
            'role' => 'RND',
            'password' => Hash::make('password'),
        ]);

        $this->fss1 = User::factory()->create([
            'role' => 'FSS',
            'password' => Hash::make('password'),
        ]);

        $this->fss2 = User::factory()->create([
            'role' => 'FSS',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_approving_list_creates_draft_pos_with_no_notifications(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);
        // Purchase orders are now born from approving a shopping list, as drafts —
        // FSS is only notified once RND marks a vendor order "ordered" (tested below).
        $supplier = Supplier::factory()->create();
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'L', 'list_date' => '2026-06-20',
            'list_type' => 'manual', 'status' => 'draft',
        ]);
        $list->items()->create([
            'ingredient_name' => 'Eggs', 'qty' => 5, 'unit' => 'tray', 'supplier_id' => $supplier->id,
            'unit_price' => 3.00, 'total' => 15,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
            ->assertCreated();

        $this->assertDatabaseHas('purchase_orders', ['shopping_list_id' => $list->id, 'status' => 'draft']);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_updating_po_to_ordered_notifies_fss_users(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft', 'rnd_user_id' => $this->rnd->id]);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", [
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
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", [
                'status' => 'ordered',
                'or_number' => 'OR-001',
            ]);

        $response->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }
}
