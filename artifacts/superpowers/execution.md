### Step 1: Create AuditMiddleware
- **Files changed**: ackend/app/Http/Middleware/AuditMiddleware.php`n- **What changed**: Created AuditMiddleware class and implemented the handle method using spatie activity() helper.
- **Verification**: php -l app/Http/Middleware/AuditMiddleware.php`n- **Result**: pass
### Step 2: Register Alias in bootstrap/app.php
- **Files changed**: ackend/bootstrap/app.php`n- **What changed**: Added 'audit' alias mapped to AuditMiddleware.
- **Verification**: php -l bootstrap/app.php`n- **Result**: pass
### Step 3: Apply Middleware to Routes
- **Files changed**: ackend/routes/api.php`n- **What changed**: Added 'audit' to the RND route group middleware.
- **Verification**: php artisan route:list | findstr rnd`n- **Result**: pass
### Step 4: Write Tests
- **Files changed**: ackend/tests/Feature/AuditMiddlewareTest.php`n- **What changed**: Created AuditMiddlewareTest. Modified to use User::create to bypass missing factory.
- **Verification**: php artisan test --filter AuditMiddlewareTest`n- **Result**: pass
