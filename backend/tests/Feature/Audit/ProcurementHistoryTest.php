<?php

namespace Tests\Feature\Audit;

use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\FsItem;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class ProcurementHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->rnd()->create();
        $this->admin = User::factory()->admin()->create();
        AuditFixture::delete(AuditActivity::query());
    }

    public function test_shopping_list_create_and_line_mutation_store_complete_parent_versions(): void
    {
        $this->actingAs($this->rnd)->postJson('/api/fss/shopping-lists', [
            'name' => 'July Supplies',
            'procurement_track' => 'supplies',
        ])->assertCreated();

        $created = AuditActivity::query()->where('subject_type', ShoppingList::class)->sole();
        $list = ShoppingList::query()->where('name', 'July Supplies')->firstOrFail();
        $this->assertSame('July Supplies', $created->revision->after['title']);
        $this->assertCount(0, $created->revision->after['items']);

        $supply = FsItem::factory()->create([
            'name' => 'Disposable Gloves',
            'kind' => 'supply',
            'base_unit' => 'box',
            'purchase_unit' => 'box',
            'purchase_price' => 250,
        ]);
        AuditFixture::delete(AuditActivity::query());
        $this->actingAs($this->rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/items", [
            'fs_item_id' => $supply->uuid,
            'qty' => 2,
            'unit_price' => 250,
        ])->assertCreated();

        $updated = AuditActivity::query()->where('subject_type', ShoppingList::class)->sole();
        $this->assertSame('updated', $updated->event);
        $this->assertCount(0, $updated->revision->before['items']);
        $this->assertSame('Disposable Gloves', $updated->revision->after['items'][0]['item']);
        $this->actingAs($this->admin)
            ->getJson("/api/admin/audit-logs/{$updated->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.version.serializer', 'shopping_list')
            ->assertJsonPath('data.after.tables.0.rows.0.values.item.value', 'Disposable Gloves');
    }

    public function test_shopping_list_delete_version_survives_live_record_deletion(): void
    {
        $list = ShoppingList::factory()->create(['rnd_user_id' => $this->rnd->id, 'name' => 'Retired List']);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)->deleteJson("/api/fss/shopping-lists/{$list->uuid}")->assertNoContent();

        $activity = AuditActivity::query()->where('subject_type', ShoppingList::class)->sole();
        $this->assertSame('Retired List', $activity->revision->before['name']);
        $this->assertNull($activity->revision->after);
    }

    public function test_no_op_shopping_list_line_edit_emits_no_parent_event_or_revision(): void
    {
        $list = ShoppingList::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'procurement_track' => 'supplies',
            'status' => 'draft',
        ]);
        $item = $list->items()->create([
            'ingredient_name' => 'Gloves',
            'qty' => 2,
            'unit' => 'box',
            'unit_price' => 250,
            'total' => 500,
        ]);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/shopping-list-items/{$item->uuid}", ['qty' => 2, 'unit_price' => 250])
            ->assertOk();

        $this->assertDatabaseCount('activity_log', 0);
        $this->assertDatabaseCount('audit_revisions', 0);
    }

    public function test_po_approval_ordering_receiving_and_delete_have_event_time_versions(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);
        $supplier = Supplier::factory()->create(['name' => 'Safe Foods Inc']);
        $catalog = FsItem::factory()->create(['name' => 'Rice', 'default_supplier_id' => $supplier->id]);
        $list = ShoppingList::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'July Food List',
            'list_type' => 'manual',
            'status' => 'draft',
            'procurement_track' => 'food',
        ]);
        $list->items()->create([
            'fs_item_id' => $catalog->id,
            'ingredient_name' => 'Rice',
            'qty' => 10,
            'unit' => 'kg',
            'supplier_id' => $supplier->id,
            'unit_price' => 80,
            'total' => 800,
        ]);
        AuditFixture::delete(AuditActivity::query());

        $poId = $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
            ->assertCreated()
            ->json('data.purchase_order_id');
        $po = PurchaseOrder::where('uuid', $poId)->firstOrFail();
        $approved = AuditActivity::query()->where('subject_type', PurchaseOrder::class)->sole();
        $this->assertSame('approved', $approved->event);
        $this->assertNull($approved->revision->before);
        $this->assertSame('Rice', $approved->revision->after['lines'][0]['item']);

        AuditFixture::delete(AuditActivity::query());
        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", ['status' => 'ordered'])
            ->assertOk();
        $ordered = AuditActivity::query()->where('subject_type', PurchaseOrder::class)->sole();
        $this->assertSame('ordered', $ordered->event);
        $this->assertSame('draft', $ordered->revision->before['status']);
        $this->assertSame('ordered', $ordered->revision->after['status']);

        AuditFixture::delete(AuditActivity::query());
        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$po->uuid}", ['status' => 'received'])
            ->assertOk();
        $received = AuditActivity::query()->where('subject_type', PurchaseOrder::class)->sole();
        $this->assertSame('received', $received->event);
        $this->assertSame('ordered', $received->revision->before['status']);
        $this->assertSame('received', $received->revision->after['status']);

        $deletable = PurchaseOrder::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'status' => 'draft',
            'lifecycle_status' => 'open_execution',
        ]);
        AuditFixture::delete(AuditActivity::query());
        $this->actingAs($this->rnd)
            ->deleteJson("/api/fss/purchase-orders/{$deletable->uuid}")
            ->assertNoContent();
        $deleted = AuditActivity::query()->where('subject_type', PurchaseOrder::class)->sole();
        $this->assertSame($deletable->po_number, $deleted->revision->before['po_number']);
        $this->assertNull($deleted->revision->after);

        $archivable = PurchaseOrder::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'status' => 'received',
            'lifecycle_status' => 'completed',
            'completed_at' => now(),
        ]);
        AuditFixture::delete(AuditActivity::query());
        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/purchase-orders/{$archivable->uuid}", ['lifecycle_status' => 'archived'])
            ->assertOk();
        $archived = AuditActivity::query()->where('subject_type', PurchaseOrder::class)->sole();
        $this->assertSame('completed', $archived->revision->before['lifecycle_status']);
        $this->assertSame('archived', $archived->revision->after['lifecycle_status']);
    }
}
