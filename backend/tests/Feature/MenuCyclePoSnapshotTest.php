<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Converting a FOOD shopping list to a PO freezes the scaled values onto each
 * covered menu-cycle day cell as a permanent snapshot.
 */
class MenuCyclePoSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
    }

    public function test_food_po_conversion_writes_menu_cycle_day_snapshot(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        $supplier = Supplier::factory()->create();
        $fsItem = FsItem::factory()->create([
            'base_unit' => 'kg', 'purchase_unit' => 'kg', 'purchase_price' => 25,
        ]);

        // 2026-06-15 is a Monday.
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
            'cycle_days' => 7,
            'is_active' => true,
            'status' => 'active',
        ]);
        $day = MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'fs_item_id' => $fsItem->id,
            'quantity' => 1,
            'estimate_population' => 10,
        ]);

        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Span', 'list_date' => '2026-06-15',
            'period_start' => '2026-06-15', 'period_end' => '2026-06-15', 'days_span' => 1,
            'list_type' => 'suggested', 'procurement_track' => 'food', 'status' => 'draft',
            'estimate_population' => 10,
        ]);
        $list->items()->create([
            'fs_item_id' => $fsItem->id, 'ingredient_name' => $fsItem->name, 'qty' => 10, 'unit' => 'kg',
            'supplier_id' => $supplier->id, 'unit_price' => 25, 'total' => 250,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
            ->assertCreated();

        $day->refresh();
        $this->assertNotNull($day->po_snapshot, 'cell must hold a frozen snapshot');
        $this->assertNotNull($day->po_snapshot_at);
        $this->assertNotNull($day->snapshot_purchase_order_id);
        $this->assertSame($fsItem->id, $day->po_snapshot['fs_item_id']);
    }
}
