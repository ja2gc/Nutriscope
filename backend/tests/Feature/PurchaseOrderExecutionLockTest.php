<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A converted PO freezes structural data. During open execution the ONLY editable
 * field is the unit cost / price correction, which is audited.
 */
class PurchaseOrderExecutionLockTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
    }

    private function convertedPo(): array
    {
        $supplier = Supplier::factory()->create();
        $fsItem = FsItem::factory()->create();
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'L', 'list_date' => '2026-06-10',
            'list_type' => 'manual', 'procurement_track' => 'food', 'status' => 'draft',
        ]);
        $list->items()->create([
            'fs_item_id' => $fsItem->id, 'ingredient_name' => $fsItem->name, 'qty' => 5, 'unit' => 'kg',
            'supplier_id' => $supplier->id, 'unit_price' => 20, 'total' => 100,
        ]);

        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")->assertCreated();
        $po = PurchaseOrder::where('shopping_list_id', $list->id)->firstOrFail();

        return [$po, $po->vendorGroups()->firstOrFail()];
    }

    public function test_conversion_sets_structural_lock(): void
    {
        [$po] = $this->convertedPo();

        $this->assertNotNull($po->structural_locked_at);
        $this->assertSame('food', $po->procurement_track);
    }

    public function test_price_correction_is_allowed_and_audited(): void
    {
        [$po, $group] = $this->convertedPo();
        $line = $group->items()->firstOrFail();

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'items' => [[
                    'id' => $line->id,
                    'unit_price' => 30,
                    'reason' => 'Wrong price entered',
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('purchase_order_item_corrections', [
            'purchase_order_item_id' => $line->id,
            'old_unit_price' => 20,
            'new_unit_price' => 30,
            'corrected_by' => $this->rnd->id,
            'reason' => 'Wrong price entered',
        ]);
        // total recalculated from corrected price: 5 × 30 = 150.
        $this->assertDatabaseHas('purchase_order_items', ['id' => $line->id, 'unit_price' => 30, 'total_value' => 150]);
    }

    public function test_purchase_qty_and_unit_are_frozen(): void
    {
        [$po, $group] = $this->convertedPo();
        $line = $group->items()->firstOrFail();
        $originalQty = $line->qty;

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'items' => [[
                    'id' => $line->id,
                    'purchase_qty' => 99,
                    'purchase_unit' => 'sack',
                ]],
            ])
            ->assertOk();

        // No structural change — qty stays, no correction logged (no price field sent).
        $this->assertDatabaseHas('purchase_order_items', ['id' => $line->id, 'qty' => $originalQty]);
        $this->assertDatabaseMissing('purchase_order_item_corrections', ['purchase_order_item_id' => $line->id]);
    }
}
