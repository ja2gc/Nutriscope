<?php

use App\Enums\AuditAction;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RecordSecurityRejections;
use App\Http\Middleware\RoleMiddleware;
use App\Services\Audit\SecurityAuditDeduplicator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Trigger B (rnd.md §7) — daily follow-up reminders, one day before.
        $schedule->command('notifications:follow-up-reminders')
            ->dailyAt('07:00')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RecordSecurityRejections::class);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active' => EnsureActiveUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*')
                || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response): Response {
            $request = request();
            $middleware = $request->route()?->gatherMiddleware() ?? [];
            $isAuthenticatedRoute = collect($middleware)->contains(
                fn (string $name): bool => str_starts_with($name, 'auth:'),
            );
            $isRoleProtectedRoute = collect($middleware)->contains(
                fn (string $name): bool => str_starts_with($name, 'role:'),
            );

            if ($response->getStatusCode() === 401 && ($isAuthenticatedRoute || $isRoleProtectedRoute)) {
                app(SecurityAuditDeduplicator::class)->record(
                    AuditAction::AuthenticationFailed,
                    'authentication',
                    $request,
                    status: 401,
                );
            }

            if ($response->getStatusCode() === 403 && ($isAuthenticatedRoute || $isRoleProtectedRoute)) {
                app(SecurityAuditDeduplicator::class)->record(
                    AuditAction::AuthorizationDenied,
                    'authorization',
                    $request,
                    status: 403,
                    actor: $request->user(),
                );
            }

            return $response;
        });
    })->create();
