<?php

namespace Tests\Feature;

use App\Models\FsItem;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
        $this->rnd = User::factory()->create(['role' => 'RND', 'password' => Hash::make('password')]);

        $this->cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id]);
        $item = FsItem::factory()->create(['name' => 'Rice', 'base_unit' => 'g']);

        MenuCycleDay::create([
            'menu_cycle_id'       => $this->cycle->id,
            'day_of_week'         => 'Monday',
            'meal_type'           => 'lunch',
            'fs_item_id'          => $item->id,
            'quantity'            => 100,
            'estimate_population' => 5,
        ]);
    }

    private function complete(array $payload = [])
    {
        return $this->actingAs($this->fss)
            ->postJson("/api/fss/menu-cycles/{$this->cycle->uuid}/complete-day", array_merge([
                'service_date' => '2026-06-15',
                'population'   => 5,
            ], $payload));
    }

    public function test_mark_served_does_not_require_stock_or_create_shortfall(): void
    {
        $this->complete()->assertCreated();

        $this->assertDatabaseHas('meal_prep_logs', [
            'menu_cycle_id' => $this->cycle->id,
            'has_shortfall' => false,
            'total_value'   => 0,
        ]);
        $this->assertDatabaseCount('meal_prep_log_lines', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_allow_shortfall_payload_is_ignored_for_formal_completion(): void
    {
        $this->complete(['allow_shortfall' => true])->assertCreated();

        $this->assertDatabaseHas('meal_prep_logs', [
            'menu_cycle_id' => $this->cycle->id,
            'has_shortfall' => false,
            'total_value'   => 0,
        ]);
        $this->assertDatabaseCount('meal_prep_log_lines', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_over_prepared_records_surplus_variance_without_notification(): void
    {
        $this->complete(['population' => 10, 'served_population' => 7])->assertCreated();

        $this->assertDatabaseHas('meal_prep_logs', [
            'menu_cycle_id'       => $this->cycle->id,
            'population'          => 10,
            'served_population'   => 7,
            'population_variance' => 3,
            'has_shortfall'       => false,
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_under_prepared_records_negative_variance_without_notification(): void
    {
        $this->complete(['population' => 8, 'served_population' => 12])->assertCreated();

        $this->assertDatabaseHas('meal_prep_logs', [
            'population'          => 8,
            'served_population'   => 12,
            'population_variance' => -4,
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_on_plan_day_creates_no_notification(): void
    {
        $this->complete(['population' => 5, 'served_population' => 5])->assertCreated();

        $this->assertDatabaseHas('meal_prep_logs', [
            'population_variance' => 0,
            'has_shortfall'       => false,
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }
}
