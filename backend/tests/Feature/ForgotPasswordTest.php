<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_MESSAGE = 'Password reset request submitted.';

    public function test_sign_in_email_sends_reset_link_to_verified_recovery_email(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'rnd@nutriscope.local',
            'recovery_email' => 'rnd@example.com',
            'recovery_email_verified_at' => now(),
        ]);

        $this->postJson('/api/auth/forgot-password', ['email' => 'RND@NUTRISCOPE.LOCAL'])
            ->assertOk()
            ->assertJsonPath('message', self::GENERIC_MESSAGE);

        Notification::assertSentTo($user, ResetPassword::class, function (
            ResetPassword $notification,
            array $channels,
            User $notifiable,
        ): bool {
            parse_str((string) parse_url((string) $notification->toMail($notifiable)->actionUrl, PHP_URL_QUERY), $query);

            return $channels === ['mail']
                && $notifiable->routeNotificationFor('mail', $notification) === 'rnd@example.com'
                && ($query['email'] ?? null) === 'rnd@nutriscope.local';
        });

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'rnd@nutriscope.local']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'rnd@example.com']);
    }

    public function test_recovery_email_does_not_identify_account_for_forgot_password(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'rnd@nutriscope.local',
            'recovery_email' => 'rnd@example.com',
            'recovery_email_verified_at' => now(),
        ]);

        $this->postJson('/api/auth/forgot-password', ['email' => 'rnd@example.com'])
            ->assertOk()
            ->assertJsonPath('message', self::GENERIC_MESSAGE);

        Notification::assertNotSentTo($user, ResetPassword::class);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_unknown_sign_in_email_returns_same_generic_response(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.com'])
            ->assertOk()
            ->assertJsonPath('message', self::GENERIC_MESSAGE);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_invalid_sign_in_email_returns_a_clear_validation_error(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Enter a valid sign-in email.');
    }

    public function test_missing_or_unverified_recovery_email_returns_same_response_without_sending(): void
    {
        Notification::fake();
        $unverified = User::factory()->create([
            'email' => 'unverified@nutriscope.local',
            'recovery_email' => 'rnd@example.com',
            'recovery_email_verified_at' => null,
        ]);
        $missing = User::factory()->create([
            'email' => 'missing-recovery@nutriscope.local',
            'recovery_email' => null,
            'recovery_email_verified_at' => null,
        ]);

        $this->postJson('/api/auth/forgot-password', ['email' => $unverified->email])
            ->assertOk()
            ->assertJsonPath('message', self::GENERIC_MESSAGE);
        $this->postJson('/api/auth/forgot-password', ['email' => $missing->email])
            ->assertOk()
            ->assertJsonPath('message', self::GENERIC_MESSAGE);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_delivery_failure_returns_a_clear_error_and_deletes_the_unused_token(): void
    {
        $user = User::factory()->create([
            'email' => 'rnd@nutriscope.local',
            'recovery_email' => 'rnd@example.com',
            'recovery_email_verified_at' => now(),
        ]);
        $notificationDispatcher = \Mockery::mock(Dispatcher::class);
        $notificationDispatcher->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Provider unavailable'));
        $this->app->instance(Dispatcher::class, $notificationDispatcher);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Password reset link could not be sent. Try again later.');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_reset_submission_uses_sign_in_email_and_consumes_token_once(): void
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
            'password' => 'wrong-identifier-password',
            'password_confirmation' => 'wrong-identifier-password',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/reset-password', [
            'email' => 'RND@NUTRISCOPE.LOCAL',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk()->assertJsonMissing(['token']);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'rnd@nutriscope.local',
            'token' => $token,
            'password' => 'replayed-password',
            'password_confirmation' => 'replayed-password',
        ])->assertUnprocessable();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertFalse(Password::broker()->tokenExists($user->fresh(), $token));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'rnd@nutriscope.local']);
        $activity = Activity::where('event', 'password_reset')->where('causer_id', $user->id)->first();
        $this->assertNotNull($activity);
        $this->assertSame($user->uuid, $activity->properties['details']['subject_public_id']);
        $this->assertSame(1, Activity::where('event', 'password_reset')->count());
        $this->assertArrayNotHasKey('request', $activity->properties->all());
        $this->assertArrayNotHasKey('user_agent', $activity->properties->all());
        $this->assertStringNotContainsString($token, $activity->toJson());
        $this->assertStringNotContainsString('new-password', $activity->toJson());
        $this->assertStringNotContainsString('rnd@nutriscope.local', $activity->toJson());
        $this->assertStringNotContainsString('rnd@example.com', $activity->toJson());
    }

    public function test_reset_token_expires_after_fifteen_minutes(): void
    {
        $user = User::factory()->create([
            'email' => 'rnd@nutriscope.local',
        ]);
        $token = Password::broker()->createToken($user);

        $this->travel(14)->minutes();
        $this->assertTrue(Password::broker()->tokenExists($user, $token));

        $this->travel(61)->seconds();
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
    }
}
