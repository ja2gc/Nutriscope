<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class AuthAuditEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_and_failure_are_audited_with_request_ip(): void
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

        $failed = AuditActivity::query()->where('event', 'failed')->firstOrFail();
        $this->assertSame('127.0.0.1', data_get($failed->properties, 'ip'));

        $this->postJson('/api/auth/login', [
            'email' => 'rnd@example.com',
            'password' => 'correct-password',
            'platform' => 'web',
            'device_name' => 'DEVICE-NAME-SECRET-SENTINEL',
        ])->assertOk();

        $this->assertSame(1, AuditActivity::query()->where('event', 'failed')->count());
        $this->assertSame(1, AuditActivity::query()->where('event', 'created')->count());
        $this->assertSame('Session', data_get(AuditActivity::query()->where('event', 'created')->firstOrFail()->properties, 'details.subject_label'));
        $this->assertStringNotContainsString('DEVICE-NAME-SECRET-SENTINEL', json_encode(AuditActivity::query()->get()->toArray()));
    }

    public function test_logout_and_password_change_use_concise_audit_actions(): void
    {
        $user = User::factory()->create([
            'role' => 'RND',
            'password' => Hash::make('old-password'),
        ]);
        AuditFixture::delete(Activity::query());
        $token = $user->createToken('browser')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'browser',
        ]);
        $this->assertSame(1, AuditActivity::query()->where('event', 'deleted')->count());
        $this->assertSame('Session', data_get(AuditActivity::query()->sole()->properties, 'details.subject_label'));

        AuditFixture::delete(Activity::query());
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
        $this->assertSame(1, AuditActivity::query()->where('event', 'updated')->count());
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
