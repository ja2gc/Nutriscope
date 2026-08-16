<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\FsItem;
use App\Models\PurchaseOrder;
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
