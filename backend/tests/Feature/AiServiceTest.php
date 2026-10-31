<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\AIService;
use App\Services\Diagnosis\PesSuggestionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    public function test_sonnet_requests_use_supported_endpoint_specific_settings(): void
    {
        config()->set('services.anthropic.model', 'claude-sonnet-5-5');
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode($this->providerSuggestions())]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50]])]);
        app(AIService::class)->draftPes($this->boundedPayload());
        Http::assertSent(fn ($request) => $request['model'] === 'claude-sonnet-5-5'
            && $request['thinking'] === ['type' => 'adaptive']
            && $request['output_config']['effort'] === 'medium' && $request['max_tokens'] === 4096);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Progress needs continued review.']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20]])]);
        app(AIService::class)->narrateMonitoring([]);
        Http::assertSent(fn ($request) => $request['thinking'] === ['type' => 'between_tools']
            && $request['output_config']['effort'] === 'low' && $request['max_tokens'] === 512);
    }

    public function test_symptom_only_concern_is_cached_dismissible_and_cannot_be_approved_as_a_diagnosis(): void
    {
        $record = NcpRecord::factory()->create(['patient_id' => Patient::factory()->create()->id, 'rnd_user_id' => $this->rnd->id, 'status' => 'draft']);
        Assessment::factory()->create(['ncp_record_id' => $record->id, 'physical_assessment' => 'Night blindness reported.']);
        $ref = null;
        Http::preventStrayRequests();
        Http::fake(['api.anthropic.com/*' => function ($request) use (&$ref) {
            if ($ref === null) {
                $payload = json_decode(substr($request['messages'][0]['content'], strlen("input_json:\n")), true);
                $ref = collect($payload['evidence'])->search(fn ($item) => $item[0] === 'physical_assessment');

                return Http::response(['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => 'lookup', 'name' => 'search_nutrient_context',
                    'input' => ['query' => 'night blindness', 'evidence_refs' => [$ref]]]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 20]]);
            }

            return Http::response(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => json_encode(['suggestions' => [], 'concerns' => [
                ['label' => 'Possible vitamin A inadequacy', 'refs' => [$ref], 'card' => 'vitamin_a_inadequate', 'missing' => 'Clarify intake and absorption history'],
            ]])]], 'usage' => ['input_tokens' => 150, 'output_tokens' => 60]]);
        }]);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses";
        $response = $this->postJson($url.'/ai-suggest')->assertOk()->assertJsonCount(0, 'data')->assertJsonCount(1, 'concerns');
        $id = $response->json('concerns.0.concern_id');
        $this->postJson($url.'/ai-approve', ['draft_id' => $id])->assertStatus(422);
        $this->postJson($url.'/ai-suggest')->assertOk()->assertJsonCount(1, 'concerns');
        Http::assertSentCount(2);
        $this->postJson($url.'/ai-suggest', ['dismissed_draft_id' => $id])->assertOk()->assertJsonCount(0, 'concerns');
        $this->assertDatabaseCount('diagnoses', 0);
    }

    public function test_findings_lookup_returns_context_before_final_response_and_logs_both_turns(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence()->push(['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => 'lookup1', 'name' => 'search_nutrient_context',
            'input' => ['query' => 'night blindness', 'evidence_refs' => ['e1']]]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 20]])
            ->push(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => json_encode(['suggestions' => [], 'concerns' => [
                ['label' => 'Possible vitamin A inadequacy', 'refs' => ['e1'], 'card' => 'vitamin_a_inadequate', 'missing' => 'Clarify intake and absorption history'],
            ]])]], 'usage' => ['input_tokens' => 150, 'output_tokens' => 60]]);
        $result = app(AIService::class)->draftPes(['evidence' => ['e1' => ['physical_assessment', 'Night blindness reported.']], 'candidates' => []]);
        $this->assertArrayHasKey('vitamin_a_inadequate', $result['context_cards']);
        $this->assertCount(1, $result['concerns']);
        $this->assertSame(250, $result['usage']['input_tokens']);
        $this->assertDatabaseCount('ai_usage_logs', 2);
        Http::assertSentCount(2);
    }

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

    public function test_direct_ai_calls_require_a_user_before_contacting_the_provider(): void
    {
        auth()->forgetUser();
        Http::fake();

        foreach (['draftPes', 'narrateMonitoring'] as $method) {
            try {
                app(AIService::class)->{$method}([]);
                $this->fail('An unauthenticated AI call must be rejected before sending a request.');
            } catch (AuthenticationException) {
                Http::assertNothingSent();
                $this->assertDatabaseCount('ai_usage_logs', 0);
            }
        }
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

        $this->assertSame('unintended_weight_loss_v1', $result['suggestions'][0]['candidate_id']);
        $this->assertSame(120, $result['usage']['input_tokens']);
        $this->assertSame(50, $result['usage']['output_tokens']);
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

    public function test_haiku_55_diagnosis_disables_thinking_and_selects_text_by_type(): void
    {
        config()->set('services.anthropic.model', 'claude-haiku-5-5');
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'thinking', 'thinking' => '', 'signature' => 'test'],
                ['type' => 'text', 'text' => json_encode($this->providerSuggestions())]],
            'usage' => ['input_tokens' => 130, 'output_tokens' => 60], 'stop_reason' => 'end_turn',
        ])]);

        $result = app(AIService::class)->draftPes($this->boundedPayload());
        $this->assertSame('unintended_weight_loss_v1', $result['suggestions'][0]['candidate_id']);
        Http::assertSent(fn ($request) => $request['model'] === 'claude-haiku-5-5'
            && $request['thinking'] === ['type' => 'disabled']
            && $request['max_tokens'] === 1700
            && ! isset($request['temperature']) && ! isset($request['top_p']) && ! isset($request['top_k']));
        $this->assertDatabaseHas('ai_usage_logs', ['model' => 'claude-haiku-5-5', 'tokens_input' => 130, 'tokens_output' => 60]);
    }

    public function test_haiku_55_refusal_never_becomes_a_draft_but_keeps_usage(): void
    {
        config()->set('services.anthropic.model', 'claude-haiku-5-5');
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode($this->providerSuggestions())]],
            'usage' => ['input_tokens' => 130, 'output_tokens' => 20], 'stop_reason' => 'refusal',
        ])]);

        try {
            app(AIService::class)->draftPes($this->boundedPayload());
            $this->fail('A refused response must not become a draft.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('declined', $exception->getMessage());
        }
        $this->assertDatabaseHas('ai_usage_logs', ['model' => 'claude-haiku-5-5', 'tokens_total' => 150]);
        Http::assertSentCount(1);
    }

    public function test_haiku_55_monitoring_selects_text_and_rejects_refusal(): void
    {
        config()->set('services.anthropic.model', 'claude-haiku-5-5');
        $text = 'Intake improved. Protein adequacy remains the care focus.';
        Http::fakeSequence()->push([
            'content' => [['type' => 'thinking', 'thinking' => '', 'signature' => 'test'], ['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 80, 'output_tokens' => 25], 'stop_reason' => 'end_turn',
        ])->push([
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 80, 'output_tokens' => 15], 'stop_reason' => 'refusal',
        ]);

        $this->assertSame($text, app(AIService::class)->narrateMonitoring([]));
        $this->assertNull(app(AIService::class)->narrateMonitoring([]));
        Http::assertSent(fn ($request) => $request['model'] === 'claude-haiku-5-5'
            && $request['thinking'] === ['type' => 'disabled'] && $request['max_tokens'] === 400);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('ai_usage_logs', 2);
    }

    public function test_pes_draft_instruction_is_compact_with_native_json_contract(): void
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

            $this->assertStringStartsWith('# CONSTRAINTS & LOGIC', $system);
            $this->assertStringContainsString('# STRUCTURE & DOMAINS', $system);
            $this->assertStringContainsString('# CITATION & SYNTAX', $system);
            $this->assertStringContainsString("input_json:\n", $prompt);
            $this->assertStringNotContainsString('output_json_schema:', $prompt);
            $this->assertLessThanOrEqual(3000, strlen($system));
            $this->assertSame(1700, $body['max_tokens']);
            $this->assertStringContainsString('counterevidence', $system);
            foreach (['inference allowed', 'may contribute to lab P', 'missing E/S=concern'] as $guidance) {
                $this->assertStringContainsString($guidance, $system);
            }
            foreach (['beliefs/attitudes', 'cultural', 'knowledge', 'physical function', 'physiological/metabolic', 'psychological', 'social/personal', 'treatment', 'access', 'behavior',
                'food/activity history', 'anthropometry', 'biochemistry/tests', 'physical findings', 'client history', 'natural short clinical phrases', 'different facts',
                'one causal phrase', 'not food inventories', 'minimum distinct monitorable findings', 'do not repeat the same change', 'normal/unrelated results'] as $guidance) {
                $this->assertStringContainsString($guidance, $system);
            }
            $this->assertSame('json_schema', $body['output_config']['format']['type'] ?? null);
            $schema = $body['output_config']['format']['schema'];
            $draft = $schema['properties']['suggestions']['items'];
            $this->assertFalse($draft['additionalProperties']);
            foreach (['e', 's', 'p', 'n', 'en', 'sn'] as $field) {
                $this->assertArrayNotHasKey('pattern', $draft['properties'][$field]);
                $this->assertArrayNotHasKey('maxLength', $draft['properties'][$field]);
                $this->assertSame(
                    in_array($field, ['n', 'en', 'sn'], true) ? 'Maximum 100 characters.' : ($field === 'p' ? 'Between 1 and 255 characters.' : 'Nonempty concise clinical phrase; aim within 400 characters.'),
                    $draft['properties'][$field]['description'],
                );
            }
            $this->assertSame(['id', 'p', 'pk', 'e', 'ek', 's', 'sk', 'r', 'n', 'en', 'sn'], $draft['required']);
            $this->assertStringContainsString('choices[domain]', $system);
            $this->assertStringContainsString('fact + why', $system);
            $this->assertStringNotContainsString('copy a short phrase', $system);
            $this->assertArrayNotHasKey('source_id', $draft['properties']);

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

        $this->mock(PesSuggestionService::class, function ($mock): void {
            $mock->shouldReceive('resolveDraft')->once()->andReturn([
                'domain' => 'NC', 'problem' => 'Unintended Weight Loss', 'label' => 'Unintended Weight Loss',
                'etiology' => 'decreased appetite', 'signs_symptoms' => '6.5% weight loss over 2 months',
                'pes_statement' => 'Unintended Weight Loss related to decreased appetite as evidenced by 6.5% weight loss over 2 months',
                'ai_generated' => true,
            ]);
        });

        $this->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-approve", [
            'draft_id' => fake()->uuid(),
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
        $this->assertSame(1, Activity::where('event', 'created')
            ->whereJsonContains('properties->details->subject_label', 'AI diagnosis')
            ->count());
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
            ->assertJsonValidationErrors(['draft_id']);
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
