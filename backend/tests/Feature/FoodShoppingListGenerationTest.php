<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Food shopping list generation is all-or-nothing: if any date in the span lacks a
 * menu cycle, menu items, or an estimated population, creation is blocked entirely
 * with the exact missing dates — no partial list is ever created.
 */
class FoodShoppingListGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
    }

    public function test_catalog_and_shopping_rows_persist_generation_review_state(): void
    {
        $fsItem = FsItem::factory()->create();

        $this->assertTrue($fsItem->include_in_generated_lists);

        $list = ShoppingList::create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Review state',
            'list_date' => '2026-06-15',
            'list_type' => 'manual',
            'procurement_track' => 'food',
            'status' => 'draft',
        ]);
        $row = $list->items()->create([
            'fs_item_id' => $fsItem->id,
            'ingredient_name' => $fsItem->name,
            'qty' => 1.125,
            'unit' => 'kg',
            'source' => 'manual',
            'included_in_po' => false,
            'exclusion_note' => 'Already available',
        ])->fresh();

        $this->assertSame('manual', $row->source);
        $this->assertFalse($row->included_in_po);
        $this->assertSame('Already available', $row->exclusion_note);
        $this->assertSame('1.125', $row->qty);
    }

    public function test_missing_dates_block_creation_and_report_per_date_reasons(): void
    {
        // 2026-06-15 Monday is planned; 2026-06-16 Tuesday has no menu item.
        $fsItem = FsItem::factory()->create();
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
            'cycle_days' => 7,
        ]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'fs_item_id' => $fsItem->id,
            'quantity' => 1,
            'estimate_population' => 10,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/shopping-lists/generate', [
                'start_date' => '2026-06-15',
                'end_date' => '2026-06-16',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('missing_dates', ['2026-06-16']);

        $this->assertArrayHasKey('2026-06-16', $response->json('missing_items_by_date'));

        // No partial list created.
        $this->assertDatabaseCount('shopping_lists', 0);
    }

    public function test_fully_covered_span_creates_food_track_list(): void
    {
        $fsItem = FsItem::factory()->create();
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
            'cycle_days' => 7,
        ]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'fs_item_id' => $fsItem->id,
            'quantity' => 1,
            'estimate_population' => 10,
        ]);

        $this->actingAs($this->rnd)
            ->postJson('/api/fss/shopping-lists/generate', [
                'start_date' => '2026-06-15',
                'end_date' => '2026-06-15',
            ])
            ->assertCreated()
            ->assertJsonPath('data.procurement_track', 'food')
            ->assertJsonPath('data.coverage_status', 'full');
    }

    public function test_list_level_estimate_population_scales_generated_quantities_without_menu_day_estimate(): void
    {
        // Planning headcount belongs to the shopping list, not the menu day cell.
        $fsItem = FsItem::factory()->create([
            'name' => 'Yakult',
            'base_unit' => 'piece',
            'purchase_unit' => 'pack',
            'purchase_price' => 100,
            'units_per_purchase' => 10,
        ]);
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
            'cycle_days' => 7,
        ]);
        $day = MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'fs_item_id' => $fsItem->id,
            'quantity' => 2,
            'estimate_population' => null,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson('/api/fss/shopping-lists/generate', [
                'start_date' => '2026-06-15',
                'end_date' => '2026-06-15',
            ])
            ->assertCreated()
            ->assertJsonPath('data.estimate_population', null);

        $listId = $response->json('data.id');

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/shopping-lists/{$listId}", ['estimate_population' => 25])
            ->assertOk()
            ->assertJsonPath('data.estimate_population', 25)
            ->assertJsonPath('data.estimated_budget_per_head_per_day', 20);

        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => ShoppingList::where('uuid', $listId)->value('id'),
            'fs_item_id' => $fsItem->id,
            'qty' => 50,
            'purchase_qty' => 5,
            'total' => 500,
        ]);

        $this->assertDatabaseHas('menu_cycle_days', [
            'id' => $day->id,
            'estimate_population' => null,
        ]);
    }
}
