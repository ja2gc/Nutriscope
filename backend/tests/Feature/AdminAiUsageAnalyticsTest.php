<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiUsageAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_month_view_uses_manila_boundaries_and_distinguishes_zero_from_future_days(): void
    {
        CarbonImmutable::setTestNow('2026-07-15 04:00:00 UTC');
        $admin = User::factory()->create(['role' => 'Admin']);
        $rnd = User::factory()->create(['role' => 'RND']);
        $this->log($admin, 10, 5, '2026-06-30 16:30:00');
        $this->log($admin, 20, 0, '2026-07-01 15:59:00');
        $this->log($admin, 30, 7, '2026-07-01 16:00:00');
        $this->log($rnd, 5, 0, '2026-06-30 17:00:00');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/ai-usage?view=month&year=2026&month=7')
            ->assertOk()
            ->assertJsonPath('timezone', 'Asia/Manila')
            ->assertJsonCount(31, 'points');

        $points = collect($response->json('points'))->keyBy('day');
        $this->assertSame(40, $points[1]['tokens']);
        $this->assertSame(35, $points[1]['tokens_input']);
        $this->assertSame(5, $points[1]['tokens_output']);
        $this->assertSame(37, $points[2]['tokens']);
        $this->assertSame(30, $points[2]['tokens_input']);
        $this->assertSame(7, $points[2]['tokens_output']);
        $this->assertSame(0, $points[3]['tokens']);
        $this->assertSame(0, $points[3]['tokens_input']);
        $this->assertSame(0, $points[3]['tokens_output']);
        $this->assertNull($points[16]['tokens']);
        $this->assertNull($points[16]['tokens_input']);
        $this->assertNull($points[16]['tokens_output']);
        $response->assertJsonPath('total_tokens', 77)
            ->assertJsonPath('total_tokens_input', 65)
            ->assertJsonPath('total_tokens_output', 12);
    }

    public function test_year_view_returns_every_month_and_total_usage(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->log($admin, 25, 5, '2026-01-10 00:00:00');
        $this->log($admin, 75, 10, '2026-12-10 00:00:00');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/ai-usage?view=year&year=2026')
            ->assertOk()
            ->assertJsonCount(12, 'points')
            ->assertJsonPath('total_tokens', 115)
            ->assertJsonPath('total_tokens_input', 100)
            ->assertJsonPath('total_tokens_output', 15);

        $points = collect($response->json('points'))->keyBy('month');
        $this->assertSame(30, $points[1]['tokens']);
        $this->assertSame(25, $points[1]['tokens_input']);
        $this->assertSame(5, $points[1]['tokens_output']);
        $this->assertSame(0, $points[2]['tokens']);
        $this->assertSame(85, $points[12]['tokens']);
        $this->assertSame(75, $points[12]['tokens_input']);
        $this->assertSame(10, $points[12]['tokens_output']);
    }

    public function test_query_validation_and_admin_authorization_are_enforced(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $rnd = User::factory()->create(['role' => 'RND']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/ai-usage?view=week&year=1999&month=13')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['view', 'year', 'month']);

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/admin/ai-usage?view=month&year=2026&month=7')
            ->assertForbidden();
    }

    private function log(User $user, int $inputTokens, int $outputTokens, string $createdAt): void
    {
        AiUsageLog::forceCreate([
            'user_id' => $user->id,
            'model' => 'test-model',
            'tokens_input' => $inputTokens,
            'tokens_output' => $outputTokens,
            'tokens_total' => $inputTokens + $outputTokens,
            'endpoint' => 'test',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
