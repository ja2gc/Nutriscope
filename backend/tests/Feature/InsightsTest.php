<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create(['role' => 'FSS']);
    }

    public function test_spend_by_supplier_groups_completed_pos_by_vendor(): void
    {
        $sl = ShoppingList::factory()->create(['period_start' => '2026-06-01', 'period_end' => '2026-06-07']);
        $po = PurchaseOrder::factory()->create([
            'shopping_list_id' => $sl->id,
            'lifecycle_status' => 'completed',
        ]);

        $macma  = Supplier::factory()->create(['name' => 'MACMA']);
        $gloria = Supplier::factory()->create(['name' => 'Gloria']);

        // Two separate POs so each vendor group is unique per (po, supplier).
        $po2 = PurchaseOrder::factory()->create([
            'shopping_list_id' => $sl->id,
            'lifecycle_status' => 'completed',
        ]);
        PurchaseOrderVendorGroup::create(['purchase_order_id' => $po->id, 'supplier_id' => $macma->id, 'total_amount' => 1000]);
        PurchaseOrderVendorGroup::create(['purchase_order_id' => $po2->id, 'supplier_id' => $macma->id, 'total_amount' => 500]);
        PurchaseOrderVendorGroup::create(['purchase_order_id' => $po->id, 'supplier_id' => $gloria->id, 'total_amount' => 700]);

        $res = $this->actingAs($this->fss)
            ->getJson('/api/fss/insights/spend-by-supplier?fiscal_year=2026')
            ->assertOk();

        $this->assertEqualsWithDelta(2200, $res->json('data.total'), 0.01);
        $macmaPoint = collect($res->json('data.points'))->firstWhere('supplier', 'MACMA');
        $this->assertEqualsWithDelta(1500, $macmaPoint['total'], 0.01);
    }

    public function test_insights_endpoints_respond(): void
    {
        $this->actingAs($this->fss)->getJson('/api/fss/insights/budget-burn')
            ->assertOk()->assertJsonStructure(['data' => ['points', 'summary']]);

        $this->actingAs($this->fss)->getJson('/api/fss/insights/per-head-actual-vs-limit')
            ->assertOk()->assertJsonStructure(['data' => ['points', 'summary']]);

        $this->actingAs($this->fss)->getJson('/api/fss/insights/procurement-deduction-timeline')
            ->assertOk()->assertJsonStructure(['data' => ['timeline', 'fiscal_year']]);

        $this->actingAs($this->fss)->getJson('/api/fss/insights/spend-by-supplier')
            ->assertOk()->assertJsonStructure(['data' => ['points', 'fiscal_year', 'total']]);
    }
}
