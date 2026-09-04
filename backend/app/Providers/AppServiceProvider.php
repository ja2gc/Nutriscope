<?php

namespace App\Providers;

use App\Contracts\BackupArchiveRunner;
use App\Contracts\DatabaseRestoreManager;
use App\Contracts\EnvironmentSwitcher;
use App\Enums\AuditAction;
use App\Events\PurchaseOrderCompleted;
use App\Listeners\BudgetLedgerListener;
use App\Models\AuditActivity;
use App\Models\User;
use App\Policies\AuditPolicy;
use App\Services\Audit\AuditContextResolver;
use App\Services\Audit\AuditHealthMonitor;
use App\Services\Audit\AuditRetentionService;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use App\Services\Audit\Revisions\Serializers\BudgetRevisionSerializer;
use App\Services\Audit\Revisions\Serializers\FoodServiceRecipeRevisionSerializer;
use App\Services\Audit\Revisions\Serializers\MenuCycleRevisionSerializer;
use App\Services\Audit\Revisions\Serializers\MenuCycleTemplateRevisionSerializer;
use App\Services\Audit\Revisions\Serializers\PurchaseOrderRevisionSerializer;
use App\Services\Audit\Revisions\Serializers\RndRecipeRevisionSerializer;
use App\Services\Audit\Revisions\Serializers\ShoppingListRevisionSerializer;
use App\Services\Audit\SecurityAuditDeduplicator;
use App\Services\Backup\MysqlDatabaseRestoreManager;
use App\Services\Backup\MysqlEnvironmentSwitcher;
use App\Services\Backup\SpatieBackupArchiveRunner;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BackupArchiveRunner::class, SpatieBackupArchiveRunner::class);
        $this->app->bind(DatabaseRestoreManager::class, MysqlDatabaseRestoreManager::class);
        $this->app->bind(EnvironmentSwitcher::class, MysqlEnvironmentSwitcher::class);
        $this->app->scoped(AuditContextResolver::class);
        $this->app->singleton(AuditHealthMonitor::class);
        $this->app->singleton(AuditRetentionService::class);
        $this->app->singleton(AuditRevisionRegistry::class, fn ($app): AuditRevisionRegistry => new AuditRevisionRegistry([
            $app->make(BudgetRevisionSerializer::class),
            $app->make(FoodServiceRecipeRevisionSerializer::class),
            $app->make(MenuCycleRevisionSerializer::class),
            $app->make(MenuCycleTemplateRevisionSerializer::class),
            $app->make(PurchaseOrderRevisionSerializer::class),
            $app->make(RndRecipeRevisionSerializer::class),
            $app->make(ShoppingListRevisionSerializer::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (self::shouldRegisterAuditMutationBoundary($this->app->runningInConsole(), $_SERVER['argv'] ?? [])) {
            $retention = app(AuditRetentionService::class);
            collect([config('database.default'), config('activitylog.database_connection')])
                ->filter(fn (mixed $connection): bool => is_string($connection) && $connection !== '')
                ->unique()
                ->each(fn (string $connection) => $retention->registerMutationBoundary(DB::connection($connection)));
        }
        DB::listen(function (QueryExecuted $query): void {
            $monitor = app(AuditHealthMonitor::class);
            if ($query->time >= (float) config('audit.monitoring.slow_query_ms', 250)
                && $monitor->isAuditQuery($query->sql)) {
                $monitor->recordSlowAuditQuery();
            }
        });

        Gate::policy(AuditActivity::class, AuditPolicy::class);

        // Brute-force / credential-stuffing protection for the login endpoint.
        // Layer account and source-IP limits so rotating either dimension cannot bypass throttling.
        RateLimiter::for('login', function (Request $request) {
            $key = hash('sha256', Str::transliterate(Str::lower((string) $request->input('email'))));

            return [
                $this->auditedLimit(Limit::perMinute(5)->by('account:'.$key), 'login'),
                $this->auditedLimit(
                    Limit::perMinute(20)->by('ip:'.hash('sha256', (string) $request->ip())),
                    'login-ip',
                ),
            ];
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $baseUrl = rtrim((string) config('app.frontend_url'), '/');
            $email = urlencode((string) $notifiable->getEmailForPasswordReset());

            return "{$baseUrl}/reset-password?token={$token}&email={$email}";
        });

        // AI endpoints — each call hits a paid LLM; key by user so abuse can't drain the
        // budget through a single compromised account.
        RateLimiter::for('ai', function (Request $request) {
            return $this->auditedLimit(Limit::perHour(20)->by($request->user()?->id ?? $request->ip()), 'ai');
        });

        // USDA external API — protects our API key quota; 30/min is generous for
        // interactive search but blocks programmatic scraping.
        RateLimiter::for('usda', function (Request $request) {
            return $this->auditedLimit(Limit::perMinute(30)->by($request->user()?->id ?? $request->ip()), 'usda');
        });

        // File uploads — prevents storage exhaustion; 20 uploads/hour per user
        // covers legitimate clinical workflows (lab PDFs, PO photos).
        RateLimiter::for('uploads', function (Request $request) {
            return $this->auditedLimit(Limit::perHour(20)->by($request->user()?->id ?? $request->ip()), 'uploads');
        });

        RateLimiter::for('manual-backups', function (Request $request) {
            return $this->auditedLimit(
                Limit::perHour(config('nutriscope-backups.manual_rate_limit_per_hour'))
                    ->by('admin:'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
                'manual-backups',
            );
        });

        RateLimiter::for('recovery', function (Request $request) {
            return $this->auditedLimit(Limit::perHour(3)->by('admin:'.($request->user()?->id ?? $request->ip())), 'recovery');
        });

        // Password change — prevents rapid credential cycling by a hijacked session.
        RateLimiter::for('password-change', function (Request $request) {
            return $this->auditedLimit(Limit::perHour(5)->by($request->user()?->id ?? $request->ip()), 'password-change');
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $key = $request->user()?->uuid ?? hash(
                'sha256',
                Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            );

            return $this->auditedLimit(Limit::perHour(5)->by($key), 'password-reset');
        });

        // Budget ledger: auto-deduct from fiscal year allocation when PO completes.
        Event::listen(
            PurchaseOrderCompleted::class,
            BudgetLedgerListener::class,
        );

        // Compute-heavy clinical endpoints (autofill, recommendations) — not AI-billed
        // but CPU-bound; 30/min is generous for interactive use, blocks programmatic abuse.
        RateLimiter::for('compute', function (Request $request) {
            return $this->auditedLimit(Limit::perMinute(30)->by($request->user()?->id ?? $request->ip()), 'compute');
        });

        // Report rendering — hits DB aggregations and PDF generation; 10/min per user
        // covers any real workflow, blocks runaway polling or scraping.
        RateLimiter::for('reports', function (Request $request) {
            return $this->auditedLimit(Limit::perMinute(10)->by($request->user()?->id ?? $request->ip()), 'reports');
        });
    }

    /** @param list<string> $arguments */
    public static function shouldRegisterAuditMutationBoundary(bool $runningInConsole, array $arguments): bool
    {
        if (! $runningInConsole) {
            return true;
        }

        $command = $arguments[1] ?? null;

        return ! is_string($command) || ! Str::startsWith($command, 'migrate');
    }

    private function auditedLimit(Limit $limit, string $limiter): Limit
    {
        return $limit->response(function (Request $request, array $headers) use ($limiter) {
            try {
                app(SecurityAuditDeduplicator::class)->record(
                    AuditAction::RateLimitExceeded,
                    $limiter,
                    $request,
                    status: 429,
                    details: [
                        'limiter' => $limiter,
                        'retry_after_seconds' => max(0, (int) ($headers['Retry-After'] ?? 0)),
                    ],
                    actor: $request->user(),
                    dedupIdentity: $this->limiterIdentity($request, $limiter),
                    accountPublicIdResolver: fn (): ?string => $this->limiterAccountPublicId($request, $limiter),
                );
            } catch (Throwable $exception) {
                $this->diagnoseRateLimitFailure([
                    'exception_class' => $exception::class,
                    'limiter' => $limiter,
                ]);
            }

            return response()->json(['message' => 'Too Many Attempts.'], 429, $headers);
        });
    }

    private function limiterIdentity(Request $request, string $limiter): string
    {
        if ($request->user() !== null) {
            return 'user:'.$request->user()->getAuthIdentifier();
        }

        if ($limiter === 'login') {
            return hash('sha256', Str::transliterate(Str::lower((string) $request->input('email'))));
        }

        if ($limiter === 'password-reset') {
            return hash(
                'sha256',
                Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            );
        }

        return 'ip:'.($request->ip() ?? 'unknown');
    }

    private function limiterAccountPublicId(Request $request, string $limiter): ?string
    {
        if ($request->user() instanceof User) {
            return $request->user()->uuid;
        }

        $email = Str::lower((string) $request->input('email'));

        return match ($limiter) {
            'login', 'login-ip' => User::query()->where('email', $email)->value('uuid'),
            'password-reset' => User::query()
                ->where('recovery_email', $email)
                ->whereNotNull('recovery_email_verified_at')
                ->value('uuid'),
            default => null,
        };
    }

    private function diagnoseRateLimitFailure(array $context): void
    {
        try {
            Log::warning('Rate-limit audit telemetry failed.', $context);
        } catch (Throwable) {
        }
    }
}
