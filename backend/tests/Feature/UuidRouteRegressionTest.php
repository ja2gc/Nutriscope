<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Notification;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression suite for the flat-FK-vs-uuid routing bugs introduced by the
 * sequential-int → uuid route migration. Each case asserts that a resource
 * exposes a public uuid where a client feeds the value into a uuid-bound
 * route/match — the raw internal FK would 404 or silently fail to match.
 */
class UuidRouteRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Meal-plan items must expose meal_plan_day_id (used to bucket items into the
     * grid, keyed by the day's uuid) and recipe_id (used to fetch the recipe via the
     * uuid-bound route when editing) as public uuids — not the raw internal FKs.
     */
    public function test_meal_plan_items_expose_day_and_recipe_uuids(): void
    {
        $rnd = User::factory()->rnd()->create();
        $ncpRecord = NcpRecord::factory()->create(['rnd_user_id' => $rnd->id]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncpRecord->id]);
        $mealPlan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $ncpRecord->patient_id,
        ]);
        $day = MealPlanDay::factory()->create(['meal_plan_id' => $mealPlan->id]);
        $recipe = Recipe::factory()->create();
        $item = MealPlanItem::factory()->create([
            'meal_plan_day_id' => $day->id,
            'recipe_id' => $recipe->id,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$mealPlan->uuid}/items");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $item->uuid)
            ->assertJsonPath('data.0.meal_plan_day_id', $day->uuid)
            ->assertJsonPath('data.0.recipe_id', $recipe->uuid);

        // The raw internal FKs must never leak.
        $this->assertNotSame($day->id, $response->json('data.0.meal_plan_day_id'));
        $this->assertNotSame($recipe->id, $response->json('data.0.recipe_id'));
    }

    /**
     * Notification deep-links address their target by uuid, but source_id is a bigint
     * column holding the raw internal FK. The listing must resolve and expose the
     * target's public uuid as source_uuid so the client can open the right record.
     */
    public function test_notification_listing_resolves_announcement_source_uuid(): void
    {
        $rnd = User::factory()->rnd()->create();
        $announcement = Announcement::create([
            'user_id' => $rnd->id,
            'title' => 'Cycle menu updated',
            'body' => 'Please review.',
            'visibility' => 'All',
        ]);
        Notification::factory()->create([
            'user_id' => $rnd->id,
            'type' => 'announcement',
            'source_module' => 'announcements',
            'source_id' => $announcement->id,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonPath('data.0.source_uuid', $announcement->uuid);

        // source_id keeps the raw FK (backward compat), but source_uuid is the routable id.
        $this->assertNotSame($announcement->uuid, $response->json('data.0.source_id'));
    }

    public function test_follow_up_notification_resolves_ncp_and_patient_uuids(): void
    {
        $rnd = User::factory()->rnd()->create();
        $ncp = NcpRecord::factory()->create(['rnd_user_id' => $rnd->id]);
        Notification::factory()->create([
            'user_id' => $rnd->id,
            'type' => 'follow_up',
            'source_module' => 'ncp',
            'source_id' => $ncp->id,
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.source_uuid', $ncp->uuid)
            ->assertJsonPath('data.0.source_parent_uuid', $ncp->patient->uuid);
    }

    public function test_follow_up_notification_does_not_expose_another_rnds_patient_uuid(): void
    {
        $rnd = User::factory()->rnd()->create();
        $otherRnd = User::factory()->rnd()->create();
        $otherNcp = NcpRecord::factory()->create(['rnd_user_id' => $otherRnd->id]);
        Notification::factory()->create([
            'user_id' => $rnd->id,
            'type' => 'follow_up',
            'source_module' => 'ncp',
            'source_id' => $otherNcp->id,
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.source_uuid', null)
            ->assertJsonPath('data.0.source_parent_uuid', null);
    }
}
