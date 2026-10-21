<?php

namespace Tests\Feature;

use App\Exceptions\TokenLimitExceededException;
use App\Models\AiUsageLimit;
use App\Models\AiUsageLog;
use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\AIService;
use Carbon\Carbon;
use Database\Seeders\AiUsageLimitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
                    'input_cost_per_1m_tokens_usd',
                    'output_cost_per_1m_tokens_usd',
                    'daily_used',
                    'daily_tokens_input',
                    'daily_tokens_output',
                    'monthly_used',
                    'monthly_tokens_input',
                    'monthly_tokens_output',
                ],
            ])
            ->assertJsonPath('data.daily_token_limit', null)
            ->assertJsonPath('data.monthly_token_limit', null)
            ->assertJsonPath('data.input_cost_per_1m_tokens_usd', 0.1)
            ->assertJsonPath('data.output_cost_per_1m_tokens_usd', 0.5)
            ->assertJsonPath('data.daily_used', 0)
            ->assertJsonPath('data.daily_tokens_input', 0)
            ->assertJsonPath('data.daily_tokens_output', 0)
            ->assertJsonPath('data.monthly_used', 0)
            ->assertJsonPath('data.monthly_tokens_input', 0)
            ->assertJsonPath('data.monthly_tokens_output', 0);
    }

    public function test_demo_rate_seeding_is_repeatable_with_haiku_55_prices(): void
    {
        $this->seed(AiUsageLimitSeeder::class);
        $this->seed(AiUsageLimitSeeder::class);
        $this->assertDatabaseCount('ai_usage_limits', 1);
        $this->assertDatabaseCount('ai_usage_logs', 0);
        $limits = AiUsageLimit::current();
        $this->assertSame(0.1, $limits->input_cost_per_1m_tokens_usd);
        $this->assertSame(0.5, $limits->output_cost_per_1m_tokens_usd);
        $this->assertSame(35_000, $limits->daily_token_limit);
        $this->assertSame(1_000_000, $limits->monthly_token_limit);
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
            ->assertJsonPath('data.daily_tokens_input', 300)
            ->assertJsonPath('data.daily_tokens_output', 150)
            ->assertJsonPath('data.monthly_used', 450)
            ->assertJsonPath('data.monthly_tokens_input', 300)
            ->assertJsonPath('data.monthly_tokens_output', 150);
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
                'input_cost_per_1m_tokens_usd' => 1.25,
                'output_cost_per_1m_tokens_usd' => 5.75,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.daily_token_limit', 10000)
            ->assertJsonPath('data.monthly_token_limit', 200000)
            ->assertJsonPath('data.input_cost_per_1m_tokens_usd', 1.25)
            ->assertJsonPath('data.output_cost_per_1m_tokens_usd', 5.75);

        $this->assertDatabaseHas('ai_usage_limits', [
            'daily_token_limit' => 10000,
            'monthly_token_limit' => 200000,
            'input_cost_per_1m_tokens_usd' => 1.25,
            'output_cost_per_1m_tokens_usd' => 5.75,
        ]);
    }

    public function test_changing_rates_preserves_past_cost_and_prices_future_usage(): void
    {
        AiUsageLimit::current()->update([
            'input_cost_per_1m_tokens_usd' => 1,
            'output_cost_per_1m_tokens_usd' => 5,
        ]);

        $usage = [
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 1000,
            'tokens_output' => 200,
            'tokens_total' => 1200,
            'endpoint' => 'diagnosis_suggestion',
        ];
        $past = AiUsageLog::create($usage);

        $this->actingAs($this->admin, 'sanctum')->putJson('/api/admin/ai-usage-limits', [
            'input_cost_per_1m_tokens_usd' => 0.1,
            'output_cost_per_1m_tokens_usd' => 0.5,
        ])->assertOk();

        $future = AiUsageLog::create([...$usage, 'model' => 'claude-haiku-5-5']);
        $this->assertSame('claude-haiku-4-5-20251001', $past->fresh()->model);
        $this->assertSame('claude-haiku-5-5', $future->model);
        $this->assertEqualsWithDelta(0.002, (float) $past->fresh()->estimated_cost_usd, 0.0000000001);
        $this->assertEqualsWithDelta(0.0002, (float) $future->estimated_cost_usd, 0.0000000001);

        $response = $this->getJson('/api/admin/ai-usage-limits')->assertOk();
        $this->assertEqualsWithDelta(0.0022, $response->json('data.daily_estimated_cost_usd'), 0.0000000001);
        $this->assertEqualsWithDelta(0.0022, $response->json('data.monthly_estimated_cost_usd'), 0.0000000001);
    }

    public function test_manila_daily_and_monthly_totals_combine_saved_rates_across_utc_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 02:00:00 UTC'));
        AiUsageLimit::current()->update(['input_cost_per_1m_tokens_usd' => 1, 'output_cost_per_1m_tokens_usd' => 5]);
        $usage = ['user_id' => $this->rnd->id, 'model' => 'claude-haiku-4-5-20251001', 'endpoint' => 'diagnosis_suggestion',
            'tokens_input' => 1000, 'tokens_output' => 200, 'tokens_total' => 1200];
        foreach (['2026-10-31 15:59:00', '2026-10-31 17:00:00'] as $timestamp) {
            AiUsageLog::forceCreate([...$usage, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
        }
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/admin/ai-usage-limits', [
            'input_cost_per_1m_tokens_usd' => 0.1, 'output_cost_per_1m_tokens_usd' => 0.5,
        ])->assertOk();
        AiUsageLog::forceCreate([...$usage, 'model' => 'claude-haiku-5-5', 'created_at' => '2026-11-01 01:00:00', 'updated_at' => '2026-11-01 01:00:00']);
        $response = $this->getJson('/api/admin/ai-usage-limits')->assertOk()
            ->assertJsonPath('data.daily_used', 2400)->assertJsonPath('data.daily_tokens_input', 2000)
            ->assertJsonPath('data.daily_tokens_output', 400)->assertJsonPath('data.monthly_used', 2400);
        $this->assertEqualsWithDelta(0.0022, $response->json('data.daily_estimated_cost_usd'), 0.0000000001);
        $this->assertEqualsWithDelta(0.0022, $response->json('data.monthly_estimated_cost_usd'), 0.0000000001);
        $dashboard = $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('data.ai_usage.month_tokens', 2400);
        $this->assertEqualsWithDelta(0.0022, $dashboard->json('data.ai_usage.month_estimated_cost_usd'), 0.0000000001);
        $point = collect($dashboard->json('data.ai_usage.daily'))->firstWhere('date', '2026-11-01');
        $this->assertSame(2400, $point['tokens']);

        Http::fake();
        foreach (['daily' => 'Daily', 'monthly' => 'Monthly'] as $period => $label) {
            AiUsageLimit::current()->update(['daily_token_limit' => null, 'monthly_token_limit' => null, $period.'_token_limit' => 2000]);
            try {
                app(AIService::class)->draftPes($this->pesPayload());
                $this->fail('Philippine-period usage must enforce the global cap.');
            } catch (TokenLimitExceededException $exception) {
                $this->assertStringContainsString($label, $exception->getMessage());
            }
        }
        Http::assertNothingSent();
    }

    public function test_backfill_freezes_existing_usage_at_the_saved_rates(): void
    {
        AiUsageLimit::current()->update([
            'input_cost_per_1m_tokens_usd' => 2,
            'output_cost_per_1m_tokens_usd' => 4,
        ]);
        $past = AiUsageLog::create([
            'user_id' => $this->rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 1000,
            'tokens_output' => 200,
            'tokens_total' => 1200,
            'endpoint' => 'diagnosis_suggestion',
        ]);
        DB::table('ai_usage_logs')->where('id', $past->id)->update(['estimated_cost_usd' => null]);

        $migrationFiles = glob(database_path('migrations/*_backfill_ai_usage_estimated_cost.php'));
        $this->assertCount(1, $migrationFiles);
        $migration = require $migrationFiles[0];
        $migration->up();
        $this->assertEqualsWithDelta(0.0028, (float) $past->fresh()->estimated_cost_usd, 0.0000000001);

        AiUsageLimit::current()->update([
            'input_cost_per_1m_tokens_usd' => 0.1,
            'output_cost_per_1m_tokens_usd' => 0.5,
        ]);
        $migration->up();
        $this->assertEqualsWithDelta(0.0028, (float) $past->fresh()->estimated_cost_usd, 0.0000000001);
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

    public function test_admin_put_validates_non_negative_cost_rates(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/ai-usage-limits', [
                'input_cost_per_1m_tokens_usd' => -0.01,
                'output_cost_per_1m_tokens_usd' => -0.01,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'input_cost_per_1m_tokens_usd',
                'output_cost_per_1m_tokens_usd',
            ]);
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

        $result = app(AIService::class)->draftPes($this->pesPayload());

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

        app(AIService::class)->draftPes($this->pesPayload());
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
            app(AIService::class)->draftPes($this->pesPayload());
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
        Assessment::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'weight_loss_percentage' => 6.5,
            'weight_change_period_value' => 2,
            'weight_change_period_unit' => 'months',
            'appetite_changes' => 'decreased',
        ]);

        $response = $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/diagnoses/ai-suggest");

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

        app(AIService::class)->draftPes($this->pesPayload());
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
            app(AIService::class)->draftPes($this->pesPayload());
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

        $result = app(AIService::class)->draftPes($this->pesPayload());

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

        $result = app(AIService::class)->draftPes($this->pesPayload());

        $this->assertIsArray($result);
        $this->assertSame(2, AiUsageLog::count());
    }

    private function pesPayload(): array
    {
        return [
            'catalog_version' => '2026-09-21-v1',
            'candidates' => [[
                'candidate_id' => 'unintended_weight_loss_v1',
                'domain' => 'NC',
                'problem_key' => 'Unintended Weight Loss',
                'evidence' => [
                    'weight_loss_percentage' => 6.5,
                    'weight_change_period' => ['value' => 2, 'unit' => 'months'],
                ],
                'source_id' => 'academy_ncp_diagnosis_2026',
            ]],
        ];
    }

    public function test_admin_limits_reject_values_above_supported_bounds(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/ai-usage-limits', [
                'daily_token_limit' => 100000001,
                'input_cost_per_1m_tokens_usd' => 1000.0001,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'daily_token_limit',
                'input_cost_per_1m_tokens_usd',
            ]);
    }
}
