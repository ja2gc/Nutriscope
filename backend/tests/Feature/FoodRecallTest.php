<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\NcpRecord;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodRecallTest extends TestCase
{
    use RefreshDatabase;

    private function setupCycle(): array
    {
        $rnd = User::factory()->rnd()->create();
        $ncp = NcpRecord::factory()->create(['status' => 'active', 'rnd_user_id' => $rnd->id]);
        $food = FoodItem::factory()->create(['name' => 'Recall rice', 'serving_size' => 100, 'serving_unit' => 'g',
            'calories' => 120, 'protein' => 3, 'carbs' => 25, 'fat' => 1, 'micronutrients' => ['sodium' => 2]]);

        return [$rnd, $ncp, $food];
    }

    private function plan(FoodItem $food): array
    {
        return ['days_count' => 1, 'displayed_micros' => ['sodium'], 'days' => [['items' => [[
            'food_item_id' => $food->uuid, 'recipe_id' => null, 'quantity' => 250, 'unit' => 'g',
        ]]]]];
    }

    private function assessmentPayload(array $overrides = []): array
    {
        return [...['weight' => 70, 'usual_weight' => 70, 'height' => 170, 'physical_activity_level' => 'light',
            'primary_diagnosis_category' => 'Diabetes'], ...$overrides];
    }

    public function test_recall_accepts_more_than_seven_days_and_groups_day_types_without_dates(): void
    {
        [$rnd, $ncp, $food] = $this->setupCycle();
        $plan = $this->plan($food);
        $day = $plan['days'][0];
        $plan['days_count'] = 9;
        $plan['days'] = array_map(fn (int $index): array => [...$day, 'kind' => $index % 2 ? 'weekday' : 'weekend'], range(0, 8));
        $saved = $this->actingAs($rnd, 'sanctum')->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", $this->assessmentPayload(['food_recall' => $plan]))
            ->assertCreated()->assertJsonPath('data.food_recall.days_count', 9)->json('data.food_recall.days');
        $this->assertSame(['weekday', 'weekday', 'weekday', 'weekday', 'weekend', 'weekend', 'weekend', 'weekend', 'weekend'], array_column($saved, 'kind'));
        $plan['days'][0]['kind'] = 'holiday';
        $this->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", ['food_recall' => $plan])->assertUnprocessable();
    }

    public function test_assessment_records_selected_intake_nutrients_without_a_calculator_plan(): void
    {
        [$rnd, $ncp] = $this->setupCycle();
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/assessment";
        $this->actingAs($rnd, 'sanctum')->postJson($url, $this->assessmentPayload(['intake_nutrients' => ['energy_kcal' => 1800, 'protein_g' => 65, 'micro_sodium' => null]]))
            ->assertCreated()->assertJsonPath('data.intake_nutrients.energy_kcal', 1800)->assertJsonPath('data.intake_nutrients.protein_g', 65)->assertJsonPath('data.food_recall', null);
        $this->patchJson($url, ['intake_nutrients' => ['energy_kcal' => -1]])->assertUnprocessable();
        $this->patchJson($url, ['intake_nutrients' => ['unknown' => 1]])->assertUnprocessable();
        $this->patchJson($url, ['intake_nutrients' => ['energy_kcal' => 1800]])->assertOk()->assertJsonMissingPath('data.intake_nutrients.protein_g');
    }

    public function test_assessment_saves_one_recall_and_keeps_frozen_nutrients_when_library_changes(): void
    {
        [$rnd, $ncp, $food] = $this->setupCycle();
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/assessment";
        $saved = $this->actingAs($rnd, 'sanctum')->postJson($url, $this->assessmentPayload(['food_recall' => $this->plan($food)]))
            ->assertCreated()->assertJsonPath('data.food_recall.days_count', 1)
            ->assertJsonPath('data.food_recall.days.0.items.0.nutrient_snapshot.calories', 120)
            ->json('data.food_recall');
        $food->update(['calories' => 999, 'name' => 'Changed library name']);
        unset($saved['days'][0]['items'][0]['nutrient_snapshot']);
        $saved['days'][0]['items'][0]['quantity'] = 500;

        $this->patchJson($url, ['food_recall' => $saved])->assertOk()
            ->assertJsonPath('data.food_recall.days.0.items.0.nutrient_snapshot.calories', 120)
            ->assertJsonPath('data.food_recall.days.0.items.0.nutrient_snapshot.name', 'Recall rice')
            ->assertJsonPath('data.food_recall.days.0.items.0.quantity', 500);
        $this->getJson($url)->assertOk()->assertJsonPath('data.food_recall.days_count', 1);
    }

    public function test_recall_requires_a_food_for_every_selected_day_and_rejects_forged_snapshots(): void
    {
        [$rnd, $ncp, $food] = $this->setupCycle();
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/assessment";
        $this->actingAs($rnd, 'sanctum');
        $plan = $this->plan($food);
        $plan['days_count'] = 3;
        $this->postJson($url, $this->assessmentPayload(['food_recall' => $plan]))->assertUnprocessable();
        $plan['days'] = [['items' => []], ['items' => []], ['items' => []]];
        $this->postJson($url, $this->assessmentPayload(['food_recall' => $plan]))->assertUnprocessable();
        $plan = $this->plan($food);
        $plan['days'][0]['items'][0]['nutrient_snapshot'] = ['calories' => 1];
        $this->postJson($url, $this->assessmentPayload(['food_recall' => $plan]))->assertUnprocessable();
        $this->assertDatabaseCount('assessments', 0);
    }

    public function test_invalid_unit_or_ambiguous_food_source_is_rejected_without_partial_write(): void
    {
        [$rnd, $ncp, $food] = $this->setupCycle();
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/assessment";
        $plan = $this->plan($food);
        $plan['days'][0]['items'][0]['unit'] = 'cup';
        $this->actingAs($rnd, 'sanctum')->postJson($url, $this->assessmentPayload(['food_recall' => $plan]))->assertUnprocessable();
        $plan = $this->plan($food);
        $plan['days'][0]['items'][0]['recipe_id'] = Recipe::factory()->create()->uuid;
        $this->postJson($url, $this->assessmentPayload(['food_recall' => $plan]))->assertUnprocessable();
        $this->assertDatabaseCount('assessments', 0);
    }

    public function test_recipe_local_ingredient_edits_do_not_modify_library_and_preserve_original_baselines(): void
    {
        [$rnd, $ncp, $food] = $this->setupCycle();
        $recipe = Recipe::factory()->create(['rnd_user_id' => $rnd->id, 'servings' => 2]);
        $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 200, 'unit' => 'g']);
        $plan = ['days_count' => 1, 'displayed_micros' => [], 'days' => [['items' => [[
            'recipe_id' => $recipe->uuid, 'quantity' => 1, 'unit' => 'serving',
            'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 0.4, 'unit' => 'kg']],
        ]]]]];
        $saved = $this->actingAs($rnd, 'sanctum')->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", $this->assessmentPayload(['food_recall' => $plan]))
            ->assertCreated()->assertJsonPath('data.food_recall.days.0.items.0.nutrient_snapshot.calories', 240)->json('data.food_recall');
        $this->assertEquals(200, $recipe->ingredients()->first()->quantity);
        $this->assertSame('120.00', $food->fresh()->calories);
        $food->update(['calories' => 999]);
        unset($saved['days'][0]['items'][0]['nutrient_snapshot']);
        $saved['days'][0]['items'][0]['ingredients'][0]['quantity'] = 0.2;
        $this->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", ['food_recall' => $saved])
            ->assertOk()->assertJsonPath('data.food_recall.days.0.items.0.nutrient_snapshot.calories', 120);
    }

    public function test_latest_monitoring_can_update_its_own_recall_while_older_logs_are_protected(): void
    {
        [$rnd, $ncp, $food] = $this->setupCycle();
        Assessment::factory()->create(['ncp_record_id' => $ncp->id]);
        Intervention::factory()->create(['ncp_record_id' => $ncp->id]);
        $payload = ['observed_at' => now()->toDateString(), 'visit_type' => 'scheduled_follow_up', 'weight' => 70, 'height' => 170,
            'edema_present' => false, 'physical_activity_level' => 'light', 'pregnancy_lactation_status' => 'none', 'dietary_restrictions' => null,
            'goal_achievement' => ['continuation_decision' => 'continue'], 'clinical_summary' => 'Reviewed intake.', 'food_recall' => $this->plan($food)];
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/monitorings";
        $id = $this->actingAs($rnd, 'sanctum')->postJson($url, $payload)->assertCreated()
            ->assertJsonPath('data.food_recall.days_count', 1)->json('data.id');
        $this->assertNull($ncp->assessment->food_recall);
        $this->patchJson("{$url}/{$id}", $payload)->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.food_recall.days.0.items.0.nutrient_snapshot.calories', 120);
        $this->postJson($url, $payload)->assertCreated();
        $this->patchJson("{$url}/{$id}", $payload)->assertStatus(409);
    }

    public function test_replacement_does_not_accumulate_calculations_and_wrong_roles_cannot_save(): void
    {
        [$rnd, $ncp, $food] = $this->setupCycle();
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/assessment";
        $this->actingAs(User::factory()->create(['role' => 'FSS']), 'sanctum')->postJson($url, $this->assessmentPayload(['food_recall' => $this->plan($food)]))->assertForbidden();
        $this->actingAs($rnd, 'sanctum')->postJson($url, $this->assessmentPayload(['food_recall' => $this->plan($food)]))->assertCreated();
        $plan = $this->plan($food);
        $plan['days'][0]['items'][0]['quantity'] = 50;
        $this->patchJson($url, ['food_recall' => $plan])->assertOk()->assertJsonCount(1, 'data.food_recall.days.0.items');
        $this->assertDatabaseCount('assessments', 1);
    }
}
