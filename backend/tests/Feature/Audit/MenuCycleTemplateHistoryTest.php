<?php

namespace Tests\Feature\Audit;

use App\Models\AuditActivity;
use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class MenuCycleTemplateHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_create_structural_update_instantiation_and_delete_have_event_time_history(): void
    {
        $rnd = User::factory()->rnd()->create();
        $admin = User::factory()->admin()->create();
        $item = FsItem::factory()->create(['name' => 'Brown rice', 'base_unit' => 'kg']);
        AuditFixture::delete(AuditActivity::query());

        $templateId = $this->actingAs($rnd)->postJson('/api/fss/menu-cycle-templates', [
            'name' => 'Standard ward menu',
            'description' => 'TEMPLATE-DESCRIPTION-SENTINEL',
            'cycle_days' => 7,
            'days' => [[
                'day_of_week' => 'Monday',
                'meal_type' => 'lunch',
                'fs_item_id' => $item->uuid,
                'quantity' => 2,
            ]],
        ])->assertCreated()->json('data.id');

        $template = MenuCycleTemplate::query()->where('uuid', $templateId)->firstOrFail();
        $created = AuditActivity::query()
            ->where('subject_type', $template->getMorphClass())
            ->where('subject_id', $template->id)
            ->where('event', 'created')
            ->sole();
        $this->assertNotNull($created->revision);
        $this->assertNull($created->revision->before);
        $this->assertSame(MenuCycleTemplate::class, $created->revision->subject_type);
        $this->assertSame('Brown rice', $created->revision->after['slots'][0]['item']);
        $this->assertStringNotContainsString('TEMPLATE-DESCRIPTION-SENTINEL', $created->revision->toJson());

        $this->patchJson("/api/fss/menu-cycle-templates/{$templateId}", [
            'days' => [[
                'day_of_week' => 'Monday',
                'meal_type' => 'lunch',
                'fs_item_id' => $item->uuid,
                'quantity' => 3,
            ]],
        ])->assertOk();
        $updated = AuditActivity::query()
            ->where('subject_type', $template->getMorphClass())
            ->where('subject_id', $template->id)
            ->where('event', 'updated')
            ->sole();
        $this->assertEquals(2.0, $updated->revision->before['slots'][0]['quantity']);
        $this->assertEquals(3.0, $updated->revision->after['slots'][0]['quantity']);

        $cycleId = $this->postJson("/api/fss/menu-cycle-templates/{$templateId}/instantiate", [
            'name' => 'Instantiated menu',
            'week_start_date' => '2026-07-20',
        ])->assertCreated()->json('data.id');
        $cycle = MenuCycle::query()->where('uuid', $cycleId)->firstOrFail();
        $cycleCreated = AuditActivity::query()
            ->where('subject_type', $cycle->getMorphClass())
            ->where('subject_id', $cycle->id)
            ->where('event', 'created')
            ->sole();
        $this->assertSame(MenuCycle::class, $cycleCreated->revision->subject_type);
        $this->assertSame('Brown rice', $cycleCreated->revision->after['slots'][0]['item']);

        $this->actingAs($admin)
            ->getJson("/api/admin/audit-logs/{$updated->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.version.serializer', 'menu_cycle_template')
            ->assertJsonPath('data.before.type', 'menu_cycle_template')
            ->assertJsonPath('data.after.tables.0.rows.0.values.item.value', 'Brown rice');

        $this->actingAs($rnd)->deleteJson("/api/fss/menu-cycle-templates/{$templateId}")->assertNoContent();
        $deleted = AuditActivity::query()
            ->where('subject_type', $template->getMorphClass())
            ->where('subject_id', $template->id)
            ->where('event', 'deleted')
            ->sole();
        $this->assertNotNull($deleted->revision->before);
        $this->assertNull($deleted->revision->after);
        $this->assertDatabaseMissing('menu_cycle_templates', ['id' => $template->id]);
        $this->actingAs($admin)
            ->getJson("/api/admin/audit-logs/{$deleted->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.before.tables.0.rows.0.values.item.value', 'Brown rice')
            ->assertJsonPath('data.after', null);
    }
}
