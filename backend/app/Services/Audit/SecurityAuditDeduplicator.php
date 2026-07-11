<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SecurityAuditDeduplicator
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function record(
        AuditAction $action,
        string $kind,
        Request $request,
        int $status,
        array $details = [],
        ?Authenticatable $actor = null,
        ?string $dedupIdentity = null,
        ?string $accountPublicId = null,
        ?Closure $accountPublicIdResolver = null,
    ): void {
        $routeName = $this->routeName($request);
        $identity = $actor?->getAuthIdentifier() ?? $dedupIdentity ?? $request->ip() ?? 'unknown';
        $key = 'security-audit:'.hash('sha256', implode('|', [$kind, (string) $identity, $routeName]));
        $requestMarker = 'security_audit_recorded:'.hash('sha256', $key);
        if ($request->attributes->get($requestMarker) === true) {
            return;
        }
        $request->attributes->set($requestMarker, true);
        $cooldownSeconds = max(1, (int) config('audit.deduplication.security_failure_seconds', 300));
        $stateTtlSeconds = max($cooldownSeconds + 1, $cooldownSeconds * 12);
        $stateKey = $key.':state';
        $reservation = null;

        try {
            $reservation = $this->transition($stateKey, $cooldownSeconds, $stateTtlSeconds);
            if ($reservation === null) {
                return;
            }
            $accountPublicId ??= $accountPublicIdResolver?->__invoke();

            $ip = filter_var($request->ip(), FILTER_VALIDATE_IP) !== false ? $request->ip() : null;

            $this->auditLogger->record(
                $action,
                AuditCategory::Security,
                AuditDomain::Accounts,
                outcome: $status === 429 ? AuditOutcome::Blocked : AuditOutcome::Failure,
                severity: AuditSeverity::Warning,
                details: [
                    ...$details,
                    ...($action === AuditAction::RateLimitExceeded ? ['ip' => $ip] : []),
                    ...($accountPublicId !== null ? ['account_public_id' => $accountPublicId] : []),
                    'route_name' => $routeName,
                    'status' => $status,
                    'previous_recurrence_count' => $reservation['previous_recurrence_count'],
                ],
                actor: $actor,
                includeRequestMetadata: false,
            );
            $this->finalize($stateKey, $reservation['token'], true, $stateTtlSeconds);
        } catch (Throwable $exception) {
            if ($reservation !== null) {
                $this->finalize(
                    $stateKey,
                    $reservation['token'],
                    false,
                    $stateTtlSeconds,
                    $reservation['previous_recurrence_count'],
                );
            }
            $this->diagnose([
                'exception_class' => $exception::class,
                'audit_action' => $action->value,
            ]);
        }
    }

    private function routeName(Request $request): string
    {
        $route = $request->route();

        return $route?->getName() ?? $route?->uri() ?? 'unresolved';
    }

    private function transition(string $stateKey, int $cooldownSeconds, int $ttlSeconds): ?array
    {
        return $this->withStateLock($stateKey, function () use ($stateKey, $cooldownSeconds, $ttlSeconds): ?array {
            $state = Cache::get($stateKey);
            $now = now()->timestamp;

            if (is_array($state)
                && ($state['status'] ?? null) !== 'retryable'
                && $now - (int) ($state['window_started_at'] ?? 0) < $cooldownSeconds) {
                $state['recurrences'] = (int) ($state['recurrences'] ?? 0) + 1;
                Cache::put($stateKey, $state, $ttlSeconds);

                return null;
            }

            $token = bin2hex(random_bytes(16));
            $previous = is_array($state) ? (int) ($state['recurrences'] ?? 0) : 0;
            Cache::put($stateKey, [
                'window_started_at' => $now,
                'token' => $token,
                'status' => 'pending',
                'recurrences' => 0,
            ], $ttlSeconds);

            return ['token' => $token, 'previous_recurrence_count' => $previous];
        });
    }

    private function finalize(
        string $stateKey,
        string $token,
        bool $success,
        int $ttlSeconds,
        int $carriedRecurrences = 0,
    ): void {
        try {
            $this->withStateLock($stateKey, function () use ($carriedRecurrences, $stateKey, $success, $token, $ttlSeconds): void {
                $state = Cache::get($stateKey);
                if (! is_array($state) || ! hash_equals((string) ($state['token'] ?? ''), $token)) {
                    return;
                }
                $state['status'] = $success ? 'committed' : 'retryable';
                if (! $success) {
                    $state['recurrences'] = (int) ($state['recurrences'] ?? 0) + $carriedRecurrences;
                }
                Cache::put($stateKey, $state, $ttlSeconds);
            });
        } catch (Throwable) {
        }
    }

    private function withStateLock(string $stateKey, Closure $callback): mixed
    {
        $lock = Cache::lock($stateKey.':lock', 1);
        $deadline = hrtime(true) + 50_000_000;

        do {
            if ($lock->get()) {
                try {
                    return $callback();
                } finally {
                    $lock->release();
                }
            }
            usleep(1_000);
        } while (hrtime(true) < $deadline);

        return null;
    }

    private function diagnose(array $context): void
    {
        try {
            Log::warning('Security audit telemetry failed.', $context);
        } catch (Throwable) {
        }
    }
}
