<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuthAuditEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_and_failure_write_audit_events_without_passwords(): void
    {
        $user = User::factory()->create([
            'email' => 'rnd@example.com',
            'role' => 'RND',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'rnd@example.com',
            'password' => 'wrong-password',
            'platform' => 'web',
        ])->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => 'rnd@example.com',
            'password' => 'correct-password',
            'platform' => 'web',
            'device_name' => 'browser',
        ])->assertOk();

        $failed = Activity::where('event', 'login_failed')->latest()->first();
        $login = Activity::where('event', 'login')->latest()->first();

        $this->assertNotNull($failed);
        $this->assertNotNull($login);
        $this->assertSame($user->id, $login->causer_id);

        $payload = json_encode(Activity::all()->pluck('properties'), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('wrong-password', $payload);
        $this->assertStringNotContainsString('correct-password', $payload);
    }

    public function test_logout_and_password_change_write_audit_events_and_revoke_tokens(): void
    {
        $user = User::factory()->create([
            'role' => 'RND',
            'password' => Hash::make('old-password'),
        ]);
        $token = $user->createToken('browser')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'browser',
        ]);
        $this->assertNotNull(Activity::where('event', 'logout')->where('causer_id', $user->id)->first());

        $user->createToken('browser');
        $user->createToken('mobile');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotNull(Activity::where('event', 'password_changed')->where('causer_id', $user->id)->first());
    }
}
