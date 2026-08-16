<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\FsItem;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Supplies procurement is a fully independent, manual track: no date span, no menu
 * cycle, no population. It has its own PO conversion that completes on receipts only.
 */
class SuppliesProcurementTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
    }

    public function test_supplies_list_rejects_date_span_fields(): void
    {
        $this->actingAs($this->rnd)
            ->postJson('/api/fss/shopping-lists', [
                'name' => 'Cleaning supplies',
                'procurement_track' => 'supplies',
                'list_type' => 'manual',
                'period_start' => '2026-06-01',
                'period_end' => '2026-06-07',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['period_start']);
    }

    public function test_supplies_list_creates_without_span(): void
    {
        $this->actingAs($this->rnd)
            ->postJson('/api/fss/shopping-lists', [
                'name' => 'Cleaning supplies',
                'procurement_track' => 'supplies',
                'list_type' => 'manual',
            ])
            ->assertCreated()
            ->assertJsonPath('data.procurement_track', 'supplies');
    }

    public function test_supplies_list_only_accepts_supply_items(): void
    {
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Supplies', 'list_date' => '2026-06-10',
            'list_type' => 'manual', 'procurement_track' => 'supplies', 'status' => 'draft',
        ]);

        $ingredient = FsItem::factory()->create(['kind' => 'ingredient']);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/items", [
                'fs_item_id' => $ingredient->uuid,
                'qty' => 3,
                'unit' => 'pc',
                'unit_price' => 25,
            ])
            ->assertStatus(422);

        $supply = FsItem::factory()->create(['kind' => 'supply']);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/items", [
                'fs_item_id' => $supply->uuid,
                'qty' => 3,
                'unit' => 'pc',
                'unit_price' => 25,
            ])
            ->assertCreated()
            ->assertJsonPath('data.total', '75.00');
    }

    public function test_supplies_list_converts_to_supplies_po(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);
        $supplier = Supplier::factory()->create();
        $supply = FsItem::factory()->create(['kind' => 'supply']);
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Supplies', 'list_date' => '2026-06-10',
            'list_type' => 'manual', 'procurement_track' => 'supplies', 'status' => 'draft',
        ]);
        $list->items()->create([
            'fs_item_id' => $supply->id, 'ingredient_name' => $supply->name,
            'qty' => 4, 'unit' => 'pc', 'supplier_id' => $supplier->id, 'unit_price' => 50, 'total' => 200,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
            ->assertCreated();

        $this->assertDatabaseHas('purchase_orders', [
            'shopping_list_id' => $list->id,
            'procurement_track' => 'supplies',
            'lifecycle_status' => 'open_execution',
        ]);
    }
}
