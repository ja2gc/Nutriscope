<?php

namespace Tests\Feature;

use App\Models\DietListCount;
use App\Models\FsItem;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DietListCountTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    private User $fss2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
        $this->fss2 = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
    }

    // -------------------------------------------------------------------------
    // POST store
    // -------------------------------------------------------------------------

    public function test_diet_list_distribution_counts_do_not_update_served_population(): void
    {
        $date = '2026-06-20';
        $rnd = User::factory()->create(['role' => 'RND']);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id]);

        // Create a meal_prep_log for the same date so we can assert it gets updated.
        $log = MealPrepLog::create([
            'service_date' => $date,
            'menu_cycle_id' => $cycle->id,
            'population' => 100,
            'served_population' => 100,
            'population_variance' => 0,
            'status' => 'completed',
            'completed_by' => $this->fss->id,
            'completed_at' => now(),
            'total_value' => 0,
            'has_shortfall' => false,
        ]);

        $wards = [
            ['ward' => 'Ward A', 'population' => 30],
            ['ward' => 'Ward B', 'population' => 25],
            ['ward' => 'Ward C', 'population' => 20],
        ];

        foreach ($wards as $payload) {
            $this->actingAs($this->fss)
                ->postJson('/api/fss/diet-list-counts', array_merge([
                    'service_date' => $date,
                    'menu_cycle_id' => $cycle->uuid,
                ], $payload))
                ->assertCreated();
        }

        // All three ward rows exist.
        $this->assertDatabaseCount('diet_list_counts', 3);

        // Served population on the log = 30 + 25 + 20 = 75.
        $this->assertDatabaseHas('meal_prep_logs', [
            'id' => $log->id,
            'served_population' => 100,
        ]);
    }

    public function test_task_flag_booleans_persist_correctly(): void
    {
        $response = $this->actingAs($this->fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-20',
            'ward' => 'ICU',
            'population' => 10,
            'helped_food_prep' => true,
            'collected_diet_list' => true,
            'apportioned_food' => true,
            'off_duty' => false,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('diet_list_counts', [
            'ward' => 'ICU',
            'helped_food_prep' => true,
            'collected_diet_list' => true,
            'apportioned_food' => true,
            'off_duty' => false,
            'stored_supplies' => false,
        ]);
    }

    public function test_fss_user_id_is_forced_to_authenticated_user(): void
    {
        $response = $this->actingAs($this->fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-20',
            'ward' => 'Ward A',
            'population' => 15,
            'fss_user_id' => $this->fss2->id, // attacker tries to inject a different user
        ]);

        $response->assertCreated();

        // Must be recorded under the authenticated user, not the injected one.
        $this->assertDatabaseHas('diet_list_counts', [
            'ward' => 'Ward A',
            'fss_user_id' => $this->fss->id,
        ]);
        $this->assertDatabaseMissing('diet_list_counts', [
            'ward' => 'Ward A',
            'fss_user_id' => $this->fss2->id,
        ]);
    }

    public function test_unauthenticated_cannot_post_diet_list_count(): void
    {
        $this->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-20',
            'ward' => 'Ward A',
            'population' => 15,
        ])->assertUnauthorized();
    }

    public function test_non_fss_role_cannot_post_diet_list_count(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        $this->actingAs($rnd)->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-20',
            'ward' => 'Ward A',
            'population' => 15,
        ])->assertForbidden();
    }

    public function test_future_service_date_is_rejected_using_philippine_date(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-20 16:30:00', 'UTC'));

        $this->actingAs($this->fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-22',
            'ward' => 'Ward A',
            'population' => 15,
        ])->assertUnprocessable()->assertJsonValidationErrors('service_date');

        $this->actingAs($this->fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-21',
            'ward' => 'Ward A',
            'population' => 15,
        ])->assertCreated();
    }

    public function test_off_duty_and_working_rows_cannot_coexist_for_one_date(): void
    {
        $date = CarbonImmutable::now('Asia/Manila')->subDay()->toDateString();

        $this->actingAs($this->fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => $date,
            'ward' => 'Ward A',
            'population' => 15,
        ])->assertCreated();

        $this->actingAs($this->fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => $date,
            'ward' => 'Off duty',
            'population' => 0,
            'off_duty' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('off_duty');
    }

    public function test_working_row_cannot_follow_an_off_duty_row(): void
    {
        $date = CarbonImmutable::now('Asia/Manila')->subDays(2)->toDateString();

        $this->actingAs($this->fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => $date,
            'ward' => 'Off duty',
            'population' => 0,
            'off_duty' => true,
        ])->assertCreated();

        $this->actingAs($this->fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => $date,
            'ward' => 'Ward A',
            'population' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('off_duty');
    }

    public function test_off_duty_entry_requires_zero_population_and_no_tasks(): void
    {
        $date = CarbonImmutable::now('Asia/Manila')->subDays(3)->toDateString();

        $this->actingAs($this->fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => $date,
            'ward' => 'Off duty',
            'population' => 3,
            'helped_food_prep' => true,
            'off_duty' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['population', 'helped_food_prep']);
    }

    // -------------------------------------------------------------------------
    // GET index
    // -------------------------------------------------------------------------

    public function test_index_returns_rows_in_date_range(): void
    {
        DietListCount::factory()->create(['service_date' => '2026-06-18', 'fss_user_id' => $this->fss->id]);
        DietListCount::factory()->create(['service_date' => '2026-06-20', 'fss_user_id' => $this->fss->id]);
        DietListCount::factory()->create(['service_date' => '2026-06-22', 'fss_user_id' => $this->fss->id]);

        $response = $this->actingAs($this->fss)
            ->getJson('/api/fss/diet-list-counts?from=2026-06-18&to=2026-06-20');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // complete-day integration: diet-list rows stay separate from served_population
    // -------------------------------------------------------------------------

    public function test_complete_day_uses_hand_typed_served_population_when_diet_list_rows_exist(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id]);
        $item = FsItem::factory()->create(['name' => 'Rice', 'base_unit' => 'g']);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $item->id,
            'quantity' => 10,
            'estimate_population' => 5,
        ]);
        // Pre-submit two accomplishment report distribution counts for the same date.
        DietListCount::create([
            'service_date' => '2026-06-15', // Monday
            'menu_cycle_id' => $cycle->id,
            'ward' => 'Ward A',
            'fss_user_id' => $this->fss->id,
            'population' => 25,
        ]);
        DietListCount::create([
            'service_date' => '2026-06-15',
            'menu_cycle_id' => $cycle->id,
            'ward' => 'Ward B',
            'fss_user_id' => $this->fss2->id,
            'population' => 20,
        ]);

        // complete-day passes served_population = 99. Distribution total 45 must not override it.
        $response = $this->actingAs($this->fss)
            ->postJson("/api/fss/menu-cycles/{$cycle->uuid}/complete-day", [
                'service_date' => '2026-06-15',
                'population' => 5,
                'served_population' => 99,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('meal_prep_logs', [
            'menu_cycle_id' => $cycle->id,
            'service_date' => '2026-06-15',
            'served_population' => 99,
        ]);
    }

    public function test_complete_day_falls_back_to_hand_typed_when_no_diet_list_rows(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id]);
        $item = FsItem::factory()->create(['name' => 'Bread', 'base_unit' => 'g']);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $item->id,
            'quantity' => 10,
            'estimate_population' => 5,
        ]);
        // No diet-list rows — hand-typed value must be used.
        $response = $this->actingAs($this->fss)
            ->postJson("/api/fss/menu-cycles/{$cycle->uuid}/complete-day", [
                'service_date' => '2026-06-15',
                'population' => 5,
                'served_population' => 42,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('meal_prep_logs', [
            'menu_cycle_id' => $cycle->id,
            'served_population' => 42,
        ]);
    }
}
