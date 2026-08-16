<?php

namespace Tests\Feature;

use App\Http\Resources\PurchaseOrderResource;
use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\FsItem;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FSS\PurchaseOrderLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PurchaseOrderCompletionPatternTest extends TestCase
{
    use RefreshDatabase;

    public function test_food_po_waits_for_every_span_day_served_population_then_calculates_actual_per_head(): void
    {
        $rnd = User::factory()->rnd()->create();
        $fss = User::factory()->fss()->create();
        $supplier = Supplier::factory()->create();
        $item = FsItem::factory()->create(['default_supplier_id' => $supplier->id]);
        Budget::factory()->create(['fiscal_year' => 2026]);

        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $rnd->id,
            'week_start_date' => '2026-06-01',
            'cycle_days' => 2,
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach (['Monday', 'Tuesday'] as $day) {
            MenuCycleDay::factory()->create([
                'menu_cycle_id' => $cycle->id,
                'day_of_week' => $day,
                'meal_type' => 'lunch',
                'fs_item_id' => $item->id,
                'estimate_population' => 100,
            ]);
        }

        $list = ShoppingList::factory()->create([
            'rnd_user_id' => $rnd->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-02',
            'days_span' => 2,
            'procurement_track' => 'food',
            'list_type' => 'suggested',
            'status' => 'converted',
            'estimate_population' => 100,
        ]);

        $po = PurchaseOrder::factory()->create([
            'rnd_user_id' => $rnd->id,
            'shopping_list_id' => $list->id,
            'supplier_id' => null,
            'procurement_track' => 'food',
            'lifecycle_status' => 'open_execution',
            'status' => 'draft',
            'total_amount' => 200,
        ]);
        $group = $po->vendorGroups()->create([
            'supplier_id' => $supplier->id,
            'status' => 'received',
            'total_amount' => 200,
            'received_at' => now(),
        ]);
        $po->items()->create([
            'vendor_group_id' => $group->id,
            'fs_item_id' => $item->id,
            'description' => $item->name,
            'qty' => 10,
            'unit' => 'g',
            'unit_price' => 20,
            'total_value' => 200,
            'actual_qty' => 10,
            'actual_unit_price' => 20,
        ]);
        $group->attachments()->create([
            'purchase_order_id' => $po->id,
            'type' => 'receipt',
            'path' => 'po-attachments/receipt.jpg',
        ]);
        $group->attachments()->create([
            'purchase_order_id' => $po->id,
            'type' => 'proof',
            'path' => 'po-attachments/proof.jpg',
        ]);

        MealPrepLog::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'service_date' => '2026-06-01',
            'served_population' => 80,
            'status' => 'completed',
            'completed_by' => $fss->id,
        ]);

        $service = app(PurchaseOrderLifecycleService::class);
        $service->refresh($po);

        $po->refresh();
        $this->assertSame('open_execution', $po->lifecycle_status);
        $this->assertNull($po->actual_budget_per_head_per_day);

        MealPrepLog::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'service_date' => '2026-06-02',
            'served_population' => 120,
            'status' => 'completed',
            'completed_by' => $fss->id,
        ]);

        $service->refresh($po);

        $po->refresh();
        $this->assertSame('completed', $po->lifecycle_status);
        $this->assertSame('1.00', $po->actual_budget_per_head_per_day);
        $completed = AuditActivity::query()->where('event', 'completed')->sole();
        $this->assertSame(PurchaseOrder::class, $completed->subject_type);
        $this->assertSame('open_execution', $completed->revision->before['lifecycle_status']);
        $this->assertSame('completed', $completed->revision->after['lifecycle_status']);

        $payload = (new PurchaseOrderResource($po->fresh([
            'vendorGroups.attachments',
            'shoppingList',
            'programProjectActivity',
        ])))->toArray(Request::create('/'));

        $this->assertSame([
            'expected' => 2,
            'done' => 2,
            'served' => 200,
        ], $payload['served_population_progress']);
    }
}
