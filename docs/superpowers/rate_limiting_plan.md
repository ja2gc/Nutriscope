# Rate Limiting Plan: Nutriscope API

This document outlines the detailed rate limiting strategy for the Nutriscope API, utilizing Laravel's built-in rate limiters backed by Redis, and aligned with best practices.

## 1. Brainstorm

### Goal
Implement a robust rate limiting strategy across the Nutriscope API to prevent abuse, protect costly endpoints (AI and external APIs), and ensure high availability, while communicating limits clearly to the user.

### Constraints
- Must use Laravel's native rate limiting capabilities.
- Must use Redis for the rate limiter backend storage.
- Must return clear HTTP 429 status codes with appropriate headers (`X-RateLimit-*`, `Retry-After`).
- Limits must be applied intelligently based on endpoint cost and risk.

### Risks
- Too strict limits might block legitimate users (false positives).
- Unclear error messages might lead to poor UX when limits are hit.
- High-cost endpoints (USDA, AI) could be spammed if not properly isolated.

### Recommendation
Implement a tiered rate-limiting approach using Laravel's `RateLimiter` facade in `App\Providers\RouteServiceProvider`, backed by Redis. Attach specific limiters to specific route groups in `routes/api.php`.

---

## 2. Plan (What to Rate Limit & Thresholds)

### A. Authentication Limit (`auth_limit`)
**Target:** Brute-force protection for logins.
**Specific Routes:** 
- `POST /api/auth/login`
**Threshold:** 5 requests per minute per IP and Email.
**Rationale:** Standard security practice to prevent credential stuffing.

### B. High-Cost / AI Limit (`ai_limit`)
**Target:** Endpoints that use LLMs or heavy processing.
**Specific Routes:** 
- `POST /api/rnd/ncp-records/{ncpRecord}/diagnoses/ai-suggest`
- `POST /api/rnd/ncp-records/{ncpRecord}/diagnoses/ai-approve`
- `POST /api/rnd/ncp-records/{ncpRecord}/monitorings/ai-review`
- `POST /api/rnd/ncp-records/{ncpRecord}/intervention/autofill`
- `POST /api/rnd/ncp-records/{ncpRecord}/intervention/recommend`
- `POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/generate`
- `GET /api/rnd/reports/{type}/render`
- `GET /api/fss/reports/{type}/render`
- `POST /api/fss/shopping-lists/generate`
- `POST /api/fss/shopping-lists/{shopping_list}/generate-pos`
**Threshold:** 10 requests per minute per User ID.
**Rationale:** These endpoints cost money per request or consume significant CPU.

### C. External Integration Limit (`usda_limit`)
**Target:** USDA API proxies.
**Specific Routes:** 
- `GET /api/rnd/usda/search`
- `POST /api/rnd/usda/import/{fdcId}`
- `GET /api/rnd/usda/preview/{fdcId}`
**Threshold:** 30 requests per minute per User ID.
**Rationale:** Protects against hitting USDA API rate limits.

### D. Global API Limit (`api`)
**Target:** All API routes not covered by a more specific limit.
**Specific Routes:** All routes in `routes/api.php`.
**Threshold:** 120 requests per minute per User ID (or IP if unauthenticated).
**Rationale:** Prevents general scraping and DoS attacks.

---

## 3. Implementation Code Blocks

### A. Defining Limiters (`app/Providers/RouteServiceProvider.php`)
```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

protected function configureRateLimiting(): void
{
    // Global API Limit (120 req/min per User or IP)
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
    });

    // Auth Limit (5 req/min per IP + Email)
    RateLimiter::for('auth_limit', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip() . $request->input('email'));
    });

    // AI & Heavy Reports Limit (10 req/min per User)
    RateLimiter::for('ai_limit', function (Request $request) {
        return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
    });

    // USDA API Limit (30 req/min per User)
    RateLimiter::for('usda_limit', function (Request $request) {
        return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
    });
}
```

