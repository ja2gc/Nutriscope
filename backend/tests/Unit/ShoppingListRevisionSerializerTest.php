<?php

namespace Tests\Unit;

use App\Models\FsItem;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Services\Audit\Revisions\Serializers\ShoppingListRevisionSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ShoppingListRevisionSerializerTest extends TestCase
{
    use RefreshDatabase;

    public function test_serializer_captures_only_complete_safe_shopping_list_state(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Safe Foods Inc']);
        $item = FsItem::factory()->create(['name' => 'Brown Rice', 'default_supplier_id' => $supplier->id]);
        $list = ShoppingList::factory()->create([
            'name' => 'July Food Procurement',
            'period_start' => '2026-07-13',
            'period_end' => '2026-07-19',
            'days_span' => 7,
            'procurement_track' => 'food',
            'status' => 'draft',
            'coverage_status' => 'full',
            'estimate_population' => 20,
        ]);
        $line = $list->items()->create([
            'fs_item_id' => $item->id,
            'ingredient_name' => 'Brown Rice',
            'qty' => 10,
            'unit' => 'kg',
            'supplier_id' => $supplier->id,
            'unit_price' => 80,
            'total' => 800,
            'purchase_qty' => 10,
            'purchase_unit' => 'kg',
            'purchase_price' => 80,
            'baseline_servings' => 10,
            'baseline_quantity' => 5,
            'scaled_quantity' => 10,
            'scaled_unit' => 'kg',
        ]);

        $serializer = new ShoppingListRevisionSerializer;
        $snapshot = $serializer->capture($list);
        $presented = $serializer->present($snapshot->payload)->toArray();
        $encoded = json_encode($snapshot->payload, JSON_THROW_ON_ERROR);
        $fields = collect($presented['fields'])->keyBy('key');

        $this->assertSame('shopping_list', $snapshot->serializer);
        $this->assertSame($list->uuid, $snapshot->subjectPublicId);
        $this->assertSame(800.0, $fields['total']['value']['value']);
        $this->assertSame(20, $fields['estimate_population']['value']['value']);
        $this->assertSame('Brown Rice', $presented['tables'][0]['rows'][0]['values']['item']['value']);
        $this->assertSame('Safe Foods Inc', $presented['tables'][0]['rows'][0]['values']['supplier']['value']);
        $this->assertSame($line->uuid, $snapshot->payload['items'][0]['reference']);
        foreach (['rnd_user_id', 'shopping_list_id', 'fs_item_id', 'supplier_id', 'vendor_locked_by'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded);
        }
    }

    public function test_serializer_rejects_wrong_model_and_malformed_payload(): void
    {
        $serializer = new ShoppingListRevisionSerializer;
        try {
            $serializer->capture(FsItem::factory()->create());
            $this->fail('Wrong model unexpectedly serialized.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Shopping list serializer requires a shopping list.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid shopping list revision payload.');
        $serializer->present(['items' => ['RAW-NESTED-SENTINEL']]);
    }
}
