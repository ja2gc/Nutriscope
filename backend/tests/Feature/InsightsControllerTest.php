<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InsightsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
    }

    public function test_spend_by_supplier_groups_received_pos(): void
    {
        $a = Supplier::factory()->create(['name' => 'Veg Co']);
        $b = Supplier::factory()->create(['name' => 'Meat Co']);
        PurchaseOrder::factory()->create(['rnd_user_id' => $this->fss->id, 'supplier_id' => $a->id, 'status' => 'received', 'received_date' => '2026-06-10', 'total_amount' => 300]);
        PurchaseOrder::factory()->create(['rnd_user_id' => $this->fss->id, 'supplier_id' => $a->id, 'status' => 'received', 'received_date' => '2026-06-11', 'total_amount' => 200]);
        PurchaseOrder::factory()->create(['rnd_user_id' => $this->fss->id, 'supplier_id' => $b->id, 'status' => 'received', 'received_date' => '2026-06-11', 'total_amount' => 500]);
        PurchaseOrder::factory()->create(['rnd_user_id' => $this->fss->id, 'supplier_id' => $b->id, 'status' => 'draft', 'order_date' => '2026-06-11', 'total_amount' => 999]);

        $res = $this->actingAs($this->fss)->getJson('/api/fss/insights/spend-by-supplier?start=2026-06-01&end=2026-06-30');
        $res->assertOk();

        $points = collect($res->json('data.points'))->keyBy('supplier');
        $this->assertEqualsWithDelta(500, (float) $points['Veg Co']['total'], 0.01);
        $this->assertEqualsWithDelta(500, (float) $points['Meat Co']['total'], 0.01);
        $this->assertEqualsWithDelta(1000, (float) $res->json('data.summary.total'), 0.01);
    }

    public function test_cost_per_head_reports_average_daily_per_cycle(): void
    {
        $fs = FsItem::factory()->create(['name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg', 'purchase_price' => 50]); // unit_cost 0.05/g
        $cycle = MenuCycle::factory()->create(['name' => 'Cycle A', 'population' => 10]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday',
            'meal_type' => 'lunch', 'fs_item_id' => $fs->id, 'quantity' => 1000,
        ]);

        $res = $this->actingAs($this->fss)->getJson('/api/fss/insights/cost-per-head');
        $res->assertOk();

        $point = collect($res->json('data.points'))->firstWhere('cycle_id', $cycle->id);
        $this->assertNotNull($point);
        // Direct-item qty is per-head (scales with population): 1000 g/head × ₱0.05/g = ₱50/head/day.
        $this->assertEqualsWithDelta(50, (float) $point['cost_per_head'], 0.01);
    }

    public function test_consumption_rolls_up_completed_logs_by_day(): void
    {
        $cycle = MenuCycle::factory()->create();
        MealPrepLog::create(['menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10', 'status' => 'completed', 'total_value' => 1200, 'has_shortfall' => false]);
        MealPrepLog::create(['menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-11', 'status' => 'completed', 'total_value' => 900,  'has_shortfall' => true]);
        $cycle2 = MenuCycle::factory()->create();
        MealPrepLog::create(['menu_cycle_id' => $cycle2->id, 'service_date' => '2026-06-12', 'status' => 'reversed', 'total_value' => 5000, 'has_shortfall' => false]);

        $res = $this->actingAs($this->fss)->getJson('/api/fss/insights/consumption?start=2026-06-01&end=2026-06-30');
        $res->assertOk();

        $points = collect($res->json('data.points'))->keyBy('date');
        $this->assertEqualsWithDelta(1200, (float) $points['2026-06-10']['actual'], 0.01);
        $this->assertTrue((bool) $points['2026-06-11']['shortfall']);
        $this->assertArrayNotHasKey('2026-06-12', $points->all());
        $this->assertEqualsWithDelta(2100, (float) $res->json('data.summary.total'), 0.01);
    }
}
