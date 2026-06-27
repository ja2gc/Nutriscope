<?php

namespace Tests\Feature;

use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FssDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->fss()->create();
    }

    // ── auth ──────────────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/fss/dashboard/summary')->assertUnauthorized();
    }

    public function test_non_fss_role_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->getJson('/api/fss/dashboard/summary')->assertForbidden();
    }

    // ── pending_pos ───────────────────────────────────────────────────────────

    public function test_supplies_po_without_receipt_is_pending_on_receipts(): void
    {
        $po = PurchaseOrder::factory()->create([
            'status' => 'draft',
            'lifecycle_status' => 'open_execution',
            'procurement_track' => 'supplies',
        ]);

        PurchaseOrderVendorGroup::create([
            'purchase_order_id' => $po->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'status' => 'pending',
            'total_amount' => 500,
        ]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(1, $data['pending_pos_count']);
        $this->assertContains('receipts', $data['pending_pos'][0]['waiting_on']);
    }

    public function test_supplies_po_with_receipt_is_not_pending(): void
    {
        $po = PurchaseOrder::factory()->create([
            'status' => 'draft',
            'lifecycle_status' => 'open_execution',
            'procurement_track' => 'supplies',
        ]);

        $group = PurchaseOrderVendorGroup::create([
            'purchase_order_id' => $po->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'status' => 'received',
            'total_amount' => 500,
        ]);

        PurchaseOrderAttachment::create([
            'purchase_order_id' => $po->id,
            'vendor_group_id' => $group->id,
            'type' => 'receipt',
            'path' => 'po-attachments/vendor-receipt.jpg',
            'caption' => null,
        ]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $data['pending_pos_count']);
    }

    public function test_completed_po_is_not_pending(): void
    {
        PurchaseOrder::factory()->create([
            'status' => 'received',
            'lifecycle_status' => 'completed',
            'procurement_track' => 'supplies',
        ]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $data['pending_pos_count']);
    }

    // ── meals_to_log_today ────────────────────────────────────────────────────

    public function test_no_active_cycle_returns_zero_meals_to_log(): void
    {
        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $data['meals_to_log_today']);
        $this->assertEmpty($data['today_service']);
    }

    public function test_active_cycle_with_slot_today_and_no_log_returns_one(): void
    {
        $weekday = Carbon::today()->format('l');

        $cycle = MenuCycle::factory()->create([
            'is_active' => true,
            'status'    => 'active',
        ]);

        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week'   => $weekday,
            'meal_type'     => 'lunch',
        ]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(1, $data['meals_to_log_today']);
    }

    public function test_service_day_already_completed_returns_zero_meals_to_log(): void
    {
        $weekday = Carbon::today()->format('l');
        $today   = now()->toDateString();

        $cycle = MenuCycle::factory()->create([
            'is_active' => true,
            'status'    => 'active',
        ]);

        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week'   => $weekday,
            'meal_type'     => 'lunch',
        ]);

        MealPrepLog::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'service_date'  => $today,
            'status'        => 'completed',
        ]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $data['meals_to_log_today']);
    }

    // ── today_service ─────────────────────────────────────────────────────────

    public function test_today_service_shows_slot_as_not_prepped_when_no_log(): void
    {
        $weekday = Carbon::today()->format('l');

        $cycle = MenuCycle::factory()->create([
            'is_active' => true,
            'status'    => 'active',
        ]);

        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week'   => $weekday,
            'meal_type'     => 'breakfast',
        ]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($data['today_service']);
        $this->assertFalse($data['today_service'][0]['prepped']);
    }

    // ── response shape ────────────────────────────────────────────────────────

    public function test_summary_returns_expected_keys_and_no_stock_key(): void
    {
        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('meals_to_log_today', $data);
        $this->assertArrayHasKey('pending_pos', $data);
        $this->assertArrayHasKey('pending_pos_count', $data);
        $this->assertArrayHasKey('today_service', $data);
        // Stock-related KPIs are removed.
        $this->assertArrayNotHasKey('inventory_no_stock', $data);
        $this->assertArrayNotHasKey('pos_awaiting_receipt', $data);
    }

    public function test_rnd_user_can_access_fss_dashboard_summary(): void
    {
        $rnd = User::factory()->rnd()->create();
        $this->actingAs($rnd)->getJson('/api/fss/dashboard/summary')->assertOk();
    }
}
