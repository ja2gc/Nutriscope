<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\User;
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

    public function test_active_cycle_rejects_structural_day_edits(): void
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
            ->assertStatus(422)
            ->assertJsonPath('message', 'Active or snapshotted menu cycles cannot change menu day structure.');

        $this->assertDatabaseHas('menu_cycle_days', [
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
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
            ->assertStatus(422);
    }
}
