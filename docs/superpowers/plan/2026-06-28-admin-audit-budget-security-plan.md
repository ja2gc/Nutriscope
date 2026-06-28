# Admin Audit, Budget, Profile, and Security Plan

> **Instruction:** do not implement anything after defense.
> Keep scope tight. Only do items that help us present cleanly and safely.

**Goal:** tighten Admin oversight, add a read-only Admin budget view, make audit logs match expected admin actions, and harden login/profile/password reset.

**Architecture:** Laravel stays enforcement boundary. Next.js stays UI/proxy only. Role checks, audit events, file validation, password reset rules, and token cleanup must be enforced in Laravel.

**Tech Stack:** Laravel 13, PHP 8.3, Sanctum, Spatie activitylog, Next.js 16, React 19, TypeScript, Tailwind CSS, Vitest, PHPUnit 12.

---

## Defense Scope

1. **Admin budget page missing.** Need `/admin/budget` read-only page, sidebar link, and backend/Admin proxies. Reuse RND budget UI so Admin sees same data without mutation controls.
2. **Audit expectations do not match backend yet.** Login/logout, password changes, admin user actions, and budget mutations need clear audit rows.
3. **Profile photo handling is too loose.** Current validation allows oversized arbitrary strings. Tighten it or move to safer storage.
4. **Forgot-password flow is missing.** Add it if time allows. If not, keep it clearly marked as follow-up.
5. **Token/session cleanup needs work.** Logout should clear both cookies, and password changes/reset should revoke existing tokens.

---

## What Already Works

- Role middleware protects Admin/FSS/RND routes.
- Login throttling exists.
- Password-change throttling exists.
- Admin audit log reader already exists.
- Budget ledger and clinical audit tests already cover some domain behavior.

---

## Universal Settings To Keep

### Admin

- **Security policy:** token lifetime, password strength, password reset throttle, login throttle, session revocation.
- **User defaults:** default active status for new users, force password reset on first login if added.

### All Roles

- **Security:** password change.

### Notification Preferences

- **Announcements:** optional.
- **Push enabled on this browser/device:** optional.

---

## In Scope

### Task 1: Admin Read-Only Budget

**Files:**
- `backend/routes/api.php`
- `frontend/services/budgetService.ts`
- `frontend/app/api/admin/budgets/**`
- `frontend/app/admin/budget/page.tsx`
- `frontend/components/budget/BudgetPageShell.tsx`
- `frontend/components/layout/Sidebar.tsx`

- [ ] Add Admin read-only budget routes under `auth:sanctum` + `role:Admin`.

```php
Route::get('budgets/summary', [BudgetController::class, 'summary']);
Route::get('budgets/ledger', [BudgetController::class, 'ledger']);
Route::apiResource('budgets', BudgetController::class)->only(['index', 'show']);
```

- [ ] Add Next proxies for Admin budget list, summary, and ledger.

```ts
import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  const search = new URL(req.url).searchParams;
  return proxy("/admin/budgets/summary", { search });
}
```

- [ ] Extract shared budget UI so RND and Admin use one shell.

```ts
type BudgetPageShellProps = {
  apiPrefix: "fss" | "admin";
  canMutate: boolean;
  crumbs: [string, string?][];
  homeHref: string;
};
```

- [ ] Hide setup/manual-adjust controls in Admin mode.
- [ ] Add Admin sidebar entry.

```tsx
<BudgetPageShell
  apiPrefix="admin"
  canMutate={false}
  crumbs={[["Admin", "/admin/dashboard"], ["Budget"]]}
  homeHref="/admin/dashboard"
/>
```

### Task 2: Audit Contract + Security Events

**Files:**
- `frontend/app/admin/audit-logs/page.tsx`
- `frontend/services/auditLogService.ts`
- `backend/app/Http/Controllers/Auth/AuthController.php`
- `backend/app/Http/Controllers/Admin/UserController.php`
- `backend/routes/api.php`

- [ ] Map subject filters to backend class names, not short labels.

```ts
const uniqueSubjectTypes = [
  { label: "Patient", value: "App\\Models\\Patient" },
  { label: "User Account", value: "App\\Models\\User" },
  { label: "Budget", value: "App\\Models\\Budget" },
  { label: "Budget Ledger", value: "App\\Models\\BudgetLedger" },
  { label: "Purchase Order", value: "App\\Models\\PurchaseOrder" },
];
```

