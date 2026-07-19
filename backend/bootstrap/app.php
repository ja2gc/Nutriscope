<?php

use App\Enums\AuditAction;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RecordSecurityRejections;
use App\Http\Middleware\RoleMiddleware;
use App\Models\Notification;
use App\Services\Audit\AuditHealthMonitor;
use App\Services\Audit\AuditRetentionState;
use App\Services\Audit\SecurityAuditDeduplicator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
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
        $schedule->command('model:prune', ['--model' => [Notification::class]])
            ->dailyAt('00:20')
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->command('reports:process-file-operations')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->command('audit:prune --force')
            ->daily()
            ->withoutOverlapping()
            ->onOneServer()
            ->when(fn (): bool => app(AuditRetentionState::class)->enabled());
        $schedule->call(fn (): mixed => app(AuditHealthMonitor::class)->inspectDaily())
            ->dailyAt('00:10')
            ->name('audit:monitor-health')
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->call(fn (): mixed => app(AuditHealthMonitor::class)->emitMonthlyMetrics())
            ->monthlyOn(1, '00:30')
            ->name('audit:emit-monthly-metrics')
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RecordSecurityRejections::class);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active' => EnsureActiveUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $exception): ?bool {
            $monitor = app(AuditHealthMonitor::class);
            if (! $monitor->isAuditInsertQuery($exception->getSql())) {
                return null;
            }

            try {
                $monitor->writerFailure($exception);
            } catch (Throwable) {
            }

            return false;
        });
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
                try {
                    app(SecurityAuditDeduplicator::class)->record(
                        AuditAction::AuthenticationFailed,
                        'authentication',
                        $request,
                        status: 401,
                    );
                } catch (Throwable $exception) {
                    try {
                        app(AuditHealthMonitor::class)->writerFailure($exception);
                    } catch (Throwable) {
                    }
                }
            }

            if ($response->getStatusCode() === 403 && ($isAuthenticatedRoute || $isRoleProtectedRoute)) {
                try {
                    app(SecurityAuditDeduplicator::class)->record(
                        AuditAction::AuthorizationDenied,
                        'authorization',
                        $request,
                        status: 403,
                        actor: $request->user(),
                    );
                } catch (Throwable $exception) {
                    try {
                        app(AuditHealthMonitor::class)->writerFailure($exception);
                    } catch (Throwable) {
                    }
                }
            }

            return $response;
        });
    })->create();
