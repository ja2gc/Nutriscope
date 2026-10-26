<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Support\AiReviewAllowance;
use App\Support\DiagnosisBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClinicalAiReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_uses_client_bmi_reference_without_generating_a_growth_diagnosis_from_it(): void
    {
        $record = $this->record();
        $record->patient()->update(['dob' => '2016-01-01', 'sex' => 'Male']);
        $record->assessment()->update(['weight' => 40, 'usual_weight' => 40, 'height' => 120, 'knowledge_notes' => null, 'social_history' => null]);
        $this->getJson("/api/rnd/ncp-records/{$record->uuid}/assessment")->assertOk()->assertJsonPath('data.bmi_classification', 'Obesity');
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(0, 'data');
        Http::assertNothingSent();
    }

    public function test_asia_pacific_overweight_uses_compact_classification_without_screening_status(): void
    {
        $record = $this->record();
        $record->assessment()->update(['weight' => 69, 'usual_weight' => 69, 'height' => 170, 'ibw_percentage' => 65, 'nutritional_status' => 'Severe Malnutrition']);
        $this->provider([[...$this->draft(), 'candidate_id' => 'overweight_obesity_v1', 'domain' => 'NC', 'problem_key' => 'Overweight / Obesity',
            'signs' => 'BMI 23.88', 'evidence_used' => ['social_history', 'bmi'], 'signs_evidence' => ['bmi'], 'source_id' => 'asia_pacific_bmi_2026']]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonPath('data.0.label', 'Overweight / Obesity');
        Http::assertSent(function ($request): bool {
            $payload = json_decode(substr($request['messages'][0]['content'], strlen("input_json:\n")), true);
            $fields = array_column($payload['evidence'], 1, 0);
            $this->assertSame('Overweight', $fields['bmi_classification']);
            $this->assertSame(23.88, $fields['bmi']);
            foreach (['risk_score', 'nutritional_status', 'height_cm', 'weight_kg', 'usual_weight_kg'] as $key) {
                $this->assertArrayNotHasKey($key, $fields);
            }

            return true;
        });
        $this->assertSame('Severe Malnutrition', $record->assessment()->first()->nutritional_status);
    }

    public function test_underweight_can_be_supported_without_inventing_malnutrition_or_severity(): void
    {
        $record = $this->record();
        $record->assessment()->update(['weight' => 49.13, 'usual_weight' => 49.13, 'height' => 170, 'appetite_changes' => 'decreased', 'nutritional_status' => 'Severe Malnutrition']);
        $this->provider([[...$this->draft(), 'candidate_id' => 'clinical_other_v2', 'domain' => 'NC', 'problem_key' => 'Underweight', 'etiology' => 'decreased appetite',
            'signs' => 'BMI 17', 'evidence_used' => ['appetite', 'bmi'], 'etiology_evidence' => ['appetite'], 'signs_evidence' => ['bmi']]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonPath('data.0.label', 'Underweight');
    }

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
        Http::preventStrayRequests();

        return $record;
    }

    private function draft(): array
    {
        return ['candidate_id' => 'knowledge_deficit_v2', 'domain' => 'NB',
            'problem_key' => 'Food and Nutrition Knowledge Deficit',
            'etiology' => 'Limited access to nutrition education', 'signs' => 'Cannot identify carbohydrate foods',
            'evidence_used' => ['social_history', 'knowledge_notes'], 'etiology_evidence' => ['social_history'],
            'signs_evidence' => ['knowledge_notes'], 'source_id' => 'academy_ncp_diagnosis_2026'];
    }

    private function provider(array $drafts): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['suggestions' => $drafts])]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ])]);
    }

    public function test_uncited_relevant_evidence_reopens_review_but_unrelated_changes_do_not(): void
    {
        $record = $this->record();
        $this->provider([$this->draft()]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertOk()->assertJsonPath('meta.can_generate', false);
        $record->assessment()->update(['dietary_restrictions' => 'New dietary restriction affecting education needs']);
        $this->getJson($url)->assertOk()->assertJsonPath('meta.can_generate', true);
        $this->postJson($url)->assertOk();
        Http::assertSentCount(2);
        $record->assessment()->update(['food_dislikes' => ['Synthetic dislike']]);
        $this->postJson($url)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_rejected_output_is_not_reported_as_no_evidence_and_requires_deliberate_retry(): void
    {
        $record = $this->record();
        $bad = $this->draft();
        $bad['etiology'] = 'Invented kidney failure';
        $this->provider([$bad]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertOk()->assertJsonPath('meta.review_outcome', 'rejected')->assertJsonPath('meta.can_retry', true);
        $this->postJson($url)->assertOk();
        Http::assertSentCount(1);
        $this->provider([$this->draft()]);
        $this->postJson($url, ['retry_rejected' => true])->assertOk()->assertJsonCount(1, 'data');
        Http::assertSentCount(1);
    }

    public function test_removing_a_draft_reopens_its_review_without_resurrecting_the_old_draft(): void
    {
        $record = $this->record();
        $this->provider([$this->draft()]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $draft = $this->postJson($url)->assertOk()->json('data.0');
        $this->postJson($url, ['dismissed_draft_id' => $draft['draft_id']])->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.can_generate', true);
        Http::assertSentCount(1);
        $this->postJson($url)->assertOk()->assertJsonCount(1, 'data');
        Http::assertSentCount(2);
    }

    public function test_feature_allowances_are_independent_per_user_and_expire_after_ten_minutes(): void
    {
        $this->record();
        for ($i = 0; $i < 10; $i++) {
            AiReviewAllowance::reserve('diagnosis');
        }
        $this->assertSame(0, AiReviewAllowance::status('diagnosis')['remaining']);
        $this->assertSame(10, AiReviewAllowance::status('monitoring')['remaining']);
        try {
            AiReviewAllowance::reserve('diagnosis');
            $this->fail('Eleventh request must be blocked.');
        } catch (HttpResponseException $error) {
            $this->assertSame(429, $error->getResponse()->getStatusCode());
            $this->assertGreaterThan(0, (int) $error->getResponse()->headers->get('Retry-After'));
        }
        $this->actingAs(User::factory()->rnd()->create(), 'sanctum');
        $this->assertSame(10, AiReviewAllowance::status('diagnosis')['remaining']);
        $this->travel(601)->seconds();
        $this->assertSame(10, AiReviewAllowance::status('diagnosis')['remaining']);
    }

    public function test_no_evidence_skips_paid_generation_and_does_not_consume_allowance(): void
    {
        $record = $this->record();
        $record->assessment()->update(['knowledge_notes' => 'Understands carbohydrate portions.', 'social_history' => 'No concerns']);
        $this->provider([]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")
            ->assertOk()->assertJsonPath('meta.message', 'No supported evidence left.')->assertJsonPath('meta.budget.remaining', 10);
        Http::assertNothingSent();
    }

    public function test_referral_context_and_three_explanations_are_preserved_without_patient_name(): void
    {
        $record = $this->record();
        $record->patient()->update(['first_name' => 'SyntheticName', 'last_name' => 'ExampleName', 'medical_diagnosis' => 'SyntheticName has type 2 diabetes']);
        $record->assessment()->update(['medications' => ['Metformin with meals for SyntheticName'], 'dietary_intake' => 'Inconsistent carbohydrate intake']);
        Http::fake(['api.anthropic.com/*' => function ($request) {
            $payload = json_decode(substr($request['messages'][0]['content'], strlen("input_json:\n")), true);
            $this->assertStringNotContainsString('SyntheticName', json_encode($payload));
            $this->assertStringContainsString('Metformin', json_encode($payload));
            $this->assertStringContainsString('type 2 diabetes', json_encode($payload));
            $refs = [];
            foreach ($payload['evidence'] as $ref => $item) {
                $refs[$item[0]] = $ref;
            }
            $candidate = collect($payload['candidates'])->search(fn ($item) => $item[1] === 'Food and Nutrition Knowledge Deficit');

            return Http::response(['content' => [['type' => 'text', 'text' => json_encode(['suggestions' => [[
                'id' => $candidate, 'e' => 'Limited access to nutrition education', 'ek' => [$refs['social_history']],
                's' => 'Cannot identify carbohydrate foods', 'sk' => [$refs['knowledge_notes']],
                'n' => 'Cannot identify carbohydrate foods supports knowledge deficit.',
                'en' => 'Limited access to nutrition education is the documented barrier.',
                'sn' => 'Cannot identify carbohydrate foods qualifies the documented knowledge gap.',
            ]]])]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 80]]);
        }]);
        $draft = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->json('data.0');
        $this->assertNotEmpty($draft['extra_notes']);
        $this->assertNotEmpty($draft['builder_data']['etiology_notes']);
        $this->assertNotEmpty($draft['builder_data']['sign_notes']);
    }

    public function test_deleted_saved_diagnosis_reopens_review_without_resurrecting_accepted_draft(): void
    {
        $record = $this->record();
        $this->provider([$this->draft()]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses";
        $draftId = $this->postJson($url.'/ai-suggest')->assertOk()->json('data.0.draft_id');
        $saved = $this->postJson($url.'/ai-approve', ['draft_id' => $draftId])->assertCreated()->json('data.id');
        Diagnosis::factory()->create(['ncp_record_id' => $record->id, 'problem' => 'Physical Inactivity', 'ai_generated' => false]);
        $this->deleteJson($url.'/'.$saved)->assertNoContent();
        $this->getJson($url.'/ai-suggest')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.can_generate', true);
        Http::assertSentCount(1);
    }

    public function test_partially_accepted_review_allows_deliberate_retry_without_automatic_refill(): void
    {
        $record = $this->record();
        $this->provider([$this->draft(), [...$this->draft(), 'candidate_id' => 'unknown']]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.review_outcome', 'partial')->assertJsonPath('meta.can_generate', false)->assertJsonPath('meta.can_retry', true);
        $this->postJson($url)->assertOk()->assertJsonCount(1, 'data');
        Http::assertSentCount(1);
        $this->postJson($url, ['retry_rejected' => true])->assertOk()->assertJsonCount(1, 'data');
        Http::assertSentCount(2);
    }

    public function test_deleting_two_legacy_custom_diagnoses_reopens_their_domain_and_allows_both_again(): void
    {
        $record = $this->record();
        $record->assessment()->update(['dietary_intake' => 'Excessive sodium intake from salted meals; inadequate iron intake from few iron foods.']);
        $this->provider([]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses";
        $this->postJson($url.'/ai-suggest')->assertOk();
        $saved = [];
        foreach (['Excessive Sodium Intake', 'Inadequate Iron Intake'] as $label) {
            $saved[] = Diagnosis::create(['ncp_record_id' => $record->id, 'domain' => 'NI', 'problem' => $label,
                'etiology' => 'Limited access to nutrition education', 'signs_symptoms' => 'Recorded intake pattern',
                'pes_statement' => $label.' related to limited nutrition education as evidenced by recorded intake.',
                'ai_generated' => true, 'ai_provenance' => ['candidate_id' => 'legacy_intake_v1', 'catalog_version' => 'old']]);
        }
        Diagnosis::factory()->create(['ncp_record_id' => $record->id, 'problem' => 'Physical Inactivity', 'ai_generated' => false]);
        $this->getJson($url.'/ai-suggest')->assertOk()->assertJsonPath('meta.can_generate', false);
        foreach ($saved as $diagnosis) {
            $this->deleteJson($url.'/'.$diagnosis->uuid)->assertNoContent();
        }
        $this->getJson($url.'/ai-suggest')->assertOk()->assertJsonPath('meta.can_generate', true);
        Http::assertSentCount(1);
        $base = [...$this->draft(), 'candidate_id' => 'intake_other_v2', 'domain' => 'NI',
            'evidence_used' => ['social_history', 'dietary_intake'], 'signs_evidence' => ['dietary_intake']];
        $this->provider([
            [...$base, 'problem_key' => 'Excessive Sodium Intake', 'signs' => 'Excessive sodium intake from salted meals'],
            [...$base, 'problem_key' => 'Inadequate Iron Intake', 'signs' => 'Inadequate iron intake from few iron foods'],
        ]);
        $this->postJson($url.'/ai-suggest')->assertOk()->assertJsonCount(2, 'data');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('ai_usage_logs', 2);
    }

    public function test_missing_or_unsupported_notes_are_withheld_without_repeating_valid_pes(): void
    {
        $record = $this->record();
        $draft = [...$this->draft(), 'extra_notes' => 'Kidney failure requires 700 mg medication.'];
        $this->provider([$draft]);
        $result = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->json('data.0');
        $this->assertSame('', $result['extra_notes']);
        $this->assertSame('', $result['builder_data']['etiology_notes']);
        $this->assertSame('', $result['builder_data']['sign_notes']);
    }

    public function test_repeated_components_are_not_presented_as_explanations(): void
    {
        $record = $this->record();
        $draft = $this->draft();
        $this->provider([[...$draft, 'extra_notes' => $draft['problem_key'],
            'etiology_notes' => $draft['etiology'], 'sign_notes' => 'Recorded sign: '.$draft['signs']]]);
        $result = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->json('data.0');
        $this->assertSame('', $result['extra_notes']);
        $this->assertSame('', $result['builder_data']['etiology_notes']);
        $this->assertSame('', $result['builder_data']['sign_notes']);
    }

    public function test_raw_meal_and_status_as_cause_cannot_form_a_predicted_intake_draft(): void
    {
        $record = $this->record();
        $record->assessment()->update(['energy_intake_status' => 'Sub-optimal', 'appetite_changes' => 'normal',
            'dietary_intake' => 'Breakfast: 2 cups rice, egg, sweet coffee.']);
        $this->provider([[...$this->draft(), 'candidate_id' => 'predicted_suboptimal_intake_v1', 'domain' => 'NC',
            'problem_key' => 'Predicted Suboptimal Intake', 'etiology' => 'Energy intake status: Sub-optimal',
            'signs' => 'Breakfast 2 cups rice, egg, sweet coffee',
            'evidence_used' => ['energy_intake_status', 'dietary_intake'],
            'etiology_evidence' => ['energy_intake_status'], 'signs_evidence' => ['dietary_intake']]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_known_problem_cannot_use_an_unrelated_concern_as_its_sign(): void
    {
        $record = $this->record();
        $record->assessment()->update(['lifestyle' => 'No planned physical activity.']);
        $this->provider([[...$this->draft(), 'signs' => 'No planned physical activity',
            'evidence_used' => ['social_history', 'lifestyle'], 'signs_evidence' => ['lifestyle']]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.review_outcome', 'rejected');
    }

    public function test_provider_gets_existing_choices_only_for_eligible_domains(): void
    {
        $record = $this->record();
        $this->provider([$this->draft()]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk();
        Http::assertSent(function ($request): bool {
            $payload = json_decode(substr($request['messages'][0]['content'], strlen("input_json:\n")), true);
            $this->assertSame(['NB'], array_keys($payload['choices'] ?? []));
            $this->assertSame(DiagnosisBuilder::options()['NB']['etiologies'], $payload['choices']['NB']['e']);
            $this->assertSame(DiagnosisBuilder::options()['NB']['signs'], $payload['choices']['NB']['s']);

            return true;
        });
    }

    public function test_intake_status_is_not_an_explanation_for_another_problem(): void
    {
        $record = $this->record();
        $record->assessment()->update(['energy_intake_status' => 'Sub-optimal', 'dietary_intake' => 'Protein intake below prescribed needs.']);
        $this->provider([[...$this->draft(), 'candidate_id' => 'inadequate_protein_v2', 'domain' => 'NI',
            'problem_key' => 'Inadequate Protein Intake', 'etiology' => 'Energy intake status: Sub-optimal',
            'signs' => 'Protein intake below prescribed needs',
            'evidence_used' => ['energy_intake_status', 'dietary_intake'],
            'etiology_evidence' => ['energy_intake_status'], 'signs_evidence' => ['dietary_intake']]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.review_outcome', 'rejected');
    }

    public function test_other_intake_cannot_infer_an_intake_direction_from_unrelated_words(): void
    {
        $record = $this->record();
        $record->assessment()->update(['dietary_intake' => 'Carbohydrate intake: 280 g; high glycemic load.']);
        $this->provider([[...$this->draft(), 'candidate_id' => 'intake_other_v2', 'domain' => 'NI',
            'problem_key' => 'Excessive Carbohydrates Intake', 'signs' => 'High carbohydrate intake',
            'evidence_used' => ['social_history', 'dietary_intake'], 'signs_evidence' => ['dietary_intake']]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.review_outcome', 'rejected');
    }

    public function test_supported_presets_keep_distinct_assessment_explanations(): void
    {
        $record = $this->record();
        $record->assessment()->update(['social_history' => 'Limited access to nutrition resources.',
            'knowledge_notes' => 'Patient unable to identify appropriate food choices.']);
        $this->provider([[...$this->draft(), 'etiology' => 'Limited access to food or nutrition resources',
            'signs' => 'Patient unable to identify appropriate food choices',
            'extra_notes' => 'Unable to identify appropriate food choices supports a nutrition knowledge deficit.',
            'etiology_notes' => 'Limited access to nutrition resources is the documented barrier.',
            'sign_notes' => 'Inability to identify appropriate food choices qualifies the knowledge deficit.']]);
        $result = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->json('data.0');
        $this->assertSame(['Limited access to food or nutrition resources'], $result['builder_data']['etiology_checks']);
        $this->assertSame(['Patient unable to identify appropriate food choices'], $result['builder_data']['sign_checks']);
        $this->assertSame('', $result['builder_data']['sign_other']);
        $this->assertStringContainsString('supports', $result['extra_notes']);
        $this->assertStringContainsString('barrier', $result['builder_data']['etiology_notes']);
        $this->assertStringContainsString('qualifies', $result['builder_data']['sign_notes']);
    }

    public function test_distinct_other_problems_share_evidence_in_one_pass_and_delete_individually(): void
    {
        $record = $this->record();
        $record->assessment()->update(['dietary_intake' => 'Excessive sodium intake from salted meals; inadequate iron intake from few iron foods.']);
        $base = [...$this->draft(), 'candidate_id' => 'intake_other_v2', 'domain' => 'NI',
            'evidence_used' => ['social_history', 'dietary_intake'], 'signs_evidence' => ['dietary_intake']];
        $this->provider([
            [...$base, 'problem_key' => 'Excessive Sodium Intake', 'signs' => 'Excessive sodium intake from salted meals'],
            [...$base, 'problem_key' => 'Inadequate Iron Intake', 'signs' => 'Inadequate iron intake from few iron foods'],
        ]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $drafts = $this->postJson($url)->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->postJson($url, ['dismissed_draft_id' => $drafts[0]['draft_id']])->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.draft_id', $drafts[1]['draft_id']);
        Http::assertSentCount(1);
    }

    public function test_qualified_medical_diagnosis_cannot_be_an_other_nutrition_problem(): void
    {
        $record = $this->record();
        $record->assessment()->update(['physical_assessment' => 'Reduced renal function']);
        $this->provider([[...$this->draft(), 'candidate_id' => 'clinical_other_v2', 'domain' => 'NC',
            'problem_key' => 'Advanced chronic kidney disease', 'signs' => 'Reduced renal function',
            'evidence_used' => ['social_history', 'physical_assessment'], 'signs_evidence' => ['physical_assessment']]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.review_outcome', 'rejected');
    }

    public function test_medication_name_or_care_category_alone_cannot_establish_the_etiology(): void
    {
        $record = $this->record();
        $record->assessment()->update(['medications' => ['Metformin']]);
        $this->provider([[...$this->draft(), 'etiology' => 'Metformin',
            'evidence_used' => ['clinical_context', 'knowledge_notes'], 'etiology_evidence' => ['clinical_context']]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.review_outcome', 'rejected');
    }

    public function test_adequate_protein_cannot_support_excessive_protein_intake(): void
    {
        $record = $this->record();
        $record->assessment()->update(['dietary_intake' => 'Consumes half of usual meals; protein intake is adequate.', 'appetite_changes' => 'decreased']);
        $this->provider([[...$this->draft(), 'candidate_id' => 'excessive_protein_v2', 'domain' => 'NI', 'problem_key' => 'Excessive Protein Intake',
            'etiology' => 'decreased appetite', 'signs' => 'Consumes half of usual meals',
            'evidence_used' => ['appetite', 'dietary_intake'], 'etiology_evidence' => ['appetite'], 'signs_evidence' => ['dietary_intake']]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.review_outcome', 'rejected');
    }

    public function test_other_problem_needs_its_own_nutrition_evidence(): void
    {
        $record = $this->record();
        $this->provider([[...$this->draft(), 'candidate_id' => 'behavioral_other_v2', 'problem_key' => 'Severe Iron Deficiency']]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.review_outcome', 'rejected');
    }

    public function test_eligible_intake_problem_still_needs_matching_signs(): void
    {
        $record = $this->record();
        $record->assessment()->update(['dietary_intake' => 'Excessive protein intake from supplements; consumes half of usual meals.', 'appetite_changes' => 'decreased']);
        $this->provider([[...$this->draft(), 'candidate_id' => 'excessive_protein_v2', 'domain' => 'NI', 'problem_key' => 'Excessive Protein Intake',
            'etiology' => 'decreased appetite', 'signs' => 'Consumes half of usual meals',
            'evidence_used' => ['appetite', 'dietary_intake'], 'etiology_evidence' => ['appetite'], 'signs_evidence' => ['dietary_intake']]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.review_outcome', 'rejected');
    }

    public function test_documented_protein_intake_problem_stays_supported(): void
    {
        $record = $this->record();
        $record->assessment()->update(['dietary_intake' => 'Protein intake below prescribed needs.', 'appetite_changes' => 'decreased']);
        $this->provider([[...$this->draft(), 'candidate_id' => 'inadequate_protein_v2', 'domain' => 'NI', 'problem_key' => 'Inadequate Protein Intake',
            'etiology' => 'decreased appetite', 'signs' => 'Protein intake below prescribed needs',
            'evidence_used' => ['appetite', 'dietary_intake'], 'etiology_evidence' => ['appetite'], 'signs_evidence' => ['dietary_intake']]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonPath('data.0.label', 'Inadequate Protein Intake');
    }

    public function test_uncited_medication_change_preserves_behavioral_review_and_draft(): void
    {
        $record = $this->record();
        $record->assessment()->update(['medications' => ['Metformin']]);
        $this->provider([$this->draft()]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $id = $this->postJson($url)->assertOk()->json('data.0.draft_id');
        $record->assessment()->update(['medications' => ['Metformin; saline eye drops']]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.draft_id', $id)->assertJsonPath('meta.can_generate', false);
        $this->postJson($url)->assertOk();
        Http::assertSentCount(1);
    }

    public function test_cited_clinical_context_still_reopens_behavioral_review(): void
    {
        $record = $this->record();
        $record->assessment()->update(['medications' => ['Metformin']]);
        $draft = $this->draft();
        $draft['evidence_used'][] = 'clinical_context';
        $this->provider([$draft]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.can_generate', false);
        $record->assessment()->update(['medications' => ['Insulin']]);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.can_generate', true);
        Http::assertSentCount(1);
    }

    public function test_review_tracks_citations_from_every_custom_draft_sharing_a_candidate(): void
    {
        $record = $this->record();
        $record->assessment()->update(['medications' => ['Metformin'], 'knowledge_notes' => 'Cannot identify carbohydrate foods; food knowledge gap noted.']);
        $first = [...$this->draft(), 'candidate_id' => 'behavioral_other_v2', 'problem_key' => 'Nutrition education access difficulty'];
        $second = [...$first, 'problem_key' => 'Food knowledge gap', 'evidence_used' => ['social_history', 'knowledge_notes', 'clinical_context']];
        $this->provider([$first, $second]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.can_generate', false);
        $record->assessment()->update(['medications' => ['Insulin']]);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.can_generate', true);
        Http::assertSentCount(1);
    }
}
