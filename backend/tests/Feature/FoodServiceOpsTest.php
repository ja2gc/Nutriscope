<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Events\PurchaseOrderCompleted;
use App\Events\PurchaseOrderConverted;
use App\Models\BudgetLedger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FoodServiceOpsTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;
    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create([
            'role'     => 'FSS',
            'password' => Hash::make('password'),
        ]);
        $this->rnd = User::factory()->create([
            'role'     => 'RND',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeFsItem(array $attrs = []): FsItem
    {
        return FsItem::factory()->create($attrs);
    }

    // ===== INVENTORY =====

    public function test_fss_can_list_inventory(): void
    {
        $fsItem = $this->makeFsItem();
        Inventory::factory()->create(['fs_item_id' => $fsItem->id]);

        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/inventory');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'fs_item_id', 'quantity_in_stock', 'unit']]]);
    }

    public function test_fss_can_update_inventory(): void
    {
        $fsItem    = $this->makeFsItem();
        $inventory = Inventory::factory()->create(['fs_item_id' => $fsItem->id, 'quantity_in_stock' => 50]);

        $response = $this->actingAs($this->fss)
            ->patchJson("/api/fss/inventory/{$inventory->id}", [
                'quantity_in_stock' => 80,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.quantity_in_stock', '80.00');

        $this->assertDatabaseHas('inventory', ['id' => $inventory->id, 'quantity_in_stock' => 80]);
    }

    public function test_fss_can_restock_inventory(): void
    {
        $fsItem    = $this->makeFsItem();
        $inventory = Inventory::factory()->create(['fs_item_id' => $fsItem->id, 'quantity_in_stock' => 20]);

        $response = $this->actingAs($this->fss)
            ->postJson("/api/fss/inventory/{$inventory->id}/restock", [
                'quantity' => 30,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.quantity_in_stock', '50.00');
    }

    public function test_restock_requires_positive_quantity(): void
    {
        $fsItem    = $this->makeFsItem();
        $inventory = Inventory::factory()->create(['fs_item_id' => $fsItem->id]);

        $response = $this->actingAs($this->fss)
            ->postJson("/api/fss/inventory/{$inventory->id}/restock", [
                'quantity' => -5,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_rnd_can_access_fss_inventory_routes(): void
    {
        $fsItem    = $this->makeFsItem();
        Inventory::factory()->create(['fs_item_id' => $fsItem->id]);

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/fss/inventory');

        $response->assertOk();
    }

    // ===== SUPPLIERS (RND-only — FSS has no supplier scope per §6) =====

    public function test_fss_cannot_create_supplier(): void
    {
        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/suppliers', [
                'name'    => 'Green Valley Farm',
                'contact' => '0912-345-6789',
                'address' => 'Quezon City, Philippines',
            ]);

        $response->assertForbidden();
    }

    public function test_rnd_can_create_supplier(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/suppliers', [
                'name'    => 'Green Valley Farm',
                'contact' => '0912-345-6789',
                'address' => 'Quezon City, Philippines',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Green Valley Farm');

        $this->assertDatabaseHas('suppliers', ['name' => 'Green Valley Farm']);
    }

    public function test_fss_cannot_list_suppliers(): void
    {
        Supplier::factory(3)->create();

        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/suppliers');

        $response->assertForbidden();
    }

    public function test_rnd_can_list_suppliers(): void
    {
        Supplier::factory(3)->create();

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/fss/suppliers');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_supplier_creation_requires_name(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/suppliers', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ===== PURCHASE ORDERS =====

    public function test_fss_cannot_approve_shopping_list(): void
    {
        $supplier = Supplier::factory()->create();
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'L', 'list_date' => '2026-06-10',
            'list_type' => 'manual', 'status' => 'draft',
        ]);
        $list->items()->create([
            'ingredient_name' => 'Bleach', 'qty' => 5, 'unit' => 'L', 'supplier_id' => $supplier->id,
            'unit_price' => 50, 'total' => 250,
        ]);

        $this->actingAs($this->fss)
            ->postJson("/api/fss/shopping-lists/{$list->id}/approve")
            ->assertForbidden();
    }

    public function test_rnd_approving_list_creates_per_vendor_purchase_orders(): void
    {
        Event::fake([PurchaseOrderConverted::class]);

        $supplier = Supplier::factory()->create();
        $supplierTwo = Supplier::factory()->create();
        $fsItem = $this->makeFsItem();
        $supply = $this->makeFsItem(['kind' => 'supply', 'name' => 'Gloves', 'base_unit' => 'box', 'purchase_unit' => 'box']);
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'L', 'list_date' => '2026-06-10',
            'list_type' => 'manual', 'status' => 'draft',
        ]);
        $list->items()->create([
            'fs_item_id' => $fsItem->id, 'ingredient_name' => $fsItem->name, 'qty' => 50, 'unit' => 'kg',
            'supplier_id' => $supplier->id, 'unit_price' => 25.00, 'total' => 1250,
        ]);
        $list->items()->create([
            'fs_item_id' => $supply->id, 'ingredient_name' => $supply->name, 'qty' => 3, 'unit' => 'box',
            'supplier_id' => $supplierTwo->id, 'unit_price' => 100.00, 'total' => 300,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->id}/approve")
            ->assertCreated();

        $this->assertDatabaseHas('purchase_orders', ['shopping_list_id' => $list->id, 'lifecycle_status' => 'open_execution']);
        $this->assertSame(1, PurchaseOrder::where('shopping_list_id', $list->id)->count());
        $this->assertDatabaseCount('purchase_order_vendor_groups', 2);
        $this->assertDatabaseHas('purchase_order_items', ['fs_item_id' => $fsItem->id]);
        $this->assertDatabaseHas('purchase_order_items', ['fs_item_id' => $supply->id]);
        $this->assertDatabaseHas('program_project_activities', [
            'activity' => 'Food Subsistence for Patients',
            'estimated_total_cost' => 1550,
        ]);
        $this->assertDatabaseHas('shopping_lists', ['id' => $list->id, 'status' => 'converted']);
        Event::assertDispatched(PurchaseOrderConverted::class);

        // One-shot: re-approving is rejected.
        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->id}/approve")
            ->assertStatus(422);
    }

    public function test_rnd_and_fss_can_update_vendor_group_operational_fields(): void
    {
        $supplier = Supplier::factory()->create();
        $fsItem = $this->makeFsItem();
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Vendor ops', 'list_date' => '2026-06-10',
            'list_type' => 'manual', 'status' => 'draft',
        ]);
        $list->items()->create([
            'fs_item_id' => $fsItem->id, 'ingredient_name' => $fsItem->name, 'qty' => 5, 'unit' => 'kg',
            'supplier_id' => $supplier->id, 'unit_price' => 20, 'total' => 100,
        ]);

        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->id}/approve")->assertCreated();
        $group = \App\Models\PurchaseOrderVendorGroup::firstOrFail();
        $line = $group->items()->firstOrFail();

        $this->actingAs($this->fss)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->id}", [
                'or_number' => 'OR-FSS-1',
                'items' => [[
                    'id' => $line->id,
                    'purchase_qty' => 6,
                    'purchase_unit' => 'kg',
                    'purchase_price' => 22,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.vendor_groups.0.or_number', 'OR-FSS-1');

        $this->assertDatabaseHas('purchase_order_vendor_groups', ['id' => $group->id, 'or_number' => 'OR-FSS-1', 'total_amount' => 132]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $group->purchase_order_id, 'total_amount' => 132]);
    }

    public function test_po_completes_when_all_vendor_receipts_and_served_population_exist(): void
    {
        Event::fake([PurchaseOrderCompleted::class]);
        Storage::fake('public');

        $supplier = Supplier::factory()->create();
        $fsItem = $this->makeFsItem(['base_unit' => 'kg', 'purchase_unit' => 'kg', 'purchase_price' => 25]);
        Inventory::factory()->create(['fs_item_id' => $fsItem->id, 'quantity_in_stock' => 0, 'unit' => 'kg']);
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-08',
            'cycle_days' => 7,
            'is_active' => true,
            'status' => 'active',
        ]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Wednesday',
            'fs_item_id' => $fsItem->id,
            'quantity' => 1,
            'estimate_population' => 10,
        ]);
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Span',
            'list_date' => '2026-06-10',
            'period_start' => '2026-06-10',
            'period_end' => '2026-06-10',
            'days_span' => 1,
            'list_type' => 'manual',
            'status' => 'draft',
            'estimate_population' => 10,
        ]);
        $list->items()->create([
            'fs_item_id' => $fsItem->id, 'ingredient_name' => $fsItem->name, 'qty' => 5, 'unit' => 'kg',
            'supplier_id' => $supplier->id, 'unit_price' => 20, 'total' => 100,
        ]);
        // Fiscal year 2026 must exist so the lifecycle guard allows completion.
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->id}/approve")->assertCreated();
        $po = PurchaseOrder::where('shopping_list_id', $list->id)->firstOrFail();
        $group = $po->vendorGroups()->firstOrFail();

        MealPrepLog::create([
            'menu_cycle_id' => $cycle->id,
            'service_date' => '2026-06-10',
            'population' => 10,
            'served_population' => 8,
            'status' => 'completed',
            'completed_by' => $this->fss->id,
            'completed_at' => now(),
            'total_value' => 50,
            'has_shortfall' => false,
        ]);

        $this->actingAs($this->fss)
            ->post("/api/fss/purchase-order-vendor-groups/{$group->id}/attachments", [
                'type' => 'receipt',
                'file' => UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'lifecycle_status' => 'completed',
            'status' => 'received',
            'actual_budget_per_head_per_day' => 12.50,
        ]);
        $this->assertDatabaseHas('program_project_activities', [
            'purchase_order_id' => $po->id,
            'actual_total_cost' => 100,
            'actual_output_patients' => 8,
        ]);
        Event::assertDispatched(PurchaseOrderCompleted::class);

        $this->actingAs($this->fss)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->id}", ['or_number' => 'LOCKED'])
            ->assertStatus(422);
    }

    public function test_ppa_is_rnd_only_and_fss_cannot_delete_po(): void
    {
        $fsItem = $this->makeFsItem();
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'PPA', 'list_date' => '2026-06-10',
            'list_type' => 'manual', 'status' => 'draft',
        ]);
        $list->items()->create([
            'fs_item_id' => $fsItem->id, 'ingredient_name' => $fsItem->name, 'qty' => 2, 'unit' => 'kg',
            'unit_price' => 50, 'total' => 100,
        ]);
        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->id}/approve")->assertCreated();
        $po = PurchaseOrder::where('shopping_list_id', $list->id)->firstOrFail();

        $this->actingAs($this->rnd)->getJson("/api/fss/purchase-orders/{$po->id}/ppa")
            ->assertOk()
            ->assertJsonPath('data.activity', 'Food Subsistence for Patients');

        $this->actingAs($this->fss)->getJson("/api/fss/purchase-orders/{$po->id}/ppa")
            ->assertForbidden();

        $this->actingAs($this->fss)->deleteJson("/api/fss/purchase-orders/{$po->id}")
            ->assertForbidden();
    }

    public function test_purchase_order_index_exposes_event_vendor_groups(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Vendor A']);
        $fsItem = $this->makeFsItem();
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Event', 'list_date' => '2026-06-10',
            'period_start' => '2026-06-10', 'period_end' => '2026-06-12',
            'days_span' => 3, 'list_type' => 'manual', 'status' => 'draft',
        ]);
        $list->items()->create([
            'fs_item_id' => $fsItem->id, 'ingredient_name' => $fsItem->name, 'qty' => 2, 'unit' => 'kg',
            'supplier_id' => $supplier->id, 'unit_price' => 50, 'total' => 100,
        ]);
        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->id}/approve")->assertCreated();

        $this->actingAs($this->fss)
            ->getJson('/api/fss/purchase-orders')
            ->assertOk()
            ->assertJsonPath('data.0.shopping_list_id', $list->id)
            ->assertJsonPath('data.0.vendor_groups.0.supplier.name', 'Vendor A')
            ->assertJsonPath('data.0.lifecycle_status', 'open_execution');
    }

    public function test_menu_cycle_index_is_active_first_with_per_day_plan_flags(): void
    {
        $past = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Past',
            'week_start_date' => '2026-06-01',
            'is_active' => false,
            'status' => 'draft',
        ]);
        $active = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Active',
            'week_start_date' => '2026-06-08',
            'is_active' => true,
            'status' => 'active',
        ]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $active->id,
            'day_of_week' => 'Monday',
            'fs_item_id' => $this->makeFsItem()->id,
        ]);

        $this->actingAs($this->fss)
            ->getJson('/api/fss/menu-cycles')
            ->assertOk()
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.plan_days.Monday', true)
            ->assertJsonPath('data.0.plan_days.Tuesday', false)
            ->assertJsonPath('data.1.id', $past->id);
    }

    public function test_fss_can_read_purchase_order(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft']);

        $this->actingAs($this->fss)
            ->getJson("/api/fss/purchase-orders/{$po->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_fss_cannot_update_purchase_order_status(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft']);

        $this->actingAs($this->fss)
            ->patchJson("/api/fss/purchase-orders/{$po->id}", ['status' => 'received'])
            ->assertForbidden();
    }

    public function test_rnd_can_update_purchase_order_status(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->id}", [
                'status' => 'received',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'received');
    }

    public function test_rnd_po_status_received_updates_inventory(): void
    {
        $fsItem = $this->makeFsItem([
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'purchase_price' => 20.00,
        ]);
        $inventory = Inventory::factory()->create([
            'fs_item_id' => $fsItem->id,
            'quantity_in_stock' => 10,
            'unit' => 'kg',
        ]);

        $po = PurchaseOrder::factory()->create([
            'status' => 'ordered',
        ]);

        // Add item to purchase order
        \App\Models\PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'fs_item_id' => $fsItem->id,
            'description' => $fsItem->name,
            'qty' => 5,
            'unit' => 'kg',
            'unit_price' => 20.00,
            'total_value' => 100.00,
        ]);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->id}", [
                'status' => 'received',
            ]);

        $response->assertOk();

        $inventory->refresh();
        $this->assertEquals(15.00, $inventory->quantity_in_stock);
    }

    public function test_approving_empty_shopping_list_is_rejected(): void
    {
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Empty', 'list_date' => '2026-06-10',
            'list_type' => 'manual', 'status' => 'draft',
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->id}/approve")
            ->assertStatus(422);
    }

    // ===== SHOPPING LISTS =====

    public function test_fss_can_generate_shopping_list(): void
    {
        $cycle = MenuCycle::factory()->create([
            'is_active' => true,
            'status' => 'active',
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15', // Monday — covers the span below
        ]);
        $fsItem = $this->makeFsItem();
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $fsItem->id,
            'quantity' => 1,
            'estimate_population' => 10,
        ]);
        Inventory::factory()->create([
            'fs_item_id'             => $fsItem->id,
            'quantity_in_stock'        => 5,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/shopping-lists/generate', [
                'start_date'    => '2026-06-15', // Monday
                'end_date'      => '2026-06-15', // the planned Monday → fully covered
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'items', 'coverage_status', 'uncovered_dates']]);

        $this->assertDatabaseHas('shopping_lists', ['status' => 'draft', 'coverage_status' => 'full']);
    }

    public function test_generate_shopping_list_sums_a_tuesday_to_thursday_span(): void
    {
        $fsItem = $this->makeFsItem([
            'name' => 'Banana',
            'base_unit' => 'piece',
            'purchase_unit' => 'piece',
            'purchase_price' => 5,
            'units_per_purchase' => 1,
        ]);
        Inventory::factory()->create(['fs_item_id' => $fsItem->id, 'quantity_in_stock' => 0, 'unit' => 'piece']);
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15', // Monday
        ]);
        foreach (['Tuesday', 'Wednesday', 'Thursday'] as $day) {
            MenuCycleDay::create([
                'menu_cycle_id' => $cycle->id,
                'day_of_week' => $day,
                'meal_type' => 'am_snack',
                'fs_item_id' => $fsItem->id,
                'quantity' => 1,
                'estimate_population' => 10,
            ]);
        }

        $response = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-16', // Tuesday
            'end_date'   => '2026-06-18', // Thursday
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.period_start', '2026-06-16')
            ->assertJsonPath('data.period_end', '2026-06-18')
            ->assertJsonPath('data.days_span', 3)
            ->assertJsonPath('data.coverage_status', 'full');

        $item = collect($response->json('data.items'))->firstWhere('fs_item_id', $fsItem->id);
        $this->assertEqualsWithDelta(30, (float) $item['qty'], 0.01);
    }

    public function test_generate_shopping_list_rejects_end_date_before_start_date(): void
    {
        $response = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-18',
            'end_date'   => '2026-06-16',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['end_date']);
    }

    public function test_generate_friday_to_monday_span_pulls_each_day_from_its_own_cycle(): void
    {
        $itemFri = $this->makeFsItem(['name' => 'FriItem', 'base_unit' => 'piece', 'purchase_unit' => 'piece', 'purchase_price' => 1, 'units_per_purchase' => 1]);
        $itemMon = $this->makeFsItem(['name' => 'MonItem', 'base_unit' => 'piece', 'purchase_unit' => 'piece', 'purchase_price' => 1, 'units_per_purchase' => 1]);
        Inventory::factory()->create(['fs_item_id' => $itemFri->id, 'quantity_in_stock' => 0, 'unit' => 'piece']);
        Inventory::factory()->create(['fs_item_id' => $itemMon->id, 'quantity_in_stock' => 0, 'unit' => 'piece']);

        // Week N cycle serves Friday; week N+1 cycle serves Monday.
        $weekN = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        MenuCycleDay::create(['menu_cycle_id' => $weekN->id, 'day_of_week' => 'Friday', 'meal_type' => 'lunch', 'fs_item_id' => $itemFri->id, 'quantity' => 1, 'estimate_population' => 10]);
        $weekNext = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-22']);
        MenuCycleDay::create(['menu_cycle_id' => $weekNext->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch', 'fs_item_id' => $itemMon->id, 'quantity' => 1, 'estimate_population' => 10]);

        // Fri 19 → Mon 22: Fri from week N, Mon from week N+1, Sat/Sun unplanned.
        $response = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-19',
            'end_date'   => '2026-06-22',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.coverage_status', 'partial');

        $items = collect($response->json('data.items'));
        $this->assertEqualsWithDelta(10, (float) $items->firstWhere('fs_item_id', $itemFri->id)['qty'], 0.01);
        $this->assertEqualsWithDelta(10, (float) $items->firstWhere('fs_item_id', $itemMon->id)['qty'], 0.01);
        $this->assertEqualsCanonicalizing(['2026-06-20', '2026-06-21'], $response->json('data.uncovered_dates'));
    }

    public function test_generate_hard_blocks_when_entire_span_uncovered(): void
    {
        $response = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2030-01-01',
            'end_date'   => '2030-01-03',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('uncovered_dates', ['2030-01-01', '2030-01-02', '2030-01-03']);
    }

    public function test_generate_blocks_planned_days_missing_estimate_population(): void
    {
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
        ]);
        $fsItem = $this->makeFsItem();
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $fsItem->id,
            'quantity' => 1,
            'estimate_population' => null,
        ]);

        $response = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('missing_population_dates', ['2026-06-15']);

        $this->assertDatabaseMissing('shopping_lists', [
            'period_start' => '2026-06-15',
            'period_end' => '2026-06-15',
        ]);
    }

    public function test_shopping_list_population_cascades_to_covered_menu_days_and_recalculates_items(): void
    {
        Carbon::setTestNow('2026-06-25 08:00:00');
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
        ]);
        $fsItem = $this->makeFsItem([
            'name' => 'Banana',
            'base_unit' => 'piece',
            'purchase_unit' => 'piece',
            'purchase_price' => 5,
            'units_per_purchase' => 1,
        ]);
        Inventory::factory()->create(['fs_item_id' => $fsItem->id, 'quantity_in_stock' => 0, 'unit' => 'piece']);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'am_snack',
            'fs_item_id' => $fsItem->id,
            'quantity' => 1,
            'estimate_population' => 10,
        ]);

        $list = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
        ])->assertCreated()->json('data');

        Carbon::setTestNow('2026-06-25 09:00:00');
        $response = $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-lists/{$list['id']}", [
            'estimate_population' => 25,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.estimate_population', 25);

        $this->assertDatabaseHas('menu_cycle_days', [
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'estimate_population' => 25,
        ]);
        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $list['id'],
            'fs_item_id' => $fsItem->id,
            'qty' => 25,
            'total' => 125,
        ]);
    }

    public function test_day_population_update_overrides_covering_draft_shopping_list_quantities(): void
    {
        Carbon::setTestNow('2026-06-25 08:00:00');
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
        ]);
        $fsItem = $this->makeFsItem([
            'name' => 'Banana',
            'base_unit' => 'piece',
            'purchase_unit' => 'piece',
            'purchase_price' => 5,
            'units_per_purchase' => 1,
        ]);
        Inventory::factory()->create(['fs_item_id' => $fsItem->id, 'quantity_in_stock' => 0, 'unit' => 'piece']);
        foreach (['Monday', 'Tuesday'] as $day) {
            MenuCycleDay::create([
                'menu_cycle_id' => $cycle->id,
                'day_of_week' => $day,
                'meal_type' => 'am_snack',
                'fs_item_id' => $fsItem->id,
                'quantity' => 1,
                'estimate_population' => 10,
            ]);
        }

        $list = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-16',
        ])->assertCreated()->json('data');

        Carbon::setTestNow('2026-06-25 10:00:00');
        $this->actingAs($this->rnd)->patchJson("/api/fss/menu-cycles/{$cycle->id}", [
            'name' => $cycle->name,
            'cycle_days' => 7,
            'week_start_date' => '2026-06-15',
            'days' => [
                [
                    'day_of_week' => 'Monday',
                    'meal_type' => 'am_snack',
                    'fs_item_id' => $fsItem->id,
                    'quantity' => 1,
                    'estimate_population' => 30,
                ],
                [
                    'day_of_week' => 'Tuesday',
                    'meal_type' => 'am_snack',
                    'fs_item_id' => $fsItem->id,
                    'quantity' => 1,
                    'estimate_population' => 10,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $list['id'],
            'fs_item_id' => $fsItem->id,
            'qty' => 40,
            'total' => 200,
        ]);
    }

    public function test_fss_can_list_shopping_lists(): void
    {
        ShoppingList::factory(2)->create(['rnd_user_id' => $this->fss->id]);

        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/shopping-lists');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_fss_shopping_list_suggested_includes_menu_cycle_ingredients(): void
    {
        // 1. Create two fs items and inventories
        $fsItem1 = $this->makeFsItem([
            'name' => 'Food Item A',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'purchase_price' => 10.00
        ]);
        Inventory::factory()->create([
            'fs_item_id'             => $fsItem1->id,
            'quantity_in_stock'        => 10,
            'unit'                     => 'kg',
        ]);

        $fsItem2 = $this->makeFsItem([
            'name' => 'Food Item B',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'purchase_price' => 10.00
        ]);
        Inventory::factory()->create([
            'fs_item_id'             => $fsItem2->id,
            'quantity_in_stock'        => 8,
            'unit'                     => 'kg',
        ]);

        // 2. Create active menu cycle anchored to the week of the generated span
        $cycle = MenuCycle::factory()->create([
            'is_active'   => true,
            'status'      => 'active',
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15', // Monday — covers 2026-06-15
        ]);

        // 3. Create a recipe using fsItem1
        $recipe = FoodServiceRecipe::create([
            'name'        => 'Test Recipe',
            'rnd_user_id' => $this->rnd->id,
            'servings'    => 1,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id'             => $fsItem1->id,
            'quantity'               => 3.00,
            'unit'                   => 'kg',
        ]);

        // 4. Link recipe and fsItem2 to MenuCycle via MenuCycleDay
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week'   => 'Monday',
            'meal_type'     => 'breakfast',
            'recipe_id'     => $recipe->id,
            'quantity'      => 1.00, // 1 servings. Total fsItem1 needed = 3 * 2 (population) = 6
            'estimate_population' => 2,
        ]);

        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week'   => 'Monday',
            'meal_type'     => 'lunch',
            'fs_item_id'    => $fsItem2->id,
            'quantity'      => 5.00, // Direct food item. Total fsItem2 needed = 5
            'estimate_population' => 2,
        ]);

        // 5. Generate shopping list suggestion for a single Monday.
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/shopping-lists/generate', [
                'start_date'    => '2026-06-15', // Monday
                'end_date'      => '2026-06-15', // same Monday
            ]);

        $response->assertCreated();

        // 6. NET-of-stock buy quantities (Spec 6 #2): planned − on-hand − open orders.
        // fsItem1: planned 6 kg (recipe 3 kg/serving × pop 2 ÷ servings 1), on-hand 10 → fully covered, NOT on the list.
        // fsItem2: planned 10 kg (ready item 5/head × pop 2),               on-hand 8  → buy 2.
        $this->assertDatabaseMissing('shopping_list_items', ['fs_item_id' => $fsItem1->id]);

        $this->assertDatabaseHas('shopping_list_items', [
            'fs_item_id' => $fsItem2->id,
            'qty'        => 2.00,
        ]);
    }

    // ===== MENU CYCLES =====

    public function test_fss_can_create_menu_cycle(): void
    {
        $response = $this->actingAs($this->rnd) // MenuCycles are created by RND, not FSS
            ->postJson('/api/fss/menu-cycles', [
                'name'       => 'Week 1 Cycle',
                'cycle_days' => 7,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Week 1 Cycle');

        $this->assertDatabaseHas('menu_cycles', ['name' => 'Week 1 Cycle']);
    }

    public function test_fss_can_activate_menu_cycle(): void
    {
        $cycle = MenuCycle::factory()->create([
            'is_active' => false,
            'status' => 'draft',
            'activation_date' => null,
            'rnd_user_id' => $this->rnd->id
        ]);

        $response = $this->actingAs($this->rnd) // Activations can be done by RND
            ->patchJson("/api/fss/menu-cycles/{$cycle->id}/activate");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.status', 'active');

        $cycle->refresh();
        $this->assertTrue($cycle->is_active);
        $this->assertEquals('active', $cycle->status);
        $this->assertEquals(now()->toDateString(), $cycle->activation_date?->toDateString());
    }

    public function test_menu_cycle_compute_uses_fiscal_year_per_head_limit(): void
    {
        $fs = FsItem::factory()->create([
            'base_unit' => 'g',
            'purchase_unit' => 'kg',
            'purchase_price' => 100,
            'units_per_purchase' => null,
        ]);
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $fs->id,
            'quantity' => 100,
            'estimate_population' => 10,
        ]);
        Budget::factory()->create([
            'fiscal_year' => 2026,
            'per_head_day_limit' => 12,
        ]);

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/fss/menu-cycles/{$cycle->id}/compute");

        $response->assertOk()
            ->assertJsonPath('data.budget_per_head_day', 12)
            ->assertJsonPath('data.days.Monday.budget_status', 'ok')
            ->assertJsonPath('data.within_budget', true);
    }

    public function test_menu_cycle_activation_blocks_planned_days_missing_estimate_population(): void
    {
        $cycle = MenuCycle::factory()->create([
            'is_active' => false,
            'status' => 'draft',
            'activation_date' => null,
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
        ]);
        $fsItem = $this->makeFsItem();
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $fsItem->id,
            'quantity' => 1,
            'estimate_population' => null,
        ]);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/fss/menu-cycles/{$cycle->id}/activate");

        $response->assertStatus(422)
            ->assertJsonPath('missing_population_days', ['2026-06-15']);

        $cycle->refresh();
        $this->assertFalse($cycle->is_active);
        $this->assertEquals('draft', $cycle->status);
    }

    public function test_menu_cycle_requires_name(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/menu-cycles', ['cycle_days' => 7]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ===== BUDGETS =====

    public function test_rnd_can_setup_fiscal_year_budget(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets', [
                'fiscal_year'        => 2026,
                'allocated_amount'   => 50000.00,
                'per_head_day_limit' => 120.00,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.fiscal_year', 2026)
            ->assertJsonPath('data.allocated_amount', '50000.00');

        $this->assertDatabaseHas('budgets', ['fiscal_year' => 2026, 'allocated_amount' => 50000.00]);
    }

    public function test_budget_fiscal_year_must_be_unique(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026]);

        $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets', ['fiscal_year' => 2026, 'allocated_amount' => 10000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fiscal_year']);
    }

    public function test_budget_requires_allocated_amount(): void
    {
        $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets', ['fiscal_year' => 2026])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['allocated_amount']);
    }

    public function test_fss_cannot_create_budget(): void
    {
        $this->actingAs($this->fss)
            ->postJson('/api/fss/budgets', ['fiscal_year' => 2026, 'allocated_amount' => 10000])
            ->assertForbidden();
    }

    public function test_budget_summary_returns_fiscal_year_snapshot(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);
        BudgetLedger::create([
            'fiscal_year' => 2026, 'type' => 'po_deduction',
            'amount' => 25000, 'reason' => 'PO #001',
        ]);
        BudgetLedger::create([
            'fiscal_year' => 2026, 'type' => 'manual_addition',
            'amount' => 5000, 'reason' => 'Supplemental allocation',
        ]);

        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/budgets/summary?fiscal_year=2026');

        $response->assertOk()
            ->assertJsonPath('data.fiscal_year', 2026)
            ->assertJsonPath('data.allocated_amount', '100000.00');

        $data = $response->json('data');
        $this->assertEqualsWithDelta(80000, (float) $data['remaining_balance'], 0.01); // 100k + 5k - 25k
    }

    public function test_budget_summary_returns_notice_when_no_fiscal_year(): void
    {
        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/budgets/summary?fiscal_year=2099');

        $response->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('notice', fn ($v) => str_contains($v, '2099'));
    }

    public function test_rnd_can_add_manual_adjustment(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2026,
                'type'        => 'manual_addition',
                'amount'      => 5000,
                'reason'      => 'Extra allocation from admin',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('budget_ledger', [
            'fiscal_year' => 2026,
            'type'        => 'manual_addition',
            'amount'      => 5000,
        ]);
    }

    public function test_fss_cannot_add_manual_adjustment(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        $this->actingAs($this->fss)
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2026,
                'type'        => 'manual_addition',
                'amount'      => 5000,
                'reason'      => 'Unauthorized attempt',
            ])
            ->assertForbidden();
    }

    public function test_budget_ledger_lists_entries_for_fiscal_year(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_addition', 'amount' => 1000, 'reason' => 'top-up']);
        BudgetLedger::create(['fiscal_year' => 2025, 'type' => 'po_deduction', 'amount' => 500]);

        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/budgets/ledger?fiscal_year=2026');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_budget_report_generator_uses_ledger(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 80000]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'po_deduction', 'amount' => 30000]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_addition', 'amount' => 10000]);

        $report = new \App\Models\Report(['type' => 'budget_report', 'parameters' => ['fiscal_year' => 2026]]);
        $data   = (new \App\Services\Reports\Generators\BudgetReportGenerator())->data($report);

        $this->assertSame(2026, $data['fiscal_year']);
        $this->assertEqualsWithDelta(80000, $data['allocated_amount'], 0.01);
        $this->assertEqualsWithDelta(30000, $data['total_po_deductions'], 0.01);
        $this->assertEqualsWithDelta(10000, $data['total_manual_additions'], 0.01);
        $this->assertEqualsWithDelta(60000, $data['remaining_balance'], 0.01); // 80k + 10k - 30k
    }

    public function test_complete_day_persists_served_population(): void
    {
        $fs = FsItem::factory()->create(['name' => 'Rice', 'base_unit' => 'g']);
        Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 10000, 'unit' => 'g', 'unit_price' => 0.05]);

        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday',
            'meal_type' => 'lunch', 'fs_item_id' => $fs->id, 'quantity' => 100,
            'estimate_population' => 5,
        ]);

        // Serve the day to 8 heads (override the cycle's default 5) — that headcount must be stored.
        $this->actingAs($this->fss)->postJson("/api/fss/menu-cycles/{$cycle->id}/complete-day", [
            'service_date' => '2026-06-15', // a Monday
            'population'   => 8,
        ])->assertCreated();

        $this->assertDatabaseHas('meal_prep_logs', [
            'menu_cycle_id' => $cycle->id,
            'population'    => 8,
        ]);
    }

    public function test_cost_today_returns_active_cycle_per_head_from_menu(): void
    {
        $weekday = now()->format('l');
        // kg→g: 1000 base per purchase → ₱0.10/g; 100 g/head × 10 heads = 1000 g = ₱100/day; ÷10 = ₱10/head
        $fs = FsItem::factory()->create([
            'base_unit' => 'g', 'purchase_unit' => 'kg', 'purchase_price' => 100, 'units_per_purchase' => null,
        ]);
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id, 'is_active' => true, 'status' => 'active',
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => $weekday,
            'meal_type' => 'lunch', 'fs_item_id' => $fs->id, 'quantity' => 100,
            'estimate_population' => 10,
        ]);
        // Per-head cap comes from fiscal year budget.
        Budget::factory()->create([
            'fiscal_year' => (int) now()->format('Y'), 'per_head_day_limit' => 50,
        ]);

        $res = $this->actingAs($this->fss)->getJson('/api/fss/menu-cycles/cost-today')->assertOk();
        $this->assertEqualsWithDelta(10, $res->json('data.cost_per_head'), 0.01);
        $this->assertEqualsWithDelta(50, $res->json('data.limit_per_head'), 0.01);
        $this->assertTrue($res->json('data.within_budget'));
        $this->assertSame($weekday, $res->json('data.weekday'));
    }

    public function test_insights_per_head_vs_limit_returns_po_data(): void
    {
        $sl = ShoppingList::factory()->create([
            'period_start' => '2026-06-01', 'period_end' => '2026-06-07',
        ]);
        Budget::factory()->create(['fiscal_year' => 2026, 'per_head_day_limit' => 120]);
        PurchaseOrder::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'shopping_list_id' => $sl->id,
            'lifecycle_status' => 'completed',
            'actual_budget_per_head_per_day' => 95.00,
        ]);

        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/insights/per-head-actual-vs-limit?fiscal_year=2026');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data['points']);
        $this->assertEqualsWithDelta(95, $data['points'][0]['actual_per_head'], 0.01);
        $this->assertEqualsWithDelta(120, $data['summary']['limit_per_head'], 0.01);
    }

    public function test_insights_per_head_includes_estimated_from_ppa(): void
    {
        $sl = ShoppingList::factory()->create([
            'period_start' => '2026-06-01', 'period_end' => '2026-06-07',
        ]);
        Budget::factory()->create(['fiscal_year' => 2026, 'per_head_day_limit' => 120]);
        $po = PurchaseOrder::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'shopping_list_id' => $sl->id,
            'lifecycle_status' => 'completed',
            'actual_budget_per_head_per_day' => 95.00,
        ]);
        // Frozen PPA planning snapshot → estimated per head = 8000 / 100 = 80.
        \App\Models\ProgramProjectActivity::create([
            'purchase_order_id' => $po->id,
            'activity' => 'Food Subsistence for Patients',
            'estimated_total_cost' => 8000,
            'estimated_output_patients' => 100,
        ]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/insights/per-head-actual-vs-limit?fiscal_year=2026')
            ->assertOk()->json('data');

        $this->assertEqualsWithDelta(80, $data['points'][0]['estimated_per_head'], 0.01);
    }

    public function test_generate_rounds_to_whole_purchase_units(): void
    {
        // 1 kg sack = 1000 g base; planned need 1300 g → must buy 2 sacks (2000 g).
        $fs = FsItem::factory()->create([
            'name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg',
            'purchase_price' => 50, 'units_per_purchase' => null,
        ]);
        Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 0, 'unit' => 'g']);

        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday',
            'meal_type' => 'lunch', 'fs_item_id' => $fs->id, 'quantity' => 1300,
            'estimate_population' => 1,
        ]);

        $response = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15', 'end_date' => '2026-06-15', // a Monday
        ]);

        $response->assertCreated();
        $item = collect($response->json('data.items'))->firstWhere('fs_item_id', $fs->id);
        $this->assertNotNull($item, 'Rice line should be present');
        $this->assertEqualsWithDelta(2,    (float) $item['purchase_qty'], 0.01); // ceil(1300/1000)
        $this->assertSame('kg', $item['purchase_unit']);
        $this->assertEqualsWithDelta(50,   (float) $item['purchase_price'], 0.01);
        $this->assertEqualsWithDelta(2000, (float) $item['qty'], 0.01);          // 2 sacks × 1000 g base
        $this->assertEqualsWithDelta(100,  (float) $item['total'], 0.01);        // 2 × ₱50
    }

    public function test_generate_pos_carries_purchase_units(): void
    {
        $supplier = Supplier::factory()->create();
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'L', 'list_date' => '2026-06-08',
            'list_type' => 'suggested', 'status' => 'draft',
        ]);
        $list->items()->create([
            'ingredient_name' => 'Rice', 'qty' => 2000, 'unit' => 'g', 'supplier_id' => $supplier->id,
            'unit_price' => 0.05, 'total' => 100, 'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
        ]);

        // approve is RND-only; FSS gets 403
        $this->actingAs($this->fss)
            ->postJson("/api/fss/shopping-lists/{$list->id}/approve")
            ->assertForbidden();

        // RND gets 201
        $response = $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->id}/approve");
        $response->assertCreated();

        $this->assertDatabaseHas('purchase_order_items', [
            'description' => 'Rice', 'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
        ]);
    }

    public function test_fss_cannot_manually_add_item_to_shopping_list(): void
    {
        $supplier = Supplier::factory()->create();
        $fs = FsItem::factory()->create([
            'name' => 'Banana',
            'base_unit' => 'piece',
            'purchase_unit' => 'piece',
            'purchase_price' => 6,
            'units_per_purchase' => 1,
            'default_supplier_id' => $supplier->id,
        ]);
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Manual list',
            'list_date' => '2026-06-08',
            'list_type' => 'manual',
            'status' => 'draft',
        ]);

        $this->actingAs($this->fss)->postJson("/api/fss/shopping-lists/{$list->id}/items", [
            'fs_item_id' => $fs->id,
            'qty' => 12,
            'unit' => 'piece',
            'supplier_id' => $supplier->id,
            'unit_price' => 6,
        ])->assertForbidden();
    }

    public function test_fss_cannot_generate_shopping_lists(): void
    {
        $this->actingAs($this->fss)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
        ])->assertForbidden();
    }

    public function test_rnd_can_manually_add_item_to_shopping_list(): void
    {
        $supplier = Supplier::factory()->create();
        $fs = FsItem::factory()->create([
            'name' => 'Banana',
            'base_unit' => 'piece',
            'purchase_unit' => 'piece',
            'purchase_price' => 6,
            'units_per_purchase' => 1,
            'default_supplier_id' => $supplier->id,
        ]);
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Manual list',
            'list_date' => '2026-06-08',
            'list_type' => 'manual',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->id}/items", [
            'fs_item_id' => $fs->id,
            'qty' => 12,
            'unit' => 'piece',
            'supplier_id' => $supplier->id,
            'unit_price' => 6,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.ingredient_name', 'Banana')
            ->assertJsonPath('data.total', '72.00');

        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $list->id,
            'fs_item_id' => $fs->id,
            'ingredient_name' => 'Banana',
            'qty' => 12,
            'unit_price' => 6,
            'total' => 72,
        ]);
    }

    public function test_rnd_can_manually_add_supply_item_to_shopping_list(): void
    {
        $supplier = Supplier::factory()->create();
        $supply = FsItem::factory()->create([
            'kind' => 'supply',
            'name' => 'Dish Soap',
            'base_unit' => 'bottle',
            'purchase_unit' => 'bottle',
            'purchase_price' => 80,
            'units_per_purchase' => 1,
            'default_supplier_id' => $supplier->id,
        ]);
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Supplies list',
            'list_date' => '2026-06-08',
            'list_type' => 'manual',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->id}/items", [
            'fs_item_id' => $supply->id,
            'qty' => 3,
            'unit' => 'bottle',
            'supplier_id' => $supplier->id,
            'unit_price' => 80,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.ingredient_name', 'Dish Soap')
            ->assertJsonPath('data.item_type', 'supply')
            ->assertJsonPath('data.total', '240.00');
    }

    public function test_converted_shopping_list_structural_items_are_read_only(): void
    {
        $fs = FsItem::factory()->create();
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Converted list',
            'list_date' => '2026-06-08',
            'list_type' => 'manual',
            'status' => 'converted',
        ]);
        $item = $list->items()->create([
            'fs_item_id' => $fs->id,
            'ingredient_name' => $fs->name,
            'qty' => 1,
            'unit' => 'piece',
            'unit_price' => 1,
            'total' => 1,
        ]);

        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->id}/items", [
            'fs_item_id' => $fs->id,
            'qty' => 2,
            'unit' => 'piece',
        ])->assertStatus(422);

        $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-list-items/{$item->id}", [
            'qty' => 5,
        ])->assertStatus(422);

        $this->actingAs($this->rnd)->deleteJson("/api/fss/shopping-list-items/{$item->id}")
            ->assertStatus(422);
    }

    public function test_receiving_uses_purchase_qty_times_base_per_purchase(): void
    {
        $fs = FsItem::factory()->create([
            'name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg', 'purchase_price' => 50,
        ]);
        Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 0, 'unit' => 'g']);

        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $this->rnd->id, 'status' => 'draft']);
        $po->items()->create([
            'fs_item_id' => $fs->id, 'description' => 'Rice',
            'qty' => 2000, 'unit' => 'g', 'unit_price' => 0.05, 'total_value' => 100,
            'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
        ]);

        // PO update is RND-only
        $this->actingAs($this->rnd)->patchJson("/api/fss/purchase-orders/{$po->id}", ['status' => 'received'])
            ->assertOk();

        // 2 kg × 1000 g/kg = 2000 g added to stock.
        $this->assertDatabaseHas('inventory', ['fs_item_id' => $fs->id, 'quantity_in_stock' => 2000]);
    }

    // ===== R2.4 SCOPE ENFORCEMENT: gate assertions =====

    public function test_fss_gets_403_on_fs_item_update(): void
    {
        $fsItem = $this->makeFsItem(['purchase_price' => 10.00]);

        $this->actingAs($this->fss)
            ->patchJson("/api/fss/fs-items/{$fsItem->id}", ['purchase_price' => 20.00])
            ->assertForbidden();
    }

    public function test_rnd_can_update_fs_item(): void
    {
        $fsItem = $this->makeFsItem(['purchase_price' => 10.00]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/fs-items/{$fsItem->id}", ['purchase_price' => 20.00])
            ->assertOk();
    }

    public function test_insights_routes_respond_for_fss(): void
    {
        $this->actingAs($this->fss)->getJson('/api/fss/insights/spend-by-supplier')->assertOk();
        $this->actingAs($this->fss)->getJson('/api/fss/insights/budget-burn')->assertOk();
        $this->actingAs($this->fss)->getJson('/api/fss/insights/per-head-actual-vs-limit')->assertOk();
        $this->actingAs($this->fss)->getJson('/api/fss/insights/procurement-deduction-timeline')->assertOk();
    }

    public function test_fss_gets_404_on_deleted_cleaning_log_routes(): void
    {
        $this->actingAs($this->fss)->getJson('/api/fss/cleaning-logs')->assertNotFound();
        $this->actingAs($this->fss)->postJson('/api/fss/cleaning-logs', ['item_name' => 'x'])->assertNotFound();
    }

    public function test_rnd_can_add_shopping_list_item(): void
    {
        $supplier = Supplier::factory()->create();
        $fsItem   = $this->makeFsItem();

        // Shopping list item add
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'L2', 'list_date' => '2026-06-08',
            'list_type' => 'manual', 'status' => 'draft',
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->id}/items", [
                'fs_item_id' => $fsItem->id,
                'qty' => 5,
                'unit' => $fsItem->base_unit,
                'unit_price' => 10.00,
            ])
            ->assertCreated();
    }

    // ===== PROCUREMENT PACK (report generator — model-level, no HTTP auth) =====

    public function test_procurement_pack_prints_purchase_units(): void
    {
        $fs = FsItem::factory()->create(['name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg', 'purchase_price' => 50]);
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $this->rnd->id, 'status' => 'received', 'order_date' => '2026-06-08']);
        $po->items()->create([
            'fs_item_id' => $fs->id, 'description' => 'Rice',
            'qty' => 2000, 'unit' => 'g', 'unit_price' => 0.05, 'total_value' => 100,
            'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
        ]);

        $report = new \App\Models\Report(['type' => 'procurement_pack', 'parameters' => ['purchase_order_id' => $po->id]]);
        $data = (new \App\Services\Reports\Generators\ProcurementPackGenerator())->data($report);

        $pack = $data['packs'][0];
        $this->assertEqualsWithDelta(2, (float) $pack['air_items'][0]['quantity'], 0.01); // packs, not 2000 g
        $this->assertSame('kg', $pack['air_items'][0]['unit']);
        $this->assertEqualsWithDelta(50, (float) $pack['statement_items'][0]['unit_price'], 0.01); // ₱/pack
    }
}
