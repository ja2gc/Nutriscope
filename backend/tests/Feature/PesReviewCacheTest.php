<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\Diagnosis\PesEligibilityService;
use App\Services\Diagnosis\PesEvidenceBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PesReviewCacheTest extends TestCase
{
    use RefreshDatabase;

    private function record(): NcpRecord
    {
        $user = User::factory()->rnd()->create();
        $this->actingAs($user, 'sanctum');
        $record = NcpRecord::factory()->create(['patient_id' => Patient::factory()->create(['dob' => '1990-01-01'])->id, 'rnd_user_id' => $user->id]);
        Assessment::factory()->create([
            'ncp_record_id' => $record->id, 'weight' => 60, 'usual_weight' => 60, 'height' => 170,
            'appetite_changes' => 'normal', 'energy_intake_status' => 'No change',
            'dietary_intake' => null, 'present_diet' => null, 'medical_history' => null,
            'physical_assessment' => null, 'chewing_swallowing_difficulties' => 'No',
            'constipation' => 'No', 'diarrhea_notes' => 'No',
            'knowledge_notes' => 'Cannot identify carbohydrate foods.',
            'social_history' => 'Limited access to nutrition education.',
        ]);
        config()->set('services.anthropic.key', 'test-key');
        Http::preventStrayRequests();

        return $record;
    }

    private function draft(): array
    {
        return [
            'candidate_id' => 'knowledge_deficit_v2', 'domain' => 'NB',
            'problem_key' => 'Food and Nutrition Knowledge Deficit',
            'etiology' => 'Limited access to nutrition education', 'signs' => 'Cannot identify carbohydrate foods',
            'evidence_used' => ['social_history', 'knowledge_notes'],
            'etiology_evidence' => ['social_history'], 'signs_evidence' => ['knowledge_notes'],
            'source_id' => 'academy_ncp_diagnosis_2026',
        ];
    }

    private function provider(array $drafts): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['suggestions' => $drafts])]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ])]);
    }

    public function test_rejected_review_is_cached_without_refunding_spent_requests_or_tokens(): void
    {
        $record = $this->record();
        $bad = $this->draft();
        $bad['etiology'] = 'Invented kidney failure';
        $this->provider([$bad]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.review_outcome', 'rejected')->assertJsonPath('meta.can_retry', true)
            ->assertJsonPath('meta.can_generate', false)->assertJsonPath('meta.budget.used', 1);
        $this->postJson($url)->assertOk()->assertJsonPath('meta.budget.used', 1);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('ai_usage_logs', ['tokens_total' => 150]);
        $this->assertDatabaseCount('ai_usage_logs', 1);
        $record->assessment()->update(['knowledge_notes' => 'Cannot identify carbohydrate foods or portion sizes.']);
        $this->postJson($url)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_extra_drafts_are_bounded_and_duplicates_are_withheld_without_a_technical_error(): void
    {
        $record = $this->record();
        $this->provider(array_fill(0, 4, $this->draft()));
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_non_list_provider_output_is_remembered_as_an_unsuccessful_review(): void
    {
        $record = $this->record();
        $this->provider(['unexpected' => $this->draft()]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.review_outcome', 'rejected')->assertJsonPath('meta.can_retry', true);
        $this->postJson($url)->assertOk();
        Http::assertSentCount(1);
    }

    public function test_malformed_json_from_a_successful_provider_call_is_cached_without_another_charge(): void
    {
        $record = $this->record();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{invalid-json']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ])]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.review_outcome', 'rejected')->assertJsonPath('meta.can_retry', true);
        $this->postJson($url)->assertOk();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('ai_usage_logs', 1);
    }

    public function test_upstream_failure_remains_retryable_and_does_not_mark_evidence_reviewed(): void
    {
        $record = $this->record();
        Http::fakeSequence()->push('Upstream unavailable', 503)->push([
            'content' => [['type' => 'text', 'text' => '{"suggestions":[]}']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 5],
        ]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertStatus(502);
        $this->getJson($url)->assertOk()->assertJsonPath('meta.budget.used', 1)->assertJsonPath('meta.can_generate', true);
        $this->postJson($url)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_saved_ai_problem_identity_is_excluded_even_after_label_and_evidence_changes(): void
    {
        $record = $this->record();
        $this->provider([$this->draft()]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses";
        $id = $this->postJson($url.'/ai-suggest')->assertOk()->json('data.0.draft_id');
        $this->postJson($url.'/ai-approve', ['draft_id' => $id])->assertCreated();
        $record->diagnoses()->first()->update(['problem' => 'Difficulty understanding nutrition information', 'label' => 'Difficulty understanding nutrition information']);
        $record->assessment()->update(['knowledge_notes' => 'Cannot identify carbohydrate foods or portion sizes.']);
        $evidence = app(PesEvidenceBuilder::class)->build($record->fresh());
        $this->assertNotContains('knowledge_deficit_v2', array_column(app(PesEligibilityService::class)->eligible($evidence), 'candidate_id'));
        $this->getJson($url.'/ai-suggest')->assertOk()->assertJsonPath('data', []);
    }

    public function test_intake_checkbox_spelling_variants_share_the_same_problem_identity(): void
    {
        $eligibility = app(PesEligibilityService::class);
        $this->assertSame($eligibility->canonicalProblem('Excessive Carbohydrates Intake'), $eligibility->canonicalProblem('Excessive Carbohydrate Intake'));
        $this->assertSame($eligibility->canonicalProblem('Excessive Energy Intake'), $eligibility->canonicalProblem('Excessive Calorie Intake'));
        $this->assertNotSame($eligibility->canonicalProblem('Inadequate Protein Intake'), $eligibility->canonicalProblem('Excessive Protein Intake'));
    }

    public function test_recall_summary_is_deidentified_and_invalidates_the_review_when_portions_change(): void
    {
        $record = $this->record();
        $snapshot = ['name' => 'Synthetic ingredient', 'calories' => 200, 'protein' => 10, 'carbs' => 20, 'fat' => 5, 'water_g' => null, 'micronutrients' => [], 'serving_size' => 100, 'serving_unit' => 'g'];
        $plan = ['days_count' => 1, 'displayed_micros' => [], 'days' => [['kind' => 'weekday', 'items' => [['id' => 'private-id-must-not-be-sent', 'food_item_id' => 'private-source-id', 'recipe_id' => null, 'quantity' => 200, 'unit' => 'g', 'nutrient_snapshot' => $snapshot]]]]];
        $record->assessment()->update(['food_recall' => $plan, 'intake_nutrients' => ['energy_kcal' => 450, 'protein_g' => 25]]);
        $this->provider([]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertOk();
        Http::assertSent(function ($request): bool {
            $prompt = $request['messages'][0]['content'];
            $this->assertStringContainsString('food_recall', $prompt);
            $this->assertStringContainsString('intake_nutrients', $prompt);
            $this->assertStringContainsString('400', $prompt);
            $this->assertStringContainsString('kcal', $prompt);
            $this->assertStringNotContainsString('private-source-id', $prompt);
            $this->assertStringNotContainsString('Synthetic ingredient', $prompt);

            return true;
        });
        $this->postJson($url)->assertOk();
        Http::assertSentCount(1);
        $plan['days'][0]['items'][0]['quantity'] = 100;
        $record->assessment()->update(['food_recall' => $plan]);
        $this->postJson($url)->assertOk();
        Http::assertSentCount(2);
    }
}