### B. Applying Middleware (`routes/api.php`)
```php
// Authentication
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth_limit');
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Example of applying AI limits
Route::middleware(['auth:sanctum', 'role:RND', 'audit'])->prefix('rnd')->group(function () {
    
    Route::middleware('throttle:ai_limit')->group(function() {
        Route::post('ncp-records/{ncpRecord}/diagnoses/ai-suggest', [AiDiagnosisController::class, 'aiSuggest']);
        Route::post('ncp-records/{ncpRecord}/diagnoses/ai-approve', [AiDiagnosisController::class, 'aiApprove']);
        Route::post('ncp-records/{ncpRecord}/monitorings/ai-review', [MonitoringController::class, 'aiReview']);
        Route::post('ncp-records/{ncpRecord}/intervention/autofill', [InterventionController::class, 'autofill']);
        Route::post('ncp-records/{ncpRecord}/intervention/recommend', [MealPlanController::class, 'recommend']);
        Route::post('ncp-records/{ncpRecord}/meal-plans/generate', [MealPlanController::class, 'generate']);
    });

    Route::middleware('throttle:usda_limit')->group(function() {
        Route::get('usda/search', [UsdaController::class, 'search']);
        Route::post('usda/import/{fdcId}', [UsdaController::class, 'import']);
        Route::get('usda/preview/{fdcId}', [UsdaController::class, 'preview']);
    });

    // ... other RND routes using global 'api' limit implicitly
});
```

### C. Standardizing the 429 JSON Response (`app/Exceptions/Handler.php`)
To communicate limits clearly as required, we must catch the `ThrottleRequestsException` and return a structured JSON response.

```php
use Illuminate\Http\Exceptions\ThrottleRequestsException;

public function register(): void
{
    $this->renderable(function (ThrottleRequestsException $e, $request) {
        if ($request->is('api/*')) {
            $headers = $e->getHeaders();
            $retryAfter = $headers['Retry-After'] ?? 60;

            return response()->json([
                'message' => 'Too Many Requests. Please slow down and try again later.',
                'retry_after' => $retryAfter,
                'limit' => $headers['X-RateLimit-Limit'] ?? null,
                'remaining' => $headers['X-RateLimit-Remaining'] ?? null,
                'reset_at' => $headers['X-RateLimit-Reset'] ?? null,
            ], 429, $headers);
        }
    });
}
```

---

## 4. TDD (Test-Driven Development) Steps

1. **Write Tests First:**
   - Create `tests/Feature/RateLimitingTest.php`.
   - `test_global_rate_limit()`: Send 121 GET requests to `/api/auth/me`. Assert 121st is `429`.
   - `test_auth_rate_limit()`: Send 6 POST requests to `/api/auth/login` with the same IP and email. Assert 6th is `429`.
   - `test_ai_rate_limit()`: Send 11 POST requests to `/api/rnd/ncp-records/1/diagnoses/ai-suggest`. Assert 11th is `429`.
   - `test_rate_limit_headers()`: Assert presence of `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`, and `Retry-After` headers on a 429 response.
   - `test_custom_json_response()`: Assert the response JSON structure matches the plan.

2. **Implement Code:**
   - Insert code from section 3A into `RouteServiceProvider`.
   - Insert code from section 3B into `routes/api.php`.
   - Insert code from section 3C into `Handler.php`.

3. **Verify:**
   - Run `php artisan test --filter RateLimitingTest`.
   - Ensure Laravel is using the Redis cache driver for throttling (`CACHE_DRIVER=redis` in `.env`).

---

## 5. Review Guidelines (Superpowers Review)

- **Correctness:** Does it actually block the 6th login attempt? Are headers correctly returned?
- **Security:** Are limits properly segmented by User ID to prevent one user from burning the limit of another? Does login use email + IP to prevent distributed credential stuffing?
- **Performance:** Is Redis being used efficiently? 
- **Maintainability:** Are the limiters grouped logically? Can they be extracted to `config/rate_limits.php` if thresholds need adjusting by DevOps without touching code?
