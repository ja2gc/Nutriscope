<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $logName = 'audit'): Response
    {
        $response = $next($request);

        // Decision B (Spec 5): log mutations only — routine GET reads are noise.
        if ($request->user() && ! $request->isMethodSafe()) {
            activity($logName)
                ->causedBy($request->user())
                ->withProperties([
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                ])
                ->log("Accessed " . $request->path());
        }

        return $response;
    }
}
