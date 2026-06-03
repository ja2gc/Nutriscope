<?php

namespace Tests\Feature;

use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_ai_service_returns_empty_array_on_api_failure(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([], 503),
        ]);

        $service     = app(AIService::class);
        $suggestions = $service->suggestDiagnoses(['conditions' => ['CKD']]);

        $this->assertIsArray($suggestions);
        $this->assertEmpty($suggestions);
    }

    public function test_ai_service_logs_usage_on_success(): void
    {
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
}
