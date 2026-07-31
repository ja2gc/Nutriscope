<?php

namespace Tests\Feature;

use App\Exceptions\TokenLimitExceededException;
use App\Models\AiUsageLimit;
use App\Models\AiUsageLog;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiUsageLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rnd;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'Admin',
            'password' => Hash::make('password'),
        ]);
        $this->rnd = User::factory()->create([
            'role' => 'RND',
            'password' => Hash::make('password'),
        ]);
        $this->fss = User::factory()->create([
            'role' => 'FSS',
            'password' => Hash::make('password'),
        ]);

        // Default actor for direct AIService calls (production runs behind
        // auth:sanctum); per-test actingAs() overrides this for endpoint tests.
        $this->actingAs($this->rnd, 'sanctum');
    }

    // -------------------------------------------------------------------------
    // Admin GET /api/admin/ai-usage-limits
    // -------------------------------------------------------------------------

    public function test_admin_can_get_limits_with_zero_usage(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/ai-usage-limits');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'daily_token_limit',
                    'monthly_token_limit',
                    'cost_per_1m_tokens_usd',
                    'daily_used',
                    'monthly_used',
                ],
            ])
            ->assertJsonPath('data.daily_token_limit', null)
            ->assertJsonPath('data.monthly_token_limit', null)
            ->assertJsonPath('data.cost_per_1m_tokens_usd', 1.92)
            ->assertJsonPath('data.daily_used', 0)
            ->assertJsonPath('data.monthly_used', 0);
    }

    public function test_admin_get_limits_reflects_seeded_usage_logs(): void
    {
        // Seed today + this month usage
        AiUsageLog::create([
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 100,
            'tokens_output' => 50,
            'tokens_total' => 150,
            'endpoint' => 'diagnosis_suggestion',
            'created_at' => now(),
        ]);
        AiUsageLog::create([
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 200,
            'tokens_output' => 100,
            'tokens_total' => 300,
            'endpoint' => 'monitoring_narrative',
            'created_at' => now(),
        ]);
        // Old log from last month — should NOT count in daily or monthly
        AiUsageLog::forceCreate([
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 999,
            'tokens_output' => 999,
            'tokens_total' => 1998,
            'endpoint' => 'diagnosis_suggestion',
            'created_at' => now()->subMonthNoOverflow()->startOfMonth(),
            'updated_at' => now()->subMonthNoOverflow()->startOfMonth(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/ai-usage-limits');

        $response->assertOk()
            ->assertJsonPath('data.daily_used', 450)
            ->assertJsonPath('data.monthly_used', 450);
    }

    public function test_non_admin_cannot_get_limits(): void
    {
        $response = $this->actingAs($this->rnd, 'sanctum')
            ->getJson('/api/admin/ai-usage-limits');

        $response->assertForbidden();
    }

    public function test_fss_cannot_get_limits(): void
    {
        $response = $this->actingAs($this->fss, 'sanctum')
            ->getJson('/api/admin/ai-usage-limits');

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Admin PUT /api/admin/ai-usage-limits
    // -------------------------------------------------------------------------

    public function test_admin_can_set_daily_and_monthly_limits(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/ai-usage-limits', [
                'daily_token_limit' => 10000,
                'monthly_token_limit' => 200000,
                'cost_per_1m_tokens_usd' => 2.50,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.daily_token_limit', 10000)
            ->assertJsonPath('data.monthly_token_limit', 200000)
            ->assertJsonPath('data.cost_per_1m_tokens_usd', 2.50);

        $this->assertDatabaseHas('ai_usage_limits', [
            'daily_token_limit' => 10000,
            'monthly_token_limit' => 200000,
            'cost_per_1m_tokens_usd' => 2.50,
        ]);
    }

    public function test_admin_can_clear_limits_by_sending_null(): void
    {
        AiUsageLimit::current()->update([
            'daily_token_limit' => 5000,
            'monthly_token_limit' => 100000,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/ai-usage-limits', [
                'daily_token_limit' => null,
                'monthly_token_limit' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.daily_token_limit', null)
            ->assertJsonPath('data.monthly_token_limit', null);
    }

    public function test_admin_put_validates_non_negative_integer(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/ai-usage-limits', [
                'daily_token_limit' => -1,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['daily_token_limit']);
    }

    public function test_admin_put_validates_non_negative_cost_rate(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/ai-usage-limits', [
                'cost_per_1m_tokens_usd' => -0.01,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cost_per_1m_tokens_usd']);
    }

    public function test_rnd_cannot_set_limits(): void
    {
        $response = $this->actingAs($this->rnd, 'sanctum')
            ->putJson('/api/admin/ai-usage-limits', [
                'daily_token_limit' => 1000,
            ]);

        $response->assertForbidden();
    }

    public function test_fss_cannot_set_limits(): void
    {
        $response = $this->actingAs($this->fss, 'sanctum')
            ->putJson('/api/admin/ai-usage-limits', [
                'daily_token_limit' => 1000,
            ]);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Enforcement: null caps = unlimited
    // -------------------------------------------------------------------------

    public function test_null_caps_allow_ai_service_to_proceed(): void
    {
        // Ensure limits row exists with null caps
        AiUsageLimit::current(); // creates the singleton with nulls

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode(['suggestions' => []])]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200),
        ]);

        $result = app(AIService::class)->suggestDiagnoses(['conditions' => ['DM']]);

        // Should not throw — returns array (possibly empty)
        $this->assertIsArray($result);
        // Usage was logged (call was made)
        $this->assertDatabaseHas('ai_usage_logs', ['endpoint' => 'diagnosis_suggestion']);
    }

    // -------------------------------------------------------------------------
    // Enforcement: daily cap hit
    // -------------------------------------------------------------------------

    public function test_daily_cap_blocks_ai_call_and_returns_429(): void
    {
        AiUsageLimit::current()->update(['daily_token_limit' => 100]);

        // Seed today's usage at the cap
        AiUsageLog::create([
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 60,
            'tokens_output' => 40,
            'tokens_total' => 100,
            'endpoint' => 'diagnosis_suggestion',
            'created_at' => now(),
        ]);

        Http::fake(); // Should NOT be called

        $this->expectException(TokenLimitExceededException::class);

        app(AIService::class)->suggestDiagnoses(['conditions' => ['DM']]);
    }

    public function test_daily_cap_blocked_call_does_not_log_usage(): void
    {
        AiUsageLimit::current()->update(['daily_token_limit' => 50]);

        AiUsageLog::create([
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 30,
            'tokens_output' => 20,
            'tokens_total' => 50,
            'endpoint' => 'diagnosis_suggestion',
            'created_at' => now(),
        ]);

        $countBefore = AiUsageLog::count();

        Http::fake();

        try {
            app(AIService::class)->suggestDiagnoses(['conditions' => ['DM']]);
        } catch (TokenLimitExceededException $e) {
            // expected
        }

        $this->assertSame($countBefore, AiUsageLog::count());
    }

    public function test_daily_cap_exception_renders_as_429_with_json_message(): void
    {
        AiUsageLimit::current()->update(['daily_token_limit' => 100]);

        AiUsageLog::create([
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 60,
            'tokens_output' => 40,
            'tokens_total' => 100,
            'endpoint' => 'diagnosis_suggestion',
            'created_at' => now(),
        ]);

        Http::fake();

        // Hit the HTTP layer via the RND route to confirm 429 is rendered
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);

        $response = $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest", [
                'conditions' => ['CKD'],
            ]);

        $response->assertStatus(429)
            ->assertJsonStructure(['message'])
            ->assertJsonPath('message', fn ($msg) => str_contains(strtolower($msg), 'daily'));
    }

    // -------------------------------------------------------------------------
    // Enforcement: monthly cap hit
    // -------------------------------------------------------------------------

    public function test_monthly_cap_blocks_ai_call(): void
    {
        AiUsageLimit::current()->update(['monthly_token_limit' => 200]);

        // Month-to-date usage at cap (all this month, none today)
        AiUsageLog::forceCreate([
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 120,
            'tokens_output' => 80,
            'tokens_total' => 200,
            'endpoint' => 'diagnosis_suggestion',
            'created_at' => now()->startOfMonth()->addDays(1),
            'updated_at' => now()->startOfMonth()->addDays(1),
        ]);

        Http::fake();

        $this->expectException(TokenLimitExceededException::class);

        app(AIService::class)->suggestDiagnoses(['conditions' => ['DM']]);
    }

    public function test_monthly_cap_exception_message_names_monthly(): void
    {
        AiUsageLimit::current()->update(['monthly_token_limit' => 200]);

        AiUsageLog::forceCreate([
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 120,
            'tokens_output' => 80,
            'tokens_total' => 200,
            'endpoint' => 'diagnosis_suggestion',
            'created_at' => now()->startOfMonth()->addDays(1),
            'updated_at' => now()->startOfMonth()->addDays(1),
        ]);

        Http::fake();

        try {
            app(AIService::class)->suggestDiagnoses(['conditions' => ['DM']]);
            $this->fail('Expected TokenLimitExceededException');
        } catch (TokenLimitExceededException $e) {
            $this->assertStringContainsStringIgnoringCase('monthly', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Under cap — call proceeds
    // -------------------------------------------------------------------------

    public function test_under_daily_cap_call_proceeds(): void
    {
        AiUsageLimit::current()->update(['daily_token_limit' => 1000]);

        // Seed 50 tokens used today — well under cap
        AiUsageLog::create([
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 30,
            'tokens_output' => 20,
            'tokens_total' => 50,
            'endpoint' => 'diagnosis_suggestion',
            'created_at' => now(),
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode(['suggestions' => []])]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200),
        ]);

        $result = app(AIService::class)->suggestDiagnoses(['conditions' => ['DM']]);

        $this->assertIsArray($result);
        // A new usage row was written
        $this->assertSame(2, AiUsageLog::count());
    }

    public function test_under_monthly_cap_call_proceeds(): void
    {
        AiUsageLimit::current()->update(['monthly_token_limit' => 5000]);

        // 100 tokens used this month
        AiUsageLog::forceCreate([
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 60,
            'tokens_output' => 40,
            'tokens_total' => 100,
            'endpoint' => 'diagnosis_suggestion',
            'created_at' => now()->startOfMonth()->addDays(2),
            'updated_at' => now()->startOfMonth()->addDays(2),
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode(['suggestions' => []])]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200),
        ]);

        $result = app(AIService::class)->suggestDiagnoses(['conditions' => ['CKD']]);

        $this->assertIsArray($result);
        $this->assertSame(2, AiUsageLog::count());
    }
}
