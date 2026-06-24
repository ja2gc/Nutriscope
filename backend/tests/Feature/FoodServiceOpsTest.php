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
        $supplier = Supplier::factory()->create();
        $fsItem   = $this->makeFsItem();
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'L', 'list_date' => '2026-06-10',
            'list_type' => 'manual', 'status' => 'draft',
        ]);
        $list->items()->create([
            'fs_item_id' => $fsItem->id, 'ingredient_name' => $fsItem->name, 'qty' => 50, 'unit' => 'kg',
            'supplier_id' => $supplier->id, 'unit_price' => 25.00, 'total' => 1250,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->id}/approve")
            ->assertCreated();

        $this->assertDatabaseHas('purchase_orders', ['shopping_list_id' => $list->id, 'supplier_id' => $supplier->id]);
        $this->assertDatabaseHas('purchase_order_items', ['fs_item_id' => $fsItem->id]);
        $this->assertDatabaseHas('shopping_lists', ['id' => $list->id, 'status' => 'finalized']);

        // One-shot: re-approving is rejected.
        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->id}/approve")
            ->assertStatus(422);
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

        $response = $this->actingAs($this->fss)
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

        $response = $this->actingAs($this->fss)->postJson('/api/fss/shopping-lists/generate', [
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
        $response = $this->actingAs($this->fss)->postJson('/api/fss/shopping-lists/generate', [
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
        $response = $this->actingAs($this->fss)->postJson('/api/fss/shopping-lists/generate', [
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
        $response = $this->actingAs($this->fss)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2030-01-01',
            'end_date'   => '2030-01-03',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('uncovered_dates', ['2030-01-01', '2030-01-02', '2030-01-03']);
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
        $response = $this->actingAs($this->fss)
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

    public function test_menu_cycle_requires_name(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/menu-cycles', ['cycle_days' => 7]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ===== BUDGETS =====

    public function test_rnd_can_create_budget(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets', [
                'period_start'    => '2026-06-01',
                'period_end'      => '2026-06-30',
                'allocated_amount' => 50000.00,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.allocated_amount', '50000.00');

        $this->assertDatabaseHas('budgets', ['allocated_amount' => 50000.00]);
    }

    public function test_rnd_can_log_daily_budget_expense(): void
    {
        $budget = Budget::factory()->create(['rnd_user_id' => $this->rnd->id, 'allocated_amount' => 50000]);

        $response = $this->actingAs($this->rnd)
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
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['allocated_amount']);
    }

    public function test_budget_actual_uses_consumption_when_a_day_is_served(): void
    {
        $budget = Budget::factory()->create([
            'rnd_user_id' => $this->rnd->id,
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

    public function test_budget_actual_scopes_consumption_to_budget_menu_cycle(): void
    {
        $cycle = MenuCycle::factory()->create();
        $otherCycle = MenuCycle::factory()->create();
        $budget = Budget::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'menu_cycle_id' => $cycle->id,
            'budget_per_head_day' => 100,
            'population' => 10,
        ]);
        MealPrepLog::create([
            'menu_cycle_id' => $cycle->id,
            'service_date' => '2026-06-10',
            'status' => 'completed',
            'total_value' => 500,
            'population' => 5,
            'served_population' => 5,
            'has_shortfall' => false,
        ]);
        MealPrepLog::create([
            'menu_cycle_id' => $otherCycle->id,
            'service_date' => '2026-06-10',
            'status' => 'completed',
            'total_value' => 900,
            'population' => 9,
            'served_population' => 9,
            'has_shortfall' => false,
        ]);

        $result = BudgetActualService::dailySeries($budget, Carbon::parse('2026-06-10'), Carbon::parse('2026-06-10'));

        $this->assertSame('consumption', $result['source']);
        $this->assertEqualsWithDelta(500, $result['days'][0]['actual'], 0.01);
        $this->assertSame(5, $result['days'][0]['population']);
    }

    public function test_budget_actual_uses_event_allocation_as_event_day_cap(): void
    {
        $cycle = MenuCycle::factory()->create(['week_start_date' => '2026-06-15']);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Wednesday',
            'meal_type' => 'lunch',
            'quantity' => 1,
            'estimate_population' => 10,
            'is_event' => true,
            'event_allocation' => 2500,
        ]);
        $budget = Budget::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'menu_cycle_id' => $cycle->id,
            'budget_per_head_day' => 100,
            'population' => 10,
        ]);

        $result = BudgetActualService::dailySeries($budget, Carbon::parse('2026-06-17'), Carbon::parse('2026-06-17'));

        $this->assertEqualsWithDelta(2500, $result['days'][0]['planned'], 0.01);
        $this->assertTrue($result['days'][0]['event']);
    }

    public function test_budget_actual_falls_back_to_purchases_when_nothing_served(): void
    {
        $budget = Budget::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'budget_per_head_day' => 100, 'population' => 10,
        ]);
        PurchaseOrder::factory()->create([
            'rnd_user_id' => $this->fss->id,
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
            'rnd_user_id' => $this->rnd->id,
            'budget_per_head_day' => 100, 'population' => 10,
            'period_start' => '2026-06-09', 'period_end' => '2026-06-11',
        ]);
        $cycle = MenuCycle::factory()->create();
        MealPrepLog::create([
            'menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10',
            'status' => 'completed', 'total_value' => 1200, 'has_shortfall' => false,
        ]);
        PurchaseOrder::factory()->create([
            'rnd_user_id' => $this->fss->id,
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
            'rnd_user_id' => $this->rnd->id,
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
            'rnd_user_id' => $this->rnd->id, 'allocated_amount' => 5000,
            'budget_per_head_day' => 100, 'population' => 10,
            'period_start' => '2026-06-09', 'period_end' => '2026-06-11',
        ]);
        $cycle = MenuCycle::factory()->create();
        MealPrepLog::create([ // food served worth 1200 — must NOT drive "remaining"
            'menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10',
            'status' => 'completed', 'total_value' => 1200, 'has_shortfall' => false,
        ]);
        PurchaseOrder::factory()->create([ // cash out 800 — this is what "remaining" subtracts
            'rnd_user_id' => $this->fss->id,
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
            'rnd_user_id' => $this->rnd->id,
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
        // Per-head cap is owned by the Budget covering today.
        Budget::factory()->create([
            'rnd_user_id' => $this->rnd->id, 'budget_per_head_day' => 50,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end'   => now()->endOfMonth()->toDateString(),
        ]);

        $res = $this->actingAs($this->fss)->getJson('/api/fss/menu-cycles/cost-today')->assertOk();
        $this->assertEqualsWithDelta(10, $res->json('data.cost_per_head'), 0.01);
        $this->assertEqualsWithDelta(50, $res->json('data.limit_per_head'), 0.01);
        $this->assertTrue($res->json('data.within_budget'));
        $this->assertSame($weekday, $res->json('data.weekday'));
    }

    public function test_daily_series_exposes_population_and_per_head_actual(): void
    {
        $budget = Budget::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'budget_per_head_day' => 100, 'population' => 10,
        ]);
        $cycle = MenuCycle::factory()->create();
        MealPrepLog::create([
            'menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10',
            'status' => 'completed', 'total_value' => 800, 'population' => 8, 'served_population' => 8, 'has_shortfall' => false,
        ]);

        $result = BudgetActualService::dailySeries($budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-11'));

        $this->assertEqualsWithDelta(8, $result['avg_population'], 0.01);
        $this->assertEqualsWithDelta(100, $result['per_head_actual'], 0.01); // ₱800 served ÷ 8 heads
        $served = collect($result['days'])->firstWhere('date', '2026-06-10');
        $this->assertEquals(8, $served['population']);
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

        $response = $this->actingAs($this->fss)->postJson('/api/fss/shopping-lists/generate', [
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
        // Insights are read-only analytics, re-homed under the budget page (both roles).
        $this->actingAs($this->fss)->getJson('/api/fss/insights/spend-by-supplier')->assertOk();
        $this->actingAs($this->fss)->getJson('/api/fss/insights/cost-per-head')->assertOk();
        $this->actingAs($this->fss)->getJson('/api/fss/insights/consumption')->assertOk();
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
