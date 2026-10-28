<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\PesSuggestionState;
use App\Models\User;
use App\Services\Diagnosis\PesRuleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PesSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.key', 'test-key');
        $this->rnd = User::factory()->rnd()->create();
    }

    public function test_demo_patient_receives_validated_bounded_draft_and_cached_repeat(): void
    {
        $ncpRecord = $this->makeRecord(assessment: [
            'weight_loss_percentage' => 6.5,
            'weight_change_period_value' => 2,
            'weight_change_period_unit' => 'months',
            'appetite_changes' => 'decreased',
        ]);
        Http::fake(['api.anthropic.com/*' => Http::response($this->providerResponse())]);

        $first = $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.candidate_id', 'unintended_weight_loss_v1')
            ->assertJsonPath('data.0.label', 'Unintended weight loss')
            ->assertJsonPath('meta.cached', false)
            ->assertJsonPath('meta.catalog_version', app(PesRuleCatalog::class)->version());

        $this->assertLessThanOrEqual(5, count($first->json('data')));

        $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest")
            ->assertOk()
            ->assertJsonPath('meta.cached', true);

        Http::assertSentCount(1);
        $this->assertDatabaseCount('pes_suggestion_states', 1);
    }

    public function test_zero_eligible_drafts_is_successful_without_provider_call(): void
    {
        $ncpRecord = $this->makeRecord(assessment: [
            'weight_loss_percentage' => 0,
            'energy_intake_status' => 'No change',
        ]);
        Http::fake();

        $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest")
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.message', 'No supported evidence left.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('pes_suggestion_states', 0);
    }

    public function test_dismissal_reopens_the_related_review_without_automatic_generation(): void
    {
        $ncpRecord = $this->makeRecord(assessment: [
            'weight_loss_percentage' => 6.5,
            'weight_change_period_value' => 2,
            'weight_change_period_unit' => 'months',
            'appetite_changes' => 'decreased',
        ]);
        Http::fake(['api.anthropic.com/*' => Http::response($this->providerResponse())]);
        $url = "/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest";

        $this->actingAs($this->rnd, 'sanctum')->postJson($url)->assertOk();
        $this->actingAs($this->rnd, 'sanctum')->postJson($url, [
            'dismissed_candidate_id' => 'unintended_weight_loss_v1',
        ])->assertOk()->assertJsonPath('data', []);
        $this->actingAs($this->rnd, 'sanctum')->postJson($url)
            ->assertOk()->assertJsonCount(1, 'data');

        $state = PesSuggestionState::query()->firstOrFail();
        $this->assertSame([], $state->dismissed_candidate_ids);
        Http::assertSentCount(2);
    }

    public function test_assessment_change_invalidates_fingerprint_and_allows_one_new_call(): void
    {
        $ncpRecord = $this->makeRecord(assessment: [
            'weight_loss_percentage' => 6.5,
            'weight_change_period_value' => 2,
            'weight_change_period_unit' => 'months',
            'appetite_changes' => 'decreased',
        ]);
        Http::fakeSequence()
            ->push($this->providerResponse())
            ->push($this->providerResponse(signs: '7% unintended weight loss over 2 months'));
        $url = "/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest";

        $this->actingAs($this->rnd, 'sanctum')->postJson($url)->assertOk();
        $ncpRecord->assessment()->update(['weight' => 55.8, 'weight_loss_percentage' => 7]);
        $this->actingAs($this->rnd, 'sanctum')->postJson($url)
            ->assertOk()
            ->assertJsonPath('data.0.signs', '7% unintended weight loss over 2 months')
            ->assertJsonPath('meta.cached', false);

        Http::assertSentCount(2);
        $this->assertDatabaseCount('pes_suggestion_states', 2);
    }

    public function test_unknown_source_is_withheld_and_review_is_cached(): void
    {
        $ncpRecord = $this->makeRecord(assessment: [
            'weight_loss_percentage' => 6.5,
            'weight_change_period_value' => 2,
            'weight_change_period_unit' => 'months',
            'appetite_changes' => 'decreased',
        ]);
        $bad = $this->providerResponse();
        $decoded = json_decode($bad['content'][0]['text'], true);
        $decoded['suggestions'][0]['source_id'] = 'invented-source';
        $bad['content'][0]['text'] = json_encode($decoded);
        Http::fake(['api.anthropic.com/*' => Http::response($bad)]);

        $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest")
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.review_outcome', 'rejected')->assertJsonPath('meta.can_retry', true);

        $this->assertDatabaseCount('pes_suggestion_states', 1);
    }

    public function test_unknown_evidence_is_withheld_and_review_is_cached(): void
    {
        $ncpRecord = $this->makeRecord(assessment: [
            'weight_loss_percentage' => 6.5,
            'weight_change_period_value' => 2,
            'weight_change_period_unit' => 'months',
            'appetite_changes' => 'decreased',
        ]);
        $bad = $this->providerResponse();
        $decoded = json_decode($bad['content'][0]['text'], true);
        $decoded['suggestions'][0]['evidence_used'][] = 'patient_name';
        $bad['content'][0]['text'] = json_encode($decoded);
        Http::fake(['api.anthropic.com/*' => Http::response($bad)]);

        $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest")
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.review_outcome', 'rejected')->assertJsonPath('meta.can_retry', true);

        $this->assertDatabaseCount('pes_suggestion_states', 1);
    }

    public function test_provider_payload_is_deidentified_and_token_bounded(): void
    {
        $patient = Patient::factory()->create([
            'name' => 'Fictional Private Name',
            'first_name' => 'Fictional',
            'last_name' => 'Private Name',
            'hospital_number' => 'SECRET-HOSPITAL-42',
            'address' => 'Secret address',
            'physician' => 'Dr Secret',
            'dob' => '1980-01-02',
        ]);
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        Assessment::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'weight' => 56.1, 'usual_weight' => 60, 'height' => 170,
            'medical_history' => 'Unintentional weight loss after decreased appetite.',
            'weight_loss_percentage' => 6.5,
            'weight_change_period_value' => 2,
            'weight_change_period_unit' => 'months',
            'appetite_changes' => 'decreased',
            'rnd_summary' => str_repeat('bounded summary ', 100),
        ]);
        Http::fake(['api.anthropic.com/*' => Http::response($this->providerResponse())]);

        $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest")
            ->assertOk();

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $serialized = json_encode($body);
            $content = $body['messages'][0]['content'] ?? '';

            $this->assertLessThanOrEqual(1800, $body['max_tokens']);
            $this->assertLessThanOrEqual(18000, strlen($content));
            foreach (['Fictional Private Name', 'SECRET-HOSPITAL-42', 'Secret address', 'Dr Secret', '1980-01-02'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $serialized);
            }
            foreach (['name', 'patient_code', 'hospital_number', 'address', 'physician', 'attachment', 'dob'] as $forbiddenKey) {
                $this->assertStringNotContainsString('"'.$forbiddenKey.'"', $content);
            }

            return true;
        });
    }

    public function test_bounded_drafting_has_no_demo_marker_dependency(): void
    {
        $ncpRecord = $this->makeRecord(assessment: [
            'weight_loss_percentage' => 6.5,
            'weight_change_period_value' => 2,
            'weight_change_period_unit' => 'months',
            'appetite_changes' => 'decreased',
        ]);
        Http::fake(['api.anthropic.com/*' => Http::response($this->providerResponse())]);
        $url = "/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest";

        $this->actingAs($this->rnd, 'sanctum')->postJson($url)->assertOk();
        Http::assertSentCount(1);
    }

    public function test_suggestion_route_requires_authentication_and_rnd_role(): void
    {
        $ncpRecord = $this->makeRecord(assessment: []);

        $this->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest")
            ->assertUnauthorized();

        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest")
            ->assertForbidden();
    }

    private function makeRecord(array $assessment): NcpRecord
    {
        $patient = Patient::factory()->create(['dob' => '1990-01-01']);
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        Assessment::factory()->create(array_merge([
            'ncp_record_id' => $ncpRecord->id,
            'weight' => round(60 * (1 - (float) ($assessment['weight_loss_percentage'] ?? 0) / 100), 4),
            'usual_weight' => 60, 'height' => 170, 'physical_assessment' => null, 'present_diet' => null,
            'medical_history' => ($assessment['weight_loss_percentage'] ?? 0) > 0 ? 'Unintentional weight loss after decreased appetite.' : null,
            'weight_loss_percentage' => null,
            'weight_change_period_value' => null,
            'weight_change_period_unit' => null,
            'energy_intake_status' => 'No change',
            'appetite_changes' => 'normal',
            'chewing_swallowing_difficulties' => null,
            'constipation' => null,
            'diarrhea_notes' => null,
            'nutrient_drug_interaction' => null,
        ], $assessment));

        return $ncpRecord->fresh();
    }

    private function providerResponse(string $signs = '6.5% unintended weight loss over 2 months'): array
    {
        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode(['suggestions' => [[
                    'candidate_id' => 'unintended_weight_loss_v1',
                    'domain' => 'NC',
                    'problem_key' => 'Unintended Weight Loss',
                    'etiology' => 'decreased appetite',
                    'signs' => $signs,
                    'evidence_used' => ['weight_loss_percentage', 'weight_change_period', 'appetite', 'medical_history'],
                    'evidence_quotes' => ['weight_loss_percentage' => str_starts_with($signs, '7%') ? '7' : '6.5', 'weight_change_period' => '2', 'appetite' => 'decreased', 'medical_history' => 'Unintentional weight loss'],
                    'etiology_evidence' => ['appetite'], 'signs_evidence' => ['weight_loss_percentage', 'weight_change_period', 'medical_history'],
                    'extra_notes' => 'Loss and decreased appetite require review in context.',
                    'source_id' => 'academy_ncp_diagnosis_2026',
                ]]]),
            ]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ];
    }
}
