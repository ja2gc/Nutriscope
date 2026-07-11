<?php

namespace App\Http\Middleware;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Services\Audit\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Decision B (Spec 5): log mutations only — routine GET reads are noise.
        if ($request->user()
            && ! $request->isMethodSafe()
            && ! $this->hasEquivalentAudit($request)) {
            $this->auditLogger->record(
                AuditAction::Updated,
                AuditCategory::Operations,
                AuditDomain::System,
                details: [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'access_path' => $request->path(),
                ],
                actor: $request->user(),
            );
        }

        return $response;
    }

    private function hasEquivalentAudit(Request $request): bool
    {
        $segments = collect(explode('/', strtolower($request->path())))
            ->map(fn (string $segment): string => Str::singular(str_replace('_', '-', $segment)))
            ->all();

        foreach ($request->attributes->get('_audit_events', []) as $event) {
            if (($event['source'] ?? null) === 'explicit') {
                return true;
            }

            $table = Str::singular(str_replace('_', '-', strtolower((string) ($event['subject_table'] ?? ''))));
            if ($table !== '' && in_array($table, $segments, true)) {
                return true;
            }
        }

        return false;
    }
}
