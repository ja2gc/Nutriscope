<?php

namespace Tests\Feature;

use App\Events\PurchaseOrderCompleted;
use App\Events\PurchaseOrderConverted;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FoodServiceSetting;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\Report;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FSS\ShoppingListPopulationService;
use App\Services\Reports\Generators\ProcurementPackGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            'role' => 'FSS',
            'password' => Hash::make('password'),
        ]);
        $this->rnd = User::factory()->create([
            'role' => 'RND',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeFsItem(array $attrs = []): FsItem
    {
        Budget::firstOrCreate(['fiscal_year' => 2026], ['allocated_amount' => 1000000]);

        return FsItem::factory()->create($attrs);
    }

    /**
     * Plan every weekday that isn't planned yet with a zero-cost item, so the cycle
     * satisfies the activation rule ("every weekday must have a planned item" — cycles
     * always span the full week) without affecting the cycle's cost. Returns the cycle.
     */
    private function planRemainingWeekdays(MenuCycle $cycle): MenuCycle
    {
        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $missing = array_diff($weekdays, $cycle->days()->pluck('day_of_week')->all());

        if ($missing !== []) {
            $filler = $this->makeFsItem(['purchase_price' => 0]);
            foreach ($missing as $day) {
                MenuCycleDay::create([
                    'menu_cycle_id' => $cycle->id, 'day_of_week' => $day, 'meal_type' => 'lunch',
                    'fs_item_id' => $filler->id, 'quantity' => 1, 'estimate_population' => 1,
                ]);
            }
        }

        return $cycle->fresh();
    }

    // ===== INVENTORY =====

    public function test_retired_stock_columns_are_removed(): void
    {
        $this->assertFalse(Schema::hasColumn('inventory', 'quantity_in_stock'));
        $this->assertFalse(Schema::hasColumn('inventory', 'unit'));
        $this->assertFalse(Schema::hasColumn('inventory', 'unit_price'));
        $this->assertFalse(Schema::hasColumn('inventory', 'notes'));
    }

    public function test_fss_can_list_legacy_inventory_associations_without_stock_fields(): void
    {
        $fsItem = $this->makeFsItem();
        Inventory::factory()->create(['fs_item_id' => $fsItem->id]);

        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/inventory');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'fs_item_id', 'item_type']]])
            ->assertJsonMissingPath('data.0.quantity_in_stock')
            ->assertJsonMissingPath('data.0.in_stock')
            ->assertJsonMissingPath('data.0.unit');
    }

    public function test_fss_inventory_write_route_is_removed(): void
    {
        // Inventory is now a backend reference catalog only — no FSS stocking writes.
        $fsItem = $this->makeFsItem();
        $inventory = Inventory::factory()->create(['fs_item_id' => $fsItem->id]);

        $this->actingAs($this->fss)
            ->patchJson("/api/fss/inventory/{$inventory->uuid}", ['item_type' => 'supply'])
            ->assertStatus(405);
    }

    public function test_fss_restock_route_is_removed(): void
    {
        $fsItem = $this->makeFsItem();
        $inventory = Inventory::factory()->create(['fs_item_id' => $fsItem->id]);

        $this->actingAs($this->fss)
            ->postJson("/api/fss/inventory/{$inventory->uuid}/restock", ['quantity' => 30])
            ->assertNotFound();
    }

    public function test_rnd_can_access_fss_inventory_routes(): void
    {
        $fsItem = $this->makeFsItem();
        Inventory::factory()->create(['fs_item_id' => $fsItem->id]);

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/fss/inventory');

        $response->assertOk();
    }

    public function test_catalog_search_validates_bounded_pagination(): void
    {
        FsItem::factory()->count(12)->create(['kind' => 'ingredient', 'is_active' => true]);

        $this->actingAs($this->rnd)
            ->getJson('/api/fss/fs-items/catalog?kind=ingredient&limit=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
        $defaultSecondPage = $this->actingAs($this->rnd)
            ->getJson('/api/fss/fs-items/catalog?kind=ingredient&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 10);
        $this->assertSame(12, $defaultSecondPage->json('meta.total'));

        $page = $this->actingAs($this->rnd)
            ->getJson('/api/fss/fs-items/catalog?kind=ingredient&limit=5&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5);
        $this->assertSame(12, $page->json('meta.total'));

        $this->actingAs($this->rnd)
            ->getJson('/api/fss/fs-items/catalog?kind=ingredient')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 12);
    }

    public function test_catalog_can_filter_auto_and_non_auto_grocery(): void
    {
        $auto = $this->makeFsItem(['name' => 'Auto banana', 'include_in_generated_lists' => true]);
        $manual = $this->makeFsItem(['name' => 'Manual salt', 'include_in_generated_lists' => false]);

        $this->actingAs($this->rnd)->getJson('/api/fss/fs-items/catalog?kind=ingredient&grocery=auto')
            ->assertOk()->assertJsonFragment(['id' => $auto->uuid])->assertDontSee($manual->uuid);
        $this->actingAs($this->rnd)->getJson('/api/fss/fs-items/catalog?kind=ingredient&grocery=non_auto')
            ->assertOk()->assertJsonFragment(['id' => $manual->uuid])->assertDontSee($auto->uuid);
    }

    public function test_catalog_returns_the_public_default_vendor_id_for_inventory_edits(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Fictional Produce Vendor']);
        $item = $this->makeFsItem([
            'name' => 'Vendor-linked carrot',
            'default_supplier_id' => $supplier->id,
        ]);

        $this->actingAs($this->rnd)
            ->getJson('/api/fss/fs-items/catalog?kind=ingredient&search=Vendor-linked carrot')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->uuid)
            ->assertJsonPath('data.0.default_supplier_id', $supplier->uuid)
            ->assertJsonPath('data.0.vendor', 'Fictional Produce Vendor');
    }

    // ===== SUPPLIERS (shared read scope; RND-only authoring) =====

    public function test_fss_cannot_create_supplier(): void
    {
        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/suppliers', [
                'name' => 'Green Valley Farm',
                'contact' => '0912-345-6789',
                'address' => 'Quezon City, Philippines',
            ]);

        $response->assertForbidden();
    }

    public function test_rnd_can_create_supplier(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/suppliers', [
                'name' => 'Green Valley Farm',
                'contact' => '0912-345-6789',
                'address' => 'Quezon City, Philippines',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Green Valley Farm');

        $this->assertDatabaseHas('suppliers', ['name' => 'Green Valley Farm']);
    }

    public function test_fss_can_list_suppliers_for_purchase_vendor_selection(): void
    {
        Supplier::factory(3)->create();

        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/suppliers');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
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
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
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
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
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
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
            ->assertStatus(422);
    }

    public function test_vendor_group_optional_or_actuals_and_audited_planned_price_correction(): void
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

        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")->assertCreated();
        $group = PurchaseOrderVendorGroup::firstOrFail();
        $line = $group->items()->firstOrFail();

        // FSS can save an optional OR number.
        $this->actingAs($this->fss)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'or_number' => 'OR-FSS-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.vendor_groups.0.or_number', 'OR-FSS-1');

        // FSS cannot patch frozen planned prices.
        $this->actingAs($this->fss)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'items' => [['id' => $line->id, 'unit_price' => 22]],
            ])
            ->assertForbidden();

        // FSS can submit execution details, but only RND can confirm receipt.
        $this->actingAs($this->fss)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'status' => 'received',
            ])
            ->assertForbidden();
        // RND still cannot confirm receipt before actuals and both evidence types exist.
        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'status' => 'received',
            ])
            ->assertStatus(422);

        // RND can do an audited price correction; purchase_qty/purchase_unit are frozen.
        // Qty stays 5, price → 22.
        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'items' => [[
                    'id' => $line->id,
                    'purchase_qty' => 6,    // ignored (frozen)
                    'purchase_unit' => 'sack', // ignored (frozen)
                    'unit_price' => 22,
                ]],
            ])
            ->assertOk();

        // total = frozen qty (5) × corrected unit_price (22) = 110.
        $this->assertDatabaseHas('purchase_order_vendor_groups', ['id' => $group->id, 'or_number' => 'OR-FSS-1', 'total_amount' => 110]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $group->purchase_order_id, 'total_amount' => 110]);

        // Every correction is audited with the RND user who made it.
        $this->assertDatabaseHas('purchase_order_item_corrections', [
            'purchase_order_item_id' => $line->id,
            'old_unit_price' => 20,
            'new_unit_price' => 22,
            'corrected_by' => $this->rnd->id,
        ]);
    }

    public function test_po_completes_when_all_vendor_receipts_and_served_population_exist(): void
    {
        Event::fake([PurchaseOrderCompleted::class]);
        Storage::fake('public');

        $supplier = Supplier::factory()->create();
        $fsItem = $this->makeFsItem(['base_unit' => 'kg', 'purchase_unit' => 'kg', 'purchase_price' => 25]);
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
            'list_type' => 'suggested',
            'coverage_status' => 'full',
            'status' => 'draft',
            'estimate_population' => 10,
        ]);
        $list->items()->create([
            'fs_item_id' => $fsItem->id, 'ingredient_name' => $fsItem->name, 'qty' => 5, 'unit' => 'kg',
            'supplier_id' => $supplier->id, 'unit_price' => 20, 'total' => 100,
        ]);
        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")->assertCreated();
        $po = PurchaseOrder::where('shopping_list_id', $list->id)->firstOrFail();
        $group = $po->vendorGroups()->firstOrFail();
        $line = $group->items()->firstOrFail();

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
            ->post("/api/fss/purchase-order-vendor-groups/{$group->uuid}/attachments", [
                'type' => 'receipt',
                'file' => UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
            ])
            ->assertCreated();

        $this->actingAs($this->fss)
            ->post("/api/fss/purchase-order-vendor-groups/{$group->uuid}/attachments", [
                'type' => 'proof',
                'file' => UploadedFile::fake()->createWithContent('proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
            ])
            ->assertCreated();

        $this->actingAs($this->fss)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'items' => [['id' => $line->id, 'actual_qty' => 5, 'actual_unit_price' => 20]],
            ])
            ->assertOk();
        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'status' => 'received',
            ])
            ->assertOk();

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
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", ['or_number' => 'LOCKED'])
            ->assertStatus(422);
    }

    public function test_a_missed_past_week_uses_the_normal_food_procurement_and_report_lifecycle(): void
    {
        Storage::fake('public');
        Storage::fake('report_cache');
        Storage::fake(config('filesystems.private_uploads'));
        $supplier = Supplier::factory()->create();
        $item = $this->makeFsItem([
            'base_unit' => 'piece', 'purchase_unit' => 'piece', 'purchase_price' => 5,
            'default_supplier_id' => $supplier->id,
        ]);
        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $days = array_map(fn (string $day): array => [
            'day_of_week' => $day, 'meal_type' => 'lunch', 'fs_item_id' => $item->uuid, 'quantity' => 1,
        ], $weekdays);
        $startedAt = now()->startOfSecond();

        $cycleId = $this->actingAs($this->rnd)->postJson('/api/fss/menu-cycles', [
            'week_start_date' => '2026-06-01', 'days' => $days,
        ])->assertCreated()->json('data.id');
        $cycle = MenuCycle::where('uuid', $cycleId)->firstOrFail();
        $listId = $this->postJson('/api/fss/shopping-lists/generate', [
            'name' => 'Missed June week', 'start_date' => '2026-06-01', 'end_date' => '2026-06-07',
            'estimate_population' => 10,
        ])->assertCreated()->assertJsonPath('data.coverage_status', 'full')->json('data.id');
        $list = ShoppingList::where('uuid', $listId)->firstOrFail();
        $this->postJson("/api/fss/shopping-lists/{$listId}/approve")->assertCreated();
        $po = PurchaseOrder::where('shopping_list_id', $list->id)->firstOrFail();
        $this->assertSame(7, $cycle->days()->whereNotNull('snapshot_purchase_order_id')->count());

        foreach (range(1, 7) as $day) {
            $this->actingAs($this->fss)->patchJson("/api/fss/menu-cycles/{$cycleId}/served-population", [
                'service_date' => sprintf('2026-06-%02d', $day), 'served_population' => 8,
            ])->assertOk();
        }
        $group = $po->vendorGroups()->firstOrFail();
        $line = $group->items()->firstOrFail();
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        foreach (['receipt', 'proof'] as $type) {
            $this->post("/api/fss/purchase-order-vendor-groups/{$group->uuid}/attachments", [
                'type' => $type,
                'file' => UploadedFile::fake()->createWithContent("{$type}.png", $image),
            ])->assertCreated();
        }
        $this->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
            'items' => [['id' => $line->id, 'actual_qty' => 70, 'actual_unit_price' => 5]],
        ])->assertOk();
        $this->actingAs($this->rnd)->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
            'status' => 'received',
        ])->assertOk();

        $po->refresh();
        $this->assertSame('completed', $po->lifecycle_status);
        $this->assertTrue($po->created_at->gte($startedAt));
        $this->assertTrue($po->completed_at->gte($startedAt));
        $this->assertTrue(BudgetLedger::where('purchase_order_id', $po->id)
            ->where('fiscal_year', 2026)->where('type', 'po_deduction')->exists());
        $this->assertDatabaseHas('program_project_activities', [
            'purchase_order_id' => $po->id, 'actual_output_patients' => 56,
        ]);
        $this->assertSame(1, Report::where('type', 'program_project_activity')->where('parameters->purchase_order_id', $po->id)->count());
        $this->assertSame(1, Report::where('type', 'procurement_pack')->where('parameters->purchase_order_id', $po->id)->count());
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
            'supplier_id' => Supplier::factory()->create()->id, 'unit_price' => 50, 'total' => 100,
        ]);
        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")->assertCreated();
        $po = PurchaseOrder::where('shopping_list_id', $list->id)->firstOrFail();

        $this->actingAs($this->rnd)->getJson("/api/fss/purchase-orders/{$po->uuid}/ppa")
            ->assertOk()
            ->assertJsonPath('data.activity', 'Food Subsistence for Patients');

        $this->actingAs($this->fss)->getJson("/api/fss/purchase-orders/{$po->uuid}/ppa")
            ->assertForbidden();

        $this->actingAs($this->fss)->deleteJson("/api/fss/purchase-orders/{$po->uuid}")
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
        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")->assertCreated();

        $this->actingAs($this->fss)
            ->getJson('/api/fss/purchase-orders')
            ->assertOk()
            ->assertJsonPath('data.0.shopping_list_id', $list->uuid)
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

        Carbon::setTestNow(Carbon::parse('2026-06-09 12:00:00', 'Asia/Manila'));
        try {
            $cycles = $this->actingAs($this->fss)
                ->getJson('/api/fss/menu-cycles')
                ->assertOk()
                ->assertJsonPath('data.0.id', $active->uuid)
                ->assertJsonPath('data.0.plan_days.Monday', true)
                ->assertJsonPath('data.0.plan_days.Tuesday', false)
                ->json('data');
            $this->assertContains($past->uuid, array_column($cycles, 'id'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_fss_can_read_purchase_order(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft']);

        $this->actingAs($this->fss)
            ->getJson("/api/fss/purchase-orders/{$po->uuid}")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_fss_cannot_update_purchase_order_status(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft']);

        $this->actingAs($this->fss)
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", ['status' => 'received'])
            ->assertForbidden();
    }

    public function test_rnd_can_update_purchase_order_status(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", [
                'status' => 'received',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'received');
    }

    public function test_rnd_po_receipt_updates_catalog_price_without_creating_stock(): void
    {
        $fsItem = $this->makeFsItem([
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'purchase_price' => 20.00,
        ]);
        $po = PurchaseOrder::factory()->create([
            'status' => 'ordered',
        ]);

        // Add item to purchase order
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'fs_item_id' => $fsItem->id,
            'description' => $fsItem->name,
            'qty' => 5,
            'unit' => 'kg',
            'unit_price' => 20.00,
            'total_value' => 100.00,
        ]);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", [
                'status' => 'received',
            ]);

        $response->assertOk();

        $this->assertDatabaseMissing('inventory', ['fs_item_id' => $fsItem->id]);
        $this->assertSame(20.0, (float) $fsItem->fresh()->purchase_price);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'fs_item_id' => $fsItem->id,
            'qty' => 5,
            'unit_price' => 20,
        ]);
    }

    public function test_approving_empty_shopping_list_is_rejected(): void
    {
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Empty', 'list_date' => '2026-06-10',
            'list_type' => 'manual', 'status' => 'draft',
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
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

        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/shopping-lists/generate', [
                'start_date' => '2026-06-15', // Monday
                'end_date' => '2026-06-15', // the planned Monday → fully covered
                'estimate_population' => 10,
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
            'end_date' => '2026-06-18', // Thursday
            'estimate_population' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.period_start', '2026-06-16')
            ->assertJsonPath('data.period_end', '2026-06-18')
            ->assertJsonPath('data.days_span', 3)
            ->assertJsonPath('data.coverage_status', 'full');

        // Generation immediately applies the one span estimate.
        $listId = $response->json('data.id');
        $item = collect($response->json('data.items'))->firstWhere('fs_item_id', $fsItem->id);
        $this->assertEqualsWithDelta(30, (float) $item['qty'], 0.01, 'Generation produces 3 days × qty=1 × estimate 10');

        // Now set list-level estimate_population=10 → quantities scale to 3 days × 10 = 30.
        $scaled = $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-lists/{$listId}", [
            'estimate_population' => 10,
        ]);
        $scaled->assertOk()->assertJsonPath('data.estimate_population', 10);
        $scaledItem = collect($scaled->json('data.items'))->firstWhere('fs_item_id', $fsItem->id);
        $this->assertEqualsWithDelta(30, (float) $scaledItem['qty'], 0.01, 'After estimate_population=10, qty scales to 30');
    }

    public function test_suggested_requirements_use_each_service_days_estimate(): void
    {
        $item = $this->makeFsItem(['name' => 'Banana estimate', 'base_unit' => 'pc', 'purchase_unit' => 'pc', 'purchase_price' => 5]);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        MenuCycleDay::create(['menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch', 'fs_item_id' => $item->id, 'quantity' => 1, 'estimate_population' => 10]);
        MenuCycleDay::create(['menu_cycle_id' => $cycle->id, 'day_of_week' => 'Tuesday', 'meal_type' => 'lunch', 'fs_item_id' => $item->id, 'quantity' => 1, 'estimate_population' => 25]);

        $plan = app(ShoppingListPopulationService::class)->planRange('2026-06-15', '2026-06-16');

        $this->assertSame([], $plan['uncovered_dates']);
        $this->assertEqualsWithDelta(35, $plan['items'][0]['qty'], 0.01);
        $this->assertEqualsWithDelta(175, $plan['items'][0]['total'], 0.01);
    }

    public function test_suggested_preview_uses_one_entered_estimate_without_saving_list_or_menu_changes(): void
    {
        $item = $this->makeFsItem(['name' => 'Preview banana', 'base_unit' => 'pc', 'purchase_unit' => 'pc', 'purchase_price' => 5]);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        MenuCycleDay::create(['menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch', 'fs_item_id' => $item->id, 'quantity' => 1, 'estimate_population' => 10]);

        $response = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15', 'end_date' => '2026-06-15', 'estimate_population' => 25,
            'name' => 'Preview', 'preview' => true,
        ]);

        $response->assertOk()->assertJsonPath('data.items.0.qty', 25)
            ->assertJsonPath('data.items.0.fs_item_uuid', $item->uuid);
        $this->assertDatabaseMissing('shopping_lists', ['name' => 'Preview']);
        $this->assertDatabaseHas('menu_cycle_days', ['menu_cycle_id' => $cycle->id, 'estimate_population' => 10]);
    }

    public function test_suggested_list_cannot_save_over_an_existing_saved_suggested_service_day(): void
    {
        $item = $this->makeFsItem(['base_unit' => 'pc', 'purchase_unit' => 'pc']);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        foreach (['Monday', 'Tuesday', 'Wednesday'] as $day) {
            MenuCycleDay::create(['menu_cycle_id' => $cycle->id, 'day_of_week' => $day, 'meal_type' => 'lunch', 'fs_item_id' => $item->id, 'estimate_population' => 10]);
        }
        ShoppingList::factory()->create(['rnd_user_id' => $this->rnd->id, 'list_type' => 'suggested', 'period_start' => '2026-06-15', 'period_end' => '2026-06-16', 'status' => 'draft']);

        $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-16', 'end_date' => '2026-06-17', 'estimate_population' => 10, 'name' => 'Overlap',
        ])->assertStatus(422)->assertJsonPath('conflicting_dates', ['2026-06-16']);
        $this->assertDatabaseMissing('shopping_lists', ['name' => 'Overlap']);
    }

    public function test_manual_and_supplies_lists_require_a_selected_procurement_date(): void
    {
        foreach (['food', 'supplies'] as $track) {
            $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists', [
                'name' => 'Undated '.$track, 'list_type' => 'manual', 'procurement_track' => $track,
            ])->assertStatus(422)->assertJsonValidationErrors(['list_date']);
        }
        $this->assertDatabaseMissing('shopping_lists', ['name' => 'Undated food']);
        $this->assertDatabaseMissing('shopping_lists', ['name' => 'Undated supplies']);
    }

    public function test_shopping_list_unit_change_converts_purchase_quantity_and_cost_without_changing_total(): void
    {
        $item = $this->makeFsItem(['name' => 'Beef list', 'base_unit' => 'kg', 'purchase_unit' => 'kg', 'purchase_price' => 362]);
        $list = ShoppingList::factory()->create(['rnd_user_id' => $this->rnd->id, 'list_type' => 'manual', 'status' => 'draft']);
        $line = $list->items()->create(['fs_item_id' => $item->id, 'ingredient_name' => $item->name, 'source' => 'manual', 'qty' => 2, 'unit' => 'kg', 'unit_price' => 362, 'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 362, 'total' => 724]);

        $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-list-items/{$line->uuid}", ['purchase_unit' => 'g'])->assertOk();

        $line->refresh();
        $this->assertSame('g', $line->purchase_unit);
        $this->assertEqualsWithDelta(2000, (float) $line->purchase_qty, 0.001);
        $this->assertEqualsWithDelta(0.362, (float) $line->purchase_price, 0.000001);
        $this->assertEqualsWithDelta(724, (float) $line->total, 0.01);
        $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-list-items/{$line->uuid}", ['purchase_unit' => 'L'])->assertStatus(422);
    }

    public function test_converted_shopping_list_cannot_change_estimated_servings(): void
    {
        $list = ShoppingList::factory()->create(['rnd_user_id' => $this->rnd->id, 'list_type' => 'suggested', 'status' => 'converted']);
        $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-lists/{$list->uuid}", ['estimate_population' => 50])
            ->assertStatus(422);
    }

    public function test_generate_shopping_list_rejects_end_date_before_start_date(): void
    {
        $response = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-18',
            'end_date' => '2026-06-16',
            'estimate_population' => 10,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['end_date']);
    }

    public function test_generate_friday_to_monday_span_pulls_each_day_from_its_own_cycle(): void
    {
        $itemFri = $this->makeFsItem(['name' => 'FriItem', 'base_unit' => 'piece', 'purchase_unit' => 'piece', 'purchase_price' => 1, 'units_per_purchase' => 1]);
        $itemMon = $this->makeFsItem(['name' => 'MonItem', 'base_unit' => 'piece', 'purchase_unit' => 'piece', 'purchase_price' => 1, 'units_per_purchase' => 1]);

        // Week N cycle serves Friday; week N+1 cycle serves Monday.
        $weekN = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        MenuCycleDay::create(['menu_cycle_id' => $weekN->id, 'day_of_week' => 'Friday', 'meal_type' => 'lunch', 'fs_item_id' => $itemFri->id, 'quantity' => 1, 'estimate_population' => 10]);
        $weekNext = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-22']);
        MenuCycleDay::create(['menu_cycle_id' => $weekNext->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch', 'fs_item_id' => $itemMon->id, 'quantity' => 1, 'estimate_population' => 10]);

        // Fri 19 → Mon 22: Sat/Sun unplanned → all-or-nothing blocks the whole list.
        $response = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-19',
            'end_date' => '2026-06-22',
            'estimate_population' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertEqualsCanonicalizing(['2026-06-20', '2026-06-21'], $response->json('missing_dates'));
        // No partial list is created.
        $this->assertDatabaseMissing('shopping_lists', ['period_start' => '2026-06-19']);
    }

    public function test_saved_cross_week_list_applies_one_estimate_to_each_covered_date_without_preview_writes(): void
    {
        $item = $this->makeFsItem(['name' => 'Cross-week fruit', 'base_unit' => 'piece', 'purchase_unit' => 'piece', 'purchase_price' => 1, 'units_per_purchase' => 1]);
        $first = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        $second = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-22']);
        foreach (['Friday', 'Saturday', 'Sunday'] as $day) {
            MenuCycleDay::create(['menu_cycle_id' => $first->id, 'day_of_week' => $day, 'meal_type' => 'lunch', 'fs_item_id' => $item->id, 'quantity' => 1, 'estimate_population' => 10]);
        }
        MenuCycleDay::create(['menu_cycle_id' => $second->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch', 'fs_item_id' => $item->id, 'quantity' => 1, 'estimate_population' => 10]);

        $url = '/api/fss/shopping-lists/generate';
        $span = ['start_date' => '2026-06-19', 'end_date' => '2026-06-22', 'name' => 'Cross-week list'];
        $this->actingAs($this->rnd)->postJson($url, $span + ['estimate_population' => 20, 'preview' => true])
            ->assertOk()->assertJsonPath('data.items.0.qty', 80);
        $this->assertDatabaseMissing('shopping_lists', ['name' => 'Cross-week list']);
        $this->assertSame(10, (int) $first->days()->firstOrFail()->estimate_population);

        $response = $this->postJson($url, $span + ['estimate_population' => 25])->assertCreated();
        $this->assertEqualsWithDelta(100, (float) $response->json('data.items.0.qty'), 0.01);
        $this->assertSame(25, (int) $second->days()->firstOrFail()->estimate_population);
        $this->assertSame([25, 25, 25], $first->days()->pluck('estimate_population')->all());
        $this->assertSame('draft', ShoppingList::where('uuid', $response->json('data.id'))->firstOrFail()->status);
    }

    public function test_generate_hard_blocks_when_entire_span_uncovered(): void
    {
        $response = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2030-01-01',
            'end_date' => '2030-01-03',
            'estimate_population' => 10,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('missing_dates', ['2030-01-01', '2030-01-02', '2030-01-03']);
    }

    public function test_generate_sets_estimate_on_planned_days_that_had_none(): void
    {
        // estimate_population is at shopping-list level only. Per-day null population is
        // no longer a blocking condition — generation uses population=1 as default scaling.
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
            'estimate_population' => 10,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('shopping_lists', [
            'period_start' => '2026-06-15',
            'period_end' => '2026-06-15',
        ]);
        $this->assertDatabaseHas('menu_cycle_days', [
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'estimate_population' => 10,
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
            'estimate_population' => 10,
        ])->assertCreated()->json('data');

        Carbon::setTestNow('2026-06-25 09:00:00');
        $response = $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-lists/{$list['id']}", [
            'estimate_population' => 25,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.estimate_population', 25);

        // The span estimate is also exposed on covered menu days for profile display.
        $this->assertDatabaseHas('menu_cycle_days', [
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'estimate_population' => 25,
        ]);
        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => ShoppingList::where('uuid', $list['id'])->value('id'),
            'fs_item_id' => $fsItem->id,
            'qty' => 25,
            'total' => 125,
        ]);
    }

    public function test_population_refresh_keeps_procurement_edits_and_manual_lines(): void
    {
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
        ]);
        $item = $this->makeFsItem([
            'name' => 'Flour', 'base_unit' => 'kg', 'purchase_unit' => 'kg',
            'purchase_price' => 100, 'units_per_purchase' => 1,
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday',
            'meal_type' => 'lunch', 'fs_item_id' => $item->id,
            'quantity' => 1, 'estimate_population' => 10,
        ]);
        $list = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15', 'end_date' => '2026-06-15',
            'estimate_population' => 10,
        ])->assertCreated()->json('data');
        $line = $list['items'][0];
        $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-list-items/{$line['id']}", [
            'purchase_unit' => 'g',
        ])->assertOk();
        $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-list-items/{$line['id']}", [
            'purchase_price' => 0.12,
        ])->assertOk();
        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list['id']}/items", [
            'fs_item_id' => $item->uuid, 'qty' => 2, 'unit' => 'kg', 'unit_price' => 100,
        ])->assertCreated();

        $refreshed = $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-lists/{$list['id']}", [
            'estimate_population' => 20,
        ])->assertOk()->json('data');
        $generated = collect($refreshed['items'])->firstWhere('source', 'generated');
        $this->assertEquals(20, (float) $generated['qty']);
        $this->assertSame('g', $generated['purchase_unit']);
        $this->assertEquals(20000, (float) $generated['purchase_qty']);
        $this->assertEquals(0.12, (float) $generated['purchase_price']);
        $this->assertCount(1, collect($refreshed['items'])->where('source', 'manual'));
    }

    public function test_shopping_list_rounding_and_manual_quantity_do_not_change_estimated_servings(): void
    {
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        $item = $this->makeFsItem(['name' => 'Flour', 'base_unit' => 'kg', 'purchase_unit' => 'kg', 'purchase_price' => 100, 'units_per_purchase' => 1]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch',
            'fs_item_id' => $item->id, 'quantity' => 1.23, 'estimate_population' => 10,
        ]);
        $list = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15', 'end_date' => '2026-06-15', 'estimate_population' => 10,
        ])->assertCreated()->json('data');
        $line = $list['items'][0];
        $this->assertEquals(12.3, (float) $line['qty']);
        $this->assertEquals(12.3, (float) $line['purchase_qty']);

        $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-list-items/{$line['id']}", [
            'quantity_mode' => 'rounded',
        ])->assertOk()->assertJsonPath('data.quantity_mode', 'rounded');
        $rounded = $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-lists/{$list['id']}", [
            'estimate_population' => 20,
        ])->assertOk()->json('data.items.0');
        $this->assertEquals(24.6, (float) $rounded['qty']);
        $this->assertEquals(25, (float) $rounded['purchase_qty']);

        $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-list-items/{$line['id']}", [
            'purchase_qty' => 30,
        ])->assertOk();
        $manual = $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-lists/{$list['id']}", [
            'estimate_population' => 30,
        ])->assertOk()->json('data.items.0');
        $this->assertEquals(36.9, (float) $manual['qty']);
        $this->assertEquals(30, (float) $manual['purchase_qty']);
        $this->assertSame('manual', $manual['quantity_mode']);
        $this->assertDatabaseHas('menu_cycle_days', ['menu_cycle_id' => $cycle->id, 'estimate_population' => 30]);
    }

    public function test_suggested_list_save_applies_preview_edits_atomically(): void
    {
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        $item = $this->makeFsItem(['name' => 'Flour', 'base_unit' => 'kg', 'purchase_unit' => 'kg', 'purchase_price' => 100, 'units_per_purchase' => 1]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch',
            'fs_item_id' => $item->id, 'quantity' => 1, 'estimate_population' => 5,
        ]);
        $base = ['start_date' => '2026-06-15', 'end_date' => '2026-06-15', 'estimate_population' => 10, 'name' => 'Monday food'];
        $preview = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', $base + ['preview' => true])->assertOk()->json('data');
        $this->assertDatabaseCount('shopping_lists', 0);
        $this->assertDatabaseHas('menu_cycle_days', ['menu_cycle_id' => $cycle->id, 'estimate_population' => 5]);
        $line = [
            'source' => 'generated', 'fs_item_uuid' => $preview['items'][0]['fs_item_uuid'],
            'purchase_unit' => 'g', 'purchase_qty' => 10000,
            'purchase_price' => 0.12, 'quantity_mode' => 'manual',
            'included_in_po' => true,
        ];
        $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', $base + [
            'items' => [array_merge($line, ['purchase_unit' => 'L'])],
        ])->assertStatus(422);
        $this->assertDatabaseCount('shopping_lists', 0);
        $this->assertDatabaseHas('menu_cycle_days', ['menu_cycle_id' => $cycle->id, 'estimate_population' => 5]);

        $saved = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', $base + [
            'items' => [$line, [
                'source' => 'manual', 'fs_item_uuid' => $item->uuid,
                'purchase_unit' => 'g', 'purchase_qty' => 2000,
                'purchase_price' => 0.12, 'quantity_mode' => 'manual',
                'included_in_po' => true,
            ]],
        ])->assertCreated()->json('data');
        $this->assertSame('g', $saved['items'][0]['purchase_unit']);
        $this->assertEquals(10000, (float) $saved['items'][0]['purchase_qty']);
        $this->assertEquals(0.12, (float) $saved['items'][0]['purchase_price']);
        $this->assertEquals(1440, (float) $saved['estimated_total']);
        $this->assertEquals(2, (float) $saved['items'][1]['qty']);
        $this->assertSame('kg', $saved['items'][1]['unit']);
        $this->assertDatabaseHas('menu_cycle_days', ['menu_cycle_id' => $cycle->id, 'estimate_population' => 10]);
    }

    public function test_saved_suggested_list_batch_edit_preserves_identity_and_rolls_back_invalid_items(): void
    {
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        $item = $this->makeFsItem(['name' => 'Flour', 'base_unit' => 'kg', 'purchase_unit' => 'kg', 'purchase_price' => 100, 'units_per_purchase' => 1]);
        MenuCycleDay::create(['menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch', 'fs_item_id' => $item->id, 'quantity' => 1, 'estimate_population' => 10]);
        $saved = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15', 'end_date' => '2026-06-15', 'estimate_population' => 10,
        ])->assertCreated()->json('data');
        $line = [
            'id' => $saved['items'][0]['id'], 'fs_item_uuid' => $item->uuid, 'source' => 'generated',
            'purchase_unit' => 'g', 'purchase_qty' => 10000, 'purchase_price' => 0.12,
            'supplier_id' => null, 'quantity_mode' => 'manual', 'included_in_po' => true, 'exclusion_note' => null,
        ];
        $url = "/api/fss/shopping-lists/{$saved['id']}";
        $this->actingAs($this->rnd)->patchJson($url, ['name' => 'Updated', 'items' => [array_merge($line, ['purchase_unit' => 'L'])]])->assertStatus(422);
        $this->assertDatabaseHas('shopping_lists', ['uuid' => $saved['id'], 'name' => $saved['name']]);
        $updated = $this->actingAs($this->rnd)->patchJson($url, ['name' => 'Updated', 'items' => [$line]])->assertOk()->json('data');
        $this->assertSame($saved['id'], $updated['id']);
        $this->assertSame('Updated', $updated['name']);
        $this->assertSame('g', $updated['items'][0]['purchase_unit']);
        $this->assertEquals(1200, (float) $updated['items'][0]['total']);
        $this->actingAs($this->rnd)->postJson("{$url}/approve")->assertCreated();
        $this->actingAs($this->rnd)->patchJson($url, ['name' => 'Too late'])->assertStatus(422);
    }

    public function test_non_auto_inventory_item_can_be_added_edited_and_removed_from_suggested_list(): void
    {
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        $planned = $this->makeFsItem(['name' => 'Rice planned', 'base_unit' => 'kg', 'purchase_unit' => 'kg', 'purchase_price' => 50]);
        $manual = $this->makeFsItem([
            'name' => 'Seasoning', 'base_unit' => 'box', 'purchase_unit' => 'box',
            'purchase_price' => 30, 'include_in_generated_lists' => false,
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch',
            'fs_item_id' => $planned->id, 'quantity' => 1, 'estimate_population' => 10,
        ]);
        $saved = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15', 'end_date' => '2026-06-15', 'estimate_population' => 10,
        ])->assertCreated()->json('data');
        $plannedLine = [
            'id' => $saved['items'][0]['id'], 'source' => 'generated', 'fs_item_uuid' => $planned->uuid,
            'purchase_unit' => 'kg', 'purchase_qty' => 10, 'purchase_price' => 50,
            'quantity_mode' => 'calculated', 'included_in_po' => true,
        ];
        $manualLine = [
            'source' => 'manual', 'fs_item_uuid' => $manual->uuid,
            'purchase_unit' => 'box', 'purchase_qty' => 2, 'purchase_price' => 30,
            'quantity_mode' => 'manual', 'included_in_po' => true,
        ];
        $url = "/api/fss/shopping-lists/{$saved['id']}";
        $added = $this->actingAs($this->rnd)->patchJson($url, ['items' => [$plannedLine, $manualLine]])
            ->assertOk()->json('data');
        $this->assertCount(2, $added['items']);
        $this->assertSame($manual->uuid, $added['items'][1]['fs_item_uuid']);
        $this->assertSame('manual', $added['items'][1]['source']);
        $reloaded = $this->actingAs($this->rnd)->getJson($url)->assertOk()->json('data');
        $this->assertSame($added['items'][1]['id'], $reloaded['items'][1]['id']);
        $manualLine['id'] = $reloaded['items'][1]['id'];
        $manualLine['purchase_qty'] = 3;
        $this->actingAs($this->rnd)->patchJson($url, ['items' => [$plannedLine, $manualLine]])
            ->assertOk()->assertJsonPath('data.items.1.purchase_qty', '3.000000');
        $this->actingAs($this->rnd)->patchJson($url, ['items' => [$plannedLine]])->assertOk();
        $this->assertDatabaseMissing('shopping_list_items', ['uuid' => $manualLine['id']]);
    }

    public function test_shopping_catalog_search_ranks_exact_prefix_and_fuzzy_item_names(): void
    {
        $this->makeFsItem(['name' => 'Brown Rice', 'include_in_generated_lists' => false]);
        $this->makeFsItem(['name' => 'Rice Noodles', 'include_in_generated_lists' => false]);
        $this->makeFsItem(['name' => 'Rice', 'include_in_generated_lists' => false]);

        $this->actingAs($this->rnd)->getJson('/api/fss/fs-items/catalog?kind=ingredient&grocery=non_auto&limit=5')
            ->assertOk()->assertJsonCount(3, 'data');
        $matches = $this->getJson('/api/fss/fs-items/catalog?kind=ingredient&search=Rice&limit=5')
            ->assertOk()->json('data');
        $this->assertSame(['Rice', 'Rice Noodles', 'Brown Rice'], array_column($matches, 'name'));
        $typo = $this->getJson('/api/fss/fs-items/catalog?kind=ingredient&search=Rcie&limit=5')
            ->assertOk()->json('data');
        $this->assertSame('Rice', $typo[0]['name']);
    }

    public function test_manual_food_list_item_addition_persists_and_reloads(): void
    {
        $item = $this->makeFsItem([
            'name' => 'Rice for event', 'base_unit' => 'kg', 'purchase_unit' => 'kg',
            'purchase_price' => 60, 'include_in_generated_lists' => false,
        ]);
        $list = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists', [
            'name' => 'Event food', 'list_type' => 'manual', 'procurement_track' => 'food',
            'list_date' => '2026-06-20',
        ])->assertCreated()->json('data');
        $itemPayload = [
            'source' => 'manual', 'fs_item_uuid' => $item->uuid,
            'purchase_unit' => 'kg', 'purchase_qty' => 4, 'purchase_price' => 60,
            'quantity_mode' => 'manual', 'included_in_po' => true,
        ];
        $url = "/api/fss/shopping-lists/{$list['id']}";
        $this->actingAs($this->rnd)->patchJson($url, ['items' => [$itemPayload]])->assertOk();
        $reloaded = $this->actingAs($this->rnd)->getJson($url)->assertOk()->json('data');
        $this->assertSame($item->uuid, $reloaded['items'][0]['fs_item_uuid']);
        $this->assertEquals(4, (float) $reloaded['items'][0]['purchase_qty']);
        $this->assertSame('2026-06-20', $reloaded['list_date']);
    }

    public function test_list_level_population_scales_all_items_across_span(): void
    {
        // Replacing old per-day cascade test. Scaling is triggered by setting
        // estimate_population at the shopping-list level, not per menu-cycle day.
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
            'start_date' => '2026-06-15', // Monday
            'end_date' => '2026-06-16',   // Tuesday
            'estimate_population' => 20,
        ])->assertCreated()->json('data');

        // The editor saves its item rows alongside a revised span estimate.
        Carbon::setTestNow('2026-06-25 10:00:00');
        $updated = $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-lists/{$list['id']}", [
            'estimate_population' => 30,
            'items' => [[
                'id' => $list['items'][0]['id'],
                'source' => 'generated',
                'fs_item_uuid' => $fsItem->uuid,
                'purchase_unit' => 'piece',
                'purchase_qty' => 40,
                'purchase_price' => 5,
                'quantity_mode' => 'calculated',
                'included_in_po' => true,
            ]],
        ])->assertOk()->json('data');

        $this->assertEquals(60, (float) $updated['items'][0]['purchase_qty']);
        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => ShoppingList::where('uuid', $list['id'])->value('id'),
            'fs_item_id' => $fsItem->id,
            'qty' => 60,
            'total' => 300,
        ]);
        foreach (['Monday', 'Tuesday'] as $day) {
            $this->assertDatabaseHas('menu_cycle_days', [
                'menu_cycle_id' => $cycle->id,
                'day_of_week' => $day,
                'estimate_population' => 30,
            ]);
        }
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
            'purchase_price' => 10.00,
        ]);

        $fsItem2 = $this->makeFsItem([
            'name' => 'Food Item B',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'purchase_price' => 10.00,
        ]);

        // 2. Create active menu cycle anchored to the week of the generated span
        $cycle = MenuCycle::factory()->create([
            'is_active' => true,
            'status' => 'active',
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15', // Monday — covers 2026-06-15
        ]);

        // 3. Create a recipe using fsItem1
        $recipe = FoodServiceRecipe::create([
            'name' => 'Test Recipe',
            'rnd_user_id' => $this->rnd->id,
            'servings' => 1,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id' => $fsItem1->id,
            'quantity' => 3.00,
            'unit' => 'kg',
        ]);

        // 4. Link recipe and fsItem2 to MenuCycle via MenuCycleDay
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'breakfast',
            'recipe_id' => $recipe->id,
            'quantity' => 1.00, // 1 servings. Total fsItem1 needed = 3 * 2 (population) = 6
            'estimate_population' => 2,
        ]);

        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $fsItem2->id,
            'quantity' => 5.00, // Direct food item. Total fsItem2 needed = 5
            'estimate_population' => 2,
        ]);

        // 5. Generate shopping list suggestion for a single Monday.
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/shopping-lists/generate', [
                'start_date' => '2026-06-15', // Monday
                'end_date' => '2026-06-15', // same Monday
                'estimate_population' => 2,
            ]);

        $response->assertCreated();

        // Generation immediately applies the span estimate of 2.
        $listId = $response->json('data.id');
        $this->assertDatabaseHas('shopping_list_items', [
            'fs_item_id' => $fsItem1->id,
            'qty' => 6.00,
        ]);
        $this->assertDatabaseHas('shopping_list_items', [
            'fs_item_id' => $fsItem2->id,
            'qty' => 10.00,
        ]);

        // Set list-level estimate_population=2 → scales to original per-day expectations.
        // fsItem1: 3 kg × pop=2 = 6 kg; fsItem2: 5 kg × pop=2 = 10 kg.
        $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-lists/{$listId}", [
            'estimate_population' => 2,
        ])->assertOk();

        $this->assertDatabaseHas('shopping_list_items', [
            'fs_item_id' => $fsItem1->id,
            'qty' => 6.00,
        ]);
        $this->assertDatabaseHas('shopping_list_items', [
            'fs_item_id' => $fsItem2->id,
            'qty' => 10.00,
        ]);
    }

    // ===== MENU CYCLES =====

    public function test_fss_can_create_menu_cycle(): void
    {
        $response = $this->actingAs($this->rnd) // MenuCycles are created by RND, not FSS
            ->postJson('/api/fss/menu-cycles', [
                'name' => 'Week 1 Cycle',
                'cycle_days' => 7,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Week 1 Cycle')
            ->assertJsonPath('data.cycle_days', 7);

        $this->assertDatabaseHas('menu_cycles', ['name' => 'Week 1 Cycle']);
    }

    public function test_menu_cycle_rejects_non_week_span_and_non_monday_start(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/menu-cycles', [
                'name' => 'Invalid span',
                'cycle_days' => 5,
                'week_start_date' => '2026-06-16', // Tuesday
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cycle_days', 'week_start_date']);
    }

    public function test_fss_can_activate_menu_cycle(): void
    {
        $cycle = MenuCycle::factory()->create([
            'is_active' => false,
            'status' => 'draft',
            'activation_date' => null,
            'rnd_user_id' => $this->rnd->id,
        ]);
        // A cycle can only be activated once every weekday has a planned item.
        $this->planRemainingWeekdays($cycle);

        $response = $this->actingAs($this->rnd) // Activations can be done by RND
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/activate");

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
        // Per-head limit now lives in the shared Food Service settings.
        FoodServiceSetting::singleton()->update(['per_head_day_limit' => 12]);

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/fss/menu-cycles/{$cycle->uuid}/compute");

        $response->assertOk()
            ->assertJsonPath('data.budget_per_head_day', 12)
            ->assertJsonPath('data.days.Monday.budget_status', 'ok')
            ->assertJsonPath('data.within_budget', true);
    }

    public function test_menu_cycle_activation_allows_planned_days_missing_estimate_population(): void
    {
        $cycle = MenuCycle::factory()->create([
            'is_active' => false,
            'status' => 'draft',
            'activation_date' => null,
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
        ]);
        $fsItem = $this->makeFsItem();
        // Monday is planned but has NO estimate_population — the case under test.
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $fsItem->id,
            'quantity' => 1,
            'estimate_population' => null,
        ]);
        // Plan the rest of the week so the cycle is activatable; Monday keeps its null
        // population, so activation succeeding proves the missing-population case is allowed.
        $this->planRemainingWeekdays($cycle);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/activate");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.status', 'active');

        $cycle->refresh();
        $this->assertTrue($cycle->is_active);
        $this->assertEquals('active', $cycle->status);
    }

    public function test_menu_cycle_uses_date_span_name_when_name_is_omitted(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/menu-cycles', ['cycle_days' => 7, 'week_start_date' => '2026-06-15']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Weekly Menu — Jun 15–21, 2026');
    }

    // ===== BUDGETS =====

    public function test_rnd_can_setup_fiscal_year_budget(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets', [
                'fiscal_year' => 2026,
                'allocated_amount' => 50000.00,
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
        $this->assertEqualsWithDelta(80000, (float) $data['remaining'], 0.01); // 100k + 5k - 25k
        $this->assertEqualsWithDelta(25000, (float) $data['total_deductions'], 0.01);
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
                'type' => 'manual_addition',
                'amount' => 5000,
                'reason' => 'Extra allocation from admin',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('budget_ledger', [
            'fiscal_year' => 2026,
            'type' => 'manual_addition',
            'amount' => 5000,
        ]);
    }

    public function test_fss_cannot_add_manual_adjustment(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        $this->actingAs($this->fss)
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2026,
                'type' => 'manual_addition',
                'amount' => 5000,
                'reason' => 'Unauthorized attempt',
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

    public function test_complete_day_persists_served_population(): void
    {
        $fs = FsItem::factory()->create(['name' => 'Rice', 'base_unit' => 'g']);

        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday',
            'meal_type' => 'lunch', 'fs_item_id' => $fs->id, 'quantity' => 100,
            'estimate_population' => 5,
        ]);

        // Serve the day to 8 heads (override the cycle's default 5) — that headcount must be stored.
        $this->actingAs($this->fss)->postJson("/api/fss/menu-cycles/{$cycle->uuid}/complete-day", [
            'service_date' => '2026-06-15', // a Monday
            'population' => 8,
            'served_population' => 8,
        ])->assertCreated();

        $this->assertDatabaseHas('meal_prep_logs', [
            'menu_cycle_id' => $cycle->id,
            'population' => 8,
        ]);
    }

    public function test_cost_today_returns_active_cycle_per_head_from_menu(): void
    {
        $weekday = now('Asia/Manila')->format('l');
        // kg→g: 1000 base per purchase → ₱0.10/g; 100 g/head × 10 heads = 1000 g = ₱100/day; ÷10 = ₱10/head
        $fs = FsItem::factory()->create([
            'base_unit' => 'g', 'purchase_unit' => 'kg', 'purchase_price' => 100, 'units_per_purchase' => null,
        ]);
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id, 'is_active' => true, 'status' => 'active',
            'week_start_date' => now('Asia/Manila')->startOfWeek(Carbon::MONDAY)->toDateString(),
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => $weekday,
            'meal_type' => 'lunch', 'fs_item_id' => $fs->id, 'quantity' => 100,
            'estimate_population' => 10,
        ]);
        // Per-head cap is the shared Food Service setting.
        FoodServiceSetting::singleton()->update(['per_head_day_limit' => 50]);

        $res = $this->actingAs($this->fss)->getJson('/api/fss/menu-cycles/cost-today')->assertOk();
        $this->assertEqualsWithDelta(10, $res->json('data.cost_per_head'), 0.01);
        $this->assertEqualsWithDelta(50, $res->json('data.limit_per_head'), 0.01);
        $this->assertTrue($res->json('data.within_budget'));
        $this->assertSame($weekday, $res->json('data.weekday'));
    }

    public function test_generate_rounds_to_whole_purchase_units(): void
    {
        // 1 kg sack = 1000 g base; planned need 1300 g → must buy 2 sacks (2000 g).
        $fs = FsItem::factory()->create([
            'name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg',
            'purchase_price' => 50, 'units_per_purchase' => null,
        ]);

        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15']);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday',
            'meal_type' => 'lunch', 'fs_item_id' => $fs->id, 'quantity' => 1300,
            'estimate_population' => 1,
        ]);

        $response = $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15', 'end_date' => '2026-06-15', // a Monday
            'estimate_population' => 1,
        ]);

        $response->assertCreated();
        $item = collect($response->json('data.items'))->firstWhere('fs_item_id', $fs->id);
        $this->assertNotNull($item, 'Rice line should be present');
        $this->assertEqualsWithDelta(2, (float) $item['purchase_qty'], 0.01); // ceil(1300/1000)
        $this->assertSame('kg', $item['purchase_unit']);
        $this->assertEqualsWithDelta(50, (float) $item['purchase_price'], 0.01);
        $this->assertEqualsWithDelta(2000, (float) $item['qty'], 0.01);          // 2 sacks × 1000 g base
        $this->assertEqualsWithDelta(100, (float) $item['total'], 0.01);        // 2 × ₱50
    }

    public function test_generate_pos_carries_purchase_units(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 1000000]);
        $supplier = Supplier::factory()->create();
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'L', 'list_date' => '2026-06-08',
            'period_start' => '2026-06-08', 'period_end' => '2026-06-08', 'days_span' => 1,
            'list_type' => 'suggested', 'coverage_status' => 'full', 'estimate_population' => 1, 'status' => 'draft',
        ]);
        $list->items()->create([
            'ingredient_name' => 'Rice', 'qty' => 2000, 'unit' => 'g', 'supplier_id' => $supplier->id,
            'unit_price' => 0.05, 'total' => 100, 'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
        ]);

        // approve is RND-only; FSS gets 403
        $this->actingAs($this->fss)
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
            ->assertForbidden();

        // RND gets 201
        $response = $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve");
        $response->assertCreated();

        $this->assertDatabaseHas('purchase_orders', [
            'uuid' => $response->json('data.purchase_order_id'),
            'order_date' => '2026-06-08',
        ]);

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

        $this->actingAs($this->fss)->postJson("/api/fss/shopping-lists/{$list->uuid}/items", [
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
            'estimate_population' => 10,
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

        $response = $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/items", [
            'fs_item_id' => $fs->uuid,
            'qty' => 12,
            'unit' => 'piece',
            'supplier_id' => $supplier->uuid,
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
            'procurement_track' => 'supplies',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/items", [
            'fs_item_id' => $supply->uuid,
            'qty' => 3,
            'unit' => 'bottle',
            'supplier_id' => $supplier->uuid,
            'unit_price' => 80,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.ingredient_name', 'Dish Soap')
            ->assertJsonPath('data.item_type', 'supply')
            ->assertJsonPath('data.total', '240.00');
    }

    public function test_supplies_list_is_manual_supply_only_and_converts_to_supplies_po(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 1000000]);
        $supplier = Supplier::factory()->create();
        $supply = FsItem::factory()->create([
            'kind' => 'supply',
            'name' => 'Paper meal box',
            'base_unit' => 'pc',
            'purchase_unit' => 'pc',
            'purchase_price' => 3,
            'units_per_purchase' => 1,
            'default_supplier_id' => $supplier->id,
        ]);
        $ingredient = FsItem::factory()->create(['kind' => 'ingredient']);

        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/shopping-lists', [
                'name' => 'June supplies',
                'list_type' => 'manual',
                'procurement_track' => 'supplies',
                'list_date' => '2026-06-27',
            ])
            ->assertCreated()
            ->assertJsonPath('data.procurement_track', 'supplies')
            ->assertJsonPath('data.period_start', null)
            ->assertJsonPath('data.estimate_population', null);

        $listId = $response->json('data.id');

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$listId}/items", [
                'fs_item_id' => $ingredient->uuid,
                'qty' => 10,
                'unit_price' => 10,
            ])
            ->assertStatus(422);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$listId}/items", [
                'fs_item_id' => $supply->uuid,
                'qty' => 50,
                'unit_price' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.ingredient_name', 'Paper meal box')
            ->assertJsonPath('data.item_type', 'supply')
            ->assertJsonPath('data.unit', 'pc')
            ->assertJsonPath('data.total', '150.00');

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$listId}/approve")
            ->assertCreated();

        $this->assertDatabaseHas('purchase_orders', [
            'shopping_list_id' => ShoppingList::where('uuid', $listId)->value('id'),
            'procurement_track' => 'supplies',
            'lifecycle_status' => 'open_execution',
        ]);
        $this->assertDatabaseHas('program_project_activities', [
            'activity' => 'Food Service Supplies',
            'estimated_total_cost' => 150,
        ]);
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

        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/items", [
            'fs_item_id' => $fs->id,
            'qty' => 2,
            'unit' => 'piece',
        ])->assertStatus(422);

        $this->actingAs($this->rnd)->patchJson("/api/fss/shopping-list-items/{$item->uuid}", [
            'qty' => 5,
        ])->assertStatus(422);

        $this->actingAs($this->rnd)->deleteJson("/api/fss/shopping-list-items/{$item->uuid}")
            ->assertStatus(422);
    }

    public function test_received_status_preserves_purchase_history_without_updating_inventory_before_completion(): void
    {
        $fs = FsItem::factory()->create([
            'name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg', 'purchase_price' => 50,
        ]);

        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $this->rnd->id, 'status' => 'draft']);
        $po->items()->create([
            'fs_item_id' => $fs->id, 'description' => 'Rice',
            'qty' => 2000, 'unit' => 'g', 'unit_price' => 0.05, 'total_value' => 100,
            'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
            'actual_qty' => 1.875, 'actual_unit_price' => 52,
        ]);

        // PO update is RND-only
        $this->actingAs($this->rnd)->patchJson("/api/fss/purchase-orders/{$po->uuid}", ['status' => 'received'])
            ->assertOk();

        // 2 kg × 1000 g/kg = 2000 g added to stock.
        $this->assertDatabaseMissing('inventory', ['fs_item_id' => $fs->id]);
        $this->assertSame(50.0, (float) $fs->fresh()->purchase_price);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'fs_item_id' => $fs->id,
            'purchase_qty' => 2,
            'purchase_unit' => 'kg',
            'purchase_price' => 50,
        ]);
    }

    public function test_received_status_keeps_catalog_unit_until_po_completion(): void
    {
        $fs = FsItem::factory()->create([
            'name' => 'Rice',
            'base_unit' => 'g',
            'purchase_unit' => 'sack',
            'purchase_price' => 999,
            'units_per_purchase' => 5_000,
        ]);
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $this->rnd->id, 'status' => 'draft']);
        $po->items()->create([
            'fs_item_id' => $fs->id,
            'description' => 'Rice',
            'qty' => 2_000,
            'unit' => 'g',
            'unit_price' => 0.05,
            'total_value' => 100,
            'purchase_qty' => 2,
            'purchase_unit' => 'kg',
            'purchase_price' => 50,
        ]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", ['status' => 'received'])
            ->assertOk();

        $fs->refresh();
        $this->assertSame('sack', $fs->purchase_unit);
        $this->assertSame(5_000.0, (float) $fs->units_per_purchase);
        $this->assertSame(999.0, (float) $fs->purchase_price);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'purchase_unit' => 'kg',
            'purchase_price' => 50,
        ]);
    }

    public function test_received_status_does_not_change_recipe_cost_before_completion(): void
    {
        $fs = FsItem::factory()->create([
            'base_unit' => 'g',
            'purchase_unit' => 'kg',
            'purchase_price' => 100,
        ]);
        $recipe = FoodServiceRecipe::create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Rice serving',
            'servings' => 1,
            'cost' => 10,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id' => $fs->id,
            'quantity' => 100,
            'unit' => 'g',
        ]);
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $this->rnd->id, 'status' => 'draft']);
        $po->items()->create([
            'fs_item_id' => $fs->id,
            'description' => 'Rice',
            'qty' => 3_000,
            'unit' => 'g',
            'unit_price' => 0.08,
            'total_value' => 240,
            'purchase_qty' => 3,
            'purchase_unit' => 'kg',
            'purchase_price' => null,
        ]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", ['status' => 'received'])
            ->assertOk();

        $this->assertSame(100.0, (float) $fs->fresh()->purchase_price);
        $this->assertSame('kg', $fs->fresh()->purchase_unit);
        $this->assertSame(10.0, (float) $recipe->fresh()->cost);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'purchase_qty' => 3,
            'purchase_price' => null,
            'unit_price' => 0.08,
        ]);
    }

    public function test_received_status_keeps_later_catalog_edits_until_completion(): void
    {
        $fs = FsItem::factory()->create([
            'base_unit' => 'g',
            'purchase_unit' => 'kg',
            'purchase_price' => 100,
        ]);
        $recipe = FoodServiceRecipe::create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Frozen-cost serving',
            'servings' => 1,
            'cost' => 10,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id' => $fs->id,
            'quantity' => 100,
            'unit' => 'g',
        ]);
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $this->rnd->id, 'status' => 'draft']);
        $po->items()->create([
            'fs_item_id' => $fs->id,
            'description' => 'Rice',
            'qty' => 2_000,
            'unit' => 'g',
            'unit_price' => 0.05,
            'total_value' => 100,
            'purchase_qty' => 2,
            'purchase_unit' => 'kg',
            'purchase_price' => 50,
        ]);
        $fs->update([
            'purchase_unit' => 'sack',
            'units_per_purchase' => 5_000,
            'purchase_price' => 999,
        ]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", ['status' => 'received'])
            ->assertOk();

        $this->assertSame('sack', $fs->fresh()->purchase_unit);
        $this->assertSame(999.0, (float) $fs->fresh()->purchase_price);
        $this->assertSame(10.0, (float) $recipe->fresh()->cost);
        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", ['status' => 'received'])
            ->assertOk();
        $this->assertSame(999.0, (float) $fs->fresh()->purchase_price);
    }

    public function test_received_status_does_not_lock_inventory_before_completion(): void
    {
        $fs = FsItem::factory()->create(['base_unit' => 'g', 'purchase_unit' => 'kg']);
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $this->rnd->id, 'status' => 'draft']);
        $po->items()->create([
            'fs_item_id' => $fs->id,
            'description' => 'Rice',
            'qty' => 1_000,
            'unit' => 'g',
            'unit_price' => 0.05,
            'total_value' => 50,
        ]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", ['status' => 'received'])
            ->assertOk();

        $this->assertFalse(collect($queries)->contains(
            fn (string $sql): bool => str_contains($sql, 'from `fs_items`')
                && str_contains($sql, 'for update'),
        ));
    }

    // ===== R2.4 SCOPE ENFORCEMENT: gate assertions =====

    public function test_fss_gets_403_on_fs_item_update(): void
    {
        $fsItem = $this->makeFsItem(['purchase_price' => 10.00]);

        $this->actingAs($this->fss)
            ->patchJson("/api/fss/fs-items/{$fsItem->uuid}", ['purchase_price' => 20.00])
            ->assertForbidden();
    }

    public function test_rnd_can_update_fs_item(): void
    {
        $fsItem = $this->makeFsItem(['purchase_price' => 10.00]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/fs-items/{$fsItem->uuid}", ['purchase_price' => 20.00])
            ->assertOk();
    }

    public function test_compatible_inventory_unit_change_preserves_price_and_recipe_quantity(): void
    {
        $item = $this->makeFsItem(['name' => 'Beef', 'base_unit' => 'kg', 'purchase_unit' => 'kg', 'purchase_price' => 362]);
        $recipe = FoodServiceRecipe::create(['rnd_user_id' => $this->rnd->id, 'name' => 'Beef stew', 'servings' => 50]);
        $ingredient = FoodServiceRecipeIngredient::create(['food_service_recipe_id' => $recipe->id, 'fs_item_id' => $item->id, 'quantity' => 3500, 'unit' => 'g']);

        $this->actingAs($this->rnd)->patchJson("/api/fss/fs-items/{$item->uuid}", ['base_unit' => 'g'])->assertOk();

        $this->assertSame('g', $item->fresh()->base_unit);
        $this->assertEqualsWithDelta(0.362, $item->fresh()->unit_cost, 0.000001);
        $this->assertSame('g', $ingredient->fresh()->unit);
        $this->assertEqualsWithDelta(3500, (float) $ingredient->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(1267, (float) $recipe->fresh()->cost, 0.01);
    }

    public function test_incompatible_inventory_unit_change_requires_recipe_corrections_without_mutating_either_record(): void
    {
        $item = $this->makeFsItem(['name' => 'Salt', 'base_unit' => 'kg', 'purchase_unit' => 'kg', 'purchase_price' => 50]);
        $recipe = FoodServiceRecipe::create(['rnd_user_id' => $this->rnd->id, 'name' => 'Soup', 'servings' => 10]);
        $ingredient = FoodServiceRecipeIngredient::create(['food_service_recipe_id' => $recipe->id, 'fs_item_id' => $item->id, 'quantity' => 2, 'unit' => 'tbsp']);

        $this->actingAs($this->rnd)->patchJson("/api/fss/fs-items/{$item->uuid}", ['base_unit' => 'g'])
            ->assertStatus(422)->assertJsonPath('affected_recipes.0.recipe_id', $recipe->uuid);
        $this->assertSame('kg', $item->fresh()->base_unit);
        $this->assertSame('tbsp', $ingredient->fresh()->unit);
    }

    public function test_unrelated_inventory_edit_does_not_block_legacy_incompatible_auto_recipe(): void
    {
        $item = $this->makeFsItem(['name' => 'Salt', 'base_unit' => 'kg', 'include_in_generated_lists' => true]);
        $recipe = FoodServiceRecipe::create(['rnd_user_id' => $this->rnd->id, 'name' => 'Soup', 'servings' => 10]);
        FoodServiceRecipeIngredient::create(['food_service_recipe_id' => $recipe->id, 'fs_item_id' => $item->id, 'quantity' => 2, 'unit' => 'tbsp']);

        $this->actingAs($this->rnd)->patchJson("/api/fss/fs-items/{$item->uuid}", [
            'name' => 'Sea salt', 'include_in_generated_lists' => true, 'base_unit' => 'kg',
        ])->assertOk();
        $this->assertSame('Sea salt', $item->fresh()->name);
    }

    public function test_inventory_auto_switch_saves_all_recipe_corrections_together(): void
    {
        $item = $this->makeFsItem(['name' => 'Salt', 'base_unit' => 'kg', 'include_in_generated_lists' => false]);
        $recipes = collect(['Soup', 'Stew'])->map(function (string $name) use ($item) {
            $recipe = FoodServiceRecipe::create(['rnd_user_id' => $this->rnd->id, 'name' => $name, 'servings' => 10]);
            FoodServiceRecipeIngredient::create(['food_service_recipe_id' => $recipe->id, 'fs_item_id' => $item->id, 'quantity' => 2, 'unit' => 'tbsp']);

            return $recipe;
        });
        $url = "/api/fss/fs-items/{$item->uuid}";
        $affected = $this->actingAs($this->rnd)->patchJson($url, [
            'include_in_generated_lists' => true,
        ])->assertStatus(422)->json('affected_recipes');
        $this->assertCount(2, $affected);
        $oneCorrection = [[
            'recipe_id' => $affected[0]['recipe_id'], 'line_index' => $affected[0]['line_index'],
            'quantity' => 0.1, 'unit' => 'kg',
        ]];
        $this->actingAs($this->rnd)->patchJson($url, [
            'include_in_generated_lists' => true, 'recipe_corrections' => $oneCorrection,
        ])->assertStatus(422);
        $this->assertFalse($item->fresh()->include_in_generated_lists);
        $this->assertSame('tbsp', $recipes[0]->ingredients()->first()->unit);

        $corrections = collect($affected)->map(fn (array $line): array => [
            'recipe_id' => $line['recipe_id'], 'line_index' => $line['line_index'],
            'quantity' => 0.1, 'unit' => 'kg',
        ])->all();
        $this->actingAs($this->rnd)->patchJson($url, [
            'include_in_generated_lists' => true, 'recipe_corrections' => $corrections,
        ])->assertOk();
        $this->assertTrue($item->fresh()->include_in_generated_lists);
        foreach ($recipes as $recipe) {
            $this->assertSame('kg', $recipe->ingredients()->first()->unit);
            $this->assertEquals(0.1, (float) $recipe->ingredients()->first()->quantity);
            $this->assertSame(10, $recipe->fresh()->servings);
        }
    }

    public function test_every_recipe_ingredient_needs_a_quantity_and_adjust_to_taste_is_separate(): void
    {
        $salt = $this->makeFsItem(['name' => 'Salt', 'base_unit' => 'kg', 'include_in_generated_lists' => false]);
        $this->actingAs($this->rnd)->postJson('/api/fss/food-service-recipes', [
            'name' => 'Unmeasured soup', 'servings' => 10,
            'ingredients' => [['fs_item_id' => $salt->uuid, 'quantity' => null, 'unit' => 'to taste']],
        ])->assertStatus(422);

        $food = $this->actingAs($this->rnd)->postJson('/api/fss/food-service-recipes', [
            'name' => 'Soup', 'servings' => 10,
            'ingredients' => [['fs_item_id' => $salt->uuid, 'quantity' => 2, 'unit' => 'tbsp', 'adjust_to_taste' => true]],
        ])->assertCreated()->json('data');
        $this->assertEquals(2, $food['ingredients'][0]['quantity']);
        $this->assertSame('tbsp', $food['ingredients'][0]['unit']);
        $this->assertTrue($food['ingredients'][0]['adjust_to_taste']);
        $this->assertEquals(0, $food['cost']);

        $rice = $this->makeFsItem(['name' => 'Rice', 'base_unit' => 'kg', 'include_in_generated_lists' => true]);
        $this->actingAs($this->rnd)->postJson('/api/fss/food-service-recipes', [
            'name' => 'Rice soup', 'servings' => 10,
            'ingredients' => [['fs_item_id' => $rice->uuid, 'quantity' => null, 'unit' => 'to taste']],
        ])->assertStatus(422);
    }

    public function test_new_auto_inventory_item_cannot_use_sack(): void
    {
        $this->actingAs($this->rnd)->postJson('/api/fss/fs-items', [
            'name' => 'Rice sack', 'kind' => 'ingredient', 'base_unit' => 'sack',
            'purchase_price' => 2000, 'include_in_generated_lists' => true,
        ])->assertStatus(422);
    }

    public function test_fss_can_view_single_fs_item_profile_for_menu_detail(): void
    {
        $fsItem = $this->makeFsItem([
            'kind' => 'ingredient',
            'name' => 'Banana',
            'category' => 'Fruit',
            'base_unit' => 'piece',
            'purchase_unit' => 'piece',
            'purchase_price' => 8,
            'units_per_purchase' => 1,
        ]);

        $this->actingAs($this->fss)
            ->getJson("/api/fss/fs-items/{$fsItem->uuid}/profile?population=30&quantity=1")
            ->assertOk()
            ->assertJsonPath('data.id', $fsItem->uuid)
            ->assertJsonPath('data.name', 'Banana')
            ->assertJsonPath('data.kind', 'ingredient')
            ->assertJsonPath('data.population', 30)
            ->assertJsonPath('data.quantity', 1)
            ->assertJsonPath('data.total_quantity', 30)
            ->assertJsonPath('data.total_cost', 240)
            ->assertJsonPath('data.formula', 'total_cost = quantity_per_head * population * unit_cost');
    }

    public function test_insights_routes_are_removed(): void
    {
        // Insights/graphs were removed in the food-service redesign.
        $this->actingAs($this->fss)->getJson('/api/fss/insights/spend-by-supplier')->assertNotFound();
        $this->actingAs($this->fss)->getJson('/api/fss/insights/budget-burn')->assertNotFound();
        $this->actingAs($this->fss)->getJson('/api/fss/insights/per-head-actual-vs-limit')->assertNotFound();
        $this->actingAs($this->fss)->getJson('/api/fss/insights/procurement-deduction-timeline')->assertNotFound();
    }

    public function test_fss_gets_404_on_deleted_cleaning_log_routes(): void
    {
        $this->actingAs($this->fss)->getJson('/api/fss/cleaning-logs')->assertNotFound();
        $this->actingAs($this->fss)->postJson('/api/fss/cleaning-logs', ['item_name' => 'x'])->assertNotFound();
    }

    public function test_rnd_can_add_shopping_list_item(): void
    {
        $supplier = Supplier::factory()->create();
        $fsItem = $this->makeFsItem();

        // Shopping list item add
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'L2', 'list_date' => '2026-06-08',
            'list_type' => 'manual', 'status' => 'draft',
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/items", [
                'fs_item_id' => $fsItem->uuid,
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
            'actual_qty' => 1.875, 'actual_unit_price' => 52,
        ]);

        $report = new Report(['type' => 'procurement_pack', 'parameters' => ['purchase_order_id' => $po->id]]);
        $data = (new ProcurementPackGenerator)->data($report);

        $pack = $data['packs'][0];
        $this->assertEqualsWithDelta(1.875, (float) $pack['air_items'][0]['quantity'], 0.001);
        $this->assertSame('kg', $pack['air_items'][0]['unit']);
        $this->assertEqualsWithDelta(52, (float) $pack['statement_items'][0]['unit_price'], 0.01);
        $this->assertEqualsWithDelta(97.50, (float) $pack['grand_total'], 0.01);
    }

    public function test_procurement_pack_includes_every_po_line_regardless_of_shopping_list_source(): void
    {
        $generatedList = ShoppingList::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'list_type' => 'suggested',
            'status' => 'converted',
        ]);
        $generatedPo = PurchaseOrder::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'shopping_list_id' => $generatedList->id,
        ]);
        $plannedSupplier = Supplier::factory()->create(['name' => 'Planned Vendor']);
        $plannedGroup = $generatedPo->vendorGroups()->create([
            'supplier_id' => $plannedSupplier->id,
            'status' => 'pending',
            'total_amount' => 20,
        ]);
        $generatedLines = [];
        foreach (['Menu ingredient', 'Manual event addition'] as $description) {
            $generatedLines[] = $generatedPo->items()->create([
                'vendor_group_id' => $plannedGroup->id,
                'description' => $description,
                'qty' => 1,
                'unit' => 'piece',
                'unit_price' => 10,
                'total_value' => 10,
            ]);
        }

        $manualList = ShoppingList::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'list_type' => 'manual',
            'status' => 'converted',
        ]);
        $manualPo = PurchaseOrder::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'shopping_list_id' => $manualList->id,
        ]);
        $manualSupplier = Supplier::factory()->create(['name' => 'Manual List Vendor']);
        $manualGroup = $manualPo->vendorGroups()->create([
            'supplier_id' => $manualSupplier->id,
            'status' => 'pending',
            'total_amount' => 50,
        ]);
        $manualPo->items()->create([
            'vendor_group_id' => $manualGroup->id,
            'description' => 'Manual supply purchase',
            'qty' => 2,
            'unit' => 'box',
            'unit_price' => 25,
            'total_value' => 50,
        ]);

        $generatedPack = (new ProcurementPackGenerator)->data(new Report([
            'type' => 'procurement_pack',
            'parameters' => ['purchase_order_id' => $generatedPo->id],
        ]));
        $manualPack = (new ProcurementPackGenerator)->data(new Report([
            'type' => 'procurement_pack',
            'parameters' => ['purchase_order_id' => $manualPo->id],
        ]));

        $this->assertSame(
            ['Menu ingredient', 'Manual event addition'],
            array_column($generatedPack['packs'][0]['statement_items'], 'item'),
        );
        $this->assertSame(
            ['Manual supply purchase'],
            array_column($manualPack['packs'][0]['statement_items'], 'item'),
        );

        $actualSupplier = Supplier::factory()->create(['name' => 'Actual Vendor']);
        $this->actingAs($this->rnd)->patchJson("/api/fss/purchase-order-vendor-groups/{$plannedGroup->uuid}", [
            'supplier_id' => $actualSupplier->uuid,
            'item_id' => $generatedLines[1]->id,
        ])->assertOk();
        $movedPack = (new ProcurementPackGenerator)->data(new Report([
            'type' => 'procurement_pack',
            'parameters' => ['purchase_order_id' => $generatedPo->id],
        ]));

        $this->assertCount(2, $movedPack['packs']);
        $this->assertEqualsCanonicalizing(
            ['Menu ingredient', 'Manual event addition'],
            collect($movedPack['packs'])->flatMap(fn (array $pack) => array_column($pack['statement_items'], 'item'))->all(),
        );
        $actualVendorPack = collect($movedPack['packs'])
            ->first(fn (array $pack) => $pack['supplier']?->name === 'Actual Vendor');
        $this->assertNotNull($actualVendorPack);
        $this->assertSame(['Manual event addition'], array_column($actualVendorPack['statement_items'], 'item'));
    }

    // ===== SERVED-POPULATION BACKFILL (any cycle day) =====

    public function test_fss_can_set_served_population_for_a_day_with_no_log_yet(): void
    {
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15', // Monday
            'is_active' => true,
            'status' => 'active',
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Wednesday',
            'meal_type' => 'lunch',
            'fs_item_id' => $this->makeFsItem()->id,
            'estimate_population' => 40,
        ]);

        // No meal_prep_log exists for this date — the backfill must create one.
        $this->actingAs($this->fss)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/served-population", [
                'service_date' => '2026-06-17', // Wednesday
                'served_population' => 33,
            ])
            ->assertOk()
            ->assertJsonPath('data.served_population', 33);

        $this->assertDatabaseHas('meal_prep_logs', [
            'menu_cycle_id' => $cycle->id,
            'service_date' => '2026-06-17',
            'served_population' => 33,
            'population' => 40, // pulled from the weekday's planned estimate
        ]);
    }

    public function test_setting_served_population_again_updates_the_same_log(): void
    {
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
            'is_active' => true,
            'status' => 'active',
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Wednesday',
            'meal_type' => 'lunch',
            'fs_item_id' => $this->makeFsItem()->id,
            'estimate_population' => 40,
        ]);

        $url = "/api/fss/menu-cycles/{$cycle->uuid}/served-population";
        $this->actingAs($this->fss)->patchJson($url, ['service_date' => '2026-06-17', 'served_population' => 30])->assertOk();
        $this->actingAs($this->fss)->patchJson($url, ['service_date' => '2026-06-17', 'served_population' => 38])->assertOk();

        $this->assertSame(1, MealPrepLog::where('menu_cycle_id', $cycle->id)->whereDate('service_date', '2026-06-17')->count());
        $this->assertDatabaseHas('meal_prep_logs', [
            'menu_cycle_id' => $cycle->id,
            'service_date' => '2026-06-17',
            'served_population' => 38,
        ]);
    }

    public function test_meal_prep_log_filter_accepts_public_menu_cycle_id(): void
    {
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id]);
        MealPrepLog::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'service_date' => '2026-06-17',
        ]);

        $this->actingAs($this->fss)
            ->getJson("/api/fss/meal-prep-logs?menu_cycle_id={$cycle->uuid}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.service_date', '2026-06-17');
    }

    public function test_served_population_rejects_future_or_unplanned_service_dates(): void
    {
        $today = now('Asia/Manila')->startOfWeek();
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => $today->toDateString(),
            'cycle_days' => 7,
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => $today->format('l'),
            'meal_type' => 'lunch',
            'estimate_population' => 40,
        ]);

        $this->actingAs($this->fss)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/served-population", [
                'service_date' => now('Asia/Manila')->addDay()->toDateString(),
                'served_population' => 30,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_date');

        $unplannedDate = $today->copy()->addDay();
        $this->actingAs($this->fss)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/served-population", [
                'service_date' => $unplannedDate->toDateString(),
                'served_population' => 30,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_date');
    }
}
