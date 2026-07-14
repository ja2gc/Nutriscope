<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'Admin',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_admin_dashboard_returns_aggregated_kpis(): void
    {
        // Seed users by role
        User::factory()->count(3)->create(['role' => 'RND']);
        User::factory()->count(2)->create(['role' => 'FSS']);

        // Seed patients (count only — no names in response)
        Patient::factory()->count(5)->create();

        // Seed AI usage logs (no factory — create directly)
        $rnd = User::where('role', 'RND')->first();
        AiUsageLog::create([
            'user_id' => $rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 200,
            'tokens_output' => 100,
            'tokens_total' => 300,
            'endpoint' => 'diagnosis_suggestion',
        ]);
        AiUsageLog::create([
            'user_id' => $rnd->id,
            'model' => 'claude-haiku-4-5-20251001',
            'tokens_input' => 150,
            'tokens_output' => 80,
            'tokens_total' => 230,
            'endpoint' => 'monitoring_narrative',
        ]);

        // Seed audit activity
        $auditCountBefore = Activity::count();
        Activity::create([
            'log_name' => 'audit',
            'event' => 'created',
            'description' => 'Created patient',
            'causer_type' => User::class,
            'causer_id' => $rnd->id,
            'subject_type' => Patient::class,
            'subject_id' => 1,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'users' => ['total', 'by_role' => ['Admin', 'RND', 'FSS']],
                    'patients' => ['total'],
                    'ai_usage' => [
                        'total_calls',
                        'total_tokens',
                        'tokens_input',
                        'tokens_output',
                        'month_calls',
                        'month_tokens',
                        'by_endpoint',
                        'daily',
                    ],
                    'audit_logs' => ['total', 'last_7_days'],
                    'reports' => ['total'],
                ],
            ]);

        // Verify counts — 1 admin (setUp) + 3 RND + 2 FSS = 6
        $response->assertJsonPath('data.users.total', 6);
        $response->assertJsonPath('data.users.by_role.RND', 3);
        $response->assertJsonPath('data.users.by_role.FSS', 2);
        $response->assertJsonPath('data.users.by_role.Admin', 1);
        $response->assertJsonPath('data.patients.total', 5);
        $response->assertJsonPath('data.ai_usage.total_calls', 2);
        $response->assertJsonPath('data.ai_usage.total_tokens', 530);
        $response->assertJsonPath('data.ai_usage.tokens_input', 350);
        $response->assertJsonPath('data.ai_usage.tokens_output', 180);
        $response->assertJsonPath('data.ai_usage.month_calls', 2);
        $response->assertJsonPath('data.ai_usage.month_tokens', 530);
        $response->assertJsonPath('data.ai_usage.by_endpoint.diagnosis_suggestion.tokens', 300);
        $response->assertJsonPath('data.ai_usage.by_endpoint.monitoring_narrative.tokens', 230);
        $response->assertJsonPath('data.ai_usage.daily.0.date', now()->toDateString());
        $response->assertJsonPath('data.ai_usage.daily.0.calls', 2);
        $response->assertJsonPath('data.ai_usage.daily.0.tokens', 530);

        // AuditsChanges trait auto-logs Patient creates; assert at least our manual row
        $this->assertGreaterThanOrEqual(
            $auditCountBefore + 1,
            $response->json('data.audit_logs.total'),
        );
    }

    public function test_dashboard_response_is_cached(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->with('admin_dashboard', \Mockery::type('int'), \Mockery::type('callable'))
            ->andReturn([
                'users' => ['total' => 1, 'by_role' => ['Admin' => 1, 'RND' => 0, 'FSS' => 0]],
                'patients' => ['total' => 0],
                'ai_usage' => [
                    'total_calls' => 0,
                    'total_tokens' => 0,
                    'tokens_input' => 0,
                    'tokens_output' => 0,
                    'by_endpoint' => [],
                ],
                'audit_logs' => ['total' => 0, 'last_7_days' => 0],
                'reports' => ['total' => 0],
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertOk();
    }

    public function test_non_admin_cannot_access_dashboard(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_dashboard_contains_no_patient_identifying_data(): void
    {
        Patient::factory()->count(3)->create([
            'name' => 'Confidential Patient',
            'contact' => '09171234567',
            'medical_diagnosis' => 'Secret diagnosis',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertOk();

        $payload = json_encode($response->json(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('Confidential Patient', $payload);
        $this->assertStringNotContainsString('09171234567', $payload);
        $this->assertStringNotContainsString('Secret diagnosis', $payload);
    }
}
