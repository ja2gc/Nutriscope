<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MealPrepShortfallTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;
    private User $rnd;
    private MenuCycle $cycle;
    private FsItem $item;
    private Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
        $this->rnd = User::factory()->create(['role' => 'RND', 'password' => Hash::make('password')]);

        // Cycle owned by the RND; Monday lunch needs 100 g/head of one item.
        $this->cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id, 'population' => 5]);
        $this->item  = FsItem::factory()->create(['name' => 'Rice', 'base_unit' => 'g']);
        MenuCycleDay::create([
            'menu_cycle_id' => $this->cycle->id, 'day_of_week' => 'Monday',
            'meal_type' => 'lunch', 'fs_item_id' => $this->item->id, 'quantity' => 100,
            'estimate_population' => 5,
        ]);
    }

    private function stock(float $qty): void
    {
        $this->inventory = Inventory::factory()->create([
            'fs_item_id' => $this->item->id, 'quantity_in_stock' => $qty, 'unit' => 'g', 'unit_price' => 0.05,
        ]);
    }

    /** 2026-06-15 is a Monday. */
    private function complete(array $payload)
    {
        return $this->actingAs($this->fss)
            ->postJson("/api/fss/menu-cycles/{$this->cycle->id}/complete-day", array_merge([
                'service_date' => '2026-06-15',
                'population'   => 5,
            ], $payload));
    }

    public function test_short_stock_hard_blocks_by_default(): void
    {
        $this->stock(300); // need 500 (100 g × 5 heads)

        $this->complete([])->assertStatus(422);

        $this->assertDatabaseMissing('meal_prep_logs', ['menu_cycle_id' => $this->cycle->id]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_allow_shortfall_serves_partial_and_notifies_rnd(): void
    {
        $this->stock(300); // need 500 -> short 200

        $res = $this->complete(['allow_shortfall' => true]);

        $res->assertCreated();
        $this->assertDatabaseHas('meal_prep_logs', [
            'menu_cycle_id' => $this->cycle->id, 'has_shortfall' => true,
        ]);
        $this->assertDatabaseHas('meal_prep_log_lines', [
            'fs_item_id' => $this->item->id, 'shortfall_qty' => 200,
        ]);
        // Stock drawn down to zero (served what was available).
        $this->assertEqualsWithDelta(0, $this->inventory->fresh()->quantity_in_stock, 0.01);
        $logId = \App\Models\MealPrepLog::latest('id')->value('id');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->rnd->id, 'type' => 'meal_prep_shortfall', 'source_id' => $logId,
        ]);
    }

    public function test_over_prepared_records_surplus_variance_and_notifies_rnd(): void
    {
        $this->stock(10000);

        $res = $this->complete(['population' => 10, 'served_population' => 7]);

        $res->assertCreated();
        $this->assertDatabaseHas('meal_prep_logs', [
            'menu_cycle_id'       => $this->cycle->id,
            'population'          => 10,
            'served_population'   => 7,
            'population_variance' => 3, // cooked too much
            'has_shortfall'       => false,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->rnd->id, 'type' => 'meal_prep_variance',
        ]);
    }

    public function test_under_prepared_records_negative_variance_and_notifies_rnd(): void
    {
        $this->stock(10000);

        $res = $this->complete(['population' => 8, 'served_population' => 12]);

        $res->assertCreated();
        $this->assertDatabaseHas('meal_prep_logs', [
            'population' => 8, 'served_population' => 12, 'population_variance' => -4,
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->rnd->id, 'type' => 'meal_prep_variance']);
    }

    public function test_on_plan_day_creates_no_notification(): void
    {
        $this->stock(10000);

        $this->complete(['population' => 5, 'served_population' => 5])->assertCreated();

        $this->assertDatabaseHas('meal_prep_logs', ['population_variance' => 0, 'has_shortfall' => false]);
        $this->assertDatabaseCount('notifications', 0);
    }
}
