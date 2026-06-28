<?php

namespace Tests\Feature;

use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create([
            'role'     => 'RND',
            'password' => Hash::make('password'),
        ]);

        // AI routes always run behind auth:sanctum in production, so the service
        // can rely on auth()->id() for the usage-log owner. Mirror that here.
        $this->actingAs($this->rnd, 'sanctum');
    }

    private function makeNcpRecord(): NcpRecord
    {
        $patient = Patient::factory()->create();
        return NcpRecord::factory()->create([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
    }

    // --- AIService unit tests ---

    public function test_ai_service_throws_on_api_failure(): void
    {
        // Previously the service swallowed failures and returned [], which made a
        // broken API key look like "no suggestions". It now surfaces the failure.
        Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);

        $this->expectException(\RuntimeException::class);

        app(AIService::class)->suggestDiagnoses(['conditions' => ['CKD']]);
    }

    public function test_ai_service_throws_on_connection_timeout(): void
    {
        Http::fake([
            'api.anthropic.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'),
        ]);

        $this->expectException(\RuntimeException::class);

        app(AIService::class)->suggestDiagnoses(['conditions' => ['CKD']]);
    }

    public function test_ai_service_returns_array_of_suggestions(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'suggestions' => [
                            [
                                'domain'    => 'NI',
                                'label'     => 'Inadequate energy intake',
                                'etiology'  => 'related to poor appetite',
                                'signs'     => 'evidenced by weight loss',
                            ]
                        ]
                    ]),
                ]],
            ], 200),
        ]);

        $service     = app(AIService::class);
        $suggestions = $service->suggestDiagnoses([
            'conditions' => ['CKD'],
            'ibw_percentage' => 75,
        ]);

        $this->assertIsArray($suggestions);
        $this->assertNotEmpty($suggestions);
        $this->assertArrayHasKey('domain', $suggestions[0]);
    }

    public function test_ai_service_throws_on_service_unavailable(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([], 503),
        ]);

        $this->expectException(\RuntimeException::class);

        app(AIService::class)->suggestDiagnoses(['conditions' => ['CKD']]);
    }

    public function test_ai_service_strips_markdown_fences_before_decoding(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => "```json\n".json_encode([
                        'suggestions' => [[
                            'domain' => 'NI', 'label' => 'Inadequate energy intake',
                            'etiology' => 'poor appetite', 'signs' => 'weight loss',
                        ]],
                    ])."\n```",
                ]],
            ], 200),
        ]);

        $suggestions = app(AIService::class)->suggestDiagnoses(['conditions' => ['CKD']]);

        $this->assertNotEmpty($suggestions);
        $this->assertSame('NI', $suggestions[0]['domain']);
    }

    public function test_ai_suggest_endpoint_returns_502_on_upstream_failure(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);

        $ncpRecord = $this->makeNcpRecord();

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/diagnoses/ai-suggest", [
                'conditions' => ['CKD'],
            ])
            ->assertStatus(502)
            ->assertJsonStructure(['message']);
    }

    public function test_ai_service_logs_usage_on_success(): void
    {
        Cache::put('admin_dashboard', ['stale' => true], 300);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode(['suggestions' => []]),
                ]],
                'usage' => ['input_tokens' => 120, 'output_tokens' => 50],
            ], 200),
        ]);

        $service = app(AIService::class);
        $service->suggestDiagnoses(['conditions' => ['DM']]);

        $this->assertDatabaseHas('ai_usage_logs', [
            'model'    => config('services.anthropic.model', 'claude-haiku-20240307'),
            'endpoint' => 'diagnosis_suggestion',
        ]);
        $this->assertFalse(Cache::has('admin_dashboard'));
    }

    // --- HTTP endpoint tests ---

    public function test_ai_suggest_diagnoses_returns_suggestions(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'suggestions' => [
                            [
                                'domain'   => 'NI',
                                'label'    => 'Inadequate energy intake',
                                'etiology' => 'related to poor appetite',
                                'signs'    => 'evidenced by weight loss',
                            ]
                        ]
                    ]),
                ]],
            ], 200),
        ]);

        $ncpRecord = $this->makeNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/diagnoses/ai-suggest", [
                'conditions'     => ['CKD'],
                'ibw_percentage' => 75,
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => [['domain', 'label', 'etiology', 'signs']]]);
    }

    public function test_ai_suggest_output_can_be_approved_and_tracks_token_usage(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'suggestions' => [
                            [
                                'domain' => 'NI',
                                'label' => 'Inadequate energy intake',
                                'etiology' => 'poor appetite',
                                'signs' => '5% weight loss over 1 month',
                                'confidence' => 0.86,
                                'reasoning' => 'Supported by recent weight loss and intake history.',
                                'priority' => 1,
                            ],
                        ],
                    ]),
                ]],
                'usage' => ['input_tokens' => 240, 'output_tokens' => 80],
            ], 200),
        ]);

        $ncpRecord = $this->makeNcpRecord();
        \App\Models\Assessment::forceCreate([
            'ncp_record_id' => $ncpRecord->id,
            'weight' => 70.0,
            'height' => 170.0,
        ]);

        $suggestResponse = $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/diagnoses/ai-suggest", [
                'conditions' => ['Poor appetite'],
                'ibw_percentage' => 75,
            ])
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Inadequate energy intake');

        $suggestion = $suggestResponse->json('data.0');

        $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/diagnoses/ai-approve", $suggestion)
            ->assertCreated()
            ->assertJsonPath('data.ai_generated', true)
            ->assertJsonPath(
                'data.pes_statement',
                'Inadequate energy intake related to poor appetite as evidenced by 5% weight loss over 1 month',
            );

        $this->assertDatabaseHas('diagnoses', [
            'ncp_record_id' => $ncpRecord->id,
            'label' => 'Inadequate energy intake',
            'ai_generated' => true,
        ]);

        $this->assertDatabaseHas('ai_usage_logs', [
            'user_id' => $this->rnd->id,
            'endpoint' => 'diagnosis_suggestion',
            'tokens_input' => 240,
            'tokens_output' => 80,
            'tokens_total' => 320,
        ]);
    }

    public function test_ai_suggest_diagnoses_requires_conditions(): void
    {
        $ncpRecord = $this->makeNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/diagnoses/ai-suggest", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['conditions']);
    }

    public function test_ai_approve_diagnosis_stores_to_database(): void
    {
        $ncpRecord = $this->makeNcpRecord();
        \App\Models\Assessment::forceCreate([
            'ncp_record_id' => $ncpRecord->id, 'weight' => 70.0, 'height' => 170.0,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/diagnoses/ai-approve", [
                'domain'   => 'NI',
                'label'    => 'Inadequate energy intake',
                'etiology' => 'related to poor appetite evidenced by food recall',
                'signs'    => 'weight loss 5% over 1 month',
                'priority' => 1,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.domain', 'NI')
            ->assertJsonPath('data.label', 'Inadequate energy intake');

        $this->assertDatabaseHas('diagnoses', [
            'ncp_record_id' => $ncpRecord->id,
            'domain'        => 'NI',
            'label'         => 'Inadequate energy intake',
        ]);
    }

    public function test_ai_approve_diagnosis_normalizes_pes_components_before_saving(): void
    {
        $ncpRecord = $this->makeNcpRecord();
        \App\Models\Assessment::forceCreate([
            'ncp_record_id' => $ncpRecord->id, 'weight' => 70.0, 'height' => 170.0,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/diagnoses/ai-approve", [
                'domain'   => 'NI',
                'label'    => 'Inadequate energy intake',
                'etiology' => 'related to poor appetite',
                'signs'    => 'as evidenced by 5% weight loss',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.problem', 'Inadequate energy intake')
            ->assertJsonPath('data.label', 'Inadequate energy intake')
            ->assertJsonPath('data.etiology', 'poor appetite')
            ->assertJsonPath('data.signs_symptoms', '5% weight loss')
            ->assertJsonPath('data.pes_statement', 'Inadequate energy intake related to poor appetite as evidenced by 5% weight loss')
            ->assertJsonPath('data.ai_generated', true);
    }

    public function test_ai_approve_diagnosis_requires_valid_domain(): void
    {
        $ncpRecord = $this->makeNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/diagnoses/ai-approve", [
                'domain'   => 'INVALID',
                'label'    => 'Test',
                'etiology' => 'related to X',
                'signs'    => 'evidenced by Y',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);
    }

    public function test_ai_approve_diagnosis_rejects_problem_labels_longer_than_manual_builder_allows(): void
    {
        $ncpRecord = $this->makeNcpRecord();
        \App\Models\Assessment::forceCreate([
            'ncp_record_id' => $ncpRecord->id, 'weight' => 70.0, 'height' => 170.0,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/diagnoses/ai-approve", [
                'domain'   => 'NI',
                'label'    => str_repeat('A', 256),
                'etiology' => 'related to poor appetite',
                'signs'    => 'evidenced by low intake',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['label']);
    }
}
