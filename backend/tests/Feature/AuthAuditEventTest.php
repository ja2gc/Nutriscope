<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use RuntimeException;
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
            'device_name' => 'DEVICE-NAME-SECRET-SENTINEL',
        ])->assertOk();

        $failed = Activity::where('event', 'login_failed')->latest()->first();
        $login = Activity::where('event', 'login_succeeded')->latest()->first();

        $this->assertNotNull($failed);
        $this->assertNotNull($login);
        $this->assertSame($user->id, $login->causer_id);
        $this->assertArrayNotHasKey('request', $failed->properties->all());
        $this->assertArrayNotHasKey('user_agent', $failed->properties->all());
        $this->assertArrayNotHasKey('request', $login->properties->all());
        $this->assertArrayNotHasKey('user_agent', $login->properties->all());

        $payload = json_encode(Activity::all()->pluck('properties'), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('wrong-password', $payload);
        $this->assertStringNotContainsString('correct-password', $payload);
        $this->assertStringNotContainsString('DEVICE-NAME-SECRET-SENTINEL', $payload);
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

    public function test_login_responses_survive_noncritical_audit_storage_failure(): void
    {
        User::factory()->create([
            'email' => 'resilient@example.com',
            'role' => 'RND',
            'password' => Hash::make('correct-password'),
        ]);
        config(['activitylog.enabled' => false]);

        $this->postJson('/api/auth/login', [
            'email' => 'resilient@example.com',
            'password' => 'wrong-password',
            'platform' => 'web',
        ])->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => 'resilient@example.com',
            'password' => 'correct-password',
            'platform' => 'web',
        ])->assertOk();
    }

    public function test_login_responses_survive_broken_diagnostic_logger(): void
    {
        User::factory()->create([
            'email' => 'broken-log@example.com',
            'password' => Hash::make('correct-password'),
        ]);
        config(['activitylog.enabled' => false]);
        $logger = Log::getLogger();
        $logger->pushHandler(new class extends AbstractProcessingHandler
        {
            protected function write(LogRecord $record): void
            {
                throw new RuntimeException('Diagnostic logger unavailable.');
            }
        });

        try {
            $this->postJson('/api/auth/login', [
                'email' => 'broken-log@example.com',
                'password' => 'wrong-password',
                'platform' => 'web',
            ])->assertUnauthorized();
        } finally {
            $logger->popHandler();
        }
    }
}
