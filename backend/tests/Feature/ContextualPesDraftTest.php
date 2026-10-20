<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\Diagnosis\PesEligibilityService;
use App\Services\Diagnosis\PesEvidenceBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContextualPesDraftTest extends TestCase
{
    use RefreshDatabase;

    private function record(array $assessment = [], ?Patient $patient = null): NcpRecord
    {
        $user = User::factory()->rnd()->create();
        $this->actingAs($user, 'sanctum');
        $record = NcpRecord::factory()->create(['patient_id' => ($patient ?? Patient::factory()->create(['dob' => '1990-01-01']))->id, 'rnd_user_id' => $user->id]);
        Assessment::factory()->create(array_merge([
            'ncp_record_id' => $record->id, 'weight' => 60, 'usual_weight' => 60,
            'height' => 170, 'bmi' => 20.76, 'weight_loss_percentage' => 0,
            'weight_change_period_value' => 1, 'weight_change_period_unit' => 'months',
            'energy_intake_status' => 'No change', 'appetite_changes' => 'normal',
            'present_diet' => null, 'physical_assessment' => null,
            'chewing_swallowing_difficulties' => 'No', 'constipation' => 'No',
            'diarrhea_notes' => 'No', 'nutrient_drug_interaction' => null,
        ], $assessment));
        config()->set('services.anthropic.key', 'test-key');

        return $record;
    }

    public function test_status_and_normal_assessment_do_not_call_provider_or_spend_allowance(): void
    {
        $record = $this->record();
        Http::fake();
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->getJson($url)->assertOk()->assertJsonPath('meta.budget.remaining', 3);
        $this->postJson($url)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.budget.remaining', 3);
        Http::assertNothingSent();
    }

    public function test_three_successful_generations_are_shared_by_patient_across_cycles_and_reset_in_manila(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Manila')->setTime(23, 55));
        $record = $this->record(['knowledge_notes' => 'Cannot identify carbohydrate foods.']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '{"suggestions":[]}']], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]])]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        for ($i = 1; $i <= 3; $i++) {
            $record->assessment()->update(['knowledge_notes' => "Cannot identify carbohydrate foods. Finding {$i}."]);
            $this->postJson($url)->assertOk()->assertJsonPath('meta.budget.remaining', 3 - $i);
        }
        $other = $this->record(['knowledge_notes' => 'Cannot identify carbohydrate foods.'], $record->patient);
        $otherUrl = "/api/rnd/ncp-records/{$other->uuid}/diagnoses/ai-suggest";
        $this->postJson($otherUrl)->assertStatus(429)->assertJsonPath('meta.budget.remaining', 0);
        $this->getJson($otherUrl)->assertOk()->assertJsonPath('meta.budget.remaining', 0);
        Http::assertSentCount(3);
        $this->travel(10)->minutes();
        $this->postJson($otherUrl)->assertOk()->assertJsonPath('meta.budget.remaining', 2);
    }

    public function test_other_text_enters_pes_but_reasoning_does_not_and_other_is_limited_to_75(): void
    {
        $record = $this->record();
        $builder = [
            'problem_checks' => [], 'problem_other' => 'Difficulty managing meal schedule',
            'etiology_checks' => [], 'etiology_other' => 'Shift schedule limits meal preparation',
            'sign_checks' => [], 'sign_other' => 'Reports missing lunch on working days',
            'etiology_notes' => 'Schedule barrier documented.', 'sign_notes' => 'Patient report supports the finding.',
        ];
        $payload = ['domain' => 'NB', 'problem' => $builder['problem_other'], 'etiology' => $builder['etiology_other'], 'signs_symptoms' => $builder['sign_other'], 'builder_data' => $builder, 'extra_notes' => 'Review meal timing.'];
        $result = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses", $payload)->assertCreated();
        $this->assertStringNotContainsString('Schedule barrier documented', $result->json('data.pes_statement'));
        $this->assertSame($builder, $result->json('data.builder_data'));
        $payload['builder_data']['sign_other'] = str_repeat('x', 76);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses", $payload)->assertUnprocessable()->assertJsonValidationErrors('builder_data.sign_other');
        $payload['domain'] = 'NI';
        $payload['builder_data']['sign_other'] = $builder['sign_other'];
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses", $payload)->assertUnprocessable()->assertJsonValidationErrors('builder_data.ni_nutrient');
    }

    public function test_approval_cannot_claim_an_unissued_ai_draft(): void
    {
        $record = $this->record();
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-approve", ['draft_id' => fake()->uuid(), 'domain' => 'NC', 'label' => 'Invented problem', 'etiology' => 'Invented cause', 'signs' => 'Invented evidence'])->assertUnprocessable();
        $this->assertDatabaseCount('diagnoses', 0);
    }

    public function test_weight_gain_is_never_an_unintended_loss_candidate_and_normal_text_is_not_positive_evidence(): void
    {
        $record = $this->record(['weight' => 62.4, 'usual_weight' => 60, 'weight_loss_percentage' => 4, 'knowledge_notes' => 'Understands dietary recommendations. No barriers.']);
        $evidence = app(PesEvidenceBuilder::class)->build($record);
        $this->assertSame('gain', $evidence['weight_change_direction']);
        $candidates = app(PesEligibilityService::class)->eligible($evidence);
        $this->assertNotContains('unintended_weight_loss_v1', array_column($candidates, 'candidate_id'));
        $this->assertNotContains('knowledge_deficit_v2', array_column($candidates, 'candidate_id'));
        $eligibility = app(PesEligibilityService::class);
        $this->assertFalse($eligibility->hasConcern('- No diagnosed chronic illness.'));
        $this->assertFalse($eligibility->hasConcern('- Normal fasting glucose.'));
        $this->assertTrue($eligibility->hasConcern('- No planned physical activity.'));
    }

    private function knowledgeDraft(): array
    {
        return [
            'candidate_id' => 'knowledge_deficit_v2', 'domain' => 'NB',
            'problem_key' => 'Food and Nutrition Knowledge Deficit',
            'etiology' => 'Limited access to nutrition education',
            'signs' => 'Cannot identify carbohydrate foods',
            'evidence_used' => ['knowledge_notes', 'social_history'],
            'evidence_quotes' => ['knowledge_notes' => 'Cannot identify carbohydrate foods', 'social_history' => 'Limited access to nutrition education'],
            'etiology_evidence' => ['social_history'], 'signs_evidence' => ['knowledge_notes'],
            'extra_notes' => 'Knowledge gap and access barrier are documented.',
            'etiology_notes' => 'Access barrier reported.', 'sign_notes' => 'Specific knowledge gap reported.',
            'source_id' => 'academy_ncp_diagnosis_2026',
        ];
    }

    private function provider(array $drafts): void
    {
        Http::swap(new Factory);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['suggestions' => $drafts])]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 100]])]);
    }

    private function knowledgeRecord(): NcpRecord
    {
        return $this->record(['knowledge_notes' => 'Cannot identify carbohydrate foods.', 'social_history' => 'Limited access to nutrition education.']);
    }

    public function test_grounded_draft_keeps_reasoning_separate_and_acceptance_tracks_evidence_without_regenerating(): void
    {
        $record = $this->knowledgeRecord();
        $this->provider([$this->knowledgeDraft()]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $draft = $this->postJson($url)->assertOk()->assertJsonPath('meta.budget.remaining', 2)->json('data.0');
        $this->assertSame('Limited access to nutrition education', $draft['builder_data']['etiology_other']);
        $approved = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-approve", ['draft_id' => $draft['draft_id']])->assertCreated();
        $this->assertStringNotContainsString('Access barrier reported', $approved->json('data.pes_statement'));
        $this->assertSame('Access barrier reported.', $approved->json('data.builder_data.etiology_notes'));
        $this->assertSame($draft['draft_id'], Diagnosis::query()->sole()->ai_provenance['draft_id']);
        $this->getJson($url)->assertOk()->assertJsonPath('data', []);
        $this->postJson($url)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.budget.remaining', 2);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-approve", ['draft_id' => $draft['draft_id']])->assertUnprocessable();
        Http::assertSentCount(1);
    }

    public function test_stale_draft_and_unsupported_cause_or_denied_signs_are_rejected_without_spending_allowance(): void
    {
        $record = $this->knowledgeRecord();
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $bad = $this->knowledgeDraft();
        $bad['etiology'] = 'Kidney failure';
        $this->provider([$bad]);
        $this->postJson($url)->assertStatus(502);
        $this->getJson($url)->assertJsonPath('meta.budget.remaining', 3);
        $record->assessment()->update(['knowledge_notes' => 'Cannot identify carbohydrate foods. No difficulty swallowing.']);
        $bad = $this->knowledgeDraft();
        $bad['signs'] = 'No difficulty swallowing';
        $bad['evidence_quotes']['knowledge_notes'] = 'No difficulty swallowing';
        $this->provider([$bad]);
        $this->postJson($url)->assertStatus(502);
        $this->getJson($url)->assertJsonPath('meta.budget.remaining', 3);
        $bad['signs'] = 'Difficulty swallowing';
        $this->provider([$bad]);
        $this->postJson($url)->assertStatus(502);
        $this->getJson($url)->assertJsonPath('meta.budget.remaining', 3);
        $this->provider([$this->knowledgeDraft()]);
        $id = $this->postJson($url)->assertOk()->json('data.0.draft_id');
        $record->assessment()->update(['knowledge_notes' => 'Understands carbohydrate foods.']);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-approve", ['draft_id' => $id])->assertUnprocessable();
    }

    public function test_manual_equivalent_problems_are_not_offered_again(): void
    {
        $record = $this->knowledgeRecord();
        Diagnosis::factory()->create(['ncp_record_id' => $record->id, 'domain' => 'NB', 'problem' => 'Lack of nutrition knowledge']);
        $evidence = app(PesEvidenceBuilder::class)->build($record);
        $candidates = app(PesEligibilityService::class)->eligible($evidence);
        $this->assertNotContains('knowledge_deficit_v2', array_column($candidates, 'candidate_id'));
    }

    public function test_evidence_excerpts_come_from_the_assessment_instead_of_model_quoting(): void
    {
        $record = $this->knowledgeRecord();
        $draft = $this->knowledgeDraft();
        unset($draft['evidence_quotes']);
        $this->provider([$draft]);
        $result = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk();
        $this->assertSame('Cannot identify carbohydrate foods.', $result->json('data.0.evidence_refs.0.quote'));
        $this->assertSame(hash('sha256', json_encode('Cannot identify carbohydrate foods.')), $result->json('data.0.evidence_refs.0.value_hash'));
    }

    public function test_an_invalid_source_is_withheld_without_discarding_an_independently_supported_draft(): void
    {
        $record = $this->knowledgeRecord();
        $bad = $this->knowledgeDraft();
        $bad['candidate_id'] = 'food_insecurity_v2';
        $bad['problem_key'] = 'Food Insecurity';
        $bad['source_id'] = 'invented-source';
        $this->provider([$this->knowledgeDraft(), $bad]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.candidate_id', 'knowledge_deficit_v2');
    }

    public function test_overlong_optional_reasoning_does_not_discard_a_supported_draft(): void
    {
        $record = $this->knowledgeRecord();
        $draft = $this->knowledgeDraft();
        $draft['extra_notes'] = str_repeat('Documented evidence. ', 8);
        $this->provider([$draft]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.extra_notes', '');
    }

    public function test_a_component_over_75_does_not_discard_valid_drafts_or_mark_unreturned_problems_reviewed(): void
    {
        $record = $this->knowledgeRecord();
        $record->assessment()->update(['social_history' => 'Limited access to nutrition education. Limited food budget; misses dinner twice weekly and skips lunch on several working days.']);
        $long = [
            'candidate_id' => 'food_insecurity_v2', 'domain' => 'NB', 'problem_key' => 'Food Insecurity',
            'etiology' => 'Limited food budget',
            'signs' => 'Misses dinner twice weekly and skips lunch on several working days due to limited food budget',
            'evidence_used' => ['social_history'],
            'evidence_quotes' => ['social_history' => 'Limited food budget; misses dinner twice weekly and skips lunch on several working days'],
            'etiology_evidence' => ['social_history'], 'signs_evidence' => ['social_history'], 'source_id' => 'academy_ncp_diagnosis_2026',
        ];
        $this->provider([$this->knowledgeDraft(), $long]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.budget.used', 1)->assertJsonPath('meta.can_generate', true);
        $long['signs'] = 'Misses dinner twice weekly; skips lunch on working days';
        $this->provider([$long]);
        $this->postJson($url)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.budget.used', 2);
    }

    public function test_pending_and_dismissed_findings_survive_unrelated_assessment_edits(): void
    {
        $record = $this->knowledgeRecord();
        $this->provider([$this->knowledgeDraft()]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $id = $this->postJson($url)->assertOk()->json('data.0.draft_id');
        $record->assessment()->update(['supplements' => 'Vitamin D daily']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.draft_id', $id);
        $this->postJson($url, ['dismissed_candidate_id' => 'knowledge_deficit_v2'])->assertOk()->assertJsonPath('data', []);
        $record->assessment()->update(['supplements' => 'Vitamin D twice weekly']);
        $this->postJson($url)->assertOk()->assertJsonPath('data', []);
        Http::assertSentCount(1);
    }

    public function test_domain_balanced_batch_uses_one_shared_evidence_map(): void
    {
        $record = $this->record([
            'knowledge_notes' => 'Cannot identify carbohydrate foods.', 'social_history' => 'Limited access to nutrition education.',
            'appetite_changes' => 'decreased', 'dietary_intake' => 'Consumes half of meals because of decreased appetite.',
            'physical_assessment' => 'Poor dentition limits chewing.',
            'lifestyle' => 'Long working hours; no planned physical activity.',
            'chewing_swallowing_difficulties' => 'Yes', 'chewing_swallowing_details' => 'Difficulty chewing solids.',
        ]);
        $intake = [
            'candidate_id' => 'inadequate_energy_v2', 'domain' => 'NI', 'problem_key' => 'Inadequate Energy Intake',
            'etiology' => 'decreased appetite', 'signs' => 'Consumes half of meals',
            'evidence_used' => ['appetite', 'dietary_intake'], 'evidence_quotes' => ['appetite' => 'decreased', 'dietary_intake' => 'Consumes half of meals'],
            'etiology_evidence' => ['appetite'], 'signs_evidence' => ['dietary_intake'], 'source_id' => 'academy_ncp_diagnosis_2026',
        ];
        $clinical = [
            'candidate_id' => 'swallowing_chewing_difficulty_v1', 'domain' => 'NC', 'problem_key' => 'Swallowing / Chewing Difficulty',
            'etiology' => 'Poor dentition limits chewing', 'signs' => 'Difficulty chewing solids',
            'evidence_used' => ['physical_assessment', 'chewing_swallowing_difficulties'],
            'evidence_quotes' => ['physical_assessment' => 'Poor dentition limits chewing', 'chewing_swallowing_difficulties' => 'Difficulty chewing solids'],
            'etiology_evidence' => ['physical_assessment'], 'signs_evidence' => ['chewing_swallowing_difficulties'], 'source_id' => 'academy_ncp_diagnosis_2026',
        ];
        $this->provider([$this->knowledgeDraft(), $clinical, $intake]);
        $response = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame(['NI', 'NC', 'NB'], array_column($response->json('data'), 'domain'));
        Http::assertSent(function ($request): bool {
            $prompt = $request->data()['messages'][0]['content'];
            $encoded = explode("\noutput_json_schema:", substr($prompt, strlen("input_json:\n")))[0];
            $payload = json_decode($encoded, true);
            $this->assertArrayHasKey('knowledge_notes', $payload['evidence']);
            foreach ($payload['candidates'] as $candidate) {
                $this->assertArrayNotHasKey('evidence', $candidate);
            }

            return true;
        });
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->getJson($url)->assertOk()->assertJsonPath('meta.can_generate', true);
        $firstIds = array_column($response->json('data'), 'draft_id');
        $activity = [
            'candidate_id' => 'physical_inactivity_v2', 'domain' => 'NB', 'problem_key' => 'Physical Inactivity',
            'etiology' => 'Long working hours', 'signs' => 'No planned physical activity',
            'evidence_used' => ['lifestyle'], 'evidence_quotes' => ['lifestyle' => 'Long working hours; no planned physical activity'],
            'etiology_evidence' => ['lifestyle'], 'signs_evidence' => ['lifestyle'], 'source_id' => 'academy_ncp_diagnosis_2026',
        ];
        $this->provider([$activity]);
        $next = $this->postJson($url)->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('meta.budget.used', 2);
        $this->assertEmpty(array_diff($firstIds, array_column($next->json('data'), 'draft_id')));
        Http::assertSent(function ($request): bool {
            $prompt = $request->data()['messages'][0]['content'];
            $payload = json_decode(explode("\noutput_json_schema:", substr($prompt, strlen("input_json:\n")))[0], true);

            return in_array('Food and Nutrition Knowledge Deficit', $payload['existing_problems'], true);
        });
    }

    public function test_glucose_is_not_flagged_against_a_fasting_range_without_fasting_context(): void
    {
        $record = $this->record();
        $record->patient->update(['sex' => 'Male']);
        $record->assessment->biochemicalData()->create(['glucose' => 120, 'sodium' => 140]);
        $evidence = app(PesEvidenceBuilder::class)->build($record);
        $this->assertArrayNotHasKey('glucose', $evidence['laboratory_flags'] ?? []);
        $this->assertArrayHasKey('glucose', $evidence['laboratory_values']);
    }

    public function test_malnutrition_cannot_be_inferred_from_tiredness_and_missed_meals_with_normal_measurements(): void
    {
        $record = $this->record(['physical_assessment' => 'Reports feeling tired.', 'dietary_intake' => 'Misses meals due to shift work.']);
        $draft = [
            'candidate_id' => 'clinical_other_v2', 'domain' => 'NC', 'problem_key' => 'Malnutrition (undernutrition)',
            'etiology' => 'Shift work', 'signs' => 'Misses meals; feeling tired',
            'evidence_used' => ['physical_assessment', 'dietary_intake'],
            'evidence_quotes' => ['physical_assessment' => 'feeling tired', 'dietary_intake' => 'Misses meals due to shift work'],
            'etiology_evidence' => ['dietary_intake'], 'signs_evidence' => ['physical_assessment', 'dietary_intake'], 'source_id' => 'academy_ncp_diagnosis_2026',
        ];
        $this->provider([$draft]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertStatus(502);
        $record->assessment()->update(['physical_assessment' => 'Reduced muscle mass on nutrition-focused physical exam.', 'dietary_intake' => 'Reduced food intake for 3 weeks.']);
        $draft['etiology'] = 'Reduced food intake for 3 weeks';
        $draft['signs'] = 'Reduced muscle mass';
        $draft['evidence_quotes'] = ['physical_assessment' => 'Reduced muscle mass', 'dietary_intake' => 'Reduced food intake for 3 weeks'];
        $draft['signs_evidence'] = ['physical_assessment'];
        $this->provider([$draft]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(1, 'data');
    }
}
