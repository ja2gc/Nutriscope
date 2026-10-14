<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\MenuCycleTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuCycleWorkflowGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->rnd()->create();
    }

    public function test_service_date_uses_the_latest_covering_week_instead_of_a_stale_active_overlap(): void
    {
        $stale = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-14',
            'is_active' => true,
            'status' => 'active',
        ]);
        $current = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
            'is_active' => false,
            'status' => 'upcoming',
        ]);
        $item = FsItem::factory()->create();
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $current->id,
            'day_of_week' => 'Wednesday',
            'meal_type' => 'lunch',
            'fs_item_id' => $item->id,
        ]);

        $this->assertTrue(MenuCycle::coveringDate('2026-06-17')->is($current));
        $this->assertTrue(MenuCycle::coveringDate('2026-06-14')->is($stale));
        $this->assertNull(MenuCycle::coveringDate('2026-06-22'));

        $this->actingAs(User::factory()->create(['role' => 'FSS']))
            ->patchJson("/api/fss/menu-cycles/{$current->uuid}/served-population", [
                'service_date' => '2026-06-17',
                'served_population' => 33,
            ])
            ->assertOk()
            ->assertJsonPath('data.served_population', 33);
    }

    public function test_cost_for_a_requested_date_uses_the_covering_cycle(): void
    {
        MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Stale active menu',
            'week_start_date' => '2026-06-14',
            'is_active' => true,
        ]);
        MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Wednesday menu',
            'week_start_date' => '2026-06-15',
        ]);

        $this->actingAs($this->rnd)
            ->getJson('/api/fss/menu-cycles/cost-today?date=2026-06-17')
            ->assertOk()
            ->assertJsonPath('data.cycle', 'Wednesday menu');
    }

    public function test_date_filtered_cycle_list_resolves_a_past_service_week_without_activation(): void
    {
        $past = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
            'is_active' => false,
        ]);

        $this->actingAs($this->rnd)->getJson('/api/fss/menu-cycles?date=2026-06-17')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $past->uuid);
        $this->getJson('/api/fss/menu-cycles?date=2026-06-24')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/fss/menu-cycles?date=tomorrow')->assertUnprocessable();
    }

    public function test_a_placeholder_weekday_without_a_service_item_cannot_record_served_population(): void
    {
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
        ]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Wednesday',
            'recipe_id' => null,
            'fs_item_id' => null,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'FSS']))
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/served-population", [
                'service_date' => '2026-06-17',
                'served_population' => 33,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_date')
            ->assertJsonPath('errors.service_date.0', 'No meals are planned for this service date.');
    }

    public function test_listing_cycles_maintains_an_empty_current_week_and_one_future_week_idempotently(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-29 12:00:00', 'Asia/Manila'));
        try {
            $response = $this->actingAs($this->rnd)->getJson('/api/fss/menu-cycles?per_page=10')->assertOk();
            $this->assertSame(2, MenuCycle::count());
            $this->assertSame([
                '2026-12-28', '2027-01-04',
            ], MenuCycle::orderBy('week_start_date')->pluck('week_start_date')->map->toDateString()->all());
            $this->assertSame(1, MenuCycle::where('is_active', true)->count());
            $this->assertSame('2026-12-28', MenuCycle::where('is_active', true)->firstOrFail()->week_start_date->toDateString());
            $this->assertNull(MenuCycle::where('is_active', true)->firstOrFail()->cost_snapshot);
            $this->assertSame(['2027-01-04', '2026-12-28'], array_column($response->json('data'), 'week_start_date'));

            $this->getJson('/api/fss/menu-cycles?per_page=10')->assertOk();
            $this->assertSame(2, MenuCycle::count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_week_rollover_retires_the_past_week_without_replacing_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-29 12:00:00', 'Asia/Manila'));
        try {
            $this->actingAs($this->rnd)->getJson('/api/fss/menu-cycles?per_page=10')->assertOk();
            $past = MenuCycle::whereDate('week_start_date', '2026-12-28')->firstOrFail();
            Carbon::setTestNow(Carbon::parse('2027-01-04 12:00:00', 'Asia/Manila'));
            $this->getJson('/api/fss/menu-cycles?per_page=10')->assertOk();

            $this->assertFalse($past->fresh()->is_active);
            $this->assertSame('completed', $past->fresh()->status);
            $this->assertSame('2027-01-04', MenuCycle::where('is_active', true)->firstOrFail()->week_start_date->toDateString());
            $this->assertSame(3, MenuCycle::count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_preplanned_next_week_is_reused_and_calendar_lists_latest_week_first(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00', 'Asia/Manila'));
        try {
            $next = MenuCycle::factory()->create([
                'rnd_user_id' => $this->rnd->id,
                'name' => 'Already planned',
                'week_start_date' => '2026-10-19',
            ]);
            MenuCycle::factory()->create([
                'rnd_user_id' => $this->rnd->id,
                'week_start_date' => '2026-09-28',
            ]);

            $response = $this->actingAs($this->rnd)->getJson('/api/fss/menu-cycles?per_page=10')->assertOk();
            $this->assertSame(['2026-10-19', '2026-10-12', '2026-09-28'], array_column($response->json('data'), 'week_start_date'));
            $this->assertSame($next->id, MenuCycle::whereDate('week_start_date', '2026-10-19')->sole()->id);
            $this->assertSame('Already planned', $next->fresh()->name);

            $this->getJson('/api/fss/menu-cycles?per_page=10')->assertOk();
            $this->assertSame(3, MenuCycle::count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_cycle_name_defaults_to_its_week_span_and_tracks_date_changes(): void
    {
        $response = $this->actingAs($this->rnd)->postJson('/api/fss/menu-cycles', [
            'week_start_date' => '2026-08-17',
        ])->assertCreated()->assertJsonPath('data.name', 'Weekly Menu — Aug 17–23, 2026');

        $id = $response->json('data.id');
        $this->actingAs($this->rnd)->patchJson("/api/fss/menu-cycles/{$id}", [
            'week_start_date' => '2026-08-24',
        ])->assertOk()->assertJsonPath('data.name', 'Weekly Menu — Aug 24–30, 2026');
    }

    public function test_custom_cycle_name_is_not_replaced_when_dates_change(): void
    {
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Ward anniversary menu',
            'week_start_date' => '2026-08-17',
        ]);

        $this->actingAs($this->rnd)->patchJson("/api/fss/menu-cycles/{$cycle->uuid}", [
            'week_start_date' => '2026-08-24',
        ])->assertOk()->assertJsonPath('data.name', 'Ward anniversary menu');
    }

    public function test_template_instantiation_uses_the_new_week_name_and_copies_structure_only(): void
    {
        $item = FsItem::factory()->create();
        $template = MenuCycleTemplate::create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Standard ward menu',
            'cycle_days' => 7,
        ]);
        $template->days()->create([
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'line_order' => 1,
            'fs_item_id' => $item->id,
            'quantity' => 1,
        ]);
        $secondItem = FsItem::factory()->create();
        $template->days()->create([
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'line_order' => 2,
            'fs_item_id' => $secondItem->id,
            'quantity' => 0.5,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/fss/menu-cycle-templates/{$template->uuid}/instantiate", [
                'week_start_date' => '2026-08-17',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Weekly Menu — Aug 17–23, 2026');

        $cycle = MenuCycle::where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertDatabaseHas('menu_cycle_days', [
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'estimate_population' => null,
            'servings_override' => null,
        ]);
        $this->assertSame([1, 2], $cycle->days()->orderBy('line_order')->pluck('line_order')->all());
        $this->assertSame(2, $cycle->days()->where('day_of_week', 'Monday')->where('meal_type', 'lunch')->count());
        $this->assertSame('Standard ward menu', $template->fresh()->name);
    }

    public function test_template_detail_uses_public_item_ids_that_round_trip_through_update(): void
    {
        $item = FsItem::factory()->create();
        $templateId = $this->actingAs($this->rnd)->postJson('/api/fss/menu-cycle-templates', [
            'name' => 'Editable template',
            'days' => [[
                'day_of_week' => 'Monday',
                'meal_type' => 'lunch',
                'fs_item_id' => $item->uuid,
                'quantity' => 2,
            ]],
        ])->assertCreated()->json('data.id');

        $day = $this->getJson("/api/fss/menu-cycle-templates/{$templateId}")
            ->assertOk()
            ->assertJsonPath('data.days.0.fs_item_id', $item->uuid)
            ->json('data.days.0');

        $this->patchJson("/api/fss/menu-cycle-templates/{$templateId}", [
            'name' => 'Edited template',
            'days' => [$day],
        ])->assertOk()->assertJsonPath('data.name', 'Edited template');
    }

    public function test_incomplete_cycle_cannot_be_activated(): void
    {
        $item = FsItem::factory()->create();
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
            'cycle_days' => 7,
            'status' => 'upcoming',
        ]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $item->id,
        ]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/activate")
            ->assertStatus(422)
            ->assertJsonPath('missing_days.0', 'Tuesday');

        $this->assertFalse($cycle->fresh()->is_active);
    }

    public function test_active_cycle_without_a_purchase_order_can_fill_an_empty_service_day(): void
    {
        $item = FsItem::factory()->create();
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
            'cycle_days' => 7,
            'is_active' => true,
            'status' => 'active',
        ]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $item->id,
        ]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}", [
                'days' => [[
                    'day_of_week' => 'Tuesday',
                    'meal_type' => 'lunch',
                    'fs_item_id' => $item->uuid,
                    'quantity' => 1,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('menu_cycle_days', [
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Tuesday',
        ]);
    }

    public function test_snapshotted_cycle_rejects_structural_day_edits_even_when_inactive(): void
    {
        $item = FsItem::factory()->create();
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'week_start_date' => '2026-06-15',
            'cycle_days' => 7,
            'is_active' => false,
            'status' => 'completed',
        ]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $item->id,
            'po_snapshot' => ['name' => 'Frozen meal'],
            'po_snapshot_at' => now(),
        ]);

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}", [
                'days' => [[
                    'day_of_week' => 'Tuesday',
                    'meal_type' => 'lunch',
                    'fs_item_id' => $item->uuid,
                    'quantity' => 1,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This menu week can no longer be changed.');
    }
}
