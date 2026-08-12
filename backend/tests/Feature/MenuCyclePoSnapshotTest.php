<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
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
        $this->assertSame(10, $day->po_snapshot['population']);
        $this->assertEqualsWithDelta(10, (float) $day->po_snapshot['total_quantity'], 0.01);
        $this->assertEqualsWithDelta(250, (float) $day->po_snapshot['total_cost'], 0.01);

        $response = $this->actingAs(User::factory()->create(['role' => 'FSS']))
            ->getJson("/api/fss/menu-cycles/{$cycle->uuid}")
            ->assertOk();
        $snapshot = collect($response->json('data.days'))->firstWhere('id', $day->id)['po_snapshot'];
        $this->assertSame(10, $snapshot['population']);
        $this->assertEqualsWithDelta(10, (float) $snapshot['total_quantity'], 0.01);
    }

    public function test_food_po_snapshot_uses_slot_override_at_purchase_population(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);
        $supplier = Supplier::factory()->create();
        $item = FsItem::factory()->create([
            'name' => 'Chicken', 'base_unit' => 'kg', 'purchase_unit' => 'kg', 'purchase_price' => 100,
        ]);
        $recipe = FoodServiceRecipe::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Master', 'servings' => 20,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id, 'fs_item_id' => $item->id, 'quantity' => 2, 'unit' => 'kg',
        ]);
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id, 'week_start_date' => '2026-06-15',
            'cycle_days' => 7, 'is_active' => true, 'status' => 'active',
        ]);
        $day = MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch',
            'recipe_id' => $recipe->id, 'estimate_population' => 20,
            'recipe_override' => [
                'name' => 'Ward Adobo', 'reference_servings' => 25, 'prep_notes' => 'Slot notes',
                'ingredients' => [[
                    'fs_item_id' => $item->id, 'name' => 'Chicken', 'quantity' => 3, 'unit' => 'kg',
                ]],
            ],
        ]);
        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Span', 'list_date' => '2026-06-15',
            'period_start' => '2026-06-15', 'period_end' => '2026-06-15', 'days_span' => 1,
            'list_type' => 'suggested', 'procurement_track' => 'food', 'status' => 'draft',
            'estimate_population' => 100,
        ]);
        $list->items()->create([
            'fs_item_id' => $item->id, 'ingredient_name' => $item->name, 'qty' => 12, 'unit' => 'kg',
            'supplier_id' => $supplier->id, 'unit_price' => 100, 'total' => 1200,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")
            ->assertCreated();

        $snapshot = $day->fresh()->po_snapshot;
        $this->assertSame('Ward Adobo', $snapshot['name']);
        $this->assertSame(100, $snapshot['population']);
        $this->assertEqualsWithDelta(12, $snapshot['ingredient_usage'][0]['quantity'], 0.01);
        $this->assertEqualsWithDelta(1200, $snapshot['total_cost'], 0.01);
    }
}
