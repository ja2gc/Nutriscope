<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\FsItem;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_uses_one_readiness_result_and_omits_excluded_rows(): void
    {
        $rnd = User::factory()->rnd()->create();
        $supplier = Supplier::factory()->create();
        $included = FsItem::factory()->create(['name' => 'Chicken']);
        $excluded = FsItem::factory()->create(['name' => 'Cooking oil']);
        $list = ShoppingList::create([
            'rnd_user_id' => $rnd->id,
            'name' => 'Nutrition Month — Food',
            'list_date' => '2026-08-14',
            'list_type' => 'manual',
            'procurement_track' => 'food',
            'status' => 'draft',
        ]);
        $list->items()->create([
            'fs_item_id' => $included->id,
            'ingredient_name' => $included->name,
            'source' => 'manual',
            'qty' => 5,
            'unit' => 'kg',
            'unit_price' => 100,
            'total' => 500,
        ]);
        $list->items()->create([
            'fs_item_id' => $excluded->id,
            'ingredient_name' => $excluded->name,
            'source' => 'manual',
            'included_in_po' => false,
            'exclusion_note' => 'Already available',
            'qty' => 1,
            'unit' => 'L',
            'supplier_id' => $supplier->id,
            'unit_price' => 120,
            'total' => 120,
        ]);

        $this->actingAs($rnd)->getJson("/api/fss/shopping-lists/{$list->uuid}")
            ->assertOk()
            ->assertJsonPath('data.release_readiness.ready', true)
            ->assertJsonPath('data.release_readiness.blockers', [])
            ->assertJsonPath('data.estimated_total', 500);

        $this->actingAs($rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
            ->assertCreated();

        $po = PurchaseOrder::where('shopping_list_id', $list->id)->firstOrFail();
        $this->assertSame(1, $po->items()->count());
        $this->assertDatabaseHas('purchase_order_items', ['purchase_order_id' => $po->id, 'description' => 'Chicken']);
        $this->assertDatabaseMissing('purchase_order_items', ['purchase_order_id' => $po->id, 'description' => 'Cooking oil']);
        $this->assertEqualsWithDelta(500, (float) $po->total_amount, 0.01);
        $this->assertNull($po->vendorGroups()->sole()->supplier_id);
    }

    public function test_budget_allocation_and_open_purchase_orders_do_not_block_conversion(): void
    {
        $rnd = User::factory()->rnd()->create();
        $supplier = Supplier::factory()->create();
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 1000]);
        $existingList = ShoppingList::factory()->create(['list_date' => '2026-01-01']);
        PurchaseOrder::factory()->create([
            'shopping_list_id' => $existingList->id,
            'lifecycle_status' => 'open_execution',
            'total_amount' => 800,
        ]);
        $list = ShoppingList::create([
            'rnd_user_id' => $rnd->id,
            'name' => 'Supplies',
            'list_date' => '2026-08-14',
            'list_type' => 'manual',
            'procurement_track' => 'supplies',
            'status' => 'draft',
        ]);
        $item = FsItem::factory()->create(['kind' => 'supply']);
        $list->items()->create([
            'fs_item_id' => $item->id,
            'ingredient_name' => $item->name,
            'source' => 'manual',
            'qty' => 3,
            'unit' => 'box',
            'supplier_id' => $supplier->id,
            'unit_price' => 100,
            'total' => 300,
        ]);

        $this->actingAs($rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
            ->assertCreated();
        $this->assertDatabaseHas('purchase_orders', ['shopping_list_id' => $list->id, 'total_amount' => 300]);
    }

    public function test_manual_conversion_needs_no_item_checklist_or_purchase_values(): void
    {
        $rnd = User::factory()->rnd()->create();
        $list = ShoppingList::factory()->create(['rnd_user_id' => $rnd->id, 'list_type' => 'manual', 'procurement_track' => 'food']);
        $this->actingAs($rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")->assertCreated();
        $this->assertSame('converted', $list->fresh()->status);
        $this->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")->assertUnprocessable();
    }
}