- [ ] Add `login`, `login_failed`, `logout`, and `password_changed` audit events.

```php
activity('audit')
    ->causedBy($user)
    ->event('login')
    ->withProperties([
        'ip' => $request->ip(),
        'user_agent' => substr((string) $request->userAgent(), 0, 255),
        'platform' => $request->validated('platform') ?? 'web',
        'device_name' => $tokenName,
    ])
    ->log('User logged in');
```

- [ ] Add `password_reset` and admin user CRUD audit events.
- [ ] Keep audit payloads free of passwords, tokens, and raw photo data.

### Task 3: Budget Audit Coverage

**Files:**
- `backend/app/Models/Budget.php`
- `backend/app/Models/BudgetLedger.php`
- `backend/app/Listeners/BudgetLedgerListener.php`
- `backend/routes/api.php`

- [ ] Audit budget setup and manual adjustments.
- [ ] Audit PO deduction ledger creation.

```php
activity('audit')
    ->performedOn($entry)
    ->event('created')
    ->withProperties([
        'fiscal_year' => $year,
        'type' => 'po_deduction',
        'source' => 'system',
        'amount' => (float) $po->total_amount,
        'purchase_order_id' => $po->id,
        'reference' => $po->po_number,
    ])
    ->log('Budget ledger system deduction created');
```

- [ ] Apply audit middleware to budget mutation routes.

```php
Route::middleware(['role:RND', 'audit'])->group(function () {
    Route::post('budgets/adjust', [BudgetController::class, 'manualAdjust']);
    Route::apiResource('budgets', BudgetController::class)->only(['store']);
});
```

### Task 4: Forgot Password + Token Cleanup

**Files:**
- `backend/app/Http/Controllers/Auth/AuthController.php` or a password reset controller
- `backend/routes/api.php`
- `frontend/app/login/page.tsx`
- `frontend/app/forgot-password/page.tsx`
- `frontend/app/reset-password/page.tsx`
- `frontend/app/api/auth/forgot-password/route.ts`
- `frontend/app/api/auth/reset-password/route.ts`
- `frontend/app/api/auth/logout/route.ts`
- `frontend/services/authService.ts`

- [ ] Add forgot-password and reset-password flow.

```php
Route::prefix('auth')->group(function () {
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:password-reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset');
});
```

- [ ] Use generic responses so email existence stays hidden.

```json
{ "message": "If that email exists, a password reset link has been sent." }
```

- [ ] Clear both auth cookies on logout.

```ts
res.cookies.delete("nutriscope_token");
res.cookies.delete("nutriscope_role");
```

- [ ] Revoke existing tokens on password change/reset.

```php
$user->tokens()->delete();
```

### Task 5: Profile Photo Hardening

**Files:**
- `backend/app/Http/Requests/Auth/UpdateProfileRequest.php`
- `backend/app/Http/Controllers/Auth/AuthController.php`
- `frontend/app/(rnd)/profile/page.tsx`
- `frontend/app/admin/profile/page.tsx`
- `frontend/components/profile/ProfilePageShell.tsx`

- [ ] Stop accepting unlimited arbitrary `profile_photo` strings.
- [ ] Prefer validated uploaded file path over raw data URL.

```php
'profile_photo' => [
    'nullable',
    'string',
    'max:300000',
    'regex:/^data:image\\/(png|jpeg|webp);base64,/',
],
```

- [ ] Share one profile shell between RND and Admin.

### Task 6: Docs

**Files:**
- `docs/modules/admin.md`
- `docs/modules/rnd.md`
- `docs/security/security.md`

- [ ] Update docs only for things that are actually in the defense scope.
- [ ] Keep wording short and accurate.

---

## Verification

- `cd backend && php artisan test --filter=AdminBudgetReadOnlyTest`
- `cd backend && php artisan test --filter=AuthAuditEventTest`
- `cd backend && php artisan test --filter=BudgetAuditTest`
- `cd backend && php artisan test --filter=ForgotPasswordTest`
- `cd backend && php artisan test --filter=ProfileTest`
- `cd frontend && npm test -- app/api/admin/budgets/budget-routes.test.ts`
- `cd frontend && npm test -- app/admin/budget/page.test.ts app/(rnd)/food-service/budget/placement.test.ts`
- `cd frontend && npm test -- app/admin/audit-logs/audit-filter-contract.test.ts`
- `cd frontend && npx tsc --noEmit`
