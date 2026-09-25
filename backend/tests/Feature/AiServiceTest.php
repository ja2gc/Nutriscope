<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->rnd()->create();
        $this->actingAs($this->rnd, 'sanctum');
        config()->set('services.anthropic.key', 'test-key');
    }

    public function test_pes_draft_service_throws_on_api_failure_or_connection_timeout(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);

        try {
            app(AIService::class)->draftPes($this->boundedPayload());
            $this->fail('Expected provider failure.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('AI service request failed', $exception->getMessage());
        }

        Http::fake([
            'api.anthropic.com/*' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->expectException(\RuntimeException::class);
        app(AIService::class)->draftPes($this->boundedPayload());
    }

    public function test_pes_draft_service_decodes_json_fences_and_logs_usage(): void
    {
        Cache::put('admin_dashboard', ['stale' => true], 300);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => "```json\n".json_encode($this->providerSuggestions())."\n```",
            ]],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 50],
        ])]);

        $result = app(AIService::class)->draftPes($this->boundedPayload());

        $this->assertSame('unintended_weight_loss_v1', $result[0]['candidate_id']);
        $this->assertDatabaseHas('ai_usage_logs', [
            'user_id' => $this->rnd->id,
            'endpoint' => 'diagnosis_suggestion',
            'tokens_total' => 170,
        ]);
        $this->assertFalse(Cache::has('admin_dashboard'));
    }

    public function test_pes_draft_service_rejects_malformed_provider_content(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{not-json']],
        ])]);

        $this->expectException(\RuntimeException::class);
        app(AIService::class)->draftPes($this->boundedPayload());
    }

    public function test_pes_draft_instruction_is_compact_yaml_with_json_data_and_output_contract(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode($this->providerSuggestions())]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ])]);

        app(AIService::class)->draftPes($this->boundedPayload());

        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $system = $body['system'] ?? '';
            $prompt = $body['messages'][0]['content'] ?? '';

            $this->assertStringStartsWith('task:', $system);
            $this->assertStringContainsString("rules:\n", $system);
            $this->assertStringContainsString("input_json:\n", $prompt);
            $this->assertStringContainsString("output_json_schema:\n", $prompt);
            $this->assertLessThanOrEqual(1200, strlen($system.$prompt));

            return true;
        });
    }

    public function test_ai_approve_uses_existing_authorized_audited_save_path(): void
    {
        $ncpRecord = $this->makeNcpRecord();
        Assessment::forceCreate([
            'ncp_record_id' => $ncpRecord->id,
            'weight' => 70.0,
            'height' => 170.0,
        ]);

        $this->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-approve", [
            'domain' => 'NC',
            'label' => 'Unintended Weight Loss',
            'etiology' => 'related to decreased appetite',
            'signs' => 'as evidenced by 6.5% weight loss over 2 months',
        ])->assertCreated()
            ->assertJsonPath('data.problem', 'Unintended Weight Loss')
            ->assertJsonPath('data.etiology', 'decreased appetite')
            ->assertJsonPath('data.signs_symptoms', '6.5% weight loss over 2 months')
            ->assertJsonPath('data.ai_generated', true);

        $this->assertDatabaseHas('diagnoses', [
            'ncp_record_id' => $ncpRecord->id,
            'label' => 'Unintended Weight Loss',
            'ai_generated' => true,
        ]);
        $this->assertDatabaseHas('activity_log', ['event' => 'approved']);
    }

    public function test_ai_approve_requires_assessment_and_valid_problem_shape(): void
    {
        $ncpRecord = $this->makeNcpRecord();

        $this->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-approve", [
            'domain' => 'NC',
            'label' => 'Unintended Weight Loss',
            'etiology' => 'decreased appetite',
            'signs' => '6.5% weight loss over 2 months',
        ])->assertUnprocessable();

        Assessment::forceCreate([
            'ncp_record_id' => $ncpRecord->id,
            'weight' => 70.0,
            'height' => 170.0,
        ]);
        $this->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-approve", [
            'domain' => 'INVALID',
            'label' => str_repeat('A', 256),
            'etiology' => 'cause',
            'signs' => 'evidence',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['domain', 'label']);
    }

    private function makeNcpRecord(): NcpRecord
    {
        $patient = Patient::factory()->create();

        return NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
    }

    private function boundedPayload(): array
    {
        return [
            'catalog_version' => '2026-09-21-v1',
            'candidates' => [[
                'candidate_id' => 'unintended_weight_loss_v1',
                'problem_key' => 'Unintended Weight Loss',
                'domain' => 'NC',
                'evidence' => [
                    'weight_loss_percentage' => 6.5,
                    'weight_change_period' => ['value' => 2, 'unit' => 'months'],
                    'appetite' => 'decreased',
                ],
                'source_id' => 'academy_ncp_diagnosis_2026',
            ]],
        ];
    }

    private function providerSuggestions(): array
    {
        return ['suggestions' => [[
            'candidate_id' => 'unintended_weight_loss_v1',
            'domain' => 'NC',
            'problem_key' => 'Unintended Weight Loss',
            'etiology' => 'decreased appetite',
            'signs' => '6.5% unintended weight loss over 2 months',
            'evidence_used' => ['weight_loss_percentage', 'weight_change_period', 'appetite'],
            'source_id' => 'academy_ncp_diagnosis_2026',
        ]]];
    }
}
