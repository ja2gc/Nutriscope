<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\FoodItem;
use App\Models\Inventory;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
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

    private function makeFoodItem(array $attrs = []): FoodItem
    {
        return FoodItem::factory()->create($attrs);
    }

    // ===== INVENTORY =====

    public function test_fss_can_list_inventory(): void
    {
        $food = $this->makeFoodItem();
        Inventory::factory()->create(['food_item_id' => $food->id]);

        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/inventory');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'food_item_id', 'quantity_in_stock', 'unit']]]);
    }

    public function test_fss_can_update_inventory(): void
    {
        $food      = $this->makeFoodItem();
        $inventory = Inventory::factory()->create(['food_item_id' => $food->id, 'quantity_in_stock' => 50]);

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
        $food      = $this->makeFoodItem();
        $inventory = Inventory::factory()->create(['food_item_id' => $food->id, 'quantity_in_stock' => 20]);

        $response = $this->actingAs($this->fss)
            ->postJson("/api/fss/inventory/{$inventory->id}/restock", [
                'quantity' => 30,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.quantity_in_stock', '50.00');
    }

    public function test_restock_requires_positive_quantity(): void
    {
        $food      = $this->makeFoodItem();
        $inventory = Inventory::factory()->create(['food_item_id' => $food->id]);

        $response = $this->actingAs($this->fss)
            ->postJson("/api/fss/inventory/{$inventory->id}/restock", [
                'quantity' => -5,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_rnd_can_access_fss_inventory_routes(): void
    {
        $food      = $this->makeFoodItem();
        Inventory::factory()->create(['food_item_id' => $food->id]);

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
        $food     = $this->makeFoodItem();

        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/purchase-orders', [
                'supplier_id'  => $supplier->id,
                'order_date'   => '2026-06-10',
                'items'        => [
                    ['food_item_id' => $food->id, 'quantity' => 50, 'unit_price' => 25.00],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier->id]);
        $this->assertDatabaseHas('purchase_order_items', ['food_item_id' => $food->id]);
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
        $food = $this->makeFoodItem();
        $inventory = Inventory::factory()->create([
            'food_item_id' => $food->id,
            'quantity_in_stock' => 10,
            'unit' => 'kg',
        ]);

        $po = PurchaseOrder::factory()->create([
            'status' => 'ordered',
        ]);

        // Add item to purchase order
        \App\Models\PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'food_item_id' => $food->id,
            'description' => $food->name,
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

    public function test_purchase_order_requires_supplier_and_items(): void
    {
        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/purchase-orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id', 'items']);
    }

    // ===== SHOPPING LISTS =====

    public function test_fss_can_generate_shopping_list(): void
    {
        $food = $this->makeFoodItem();
        Inventory::factory()->create([
            'food_item_id'             => $food->id,
            'quantity_in_stock'        => 5,
            'minimum_stock_threshold'  => 20,
        ]);

        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/shopping-lists/generate', [
                'period_start' => '2026-06-09',
                'period_end'   => '2026-06-15',
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
        // 1. Create two food items and inventories
        $food1 = $this->makeFoodItem(['name' => 'Food Item A']);
        Inventory::factory()->create([
            'food_item_id'             => $food1->id,
            'quantity_in_stock'        => 10,
            'minimum_stock_threshold'  => 5,
        ]);

        $food2 = $this->makeFoodItem(['name' => 'Food Item B']);
        Inventory::factory()->create([
            'food_item_id'             => $food2->id,
            'quantity_in_stock'        => 8,
            'minimum_stock_threshold'  => 12,
        ]);

        // 2. Create active menu cycle
        $cycle = MenuCycle::factory()->create([
            'is_active'   => true,
            'status'      => 'active',
            'fss_user_id' => $this->fss->id,
        ]);

        // 3. Create a recipe using food1
        $recipe = \App\Models\Recipe::factory()->create([
            'name'        => 'Test Recipe',
            'rnd_user_id' => $this->rnd->id,
        ]);
        \App\Models\RecipeIngredient::create([
            'recipe_id'    => $recipe->id,
            'food_item_id' => $food1->id,
            'quantity'     => 3.00,
            'unit'         => 'kg',
        ]);

        // 4. Link recipe and food2 to MenuCycle via MenuCycleDay
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week'   => 'Monday',
            'meal_type'     => 'breakfast',
            'recipe_id'     => $recipe->id,
            'quantity'      => 2.00, // 2 servings. Total food1 needed = 3 * 2 = 6
        ]);

        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week'   => 'Monday',
            'meal_type'     => 'lunch',
            'food_item_id'  => $food2->id,
            'quantity'      => 5.00, // Direct food item. Total food2 needed = 5
        ]);

        // 5. Generate shopping list suggestion
        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/shopping-lists/generate', [
                'period_start' => '2026-06-09',
                'period_end'   => '2026-06-15',
            ]);

        $response->assertCreated();

        // 6. Check results
        // For food1: threshold 5 + needed 6 = 11. in_stock 10. shortfall = 1
        // For food2: threshold 12 + needed 5 = 17. in_stock 8. shortfall = 9
        $this->assertDatabaseHas('shopping_list_items', [
            'food_item_id' => $food1->id,
            'qty'          => 1.00,
        ]);

        $this->assertDatabaseHas('shopping_list_items', [
            'food_item_id' => $food2->id,
            'qty'          => 9.00,
        ]);
    }

    // ===== MENU CYCLES =====

    public function test_fss_can_create_menu_cycle(): void
    {
        $response = $this->actingAs($this->fss)
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
        $cycle = MenuCycle::factory()->create(['is_active' => false, 'status' => 'draft', 'activation_date' => null]);

        $response = $this->actingAs($this->fss)
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
        $response = $this->actingAs($this->fss)
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

    public function test_budget_requires_period_and_allocated_amount(): void
    {
        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/budgets', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['period_start', 'period_end', 'allocated_amount']);
    }
}
