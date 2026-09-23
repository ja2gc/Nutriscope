<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Auth\RecoveryEmailVerification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RecoveryEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_remove_a_verified_recovery_email(): void
    {
        $user = User::factory()->create([
            'recovery_email' => 'verified@example.com',
            'pending_recovery_email' => 'replacement@example.com',
            'recovery_email_verified_at' => now(),
            'recovery_email_verification_code' => Hash::make('123456'),
            'recovery_email_verification_expires_at' => now()->addMinutes(10),
            'must_set_recovery_email' => false,
            'onboarding_skipped_at' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/auth/recovery-email')
            ->assertOk()
            ->assertJsonPath('message', 'Recovery email removed.')
            ->assertJsonPath('user.recovery_email', null)
            ->assertJsonPath('user.pending_recovery_email', null)
            ->assertJsonPath('user.recovery_email_verified', false)
            ->assertJsonPath('user.must_set_recovery_email', true)
            ->assertJsonPath('user.onboarding_skipped', true);

        $user->refresh();
        $this->assertNull($user->recovery_email);
        $this->assertNull($user->pending_recovery_email);
        $this->assertNull($user->recovery_email_verified_at);
        $this->assertNull($user->recovery_email_verification_code);
        $this->assertNull($user->recovery_email_verification_expires_at);
        $this->assertTrue($user->must_set_recovery_email);
        $this->assertNotNull($user->onboarding_skipped_at);
        $this->assertStringNotContainsString('verified@example.com', Activity::query()->get()->toJson());
    }

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
        $this->assertStringNotContainsString('jaredabriol2@gmail.com', Activity::query()->get()->toJson());
    }

    public function test_expired_recovery_email_code_fails_without_verifying_address(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'rnd@nutriscope.local',
            'recovery_email' => null,
            'recovery_email_verified_at' => null,
        ]);

        $this->actingAs($user, 'sanctum')->patchJson('/api/auth/recovery-email', [
            'recovery_email' => 'expired@example.com',
        ])->assertOk();

        $code = null;
        Notification::assertSentOnDemand(RecoveryEmailVerification::class, function (RecoveryEmailVerification $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        $user->forceFill(['recovery_email_verification_expires_at' => now()->subSecond()])->save();

        $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/auth/recovery-email/verify', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid or expired verification code.');

        $this->assertNull($user->fresh()->recovery_email_verified_at);
    }

    public function test_recovery_email_can_match_another_users_login_email_but_still_requires_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'rnd@nutriscope.local']);
        User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/recovery-email', [
                'recovery_email' => 'owner@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('user.recovery_email', 'owner@example.com')
            ->assertJsonPath('user.recovery_email_verified', false);

        Notification::assertSentOnDemand(
            RecoveryEmailVerification::class,
            fn (RecoveryEmailVerification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'owner@example.com',
        );
    }

    public function test_verified_recovery_email_stays_linked_until_replacement_is_verified(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'recovery_email' => 'old@example.com',
            'recovery_email_verified_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')->patchJson('/api/auth/recovery-email', [
            'recovery_email' => 'new@example.com',
        ])->assertOk()
            ->assertJsonPath('user.recovery_email', 'old@example.com')
            ->assertJsonPath('user.pending_recovery_email', 'new@example.com')
            ->assertJsonPath('user.recovery_email_verified', true);

        $code = null;
        Notification::assertSentOnDemand(RecoveryEmailVerification::class, function (RecoveryEmailVerification $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->actingAs($user->fresh(), 'sanctum')->postJson('/api/auth/recovery-email/verify', ['code' => '000000'])
            ->assertUnprocessable();
        $this->assertSame('old@example.com', $user->fresh()->recovery_email);

        $this->actingAs($user->fresh(), 'sanctum')->postJson('/api/auth/recovery-email/verify', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.recovery_email', 'new@example.com')
            ->assertJsonPath('user.pending_recovery_email', null);
    }

    public function test_multiple_users_can_verify_the_same_recovery_email_independently(): void
    {
        Notification::fake();
        $first = User::factory()->create([
            'recovery_email' => 'first-old@example.com',
            'recovery_email_verified_at' => now(),
        ]);
        $second = User::factory()->create([
            'recovery_email' => 'second-old@example.com',
            'recovery_email_verified_at' => now(),
        ]);

        $this->actingAs($first, 'sanctum')->patchJson('/api/auth/recovery-email', [
            'recovery_email' => 'reserved@example.com',
        ])->assertOk();

        $this->actingAs($second, 'sanctum')->patchJson('/api/auth/recovery-email', [
            'recovery_email' => 'reserved@example.com',
        ])->assertOk()
            ->assertJsonPath('user.pending_recovery_email', 'reserved@example.com');

        Notification::assertSentOnDemandTimes(RecoveryEmailVerification::class, 2);

        $codes = Notification::sent(new AnonymousNotifiable, RecoveryEmailVerification::class)
            ->map(fn (RecoveryEmailVerification $notification): string => $notification->code)
            ->values();

        $this->actingAs($first->fresh(), 'sanctum')
            ->postJson('/api/auth/recovery-email/verify', ['code' => $codes[0]])
            ->assertOk()
            ->assertJsonPath('user.recovery_email', 'reserved@example.com')
            ->assertJsonPath('user.recovery_email_verified', true);

        $this->actingAs($second->fresh(), 'sanctum')
            ->postJson('/api/auth/recovery-email/verify', ['code' => $codes[1]])
            ->assertOk()
            ->assertJsonPath('user.recovery_email', 'reserved@example.com')
            ->assertJsonPath('user.recovery_email_verified', true);
    }

    public function test_failed_delivery_returns_a_clear_error_without_staging_the_address(): void
    {
        $user = User::factory()->create([
            'recovery_email' => null,
            'recovery_email_verified_at' => null,
        ]);

        $notificationDispatcher = \Mockery::mock(Dispatcher::class);
        $notificationDispatcher->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('SMTP delivery failed'));
        $this->app->instance(Dispatcher::class, $notificationDispatcher);

        $this->actingAs($user, 'sanctum')->patchJson('/api/auth/recovery-email', [
            'recovery_email' => 'unreachable@example.com',
        ])->assertStatus(503)
            ->assertJsonPath('message', 'Verification code could not be sent. Check the address and try again.');

        $user->refresh();
        $this->assertNull($user->recovery_email);
        $this->assertNull($user->recovery_email_verification_code);
        $this->assertNull($user->recovery_email_verification_expires_at);
        $this->assertSame(0, Activity::where('event', 'recovery_email_changed')->count());
    }
}
