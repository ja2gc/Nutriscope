<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\Diagnosis\PesEligibilityService;
use App\Services\Diagnosis\PesEvidenceBuilder;
use App\Services\Diagnosis\PesRuleCatalog;
use App\Support\DiagnosisBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $this->getJson($url)->assertOk()->assertJsonPath('meta.budget.remaining', 10);
        $this->postJson($url)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.budget.remaining', 10);
        Http::assertNothingSent();
    }

    public function test_provider_abstention_stops_generation_until_supporting_evidence_changes(): void
    {
        $record = $this->knowledgeRecord();
        $this->provider([]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->postJson($url)->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.can_generate', false)->assertJsonPath('meta.budget.used', 1);
        $this->getJson($url)->assertOk()->assertJsonPath('meta.can_generate', false);
        $this->postJson($url)->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.can_generate', false)->assertJsonPath('meta.budget.used', 1);
        Http::assertSentCount(1);
        $record->assessment()->update(['knowledge_notes' => 'Cannot identify high-sodium foods.']);
        $this->getJson($url)->assertOk()->assertJsonPath('meta.can_generate', true);
    }

    public function test_pending_drafts_remain_reviewable_when_no_new_evidence_is_left(): void
    {
        $record = $this->knowledgeRecord();
        $this->provider([$this->knowledgeDraft()]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $id = $this->postJson($url)->assertOk()->assertJsonPath('meta.can_generate', false)->json('data.0.draft_id');
        $this->postJson($url)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.draft_id', $id)->assertJsonPath('meta.can_generate', false)
            ->assertJsonPath('meta.budget.used', 1);
        Http::assertSentCount(1);
    }

    public function test_ten_requests_are_shared_by_rnd_across_cycles_and_reset_after_ten_minutes(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Manila')->setTime(23, 55));
        $record = $this->record(['knowledge_notes' => 'Cannot identify carbohydrate foods.']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '{"suggestions":[]}']], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]])]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        for ($i = 1; $i <= 10; $i++) {
            $record->assessment()->update(['knowledge_notes' => "Cannot identify carbohydrate foods. Finding {$i}."]);
            $this->postJson($url)->assertOk()->assertJsonPath('meta.budget.remaining', 10 - $i);
        }
        $user = auth()->user();
        $other = $this->record(['knowledge_notes' => 'Cannot identify carbohydrate foods.'], $record->patient);
        $other->update(['rnd_user_id' => $user->id]);
        $this->actingAs($user, 'sanctum');
        $otherUrl = "/api/rnd/ncp-records/{$other->uuid}/diagnoses/ai-suggest";
        $this->postJson($otherUrl)->assertStatus(429)->assertJsonPath('meta.budget.remaining', 0);
        $this->getJson($otherUrl)->assertOk()->assertJsonPath('meta.budget.remaining', 0);
        Http::assertSentCount(10);
        $this->travel(601)->seconds();
        $this->postJson($otherUrl)->assertOk()->assertJsonPath('meta.budget.remaining', 9);
    }

    public function test_other_text_enters_pes_but_reasoning_does_not_and_signs_allow_400(): void
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
        $payload['builder_data']['sign_other'] = str_repeat('x', 401);
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
        Http::fake(['api.anthropic.com/*' => function ($request) use ($drafts) {
            $payload = json_decode(substr($request['messages'][0]['content'], strlen("input_json:\n")), true);
            $wireDrafts = array_map(function ($draft) use ($payload) {
                if (! isset($draft['id'])) {
                    return $draft;
                }
                $card = collect(app(PesRuleCatalog::class)->cards())->firstWhere('id', $draft['id']);
                $alias = $card ? array_search([$card['domain'], DiagnosisBuilder::problemLabel($card['domain'], $card['problem_key'])], array_map(fn ($candidate) => array_slice($candidate, 0, 2), $payload['candidates']), true) : false;
                if ($alias !== false) {
                    $draft['id'] = $alias;
                }
                $keys = [];
                foreach ($payload['evidence'] as $ref => $value) {
                    if (is_array($value) && array_is_list($value) && count($value) === 2 && is_string($value[0])) {
                        $keys[$value[0]] = $ref;
                    }
                }
                foreach (['ek', 'sk'] as $role) {
                    if (is_array($draft[$role] ?? null)) {
                        $draft[$role] = array_map(fn ($key) => is_string($key) ? ($keys[$key] ?? $key) : $key, $draft[$role]);
                    }
                }

                return $draft;
            }, $drafts);

            return Http::response(['content' => [['type' => 'text', 'text' => json_encode(['suggestions' => $wireDrafts])]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 100]]);
        }]);
    }

    private function knowledgeRecord(): NcpRecord
    {
        return $this->record(['knowledge_notes' => 'Cannot identify carbohydrate foods.', 'social_history' => 'Limited access to nutrition education.']);
    }

    public function test_compact_output_uses_server_metadata_and_keeps_optional_reasoning(): void
    {
        $record = $this->knowledgeRecord();
        $this->provider([[
            'id' => 'knowledge_deficit_v2',
            'e' => 'Limited access to nutrition education', 'ek' => ['social_history'],
            's' => 'Cannot identify carbohydrate foods', 'sk' => ['knowledge_notes'],
            'n' => 'Knowledge gap and education barrier documented.',
        ]]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $draft = $this->postJson($url)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.candidate_id', 'knowledge_deficit_v2')
            ->assertJsonPath('data.0.domain', 'NB')
            ->assertJsonPath('data.0.label', 'Food- and nutrition-related knowledge deficit')
            ->assertJsonPath('data.0.source.id', 'academy_ncp_diagnosis_2026')
            ->assertJsonPath('data.0.extra_notes', 'Knowledge gap and education barrier documented.')
            ->json('data.0');
        $this->assertSame(['social_history', 'knowledge_notes'], array_column($draft['evidence_refs'], 'key'));
        Http::assertSent(function ($request): bool {
            $payload = json_decode(substr($request['messages'][0]['content'], strlen("input_json:\n")), true);
            foreach ($payload['candidates'] as $ref => $candidate) {
                $this->assertMatchesRegularExpression('/^c[1-9][0-9]*$/', $ref);
                $this->assertCount(3, $candidate);
                foreach ($candidate[2] as $anchor) {
                    $this->assertArrayHasKey($anchor, $payload['evidence']);
                }
            }
            foreach ($payload['evidence'] as $ref => $field) {
                $this->assertMatchesRegularExpression('/^e[1-9][0-9]*$/', $ref);
                $this->assertCount(2, $field);
            }

            return true;
        });
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.draft_id', $draft['draft_id']);
        $this->postJson($url)->assertOk()->assertJsonPath('meta.budget.used', 1);
        Http::assertSentCount(1);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-approve", ['draft_id' => $draft['draft_id']])->assertCreated();
        $provenance = Diagnosis::query()->sole()->ai_provenance;
        $this->assertSame('knowledge_deficit_v2', $provenance['candidate_id']);
        $this->assertSame(['social_history', 'knowledge_notes'], array_column($provenance['evidence_refs'], 'key'));
    }

    public function test_compact_output_keeps_readable_text_with_unusable_references_for_review(): void
    {
        $record = $this->knowledgeRecord();
        $this->provider([['id' => 'knowledge_deficit_v2', 'e' => 'Limited access to nutrition education', 'ek' => ['e999'],
            's' => 'Cannot identify carbohydrate foods', 'sk' => ['knowledge_notes']]]);
        $result = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonCount(1, 'review_drafts')
            ->assertJsonPath('review_drafts.0.etiology', 'Limited access to nutrition education');
        $this->assertSame([], $result->json('review_drafts.0.evidence_by_component.etiology'));
        $this->assertSame(['knowledge_notes'], array_column($result->json('review_drafts.0.evidence_refs'), 'key'));
    }

    public function test_supplied_component_references_are_not_rejected_for_their_total_count(): void
    {
        $record = $this->knowledgeRecord();
        $this->provider([[...$this->knowledgeDraft(), 'evidence_used' => [
            'social_history', 'knowledge_notes', 'age_group', 'age_70_plus', 'sex',
            'weight_kg', 'height_cm', 'weight_change_direction', 'pregnancy_lactation_status',
        ]]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonCount(0, 'review_drafts');
    }

    public function test_compact_output_still_rejects_unusable_components_and_unknown_candidates(): void
    {
        foreach ([
            ['id' => 'invented_id', 'e' => 'Limited access to nutrition education', 'ek' => ['social_history'], 's' => 'Cannot identify carbohydrate foods', 'sk' => ['knowledge_notes']],
            ['id' => 'knowledge_deficit_v2', 'e' => 'Limited access to nutrition education', 'ek' => ['social_history'], 's' => '', 'sk' => ['knowledge_notes']],
        ] as $bad) {
            $record = $this->knowledgeRecord();
            $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
            $this->provider([$bad]);
            $this->postJson($url)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.review_outcome', 'rejected')->assertJsonPath('meta.can_retry', true);
            $this->getJson($url)->assertJsonPath('meta.budget.used', 1);
            Http::assertSentCount(1);
        }
        $this->assertDatabaseCount('ai_usage_logs', 2);
        $this->assertDatabaseHas('ai_usage_logs', ['tokens_input' => 100, 'tokens_output' => 100, 'tokens_total' => 200]);
    }

    public function test_compact_standard_problem_from_other_maps_into_the_saved_builder(): void
    {
        $record = $this->knowledgeRecord();
        $this->provider([[
            'id' => 'behavioral_other_v2', 'p' => 'Food- and nutrition-related knowledge deficit',
            'e' => 'Limited access to nutrition education', 'ek' => ['social_history'],
            's' => 'Cannot identify carbohydrate foods', 'sk' => ['knowledge_notes'],
        ]]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonPath('data.0.builder_data.problem_checks.0', 'Food- and nutrition-related knowledge deficit')
            ->assertJsonPath('data.0.builder_data.problem_other', '')
            ->assertJsonPath('data.0.extra_notes', '');
    }

    public function test_review_keeps_normal_measurements_and_counterevidence_in_the_clinical_snapshot(): void
    {
        $record = $this->knowledgeRecord();
        $record->assessment()->update(['physical_assessment' => 'Normal dentition.', 'present_diet' => 'Adequate intake.', 'waist_cm' => 75]);
        $record->assessment->biochemicalData()->create(['albumin' => 4.0, 'sodium' => 140]);
        $this->provider([$this->knowledgeDraft()]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk();
        Http::assertSent(function ($request): bool {
            $payload = json_decode(substr($request['messages'][0]['content'], strlen("input_json:\n")), true);
            $fields = array_column($payload['evidence'], 1, 0);
            $this->assertSame(4.0, (float) $fields['laboratory_values']['albumin']);
            $this->assertSame(75, $fields['waist_cm']);
            $this->assertSame('Normal dentition.', $fields['physical_assessment']);
            $this->assertSame('Adequate intake.', $fields['present_diet']);
            $this->assertSame('stable', $fields['weight_change_direction']);
            $this->assertArrayHasKey('pregnancy_lactation_status', $fields);

            return true;
        });
    }

    public function test_unknown_short_references_are_withheld_individually_and_usage_remains_counted(): void
    {
        $record = $this->knowledgeRecord();
        Log::spy();
        $valid = ['id' => 'knowledge_deficit_v2', 'e' => 'Limited access to nutrition education', 'ek' => ['social_history'],
            's' => 'Cannot identify carbohydrate foods', 'sk' => ['knowledge_notes']];
        $invalid = array_replace($valid, ['id' => 'food_insecurity_v2', 'sk' => ['e999']]);
        $this->provider([$invalid, $valid]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.candidate_id', 'knowledge_deficit_v2');
        $this->assertDatabaseHas('ai_usage_logs', ['endpoint' => 'diagnosis_suggestion', 'tokens_input' => 100, 'tokens_output' => 100]);
        Log::shouldHaveReceived('info')->once()->with('PES generation outcome', \Mockery::on(fn ($data) => $data['input_tokens'] === 100 && $data['output_tokens'] === 100
            && $data['returned_drafts'] === 2 && $data['accepted_drafts'] === 1 && $data['withheld_drafts'] === 1
            && isset($data['usage_log_id']) && ! str_contains(json_encode($data), 'Cannot identify')
        ));
        Http::assertSentCount(1);
    }

    public function test_provider_input_bounds_shared_domain_choices_without_repeated_source_metadata(): void
    {
        $record = $this->knowledgeRecord();
        $record->assessment()->update([
            'dietary_intake' => 'Consumes half of usual meals for 3 weeks due to poor appetite.',
            'appetite_changes' => 'decreased', 'physical_assessment' => 'Poor dentition limits chewing.',
            'medical_history' => 'Intentional 4% gain; no unintended loss.',
            'chewing_swallowing_difficulties' => 'Yes', 'chewing_swallowing_details' => 'Painful chewing of firm meat.',
        ]);
        $this->provider([$this->knowledgeDraft()]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk();
        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $prompt = $body['messages'][0]['content'];
            $payload = json_decode(explode("\noutput_json_schema:", substr($prompt, strlen("input_json:\n")))[0], true);
            $inputCharacters = strlen($body['system'].$prompt.json_encode($body['output_config']['format']['schema'] ?? []));
            $this->assertLessThanOrEqual(8500, $inputCharacters, 'Category meanings and independently selected P/E/S citations remain bounded.');
            $this->assertArrayNotHasKey('options', $payload);
            $this->assertSame(['NI', 'NB', 'NC'], array_keys($payload['choices']));
            $this->assertArrayNotHasKey('catalog_version', $payload);
            $fields = array_column($payload['evidence'], 1, 0);
            $this->assertSame('Intentional 4% gain; no unintended loss.', $fields['medical_history']);
            $this->assertSame('Poor dentition limits chewing.', $fields['physical_assessment']);
            $this->assertSame('stable', $fields['weight_change_direction']);
            foreach ($payload['candidates'] as $candidate) {
                $this->assertArrayNotHasKey('s', $candidate);
            }

            return true;
        });
    }

    public function test_grounded_draft_keeps_reasoning_separate_and_acceptance_tracks_evidence_without_regenerating(): void
    {
        $record = $this->knowledgeRecord();
        $this->provider([$this->knowledgeDraft()]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $draft = $this->postJson($url)->assertOk()->assertJsonPath('meta.budget.remaining', 9)->json('data.0');
        $this->assertSame('Limited access to nutrition education', $draft['builder_data']['etiology_other']);
        $approved = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-approve", ['draft_id' => $draft['draft_id']])->assertCreated();
        $this->assertStringNotContainsString('Access barrier reported', $approved->json('data.pes_statement'));
        $this->assertSame('Access barrier reported.', $approved->json('data.builder_data.etiology_notes'));
        $this->assertSame($draft['draft_id'], Diagnosis::query()->sole()->ai_provenance['draft_id']);
        $this->getJson($url)->assertOk()->assertJsonPath('data', []);
        $this->postJson($url)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.budget.remaining', 9);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-approve", ['draft_id' => $draft['draft_id']])->assertUnprocessable();
        Http::assertSentCount(1);
    }

    public function test_stale_and_denied_findings_are_withheld_without_rewriting_the_draft(): void
    {
        foreach ([['etiology' => 'Kidney failure'], ['signs' => 'No difficulty swallowing'], ['signs' => 'Difficulty swallowing']] as $override) {
            $record = $this->knowledgeRecord();
            $record->assessment()->update(['knowledge_notes' => 'Cannot identify carbohydrate foods. No difficulty swallowing.']);
            $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
            $this->provider([[...$this->knowledgeDraft(), ...$override]]);
            $response = $this->postJson($url)->assertOk();
            $response->assertJsonPath('data', []);
            $this->getJson($url)->assertJsonPath('meta.budget.remaining', 9)->assertJsonPath('meta.can_generate', false);
        }
        $record = $this->knowledgeRecord();
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
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

    public function test_a_component_over_75_remains_available_without_truncation(): void
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
        $this->postJson($url)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.1.signs', $long['signs'])
            ->assertJsonPath('meta.budget.used', 1)->assertJsonPath('meta.can_generate', false);
        $long['signs'] = 'Misses dinner twice weekly; skips lunch on working days';
        $this->provider([$long]);
        $this->postJson($url)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.budget.used', 1);
        Http::assertNothingSent();
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
        $this->getJson($url)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.can_generate', true);
        Http::assertSentCount(1);
    }

    public function test_provider_priority_order_uses_one_shared_evidence_map(): void
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
        $this->provider(array_map(static fn ($draft): array => [
            'id' => $draft['candidate_id'], 'e' => $draft['etiology'], 'ek' => $draft['etiology_evidence'],
            's' => $draft['signs'], 'sk' => $draft['signs_evidence'],
        ], [$this->knowledgeDraft(), $clinical, $intake]));
        $response = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame(['NB', 'NC', 'NI'], array_column($response->json('data'), 'domain'));
        Http::assertSent(function ($request): bool {
            $prompt = $request->data()['messages'][0]['content'];
            $encoded = explode("\noutput_json_schema:", substr($prompt, strlen("input_json:\n")))[0];
            $payload = json_decode($encoded, true);
            $this->assertArrayHasKey('knowledge_notes', array_column($payload['evidence'], 1, 0));
            foreach ($payload['candidates'] as $candidate) {
                $this->assertArrayNotHasKey('evidence', $candidate);
            }

            return true;
        });
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest";
        $this->getJson($url)->assertOk()->assertJsonPath('meta.can_generate', false);
        $firstIds = array_column($response->json('data'), 'draft_id');
        $activity = [
            'candidate_id' => 'physical_inactivity_v2', 'domain' => 'NB', 'problem_key' => 'Physical Inactivity',
            'etiology' => 'Long working hours', 'signs' => 'No planned physical activity',
            'evidence_used' => ['lifestyle'], 'evidence_quotes' => ['lifestyle' => 'Long working hours; no planned physical activity'],
            'etiology_evidence' => ['lifestyle'], 'signs_evidence' => ['lifestyle'], 'source_id' => 'academy_ncp_diagnosis_2026',
        ];
        $this->provider([$activity]);
        $next = $this->postJson($url)->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('meta.budget.used', 1);
        $this->assertEmpty(array_diff($firstIds, array_column($next->json('data'), 'draft_id')));
        Http::assertNothingSent();
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
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonPath('data', []);
        $record->assessment()->update(['physical_assessment' => 'Reduced muscle mass on nutrition-focused physical exam.', 'dietary_intake' => 'Reduced food intake for 3 weeks.']);
        $draft['etiology'] = 'Reduced food intake for 3 weeks';
        $draft['signs'] = 'Reduced muscle mass';
        $draft['evidence_quotes'] = ['physical_assessment' => 'Reduced muscle mass', 'dietary_intake' => 'Reduced food intake for 3 weeks'];
        $draft['signs_evidence'] = ['physical_assessment'];
        $this->provider([$draft]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(1, 'data');
    }
}
