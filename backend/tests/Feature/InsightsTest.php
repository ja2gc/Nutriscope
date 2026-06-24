<?php

namespace Tests\Feature;

use App\Models\MenuCycle;
use App\Models\PurchaseOrder;
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

    public function test_spend_by_supplier_groups_received_pos_by_vendor(): void
    {
        $a = Supplier::factory()->create(['name' => 'MACMA']);
        $b = Supplier::factory()->create(['name' => 'Gloria']);
        PurchaseOrder::factory()->create(['supplier_id' => $a->id, 'status' => 'received', 'received_date' => '2026-06-10', 'total_amount' => 1000]);
        PurchaseOrder::factory()->create(['supplier_id' => $a->id, 'status' => 'received', 'received_date' => '2026-06-12', 'total_amount' => 500]);
        PurchaseOrder::factory()->create(['supplier_id' => $b->id, 'status' => 'received', 'received_date' => '2026-06-11', 'total_amount' => 700]);
        PurchaseOrder::factory()->create(['supplier_id' => $b->id, 'status' => 'ordered', 'order_date' => '2026-06-11', 'total_amount' => 999]); // not received → excluded

        $res = $this->actingAs($this->fss)
            ->getJson('/api/fss/insights/spend-by-supplier?start=2026-06-01&end=2026-06-30')
            ->assertOk();

        $this->assertEquals(2200, $res->json('data.summary.total'));
        $macma = collect($res->json('data.points'))->firstWhere('supplier', 'MACMA');
        $this->assertEquals(1500, $macma['total']);
    }

    public function test_cost_per_head_and_consumption_endpoints_respond(): void
    {
        MenuCycle::factory()->create(['week_start_date' => '2026-06-15']);

        $this->actingAs($this->fss)->getJson('/api/fss/insights/cost-per-head')
            ->assertOk()->assertJsonStructure(['data' => ['points', 'summary' => ['avg']]]);

        $this->actingAs($this->fss)->getJson('/api/fss/insights/consumption?start=2026-06-01&end=2026-06-30')
            ->assertOk()->assertJsonStructure(['data' => ['points', 'summary' => ['total', 'days', 'shortfall_days']]]);
    }
}
