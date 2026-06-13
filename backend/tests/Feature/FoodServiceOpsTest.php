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
use App\Services\BudgetActualService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    // ===== SUPPLIERS =====

    public function test_fss_can_create_supplier(): void
    {
        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/suppliers', [
                'name'    => 'Green Valley Farm',
                'contact' => '0912-345-6789',
                'address' => 'Quezon City, Philippines',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Green Valley Farm');

        $this->assertDatabaseHas('suppliers', ['name' => 'Green Valley Farm']);
    }

    public function test_fss_can_list_suppliers(): void
    {
        Supplier::factory(3)->create();

        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/suppliers');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_supplier_creation_requires_name(): void
    {
        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/suppliers', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ===== PURCHASE ORDERS =====

    public function test_fss_can_create_purchase_order(): void
    {
        $supplier = Supplier::factory()->create();
        $fsItem   = $this->makeFsItem();

        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/purchase-orders', [
                'supplier_id'  => $supplier->id,
                'order_date'   => '2026-06-10',
                'items'        => [
                    ['fs_item_id' => $fsItem->id, 'qty' => 50, 'unit_price' => 25.00],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier->id]);
        $this->assertDatabaseHas('purchase_order_items', ['fs_item_id' => $fsItem->id]);
    }

    public function test_fss_can_update_purchase_order_status(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->fss)
            ->patchJson("/api/fss/purchase-orders/{$po->id}", [
                'status' => 'received',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'received');
    }

    public function test_fss_po_status_received_updates_inventory(): void
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

        $response = $this->actingAs($this->fss)
            ->patchJson("/api/fss/purchase-orders/{$po->id}", [
                'status' => 'received',
            ]);

        $response->assertOk();
        
        $inventory->refresh();
        $this->assertEquals(15.00, $inventory->quantity_in_stock);
    }

    public function test_purchase_order_item_validation(): void
    {
        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/purchase-orders', [
                'items' => [
                    ['unit_price' => 25.00]
                ]
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.qty']);
    }

    // ===== SHOPPING LISTS =====

    public function test_fss_can_generate_shopping_list(): void
    {
        $cycle = MenuCycle::factory()->create([
            'is_active' => true,
            'status' => 'active',
            'rnd_user_id' => $this->rnd->id,
        ]);
        $fsItem = $this->makeFsItem();
        Inventory::factory()->create([
            'fs_item_id'             => $fsItem->id,
            'quantity_in_stock'        => 5,
            'minimum_stock_threshold'  => 20,
        ]);

        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/shopping-lists/generate', [
                'menu_cycle_id' => $cycle->id,
                'start_date'    => '2026-06-15', // Monday
                'end_date'      => '2026-06-21', // Sunday (one full week)
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'items']]);

        $this->assertDatabaseHas('shopping_lists', ['status' => 'draft']);
    }

    public function test_fss_can_list_shopping_lists(): void
    {
        ShoppingList::factory(2)->create(['fss_user_id' => $this->fss->id]);

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
            'minimum_stock_threshold'  => 5,
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
            'minimum_stock_threshold'  => 12,
            'unit'                     => 'kg',
        ]);

        // 2. Create active menu cycle
        $cycle = MenuCycle::factory()->create([
            'is_active'   => true,
            'status'      => 'active',
            'rnd_user_id' => $this->rnd->id,
            'population'  => 2, // Population scaled factor
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
        ]);

        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week'   => 'Monday',
            'meal_type'     => 'lunch',
            'fs_item_id'    => $fsItem2->id,
            'quantity'      => 5.00, // Direct food item. Total fsItem2 needed = 5
        ]);

        // 5. Generate shopping list suggestion for a single Monday.
        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/shopping-lists/generate', [
                'menu_cycle_id' => $cycle->id,
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

    public function test_menu_cycle_requires_name(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/menu-cycles', ['cycle_days' => 7]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ===== BUDGETS =====

    public function test_fss_can_create_budget(): void
    {
        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/budgets', [
                'period_start'    => '2026-06-01',
                'period_end'      => '2026-06-30',
                'allocated_amount' => 50000.00,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.allocated_amount', '50000.00');

        $this->assertDatabaseHas('budgets', ['allocated_amount' => 50000.00]);
    }

    public function test_fss_can_log_daily_budget_expense(): void
    {
        $budget = Budget::factory()->create(['fss_user_id' => $this->fss->id, 'allocated_amount' => 50000]);

        $response = $this->actingAs($this->fss)
            ->postJson("/api/fss/budgets/{$budget->id}/daily-logs", [
                'log_date'   => '2026-06-10',
                'spent'      => 1500.00,
                'notes'      => 'Market purchase',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.spent', '1500.00');

        $this->assertDatabaseHas('budget_daily_logs', ['budget_id' => $budget->id, 'spent' => 1500.00]);
    }

    public function test_budget_requires_allocated_amount(): void
    {
        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/budgets', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['allocated_amount']);
    }

    public function test_budget_actual_uses_consumption_when_a_day_is_served(): void
    {
        $budget = Budget::factory()->create([
            'fss_user_id' => $this->fss->id,
            'budget_per_head_day' => 100, 'population' => 10, // cap = 1000/day
        ]);
        $cycle = MenuCycle::factory()->create();
        MealPrepLog::create([
            'menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10',
            'status' => 'completed', 'total_value' => 1200, 'has_shortfall' => false,
        ]);

        $result = BudgetActualService::dailySeries($budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-11'));

        $this->assertSame('consumption', $result['source']);
        $byDate = collect($result['days'])->keyBy('date');
        $this->assertEqualsWithDelta(1200, $byDate['2026-06-10']['actual'], 0.01);
        $this->assertEqualsWithDelta(0, $byDate['2026-06-09']['actual'], 0.01);
        $this->assertEqualsWithDelta(1000, $byDate['2026-06-10']['planned'], 0.01); // cap
    }

    public function test_budget_actual_falls_back_to_purchases_when_nothing_served(): void
    {
        $budget = Budget::factory()->create([
            'fss_user_id' => $this->fss->id,
            'budget_per_head_day' => 100, 'population' => 10,
        ]);
        PurchaseOrder::factory()->create([
            'fss_user_id' => $this->fss->id,
            'status' => 'received', 'received_date' => '2026-06-10', 'total_amount' => 800,
        ]);

        $result = BudgetActualService::dailySeries($budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-11'));

        $this->assertSame('purchases', $result['source']);
        $byDate = collect($result['days'])->keyBy('date');
        $this->assertEqualsWithDelta(800, $byDate['2026-06-10']['actual'], 0.01);
        $this->assertEqualsWithDelta(800, $result['cash_flow'], 0.01);
    }

    public function test_summary_endpoint_reports_consumption_source_and_cash_flow(): void
    {
        $budget = Budget::factory()->create([
            'fss_user_id' => $this->fss->id,
            'budget_per_head_day' => 100, 'population' => 10,
            'period_start' => '2026-06-09', 'period_end' => '2026-06-11',
        ]);
        $cycle = MenuCycle::factory()->create();
        MealPrepLog::create([
            'menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10',
            'status' => 'completed', 'total_value' => 1200, 'has_shortfall' => false,
        ]);
        PurchaseOrder::factory()->create([
            'fss_user_id' => $this->fss->id,
            'status' => 'received', 'received_date' => '2026-06-10', 'total_amount' => 800,
        ]);

        $response = $this->actingAs($this->fss)
            ->getJson("/api/fss/budgets/{$budget->id}/summary?start=2026-06-09&end=2026-06-11&granularity=day");

        $response->assertOk()
            ->assertJsonPath('data.source', 'consumption')
            ->assertJsonPath('data.actual', fn ($v) => abs((float) $v - 1200) < 0.01)   // consumption only, POs excluded
            ->assertJsonPath('data.cash_flow', fn ($v) => abs((float) $v - 800) < 0.01); // POs surfaced separately
    }

    public function test_budget_report_actual_matches_consumption(): void
    {
        $budget = Budget::factory()->create([
            'fss_user_id' => $this->fss->id,
            'budget_per_head_day' => 100, 'population' => 10,
            'period_start' => '2026-06-09', 'period_end' => '2026-06-11',
        ]);
        $cycle = MenuCycle::factory()->create();
        MealPrepLog::create([
            'menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10',
            'status' => 'completed', 'total_value' => 1200, 'has_shortfall' => false,
        ]);

        $report = new \App\Models\Report(['type' => 'budget_report', 'parameters' => [
            'budget_id' => $budget->id, 'granularity' => 'day',
        ]]);
        $data = (new \App\Services\Reports\Generators\BudgetReportGenerator())->data($report);

        $this->assertEqualsWithDelta(1200, $data['summary']['actual'], 0.01);
    }

    public function test_budget_report_remaining_uses_cash_axis_not_food_served(): void
    {
        $budget = Budget::factory()->create([
            'fss_user_id' => $this->fss->id, 'allocated_amount' => 5000,
            'budget_per_head_day' => 100, 'population' => 10,
            'period_start' => '2026-06-09', 'period_end' => '2026-06-11',
        ]);
        $cycle = MenuCycle::factory()->create();
        MealPrepLog::create([ // food served worth 1200 — must NOT drive "remaining"
            'menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10',
            'status' => 'completed', 'total_value' => 1200, 'has_shortfall' => false,
        ]);
        PurchaseOrder::factory()->create([ // cash out 800 — this is what "remaining" subtracts
            'fss_user_id' => $this->fss->id,
            'status' => 'received', 'received_date' => '2026-06-10', 'total_amount' => 800,
        ]);

        $report = new \App\Models\Report(['type' => 'budget_report', 'parameters' => [
            'budget_id' => $budget->id, 'granularity' => 'day',
        ]]);
        $data = (new \App\Services\Reports\Generators\BudgetReportGenerator())->data($report);

        $this->assertEqualsWithDelta(4200, $data['remaining'], 0.01);  // 5000 allocated − 800 cash, NOT − 1200 food
        $this->assertEqualsWithDelta(800, $data['cash_flow'], 0.01);
        $this->assertSame(1, $data['days_served']);
    }

    public function test_summary_reports_days_served_count(): void
    {
        $budget = Budget::factory()->create([
            'fss_user_id' => $this->fss->id,
            'budget_per_head_day' => 100, 'population' => 10,
            'period_start' => '2026-06-09', 'period_end' => '2026-06-12',
        ]);
        $cycle = MenuCycle::factory()->create();
        foreach (['2026-06-10', '2026-06-11'] as $d) {
            MealPrepLog::create([
                'menu_cycle_id' => $cycle->id, 'service_date' => $d,
                'status' => 'completed', 'total_value' => 500, 'has_shortfall' => false,
            ]);
        }

        $response = $this->actingAs($this->fss)
            ->getJson("/api/fss/budgets/{$budget->id}/summary?start=2026-06-09&end=2026-06-12&granularity=day");

        $response->assertOk()->assertJsonPath('data.days_served', 2);
    }

    public function test_generate_rounds_to_whole_purchase_units(): void
    {
        // 1 kg sack = 1000 g base; planned need 1300 g → must buy 2 sacks (2000 g).
        $fs = FsItem::factory()->create([
            'name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg',
            'purchase_price' => 50, 'units_per_purchase' => null,
        ]);
        Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 0, 'unit' => 'g']);

        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'population' => 1]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday',
            'meal_type' => 'lunch', 'fs_item_id' => $fs->id, 'quantity' => 1300,
        ]);

        $response = $this->actingAs($this->fss)->postJson('/api/fss/shopping-lists/generate', [
            'menu_cycle_id' => $cycle->id, 'start_date' => '2026-06-15', 'end_date' => '2026-06-15', // a Monday
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
            'fss_user_id' => $this->fss->id, 'name' => 'L', 'list_date' => '2026-06-08',
            'list_type' => 'suggested', 'status' => 'draft',
        ]);
        $list->items()->create([
            'ingredient_name' => 'Rice', 'qty' => 2000, 'unit' => 'g', 'supplier_id' => $supplier->id,
            'unit_price' => 0.05, 'total' => 100, 'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
        ]);

        $response = $this->actingAs($this->fss)->postJson("/api/fss/shopping-lists/{$list->id}/generate-pos");
        $response->assertCreated();

        $this->assertDatabaseHas('purchase_order_items', [
            'description' => 'Rice', 'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
        ]);
    }
}
