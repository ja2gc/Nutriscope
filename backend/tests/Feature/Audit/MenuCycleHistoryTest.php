<?php

namespace Tests\Feature\Audit;

use App\Models\AuditActivity;
use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class MenuCycleHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    private User $rnd;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rnd = User::factory()->rnd()->create();
        $this->admin = User::factory()->admin()->create();
        AuditFixture::delete(AuditActivity::query());
    }

    public function test_create_emits_one_event_with_an_after_only_complete_weekly_menu_revision(): void
    {
        $item = $this->item('Banana', 12);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)->postJson('/api/fss/menu-cycles', [
            'name' => 'July Week One',
            'week_start_date' => '2026-07-13',
            'days' => [[
                'day_of_week' => 'Monday',
                'meal_type' => 'am_snack',
                'fs_item_id' => $item->uuid,
                'quantity' => 1,
                'estimate_population' => 20,
            ]],
        ])->assertCreated();

        $activity = AuditActivity::query()->where('subject_type', MenuCycle::class)->sole();
        $this->assertSame('created', $activity->event);
        $this->assertContains('days', $activity->properties['details']['changed_fields']);
        $this->assertNull($activity->revision->before);
        $this->assertSame('July Week One', $activity->revision->after['title']);
        $this->assertSame('Banana', $activity->revision->after['slots'][0]['item']);

        $this->actingAs($this->admin)
            ->getJson("/api/admin/audit-logs/{$activity->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.version.serializer', 'menu_cycle')
            ->assertJsonPath('data.event.detail_mode', 'history')
            ->assertJsonPath('data.after.type', 'menu_cycle')
            ->assertJsonPath('data.after.tables.0.rows.0.values.item.value', 'Banana');
    }

    public function test_structural_update_preserves_event_time_before_and_after_menu_versions(): void
    {
        $banana = $this->item('Banana', 12);
        $milk = $this->item('Milk', 25);
        $cycle = $this->cycleWithItem($banana, 'Original Week', 'Monday');
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)->patchJson("/api/fss/menu-cycles/{$cycle->uuid}", [
            'name' => 'Updated Week',
            'days' => [[
                'day_of_week' => 'Tuesday',
                'meal_type' => 'am_snack',
                'fs_item_id' => $milk->uuid,
                'quantity' => 1,
                'estimate_population' => 30,
            ]],
        ])->assertOk();

        $activity = AuditActivity::query()->where('subject_type', MenuCycle::class)->sole();
        $this->assertSame('updated', $activity->event);
        $this->assertSame('Original Week', $activity->revision->before['name']);
        $this->assertSame('Banana', $activity->revision->before['slots'][0]['item']);
        $this->assertSame('Updated Week', $activity->revision->after['name']);
        $this->assertSame('Milk', $activity->revision->after['slots'][0]['item']);

        MenuCycle::withoutEvents(fn (): int => MenuCycle::query()
            ->whereKey($cycle->id)
            ->update(['name' => 'Later Mutable Week']));
        $this->actingAs($this->admin)
            ->getJson("/api/admin/audit-logs/{$activity->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.before.title', 'Original Week')
            ->assertJsonPath('data.after.title', 'Updated Week')
            ->assertJsonPath('data.event.current_record_url', null);
    }

    public function test_simple_name_update_stays_in_the_typed_drawer_without_a_revision(): void
    {
        $cycle = $this->cycleWithItem($this->item('Banana', 12), 'Original Week', 'Monday');
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}", ['name' => 'Renamed Week'])
            ->assertOk();

        $activity = AuditActivity::query()->where('subject_type', MenuCycle::class)->sole();
        $this->assertNull($activity->revision);
        $presented = app(AuditEventPresenter::class)->present($activity, $this->admin)->toArray();
        $this->assertSame('changes', $presented['detail_mode']);
        $this->assertSame('Name', $presented['changes'][0]['label']);
        $this->assertSame('Original Week', $presented['changes'][0]['before']['value']);
        $this->assertSame('Renamed Week', $presented['changes'][0]['after']['value']);
    }

    public function test_activation_captures_the_complete_plan_and_frozen_status_transition(): void
    {
        $item = $this->item('Banana', 12);
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Activation Week',
            'week_start_date' => '2026-07-13',
            'status' => 'upcoming',
            'is_active' => false,
        ]);
        foreach (self::WEEKDAYS as $weekday) {
            MenuCycleDay::create([
                'menu_cycle_id' => $cycle->id,
                'day_of_week' => $weekday,
                'meal_type' => 'am_snack',
                'fs_item_id' => $item->id,
                'quantity' => 1,
                'estimate_population' => 10,
            ]);
        }
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/activate")
            ->assertOk();

        $activity = AuditActivity::query()->where('subject_type', MenuCycle::class)->sole();
        $this->assertSame('upcoming', $activity->revision->before['status']);
        $this->assertFalse($activity->revision->before['is_active']);
        $this->assertSame('active', $activity->revision->after['status']);
        $this->assertTrue($activity->revision->after['is_active']);
        $this->assertSame(840, $activity->revision->after['totals']['total_cost']);
    }

    public function test_delete_revision_survives_the_deleted_live_menu(): void
    {
        $cycle = $this->cycleWithItem($this->item('Banana', 12), 'Retired Week', 'Monday');
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)
            ->deleteJson("/api/fss/menu-cycles/{$cycle->uuid}")
            ->assertNoContent();

        $activity = AuditActivity::query()->where('subject_type', MenuCycle::class)->sole();
        $this->assertDatabaseMissing('menu_cycles', ['id' => $cycle->id]);
        $this->assertSame('Retired Week', $activity->revision->before['name']);
        $this->assertNull($activity->revision->after);
        $this->actingAs($this->admin)
            ->getJson("/api/admin/audit-logs/{$activity->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.before.title', 'Retired Week')
            ->assertJsonPath('data.after', null);
    }

    public function test_locked_structural_update_leaves_no_event_or_revision(): void
    {
        $item = $this->item('Banana', 12);
        $cycle = $this->cycleWithItem($item, 'Active Week', 'Monday', true);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)->patchJson("/api/fss/menu-cycles/{$cycle->uuid}", [
            'days' => [[
                'day_of_week' => 'Tuesday',
                'meal_type' => 'am_snack',
                'fs_item_id' => $item->uuid,
                'quantity' => 1,
                'estimate_population' => 10,
            ]],
        ])->assertUnprocessable();

        $this->assertSame(0, AuditActivity::query()->where('subject_type', MenuCycle::class)->count());
    }

    private function item(string $name, float $price): FsItem
    {
        return FsItem::factory()->create([
            'name' => $name,
            'base_unit' => 'piece',
            'purchase_unit' => 'piece',
            'purchase_price' => $price,
        ]);
    }

    private function cycleWithItem(
        FsItem $item,
        string $name,
        string $weekday,
        bool $active = false,
    ): MenuCycle {
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'name' => $name,
            'week_start_date' => '2026-07-13',
            'status' => $active ? 'active' : 'upcoming',
            'is_active' => $active,
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => $weekday,
            'meal_type' => 'am_snack',
            'fs_item_id' => $item->id,
            'quantity' => 1,
            'estimate_population' => 20,
        ]);

        return $cycle;
    }
}
