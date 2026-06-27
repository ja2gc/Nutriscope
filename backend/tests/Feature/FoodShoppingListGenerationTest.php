<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
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
                'end_date'   => '2026-06-16',
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
                'end_date'   => '2026-06-15',
            ])
            ->assertCreated()
            ->assertJsonPath('data.procurement_track', 'food')
            ->assertJsonPath('data.coverage_status', 'full');
    }
}
