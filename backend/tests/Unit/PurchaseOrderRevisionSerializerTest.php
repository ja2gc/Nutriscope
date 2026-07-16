<?php

namespace Tests\Unit;

use App\Models\FsItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Services\Audit\Revisions\Serializers\PurchaseOrderRevisionSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PurchaseOrderRevisionSerializerTest extends TestCase
{
    use RefreshDatabase;

    public function test_serializer_captures_safe_po_groups_lines_attachment_metadata_and_totals(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Safe Foods Inc']);
        $catalog = FsItem::factory()->create(['name' => 'Brown Rice']);
        $list = ShoppingList::factory()->create(['name' => 'July Food Procurement']);
        $po = PurchaseOrder::factory()->create([
            'shopping_list_id' => $list->id,
            'supplier_id' => null,
            'po_number' => 'PO-JULY-001',
            'status' => 'ordered',
            'lifecycle_status' => 'open_execution',
            'procurement_track' => 'food',
            'total_amount' => 800,
        ]);
        $group = $po->vendorGroups()->create([
            'supplier_id' => $supplier->id,
            'or_number' => 'OR-100',
            'status' => 'pending',
            'total_amount' => 800,
        ]);
        $po->items()->create([
            'vendor_group_id' => $group->id,
            'fs_item_id' => $catalog->id,
            'description' => 'Brown Rice',
            'qty' => 10,
            'unit' => 'kg',
            'unit_price' => 80,
            'total_value' => 800,
            'purchase_qty' => 10,
            'purchase_unit' => 'kg',
            'purchase_price' => 80,
        ]);
        PurchaseOrderAttachment::create([
            'purchase_order_id' => $po->id,
            'vendor_group_id' => $group->id,
            'type' => 'receipt',
            'caption' => 'Vendor receipt',
            'path' => 'private/receipt-secret.jpg',
        ]);

        $serializer = new PurchaseOrderRevisionSerializer;
        $snapshot = $serializer->capture($po);
        $presented = $serializer->present($snapshot->payload)->toArray();
        $encoded = json_encode($snapshot->payload, JSON_THROW_ON_ERROR);

        $this->assertSame('purchase_order', $snapshot->serializer);
        $this->assertSame($po->uuid, $snapshot->subjectPublicId);
        $this->assertSame('Safe Foods Inc', $presented['tables'][0]['rows'][0]['values']['supplier']['value']);
        $this->assertSame('Brown Rice', $presented['tables'][1]['rows'][0]['values']['item']['value']);
        $this->assertSame('Vendor receipt', $presented['tables'][2]['rows'][0]['values']['caption']['value']);
        $this->assertStringNotContainsString('private/receipt-secret.jpg', $encoded);
        foreach (['rnd_user_id', 'shopping_list_id', 'purchase_order_id', 'vendor_group_id', 'fs_item_id', 'supplier_id'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded);
        }
    }

    public function test_serializer_rejects_wrong_model_and_malformed_payload(): void
    {
        $serializer = new PurchaseOrderRevisionSerializer;
        try {
            $serializer->capture(FsItem::factory()->create());
            $this->fail('Wrong model unexpectedly serialized.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Purchase order serializer requires a purchase order.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid purchase order revision payload.');
        $serializer->present(['lines' => ['RAW-NESTED-SENTINEL']]);
    }

    public function test_serializer_rejects_unallowlisted_ppa_payload_fields(): void
    {
        $serializer = new PurchaseOrderRevisionSerializer;
        $payload = $serializer->capture(PurchaseOrder::factory()->create())->payload;
        $payload['ppa'] = ['activity' => 'Safe activity', 'execution_payload' => ['raw' => 'UNSAFE']];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid purchase order revision payload.');
        $serializer->present($payload);
    }
}
