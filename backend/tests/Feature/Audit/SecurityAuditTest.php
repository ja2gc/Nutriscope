<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Models\AuditActivity;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\SecurityAuditDeduplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use ReflectionMethod;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_named_limiter_429_is_logged_once_without_request_secrets(): void
    {
        $user = User::factory()->create([
            'email' => 'limited@example.com',
            'password' => Hash::make('correct-password'),
        ]);
        $otherUser = User::factory()->create([
            'email' => 'another-account@example.com',
            'password' => Hash::make('other-correct-password'),
        ]);
        $secret = 'SECURITY-AUDIT-SENTINEL';

        foreach (range(1, 7) as $attempt) {
            $response = $this->withHeaders([
                'X-Sentinel' => $secret,
                'User-Agent' => $secret,
            ])->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => $secret,
                'platform' => 'web',
            ]);

            $attempt <= 5 ? $response->assertUnauthorized() : $response->assertTooManyRequests();
        }

        foreach (range(1, 6) as $attempt) {
            $response = $this->postJson('/api/auth/login', [
                'email' => $otherUser->email,
                'password' => $secret,
                'platform' => 'web',
            ]);

            $attempt <= 5 ? $response->assertUnauthorized() : $response->assertTooManyRequests();
        }

        $events = Activity::where('event', AuditAction::RateLimitExceeded->value)->get();
        $this->assertCount(2, $events);
        $this->assertSame('blocked', $events->first()->outcome);
        $this->assertSame('login', $events->first()->properties['details']['limiter']);
        $this->assertSame('api/auth/login', $events->first()->properties['details']['route_name']);
        $this->assertGreaterThan(0, $events->first()->properties['details']['retry_after_seconds']);
        $this->assertSame('127.0.0.1', $events->first()->properties['details']['ip']);
        $this->assertArrayNotHasKey('request', $events->first()->properties->all());
        $this->assertArrayNotHasKey('user_agent', $events->first()->properties->all());
        $this->assertStringNotContainsString($secret, Activity::query()->get()->toJson());
        $this->assertStringNotContainsString($user->email, $events->toJson());
        $this->assertEqualsCanonicalizing(
            [$user->uuid, $otherUser->uuid],
            $events->pluck('properties')->pluck('details.account_public_id')->all(),
        );
    }

    public function test_deduplicator_counts_recurrences_and_emits_again_after_five_minutes(): void
    {
        Cache::flush();
        $deduplicator = $this->app->make(SecurityAuditDeduplicator::class);

        foreach (range(1, 3) as $attempt) {
            $request = Request::create('/api/auth/me', 'GET', server: ['REMOTE_ADDR' => '203.0.113.5']);
            $request->setRouteResolver(fn () => tap(new Route('GET', 'api/auth/me', fn () => null), fn (Route $route) => $route->name('auth.me')));
            $deduplicator->record(AuditAction::AuthenticationFailed, 'authentication', $request, status: 401);
        }

        $this->assertSame(1, Activity::where('event', 'authentication_failed')->count());

        $this->travel(301)->seconds();
        $request = Request::create('/api/auth/me', 'GET', server: ['REMOTE_ADDR' => '203.0.113.5']);
        $request->setRouteResolver(fn () => tap(new Route('GET', 'api/auth/me', fn () => null), fn (Route $route) => $route->name('auth.me')));
        $deduplicator->record(AuditAction::AuthenticationFailed, 'authentication', $request, status: 401);

        $events = Activity::where('event', 'authentication_failed')->oldest()->get();
        $this->assertCount(2, $events);
        $this->assertSame(2, $events->last()->properties['details']['previous_recurrence_count']);
    }

    public function test_flood_counts_every_recurrence_while_database_rows_stay_bounded(): void
    {
        Cache::flush();
        $deduplicator = $this->app->make(SecurityAuditDeduplicator::class);

        foreach (range(1, 51) as $attempt) {
            $request = Request::create('/api/auth/me', 'GET', server: ['REMOTE_ADDR' => '203.0.113.55']);
            $request->setRouteResolver(fn () => new Route('GET', 'api/auth/me', fn () => null));
            $deduplicator->record(AuditAction::AuthenticationFailed, 'authentication', $request, status: 401);
        }

        $this->assertSame(1, Activity::where('event', 'authentication_failed')->count());

        $this->travel(301)->seconds();
        $request = Request::create('/api/auth/me', 'GET', server: ['REMOTE_ADDR' => '203.0.113.55']);
        $request->setRouteResolver(fn () => new Route('GET', 'api/auth/me', fn () => null));
        $deduplicator->record(AuditAction::AuthenticationFailed, 'authentication', $request, status: 401);

        $events = Activity::where('event', 'authentication_failed')->oldest()->get();
        $this->assertCount(2, $events);
        $this->assertSame(50, $events->last()->properties['details']['previous_recurrence_count']);
    }

    public function test_authenticated_accounts_behind_the_same_nat_have_separate_dedup_keys(): void
    {
        Cache::flush();
        $request = Request::create('/api/admin/users', 'GET', server: ['REMOTE_ADDR' => '203.0.113.8']);
        $request->setRouteResolver(fn () => new Route('GET', 'api/admin/users', fn () => null));
        $first = User::factory()->create(['role' => 'RND']);
        $second = User::factory()->create(['role' => 'RND']);
        $deduplicator = $this->app->make(SecurityAuditDeduplicator::class);

        $deduplicator->record(AuditAction::AuthorizationDenied, 'authorization', $request, status: 403, actor: $first);
        $deduplicator->record(AuditAction::AuthorizationDenied, 'authorization', $request, status: 403, actor: $second);

        $this->assertSame(2, Activity::where('event', 'authorization_denied')->count());
    }

    public function test_rate_limit_response_survives_account_lookup_and_audit_database_failure(): void
    {
        Cache::flush();
        $user = User::factory()->create([
            'email' => 'audit-outage@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
                'platform' => 'web',
            ])->assertUnauthorized();
        }

        $originalConnection = config('database.default');
        config(['database.default' => 'missing-security-audit-connection']);

        try {
            $this->withThrowingLogHandler(function () use ($user): void {
                $this->postJson('/api/auth/login', [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                    'platform' => 'web',
                ])->assertTooManyRequests();
            });
        } finally {
            config(['database.default' => $originalConnection]);
        }
    }

    public function test_rejected_protected_requests_log_exactly_one_safe_event(): void
    {
        $this->withToken('SECRET-BEARER-SENTINEL')
            ->withHeader('User-Agent', 'SECRET-USER-AGENT-SENTINEL')
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
        $this->assertSame(1, Activity::where('event', 'authentication_failed')->count());

        $rnd = User::factory()->create(['role' => 'RND']);
        $this->actingAs($rnd, 'sanctum')
            ->withHeader('Cookie', 'SECRET-COOKIE-SENTINEL')
            ->getJson('/api/admin/users')
            ->assertForbidden();

        $this->assertSame(1, Activity::where('event', 'authorization_denied')->count());

        $authorization = Activity::where('event', 'authorization_denied')->firstOrFail();
        $this->assertSame($rnd->uuid, $authorization->properties['actor']['public_id']);
        $this->assertSame(403, $authorization->properties['details']['status']);
        $this->assertArrayNotHasKey('request', $authorization->properties->all());
        $this->assertArrayNotHasKey('user_agent', $authorization->properties->all());
        $payload = Activity::query()->get()->toJson();
        $this->assertStringNotContainsString('SECRET-BEARER-SENTINEL', $payload);
        $this->assertStringNotContainsString('SECRET-COOKIE-SENTINEL', $payload);
        $this->assertStringNotContainsString('SECRET-USER-AGENT-SENTINEL', $payload);
    }

    public function test_each_rejected_http_request_increments_recurrence_once(): void
    {
        Cache::flush();

        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->travel(301)->seconds();
        $this->getJson('/api/auth/me')->assertUnauthorized();

        $events = Activity::where('event', 'authentication_failed')->oldest()->get();
        $this->assertCount(2, $events);
        $this->assertSame(1, $events->last()->properties['details']['previous_recurrence_count']);
    }

    public function test_unknown_account_429_has_no_account_reference_email_or_secret(): void
    {
        Cache::flush();
        $email = 'unknown-rate-limit@example.com';
        $secret = 'UNKNOWN-ACCOUNT-SECRET';

        foreach (range(1, 7) as $attempt) {
            $response = $this->postJson('/api/auth/login', [
                'email' => $email,
                'password' => $secret,
                'platform' => 'web',
            ]);
            $attempt <= 5 ? $response->assertUnauthorized() : $response->assertTooManyRequests();
        }

        $event = Activity::where('event', 'rate_limit_exceeded')->sole();
        $this->assertArrayNotHasKey('account_public_id', $event->properties['details']);
        $this->assertStringNotContainsString($email, $event->toJson());
        $this->assertStringNotContainsString($secret, Activity::query()->get()->toJson());
    }

    public function test_broken_diagnostic_logger_never_replaces_security_responses(): void
    {
        config(['activitylog.enabled' => false]);

        $this->withThrowingLogHandler(function (): void {
            $this->getJson('/api/auth/me')->assertUnauthorized();
        });
    }

    public function test_failed_audit_reservation_is_released_for_immediate_retry(): void
    {
        Cache::flush();
        $deduplicator = $this->app->make(SecurityAuditDeduplicator::class);
        $makeRequest = function (): Request {
            $request = Request::create('/api/auth/me', 'GET', server: ['REMOTE_ADDR' => '203.0.113.77']);
            $request->setRouteResolver(fn () => new Route('GET', 'api/auth/me', fn () => null));

            return $request;
        };

        config(['activitylog.enabled' => false]);
        $deduplicator->record(AuditAction::AuthenticationFailed, 'authentication', $makeRequest(), status: 401);
        config(['activitylog.enabled' => true]);
        $deduplicator->record(AuditAction::AuthenticationFailed, 'authentication', $makeRequest(), status: 401);

        $this->assertSame(1, Activity::where('event', 'authentication_failed')->count());
    }

    public function test_late_failed_owner_cannot_erase_newer_reservation_or_recurrence(): void
    {
        Cache::flush();
        $deduplicator = $this->app->make(SecurityAuditDeduplicator::class);
        $transition = new ReflectionMethod($deduplicator, 'transition');
        $finalize = new ReflectionMethod($deduplicator, 'finalize');
        $key = 'security-audit:test-concurrent-state';

        $old = $transition->invoke($deduplicator, $key, 300, 3600);
        $finalize->invoke($deduplicator, $key, $old['token'], false, 3600);
        $new = $transition->invoke($deduplicator, $key, 300, 3600);
        $transition->invoke($deduplicator, $key, 300, 3600);
        $finalize->invoke($deduplicator, $key, $old['token'], false, 3600);

        $state = Cache::get($key);
        $this->assertSame($new['token'], $state['token']);
        $this->assertSame('pending', $state['status']);
        $this->assertSame(1, $state['recurrences']);
    }

    public function test_repeated_failed_retry_preserves_carried_and_intervening_recurrences(): void
    {
        Cache::flush();
        $deduplicator = $this->app->make(SecurityAuditDeduplicator::class);
        $transition = new ReflectionMethod($deduplicator, 'transition');
        $finalize = new ReflectionMethod($deduplicator, 'finalize');
        $key = 'security-audit:test-repeated-retry';

        $first = $transition->invoke($deduplicator, $key, 300, 3600);
        $this->assertNotNull($first, 'first reservation');
        foreach (range(1, 5) as $attempt) {
            $transition->invoke($deduplicator, $key, 300, 3600);
        }
        $finalize->invoke($deduplicator, $key, $first['token'], false, 3600, 0);

        $retry = $transition->invoke($deduplicator, $key, 300, 3600);
        $this->assertNotNull($retry, 'retry reservation');
        $this->assertSame(5, $retry['previous_recurrence_count']);
        $transition->invoke($deduplicator, $key, 300, 3600);
        $finalize->invoke($deduplicator, $key, $retry['token'], false, 3600, 5);

        $final = $transition->invoke($deduplicator, $key, 300, 3600);
        $this->assertNotNull($final, 'final reservation');
        $this->assertSame(6, $final['previous_recurrence_count']);
    }

    public function test_rotating_login_emails_hits_the_higher_per_ip_limit(): void
    {
        Cache::flush();

        foreach (range(1, 21) as $attempt) {
            $response = $this->postJson('/api/auth/login', [
                'email' => "rotating-{$attempt}@example.com",
                'password' => 'wrong-password',
                'platform' => 'web',
            ]);
            $attempt <= 20 ? $response->assertUnauthorized() : $response->assertTooManyRequests();
        }

        $event = Activity::where('event', 'rate_limit_exceeded')->sole();
        $this->assertSame('login-ip', $event->properties['details']['limiter']);
    }

    public function test_rotating_ips_for_one_account_hits_the_account_limit(): void
    {
        Cache::flush();
        $user = User::factory()->create(['email' => 'rotating-ip@example.com']);

        foreach (range(1, 6) as $attempt) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$attempt}"])
                ->postJson('/api/auth/login', [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                    'platform' => 'web',
                ]);
            $attempt <= 5 ? $response->assertUnauthorized() : $response->assertTooManyRequests();
        }

        $this->assertSame('login', Activity::where('event', 'rate_limit_exceeded')->sole()->properties['details']['limiter']);
    }

    public function test_inactive_user_is_rejected_on_all_shared_authenticated_routes(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = $user->createToken('inactive')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertForbidden();
        $this->withToken($token)->patchJson('/api/auth/profile', [])->assertForbidden();
        $this->withToken($token)->getJson('/api/notifications')->assertForbidden();
    }

    public function test_legacy_login_rows_are_presented_as_login_succeeded(): void
    {
        $activity = AuditActivity::create([
            'log_name' => config('audit.log_name'),
            'description' => 'Login succeeded',
            'event' => 'login',
            'category' => 'security',
            'domain' => 'accounts',
            'severity' => 'info',
            'outcome' => 'success',
            'properties' => [],
        ]);

        $payload = app(AuditEventPresenter::class)->present($activity)->toArray();

        $this->assertSame('login_succeeded', $payload['action']);
    }

    private function withThrowingLogHandler(callable $callback): void
    {
        $logger = Log::getLogger();
        $logger->pushHandler(new class extends AbstractProcessingHandler
        {
            protected function write(LogRecord $record): void
            {
                throw new RuntimeException('Diagnostic logger unavailable.');
            }
        });

        try {
            $callback();
        } finally {
            $logger->popHandler();
        }
    }
}
