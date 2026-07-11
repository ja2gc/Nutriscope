<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Auth\RecoveryEmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RecoveryEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_recovery_email_verification_code(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'rnd@nutriscope.local',
            'password' => Hash::make('password'),
            'recovery_email' => null,
            'recovery_email_verified_at' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/recovery-email', [
                'recovery_email' => 'JaredAbriol2@gmail.com',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Verification code sent.')
            ->assertJsonPath('user.recovery_email', 'jaredabriol2@gmail.com')
            ->assertJsonPath('user.recovery_email_verified', false);

        $user->refresh();

        $this->assertSame('jaredabriol2@gmail.com', $user->recovery_email);
        $this->assertNull($user->recovery_email_verified_at);
        $this->assertNotNull($user->recovery_email_verification_code);
        $this->assertNotNull($user->recovery_email_verification_expires_at);

        Notification::assertSentOnDemand(RecoveryEmailVerification::class, function (
            RecoveryEmailVerification $notification,
            array $channels,
            object $notifiable,
        ) {
            return $notifiable->routes['mail'] === 'jaredabriol2@gmail.com'
                && $notification->code !== '';
        });
        $this->assertSame(1, Activity::where('event', 'recovery_email_changed')->count());
    }

    public function test_user_can_verify_recovery_email_code(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'rnd@nutriscope.local',
            'recovery_email' => null,
            'recovery_email_verified_at' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/recovery-email', [
                'recovery_email' => 'jaredabriol2@gmail.com',
            ])
            ->assertOk();

        $code = null;
        Notification::assertSentOnDemand(RecoveryEmailVerification::class, function (RecoveryEmailVerification $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/auth/recovery-email/verify', [
                'code' => $code,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Recovery email verified.')
            ->assertJsonPath('user.recovery_email_verified', true);

        $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/auth/recovery-email/verify', ['code' => $code])
            ->assertUnprocessable();

        $user->refresh();

        $this->assertNotNull($user->recovery_email_verified_at);
        $this->assertNull($user->recovery_email_verification_code);
        $this->assertNull($user->recovery_email_verification_expires_at);
        $this->assertSame(1, Activity::where('event', 'recovery_email_verified')->count());
        $this->assertSame($user->uuid, Activity::where('event', 'recovery_email_verified')->sole()->properties['details']['subject_public_id']);
        $this->assertStringNotContainsString((string) $code, Activity::query()->get()->toJson());
    }

    public function test_recovery_email_cannot_match_another_users_login_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'rnd@nutriscope.local']);
        User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/recovery-email', [
                'recovery_email' => 'owner@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recovery_email']);

        Notification::assertNothingSent();
    }
}
