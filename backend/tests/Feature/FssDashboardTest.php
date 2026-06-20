<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
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

    // ── pos_awaiting_receipt ──────────────────────────────────────────────────

    public function test_ordered_po_without_attachment_increments_pos_awaiting_receipt(): void
    {
        PurchaseOrder::factory()->create(['status' => 'ordered']);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(1, $data['pos_awaiting_receipt']);
    }

    public function test_ordered_po_with_receipt_attachment_is_not_counted(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'ordered']);
        PurchaseOrderAttachment::create([
            'purchase_order_id' => $po->id,
            'type'              => 'receipt',
            'path'              => 'po-attachments/test.jpg',
            'caption'           => null,
        ]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $data['pos_awaiting_receipt']);
    }

    public function test_ordered_po_with_proof_attachment_is_not_counted(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'ordered']);
        PurchaseOrderAttachment::create([
            'purchase_order_id' => $po->id,
            'type'              => 'proof',
            'path'              => 'po-attachments/proof.jpg',
            'caption'           => null,
        ]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $data['pos_awaiting_receipt']);
    }

    public function test_draft_and_received_pos_are_excluded_from_awaiting_receipt(): void
    {
        PurchaseOrder::factory()->create(['status' => 'draft']);
        PurchaseOrder::factory()->create(['status' => 'received']);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $data['pos_awaiting_receipt']);
    }

    // ── inventory_no_stock ────────────────────────────────────────────────────

    public function test_inventory_row_at_zero_increments_no_stock(): void
    {
        Inventory::factory()->create(['quantity_in_stock' => 0]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(1, $data['inventory_no_stock']);
    }

    public function test_inventory_row_below_zero_increments_no_stock(): void
    {
        Inventory::factory()->create(['quantity_in_stock' => -5]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(1, $data['inventory_no_stock']);
    }

    public function test_inventory_row_with_stock_is_not_counted(): void
    {
        Inventory::factory()->create(['quantity_in_stock' => 10]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $data['inventory_no_stock']);
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

    public function test_active_cycle_without_slot_today_returns_zero(): void
    {
        // Create a cycle with a slot for a day that is NOT today.
        $weekday      = Carbon::today()->format('l');
        $otherWeekday = $weekday === 'Monday' ? 'Tuesday' : 'Monday';

        $cycle = MenuCycle::factory()->create([
            'is_active' => true,
            'status'    => 'active',
        ]);

        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week'   => $otherWeekday,
            'meal_type'     => 'lunch',
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

    public function test_today_service_shows_slot_as_prepped_when_log_exists(): void
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

        $this->assertNotEmpty($data['today_service']);
        $this->assertTrue($data['today_service'][0]['prepped']);
    }

    // ── response shape ────────────────────────────────────────────────────────

    public function test_summary_returns_expected_keys(): void
    {
        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('meals_to_log_today', $data);
        $this->assertArrayHasKey('pos_awaiting_receipt', $data);
        $this->assertArrayHasKey('inventory_no_stock', $data);
        $this->assertArrayHasKey('today_service', $data);
    }

    // ── RND role can also access (FSS,RND group) ──────────────────────────────

    public function test_rnd_user_can_access_fss_dashboard_summary(): void
    {
        $rnd = User::factory()->rnd()->create();
        $this->actingAs($rnd)->getJson('/api/fss/dashboard/summary')->assertOk();
    }
}
