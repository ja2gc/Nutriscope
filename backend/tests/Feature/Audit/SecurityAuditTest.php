<?php

namespace Tests\Feature\Audit;

use App\Models\AuditActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rate_limit_is_audited_as_failed_with_actor_ip(): void
    {
        $user = User::factory()->create([
            'email' => 'limited@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        foreach (range(1, 6) as $attempt) {
            $response = $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
                'platform' => 'web',
            ]);

            $attempt < 6 ? $response->assertUnauthorized() : $response->assertTooManyRequests();
        }

        $events = AuditActivity::query()->where('event', 'failed')->orderBy('id')->get();
        $this->assertCount(6, $events);
        $this->assertSame('Rate limit exceeded', data_get($events->last()->properties, 'details.subject_label'));
        $this->assertSame('127.0.0.1', data_get($events->last()->properties, 'ip'));
    }

    public function test_authentication_failure_is_audited_but_role_denial_is_not(): void
    {
        $this->withToken('SECRET-BEARER-SENTINEL')
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $rnd = User::factory()->create(['role' => 'RND']);
        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertForbidden();

        $events = AuditActivity::query()->where('event', 'failed')->get();
        $this->assertCount(1, $events);
        $this->assertSame('Authentication', data_get($events->first()->properties, 'details.subject_label'));
        $this->assertSame(0, AuditActivity::query()->where('event', 'authorization_denied')->count());
    }

    public function test_inactive_user_is_rejected_on_all_shared_authenticated_routes(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = $user->createToken('inactive')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertForbidden();
        $this->withToken($token)->patchJson('/api/auth/profile', [])->assertForbidden();
        $this->withToken($token)->getJson('/api/notifications')->assertForbidden();
    }
}
