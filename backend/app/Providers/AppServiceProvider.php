<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Brute-force / credential-stuffing protection for the login endpoint.
        // Keyed on email + IP so one attacker can't lock out everyone behind a shared NAT.
        RateLimiter::for('login', function (Request $request) {
            $key = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });

        // AI endpoints — each call hits a paid LLM; key by user so abuse can't drain the
        // budget through a single compromised account.
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perHour(20)->by($request->user()?->id);
        });

        // USDA external API — protects our API key quota; 30/min is generous for
        // interactive search but blocks programmatic scraping.
        RateLimiter::for('usda', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id);
        });

        // File uploads — prevents storage exhaustion; 20 uploads/hour per user
        // covers legitimate clinical workflows (lab PDFs, PO photos).
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perHour(20)->by($request->user()?->id);
        });

        // Password change — prevents rapid credential cycling by a hijacked session.
        RateLimiter::for('password-change', function (Request $request) {
            return Limit::perHour(5)->by($request->user()?->id);
        });

        // Budget ledger: auto-deduct from fiscal year allocation when PO completes.
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PurchaseOrderCompleted::class,
            \App\Listeners\BudgetLedgerListener::class,
        );

        // Compute-heavy clinical endpoints (autofill, recommendations) — not AI-billed
        // but CPU-bound; 30/min is generous for interactive use, blocks programmatic abuse.
        RateLimiter::for('compute', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id);
        });

        // Report rendering — hits DB aggregations and PDF generation; 10/min per user
        // covers any real workflow, blocks runaway polling or scraping.
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id);
        });
    }
}
