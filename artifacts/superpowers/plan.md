## Goal
Implement `AuditMiddleware` to log actions on sensitive routes using `spatie/laravel-activitylog`.

## Assumptions
- The `spatie/laravel-activitylog` package is already installed and its migrations have been run.
- The middleware will log the request URL, method, and IP address, associated with the authenticated user.
- It will be registered in `bootstrap/app.php` and added to specific route groups in `routes/api.php` (e.g., patient records).

## Plan
1. **Create AuditMiddleware**
   - **Files**: `backend/app/Http/Middleware/AuditMiddleware.php`
   - **Change**: Create a new middleware class. Use the `activity()` helper to log the event after the response is processed (using a terminable middleware approach or just logging before returning `$next($request)`). It should log properties like `url`, `method`, and `ip`.
   - **Verify**: Syntax check.

2. **Register Alias in bootstrap/app.php**
   - **Files**: `backend/bootstrap/app.php`
   - **Change**: Add `'audit' => \App\Http\Middleware\AuditMiddleware::class` to the `$middleware->alias([])` array.
   - **Verify**: Syntax check.

3. **Apply Middleware to Routes**
   - **Files**: `backend/routes/api.php`
   - **Change**: Apply the `audit` middleware to sensitive routes, such as the `patients` endpoints within the `rnd` prefix.
   - **Verify**: Run `php artisan route:list` to ensure the middleware is attached.

4. **Write Tests**
   - **Files**: `backend/tests/Feature/AuditMiddlewareTest.php`
   - **Change**: Write a test that sends a request as an authenticated user to an `audit`-protected route and asserts that an `Activity` record was created in the database with the correct Causer and properties.
   - **Verify**: Run `php artisan test` and ensure all tests pass.

## Risks & mitigations
- **Risk**: Logging every request can bloat the `activity_log` table.
- **Mitigation**: Ensure `AuditMiddleware` is only applied to highly sensitive routes (like viewing/modifying patient health data) and not globally.

## Rollback plan
- Delete `AuditMiddleware.php` and `AuditMiddlewareTest.php`.
- Remove the `audit` alias from `bootstrap/app.php`.
- Remove the `audit` middleware from `routes/api.php`.
