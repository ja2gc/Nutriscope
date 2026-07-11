<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_generic_response_and_sends_link_when_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'rnd@nutriscope.local',
            'recovery_email' => 'rnd@example.com',
            'recovery_email_verified_at' => now(),
        ]);

        $this->postJson('/api/auth/forgot-password', ['email' => 'rnd@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If that email exists, a password reset link has been sent.');

        $this->postJson('/api/auth/forgot-password', ['email' => 'rnd@nutriscope.local'])
            ->assertOk()
            ->assertJsonPath('message', 'If that email exists, a password reset link has been sent.');

        $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If that email exists, a password reset link has been sent.');

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_forgot_password_ignores_unverified_recovery_email(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'rnd@nutriscope.local',
            'recovery_email' => 'rnd@example.com',
            'recovery_email_verified_at' => null,
        ]);

        $this->postJson('/api/auth/forgot-password', ['email' => 'rnd@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If that email exists, a password reset link has been sent.');

        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_changes_password_revokes_tokens_and_writes_audit_event(): void
    {
        $user = User::factory()->create([
            'email' => 'rnd@nutriscope.local',
            'recovery_email' => 'rnd@example.com',
            'recovery_email_verified_at' => now(),
            'password' => Hash::make('old-password'),
        ]);
        $user->createToken('browser');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'rnd@example.com',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $activity = Activity::where('event', 'password_reset')->where('causer_id', $user->id)->first();
        $this->assertNotNull($activity);
        $this->assertSame($activity->properties['request']['ip'], $activity->properties['ip']);
        $this->assertSame($activity->properties['request']['user_agent'], $activity->properties['user_agent']);
    }
}
