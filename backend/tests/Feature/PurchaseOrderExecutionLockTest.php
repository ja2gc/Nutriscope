<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\FsItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 10000]);

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

    public function test_rnd_can_change_vendor_for_all_items_in_a_pending_group(): void
    {
        [$po, $group] = $this->convertedPo();
        $replacement = Supplier::factory()->create(['name' => 'Replacement Vendor']);
        $line = $group->items()->firstOrFail();

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'supplier_id' => $replacement->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('data.vendor_groups.0.supplier.name', 'Replacement Vendor');

        $this->assertDatabaseHas('purchase_order_vendor_groups', [
            'purchase_order_id' => $po->id,
            'supplier_id' => $replacement->id,
        ]);
        $this->assertSame(
            $replacement->id,
            $line->fresh()->vendorGroup->supplier_id,
        );
    }

    public function test_fss_can_change_vendor_for_one_item_without_moving_other_items(): void
    {
        [$po, $group] = $this->convertedPo();
        $replacement = Supplier::factory()->create(['name' => 'Item Vendor']);
        $movingLine = $group->items()->firstOrFail();
        $stayingLine = $po->items()->create([
            'vendor_group_id' => $group->id,
            'fs_item_id' => FsItem::factory()->create()->id,
            'description' => 'Rice',
            'qty' => 10,
            'unit' => 'kg',
            'unit_price' => 5,
            'total_value' => 50,
        ]);
        $fss = User::factory()->fss()->create();

        $this->actingAs($fss)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'supplier_id' => $replacement->uuid,
                'item_id' => $movingLine->id,
            ])
            ->assertOk();

        $this->assertSame($replacement->id, $movingLine->fresh()->vendorGroup->supplier_id);
        $this->assertSame($group->id, $stayingLine->fresh()->vendor_group_id);
        $this->assertDatabaseCount('purchase_order_vendor_groups', 2);
    }

    public function test_fss_can_read_replacement_vendors_but_cannot_create_them(): void
    {
        $fss = User::factory()->fss()->create();
        Supplier::factory()->create(['name' => 'Visible Replacement']);

        $this->actingAs($fss)
            ->getJson('/api/fss/suppliers')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Visible Replacement']);

        $this->actingAs($fss)
            ->postJson('/api/fss/suppliers', [
                'name' => 'Forbidden Vendor',
                'category' => 'Food',
            ])
            ->assertForbidden();
    }

    public function test_resource_exposes_when_a_pending_vendor_group_can_change_vendor(): void
    {
        [$po] = $this->convertedPo();

        $this->actingAs($this->rnd)
            ->getJson("/api/fss/purchase-orders/{$po->uuid}")
            ->assertOk()
            ->assertJsonPath('data.vendor_groups.0.can_change_vendor', true);
    }

    public function test_vendor_change_requires_removing_group_evidence_first(): void
    {
        [, $group] = $this->convertedPo();
        $replacement = Supplier::factory()->create();
        PurchaseOrderAttachment::create([
            'purchase_order_id' => $group->purchase_order_id,
            'vendor_group_id' => $group->id,
            'type' => 'receipt',
            'path' => 'receipts/example.png',
        ]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'supplier_id' => $replacement->uuid,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', "Remove this vendor group's receipt and proof before changing its vendor.");

        $this->assertNotSame($replacement->id, $group->fresh()->supplier_id);
    }

    public function test_received_vendor_group_cannot_change_vendor(): void
    {
        [, $group] = $this->convertedPo();
        $replacement = Supplier::factory()->create();
        $group->update(['status' => 'received', 'received_at' => now()]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'supplier_id' => $replacement->uuid,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Received vendor groups are locked.');
    }

    public function test_item_vendor_change_reuses_an_existing_pending_group_and_preserves_values(): void
    {
        [$po, $source] = $this->convertedPo();
        $line = $source->items()->firstOrFail();
        $line->update([
            'purchase_qty' => 5.250,
            'purchase_unit' => 'kg',
            'purchase_price' => 21,
            'actual_qty' => 5.125,
            'actual_unit_price' => 22,
        ]);
        $replacement = Supplier::factory()->create();
        $destination = $po->vendorGroups()->create([
            'supplier_id' => $replacement->id,
            'status' => 'pending',
            'total_amount' => 0,
        ]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$source->uuid}", [
                'supplier_id' => $replacement->uuid,
                'item_id' => $line->id,
            ])
            ->assertOk();

        $line->refresh();
        $this->assertSame($destination->id, $line->vendor_group_id);
        $this->assertSame('5.25', $line->purchase_qty);
        $this->assertSame('5.125', $line->actual_qty);
        $this->assertSame('22.00', $line->actual_unit_price);
        $this->assertDatabaseMissing('purchase_order_vendor_groups', ['id' => $source->id]);
    }

    public function test_item_vendor_change_requires_a_replacement_vendor(): void
    {
        [, $group] = $this->convertedPo();
        $line = $group->items()->firstOrFail();

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'item_id' => $line->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supplier_id');
    }

    public function test_item_from_another_vendor_group_cannot_be_moved_through_the_source_group(): void
    {
        [$po, $source] = $this->convertedPo();
        $otherSupplier = Supplier::factory()->create();
        $otherGroup = $po->vendorGroups()->create([
            'supplier_id' => $otherSupplier->id,
            'status' => 'pending',
            'total_amount' => 0,
        ]);
        $otherLine = $po->items()->create([
            'vendor_group_id' => $otherGroup->id,
            'fs_item_id' => FsItem::factory()->create()->id,
            'description' => 'Cooking oil',
            'qty' => 2,
            'unit' => 'L',
            'unit_price' => 50,
            'total_value' => 100,
        ]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$source->uuid}", [
                'supplier_id' => Supplier::factory()->create()->uuid,
                'item_id' => $otherLine->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The selected item does not belong to this vendor group.');

        $this->assertSame($otherGroup->id, $otherLine->fresh()->vendor_group_id);
    }

    public function test_item_cannot_move_into_a_vendor_group_that_already_has_evidence(): void
    {
        [$po, $source] = $this->convertedPo();
        $replacement = Supplier::factory()->create();
        $destination = $po->vendorGroups()->create([
            'supplier_id' => $replacement->id,
            'status' => 'pending',
            'total_amount' => 0,
        ]);
        PurchaseOrderAttachment::create([
            'purchase_order_id' => $po->id,
            'vendor_group_id' => $destination->id,
            'type' => 'proof',
            'path' => 'proofs/example.png',
        ]);
        $line = $source->items()->firstOrFail();

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$source->uuid}", [
                'supplier_id' => $replacement->uuid,
                'item_id' => $line->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', "Remove the selected vendor group's receipt and proof before adding items to it.");

        $this->assertSame($source->id, $line->fresh()->vendor_group_id);
    }

    public function test_selecting_the_current_vendor_is_a_no_op(): void
    {
        [$po, $group] = $this->convertedPo();

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
                'supplier_id' => $group->supplier->uuid,
            ])
            ->assertOk();

        $this->assertDatabaseCount('purchase_order_vendor_groups', 1);
        $this->assertSame($group->id, $po->items()->firstOrFail()->vendor_group_id);
    }

    public function test_receiving_values_are_stored_separately_with_decimal_quantity(): void
    {
        [, $group] = $this->convertedPo();
        $line = $group->items()->firstOrFail();

        $line->update([
            'actual_qty' => 4.375,
            'actual_unit_price' => 21.50,
        ]);
        $line->refresh();

        $this->assertSame('5.00', $line->qty);
        $this->assertSame('4.375', $line->actual_qty);
        $this->assertSame('21.50', $line->actual_unit_price);
    }

    public function test_evidence_upload_does_not_receive_until_actuals_are_reviewed_and_confirmed(): void
    {
        Storage::fake('private_uploads');
        [, $group] = $this->convertedPo();
        $line = $group->items()->firstOrFail();
        $fss = User::factory()->fss()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->actingAs($fss)
            ->post("/api/fss/purchase-order-vendor-groups/{$group->uuid}/attachments", [
                'type' => 'receipt',
                'file' => UploadedFile::fake()->createWithContent('receipt.png', $png),
            ])->assertCreated();

        $this->assertSame('pending', $group->fresh()->status);

        $this->actingAs($fss)->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
            'status' => 'received',
            'items' => [[
                'id' => $line->id,
                'actual_qty' => 4.375,
                'actual_unit_price' => 21.50,
            ]],
        ])->assertUnprocessable();

        $this->actingAs($fss)
            ->post("/api/fss/purchase-order-vendor-groups/{$group->uuid}/attachments", [
                'type' => 'proof',
                'file' => UploadedFile::fake()->createWithContent('proof.png', $png),
            ])->assertCreated();

        $this->assertSame('pending', $group->fresh()->status);

        $this->actingAs($fss)->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
            'status' => 'received',
            'items' => [[
                'id' => $line->id,
                'actual_qty' => 4.375,
                'actual_unit_price' => 21.50,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.vendor_groups.0.status', 'received')
            ->assertJsonPath('data.vendor_groups.0.or_number', null)
            ->assertJsonPath('data.vendor_groups.0.items.0.actual_qty', '4.375')
            ->assertJsonPath('data.vendor_groups.0.items.0.actual_total', 94.06);

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $line->id,
            'qty' => 5,
            'actual_qty' => 4.375,
            'actual_unit_price' => 21.50,
        ]);
        $this->assertNotNull($group->fresh()->stocked_at);
    }

    public function test_resource_prefills_actual_inputs_without_marking_them_reviewed(): void
    {
        [$po] = $this->convertedPo();

        $this->actingAs($this->rnd)->getJson("/api/fss/purchase-orders/{$po->uuid}")
            ->assertOk()
            ->assertJsonPath('data.vendor_groups.0.items.0.actual_qty', '5.000')
            ->assertJsonPath('data.vendor_groups.0.items.0.actual_unit_price', '20.00')
            ->assertJsonPath('data.vendor_groups.0.items.0.actual_values_confirmed', false);
    }

    public function test_actual_quantity_can_be_calculated_from_receipt_total(): void
    {
        [, $group] = $this->convertedPo();
        $line = $group->items()->firstOrFail();
        $fss = User::factory()->fss()->create();

        $this->actingAs($fss)->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
            'items' => [[
                'id' => $line->id,
                'actual_unit_price' => 80,
                'receipt_total' => 350,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.vendor_groups.0.items.0.actual_qty', '4.375')
            ->assertJsonPath('data.vendor_groups.0.items.0.actual_total', 350);

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $line->id,
            'actual_qty' => 4.375,
            'actual_unit_price' => 80,
        ]);
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
