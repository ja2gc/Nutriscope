<?php

namespace App\Http\Middleware;

use App\Enums\AuditAction;
use App\Services\Audit\AuditHealthMonitor;
use App\Services\Audit\SecurityAuditDeduplicator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordSecurityRejections
{
    public function __construct(
        private readonly SecurityAuditDeduplicator $deduplicator,
        private readonly AuditHealthMonitor $monitor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $middleware = $request->route()?->gatherMiddleware() ?? [];
        $isProtected = collect($middleware)->contains(
            fn (string $name): bool => str_starts_with($name, 'auth:') || str_starts_with($name, 'role:'),
        );

        if (! $isProtected) {
            return $response;
        }

        if ($response->getStatusCode() === 401) {
            $this->recordSafely(fn () => $this->deduplicator->record(
                AuditAction::AuthenticationFailed,
                'authentication',
                $request,
                status: 401,
            ));
        }

        if ($response->getStatusCode() === 403) {
            $this->recordSafely(fn () => $this->deduplicator->record(
                AuditAction::AuthorizationDenied,
                'authorization',
                $request,
                status: 403,
                actor: $request->user(),
            ));
        }

        return $response;
    }

    private function recordSafely(Closure $record): void
    {
        try {
            $record();
        } catch (Throwable $exception) {
            try {
                $this->monitor->writerFailure($exception);
            } catch (Throwable) {
            }
        }
    }
}
