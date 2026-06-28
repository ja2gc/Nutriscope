# Admin Audit, Budget, Profile, and Security Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tighten Admin oversight, add an Admin view-only Budget page matching the current RND budget UI, make audit logs reflect the actions admins expect to see, and harden login/profile/account recovery.

**Architecture:** Keep Laravel as the enforcement boundary. Next.js pages and route handlers are UI/proxy only; every role restriction, audit event, file limit, and reset token rule must be enforced in Laravel. Reuse the current RND budget screen by extracting shared UI, then mount Admin in read-only mode with Admin-prefixed backend routes.

**Tech Stack:** Laravel 13, PHP 8.3, Sanctum, Spatie activitylog, Next.js 16, React 19, TypeScript, Tailwind CSS, Vitest, PHPUnit 12.

---

## Executive Findings

1. **Admin budget page is not wired.** RND has `/food-service/budget`, but Admin has no `/admin/budget` page, no sidebar item, and no Admin budget proxy. Backend `/api/fss/budgets*` allows only `FSS,RND`, so Admin cannot safely reuse those routes without new Admin read-only routes.

2. **RND budget page already supports read-only behavior by role, but it is not reusable yet.** `frontend/app/(rnd)/food-service/budget/page.tsx` hides setup and manual adjustment unless `user.role === "RND"`. Extracting this into a shared component is the lowest-risk way to give Admin the exact same page while keeping writes hidden.

3. **Audit page exists, but expected events are missing or misleading.** UI advertises login/logout events and filters by short subject labels, but backend does not create login/logout audit events, and backend expects full `subject_type` class names such as `App\Models\Patient`.

4. **Budget changes are not fully auditable today.** `Budget` and `BudgetLedger` do not use `AuditsChanges`, and the `/api/fss` route group does not use `audit` middleware. RND budget setup/manual adjustments are therefore weak in system-wide audit despite being financial actions.

5. **Admin user actions are not strongly audited.** Admin routes are role protected, but the Admin group has no `audit` middleware and `User` does not use `AuditsChanges`. User creation, activation/deactivation, role changes, and admin password resets should be first-class audit events.

6. **Profile photo storage is risky.** `profile_photo` is a `longText` field and frontend sends data URLs. `UpdateProfileRequest` validates only `nullable|string`, with no size, MIME, or image validation. This can bloat DB rows and permit unexpected payloads.

7. **Forgot password is scaffolded at DB/config level but not implemented.** `password_reset_tokens` table and `config/auth.php` broker exist. No `/forgot-password` UI, no API endpoint, no notification/mail flow.

8. **Token/session hardening needs cleanup.** Sanctum token expiration is `null`, login cookie lasts 7 days, logout clears `nutriscope_token` but not `nutriscope_role`, and password changes/resets do not revoke existing tokens.

9. **Docs drift exists.** `docs/modules/admin.md` says profile photo is not supported, but migrations and frontend now support it. Plan should update docs after implementation.

## What Is Already Wired Properly

- **Backend role enforcement:** `backend/routes/api.php` uses `auth:sanctum` and `role:*` middleware. `RoleMiddleware` also blocks inactive users.
- **Login brute-force throttling:** `AppServiceProvider` defines `login` limiter keyed by email plus IP, and route uses `throttle:login`.
- **Password change throttling:** `/api/auth/password` uses `throttle:password-change`.
- **Admin audit log reader:** `/api/admin/audit-logs` exists with pagination and filters.
- **Clinical audit redaction:** clinical models using `AuditsChanges` with `$auditRedactValues = true` redact values before activity rows are persisted.
- **Budget ledger domain rules:** fiscal year summary, source filter, manual adjustments, and PO deduction idempotency have backend tests.
- **Admin user reset endpoint:** Admin can reset another user's password through `/api/admin/users/{user}/reset-password`, rate limited.

## What Admin Should See In Audit

Admin audit should show both model change records and important security/request events:

- User login success: event `login`, actor user, IP, user-agent, platform, token/device name.
- User login failure: event `login_failed`, no user or matched email hash, IP, user-agent, reason bucket such as invalid credentials, inactive account, wrong platform.
- Logout: event `logout`, actor user, IP, user-agent.
- Profile updates: event `updated`, subject `User`, changed field names, no password hash or photo payload.
- Password changed by owner: event `password_changed`, actor user, no secrets.
- Password reset by Admin: event `password_reset`, actor Admin, subject target user, no secrets.
- Admin user CRUD: created/updated/deleted/deactivated/reactivated, actor Admin, subject user, changed fields including role and active status.
- Budget fiscal year setup: event `created`, subject `Budget`, fiscal year, allocated amount.
- Budget manual adjustment: event `created`, subject `BudgetLedger`, fiscal year, type, source, amount, reason/reference.
- PO budget deduction: event `created`, subject `BudgetLedger`, fiscal year, PO number/id, amount, source `system`.
- Food-service settings changes: event `updated`, subject `FoodServiceSetting`, changed settings.
- Report branding updates: event `updated`, subject `ReportBranding`, file path changes only, no raw logo payload.

Current gaps against that expectation:

- Login/logout events are not written in `AuthController`.
- Admin user actions are not logged by model trait or request middleware.
- Budget and budget ledger models are not logged by model trait.
- `/api/fss` mutations lack `audit` middleware.
- Audit UI subject filter sends labels like `Patient`, but backend tests use full class names.

## Related Files And Why They Matter

- `backend/composer.json` - confirms Laravel 13, Sanctum, Spatie activitylog, PHPUnit.
- `frontend/package.json` - confirms Next 16, React 19, Tailwind, Vitest.
- `backend/routes/api.php` - central role wiring; needs Admin budget read routes, forgot-password routes, and audit middleware coverage review.
- `backend/app/Http/Middleware/RoleMiddleware.php` - server-side role and active-user enforcement.
- `backend/app/Http/Middleware/AuditMiddleware.php` - request-level mutation logging; currently not applied to all important mutation groups.
- `backend/app/Models/Concerns/AuditsChanges.php` - model-level audit behavior and clinical redaction.
- `backend/app/Http/Controllers/Admin/AuditLogController.php` - Admin audit query/filter endpoint.
- `backend/app/Http/Resources/Admin/AuditLogResource.php` - audit payload returned to frontend.
- `frontend/app/admin/audit-logs/page.tsx` - Admin audit UI; needs subject filter mapping and event list alignment.
- `frontend/services/auditLogService.ts` - audit query parameter contract.
- `backend/app/Http/Controllers/Auth/AuthController.php` - login, logout, profile, password change; needs audit events, reset flows, token revocation.
- `backend/app/Http/Requests/Auth/LoginRequest.php` - login validation.
- `backend/app/Http/Requests/Auth/UpdateProfileRequest.php` - profile validation; needs safer photo rules or move to upload endpoint.
- `backend/app/Http/Requests/Auth/UpdatePasswordRequest.php` - current password + min:8; can add stronger password rule.
- `backend/config/auth.php` - password reset broker already configured.
- `backend/database/migrations/2024_01_01_000001_create_users_table.php` - includes `password_reset_tokens`, so forgot-password DB storage exists.
- `backend/database/migrations/2026_06_25_000001_add_profile_fields_to_users_table.php` - adds `contact_number` and `profile_photo`.
- `backend/app/Models/User.php` - fillable profile fields and auth model; needs audit consideration without leaking password hashes.
- `frontend/app/login/page.tsx` - add forgot-password link and reset flow entry point.
- `frontend/app/api/auth/login/route.ts` - sets token/role cookies; logout cleanup must mirror it.
- `frontend/app/api/auth/logout/route.ts` - clears token only today; should also clear role cookie.
- `frontend/middleware.ts` - UX redirect guard only; do not treat as authorization.
- `frontend/contexts/AuthContext.tsx` - auth state source for RND/Admin layouts.
- `frontend/services/authService.ts` - add forgot/reset password service calls.
- `frontend/app/(rnd)/profile/page.tsx` - current reusable RND profile surface.
- `frontend/app/admin/profile/page.tsx` - near-duplicate Admin profile surface; should share a component.
- `frontend/components/layout/TopBar.tsx` - profile entry point and logout trigger; can surface security state later.
- `backend/app/Http/Controllers/FSS/BudgetController.php` - fiscal year budget list/summary/ledger/setup/adjust endpoints.
- `backend/app/Models/Budget.php` - fiscal year allocation model; needs audit trait or dedicated audit event.
- `backend/app/Models/BudgetLedger.php` - append-only ledger model; needs audit trait or dedicated audit event.
- `backend/app/Listeners/BudgetLedgerListener.php` - creates PO deduction ledger entries; should audit system deductions and warning cases.
- `backend/app/Http/Resources/BudgetResource.php` - three-card budget summary data.
- `frontend/app/(rnd)/food-service/budget/page.tsx` - exact UI to reuse for Admin read-only page.
- `frontend/services/budgetService.ts` - budget client calls; should support `prefix: "fss" | "admin"` like settings already does.
- `frontend/app/api/fss/budgets/**` - existing Next proxies for budget endpoints.
- `frontend/components/layout/Sidebar.tsx` - add Admin Budget navigation after Reports or before Audit Logs.
- `frontend/components/layout/MobileBottomNav.tsx` - decide whether Admin Budget is in mobile tabs or reachable through More only.
- `backend/tests/Feature/AdminAuditLogTest.php` - proves backend audit filtering and clinical redaction.
- `backend/tests/Feature/AuditMiddlewareTest.php` - proves mutation request logging, but only for routes using middleware.
- `backend/tests/Feature/BudgetLedgerTest.php` - proves ledger domain behavior and FSS write denial.
- `frontend/app/api/fss/budgets/budget-routes.test.ts` - proves budget proxy wiring.
- `frontend/app/(rnd)/food-service/budget/placement.test.ts` - proves current budget page shape.
- `docs/modules/admin.md` - intended Admin scope; must be updated where stale.
- `docs/modules/rnd.md` - current RND food-service and profile expectations.

## Recommended Settings By Role

Settings should be split into **enforced server settings** and **local UI preferences**. Do not add a visible setting unless it either changes backend behavior or clearly controls local UI.

### Shared All Roles

- **Profile/account:** full name, email, contact number, profile photo, password change.
- **Security:** active sessions/devices list, sign out all devices, last password change timestamp, recent login history.
- **Preferences:** density, reduced motion, notification toggles, timezone/date format if reports and audit timestamps need local display control.
- **Notification delivery:** in-app announcement alerts and task/reminder alerts. Keep actual notification creation server-side.
- **Push delivery:** web/mobile push can exist without email. Push should be a delivery channel for existing in-app notifications, not a replacement for the `notifications` table.

### Admin

- **Hospital branding:** hospital name, service name, address, accreditation, province, LGU, left/right logos.
- **Food-service finance:** budget per head/day, Admin read-only fiscal-year budget page, selected fiscal year view.
- **AI usage policy:** daily/monthly token limits, endpoint-specific caps, disable AI feature flags during outage.
- **Security policy display/enforcement:** token lifetime, password strength, password reset throttle, login throttle, session revocation controls.
- **Audit retention/export:** retention window, audit export/download, high-risk event filters.
- **User management defaults:** default active status for new users, allowed roles, password reset requirement on first login if implemented.
- **Push delivery policy:** require critical/security push alerts, allow opt-out for announcements and routine workflow reminders.

### RND

- **Food-service settings:** budget per head/day if still allowed for RND, menu-cycle defaults, report branding read-only preview.
- **Clinical/report profile:** full name used as report preparer, designation, license/registration number if hospital forms require it, contact number.
- **Workflow preferences:** default dashboard filters, preferred units, report date range defaults.
- **Notifications:** follow-up reminders, announcement alerts, procurement/open-execution alerts.

### FSS

- **Mobile profile:** full name, contact number, role/designation, profile photo if supported by mobile UI.
- **Operational preferences:** default meal period/ward assignment if the product has stable ward ownership, compact mode, notification toggles.
- **Security:** password change, sign out all devices, recent login history.
- **No finance mutation settings:** FSS can view operational budget/ledger where allowed, but should not edit fiscal-year allocation or manual adjustments.

## Push Notifications Without Email

Current state is **in-app only**: backend writes rows to `notifications`, then web/mobile fetch `/api/notifications`. There is no browser Push API, service worker, Expo push token, or email notification sender wired today.

Recommended approach:

- Keep `notifications` as the source of truth for every message.
- Add push as an optional delivery channel.
- Do not require email for push.
- Send push only after the in-app row is created.
- Respect backend-stored notification preferences for optional categories.
- Never allow users to opt out of critical/security alerts unless policy explicitly permits it.

Backend additions:

- Create `push_tokens` table:
  - `id`
  - `user_id`
  - `platform` (`web`, `ios`, `android`)
  - `token`
  - `device_name`
  - `last_used_at`
  - `disabled_at`
  - timestamps
- Create `notification_preferences` table or JSON column:
  - `user_id`
  - `announcements`
  - `follow_ups`
  - `procurement`
  - `budget`
  - `security_required` should not be user-editable
- Add authenticated endpoints:
  - `GET /api/notification-preferences`
  - `PUT /api/notification-preferences`
  - `POST /api/push-tokens`
  - `DELETE /api/push-tokens/{pushToken}`
- Add queued job `SendPushNotification` that sends after `NotificationService::notify()` creates DB rows.
- If push send fails, mark the push token disabled when provider says token is invalid, but keep the in-app notification row.

Web push path:

- Add browser permission request in web settings.
- Add service worker for push handling.
- Create browser push subscription.
- Save subscription endpoint/key material through `POST /api/push-tokens`.
- Use Web Push protocol from backend.

Mobile push path:

- Use Expo push notifications.
- App asks permission.
- App obtains Expo push token.
- Save Expo token through `POST /api/push-tokens`.
- Backend sends to Expo Push API from queued job.

User-facing settings:

- Web/Admin/RND settings: checkbox or switch for push enabled on this browser.
- Mobile/FSS settings: switch for push enabled on this device.
- Category toggles:
  - Announcements
  - Follow-up reminders
  - Procurement/order alerts
  - Budget alerts
  - Security/account alerts shown as required, not optional

Important rule:

- Turning off a category should stop both in-app row creation and push delivery for that optional category. Filtering only in the UI is not enough because unread counts and mobile still see hidden rows.

## Recommended Profile Fields

Minimum shared fields:

- `name` - required; used for audit actor labels and report prepared-by names.
- `email` - required, unique; login identifier.
- `contact_number` - optional; useful for staff coordination.
- `profile_photo` - optional; must be stored as validated image path or tightly size-limited data URL.
- `role` - read-only in self-profile; Admin changes it through user management only.

Role-specific fields worth adding only if forms need them:

- RND: `designation`, `license_number`, `department`.
- FSS: `designation`, `assigned_area` or `ward` only if assignment is stable.
- Admin: `designation`, `department`.

Fields to avoid for now:

- Free-form bio, address, birthday, and personal identifiers with no workflow/report consumer.
- Raw base64 photo storage without MIME/size validation.
- Role self-editing.

---

## Task 1: Add Admin Read-Only Budget Backend Routes

**Files:**
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Http/Controllers/FSS/BudgetController.php` only if a separate method name is needed; prefer reusing `index`, `show`, `summary`, and `ledger`.
- Test: `backend/tests/Feature/AdminBudgetReadOnlyTest.php`

- [ ] Add Admin routes under the existing `Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('admin')` group:

```php
Route::get('budgets/summary', [BudgetController::class, 'summary']);
Route::get('budgets/ledger', [BudgetController::class, 'ledger']);
Route::apiResource('budgets', BudgetController::class)->only(['index', 'show']);
```

- [ ] Do not add Admin POST/PUT/PATCH/DELETE budget routes.

- [ ] Write PHPUnit coverage:

```php
public function test_admin_can_read_budget_years_summary_and_ledger(): void
{
    $admin = User::factory()->create(['role' => 'Admin']);
    Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);
    BudgetLedger::create([
        'fiscal_year' => 2026,
        'type' => 'manual_addition',
        'source' => 'manual',
        'amount' => 5000,
        'reason' => 'Supplemental allocation',
    ]);

    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/budgets')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/budgets/summary?fiscal_year=2026')
        ->assertOk()
        ->assertJsonPath('data.fiscal_year', 2026);
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/budgets/ledger?fiscal_year=2026')
        ->assertOk()
        ->assertJsonPath('data.0.source', 'manual');
}
```

- [ ] Add denial test:

```php
public function test_admin_cannot_mutate_budgets(): void
{
    $admin = User::factory()->create(['role' => 'Admin']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/budgets', ['fiscal_year' => 2026, 'allocated_amount' => 10000])
        ->assertNotFound();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/budgets/adjust', ['fiscal_year' => 2026, 'type' => 'manual_addition', 'amount' => 1, 'reason' => 'x'])
        ->assertNotFound();
}
```

- [ ] Run:

```bash
cd backend
php artisan test --filter=AdminBudgetReadOnlyTest
```

Expected: all tests pass.

## Task 2: Add Admin Budget Next Proxies

**Files:**
- Create: `frontend/app/api/admin/budgets/route.ts`
- Create: `frontend/app/api/admin/budgets/[id]/route.ts`
- Create: `frontend/app/api/admin/budgets/summary/route.ts`
- Create: `frontend/app/api/admin/budgets/ledger/route.ts`
- Modify: `frontend/services/budgetService.ts`
- Test: `frontend/app/api/admin/budgets/budget-routes.test.ts`

- [ ] Mirror existing FSS proxy pattern, but point to `/admin/budgets*`.

```ts
import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  const search = new URL(req.url).searchParams;
  return proxy("/admin/budgets/summary", { search });
}
```

- [ ] Update `budgetService.ts` so read calls accept prefix:

```ts
export type BudgetApiPrefix = "fss" | "admin";

export async function listFiscalYears(prefix: BudgetApiPrefix = "fss"): Promise<FiscalYearBudget[]> {
  return unwrap(await apiFetch(`/api/${prefix}/budgets`), "Failed to load budgets.");
}

export async function getFiscalYearSummary(
  fiscalYear: number,
  prefix: BudgetApiPrefix = "fss",
): Promise<{ data: FiscalYearSummary | null; notice?: string }> {
  const res = await apiFetch(`/api/${prefix}/budgets/summary?fiscal_year=${fiscalYear}`);
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((json as { message?: string }).message ?? "Failed to load summary.");
  return json as { data: FiscalYearSummary | null; notice?: string };
}
```

- [ ] Keep write methods `setupFiscalYear` and `addManualAdjustment` on `/api/fss/*` only.

- [ ] Add Vitest proxy tests matching `frontend/app/api/fss/budgets/budget-routes.test.ts`.

- [ ] Run:

```bash
cd frontend
npm test -- app/api/admin/budgets/budget-routes.test.ts
```

Expected: proxies call `/admin/budgets`, `/admin/budgets/summary`, and `/admin/budgets/ledger`.

## Task 3: Extract Shared Budget Page And Add Admin Read-Only Page

**Files:**
- Create: `frontend/components/budget/BudgetPageShell.tsx`
- Modify: `frontend/app/(rnd)/food-service/budget/page.tsx`
- Create: `frontend/app/admin/budget/page.tsx`
- Modify: `frontend/components/layout/Sidebar.tsx`
- Modify: `frontend/components/layout/TopBar.tsx`
- Test: `frontend/app/admin/budget/page.test.ts`
- Test: update `frontend/app/(rnd)/food-service/budget/placement.test.ts`

- [ ] Move reusable budget UI from RND page into `BudgetPageShell`.

- [ ] Component contract:

```ts
type BudgetPageShellProps = {
  apiPrefix: "fss" | "admin";
  canMutate: boolean;
  crumbs: [string, string?][];
  homeHref: string;
};
```

- [ ] RND page becomes:

```tsx
import { BudgetPageShell } from "@/components/budget/BudgetPageShell";

export default function BudgetPage() {
  return (
    <BudgetPageShell
      apiPrefix="fss"
      canMutate={true}
      crumbs={[["Home", "/dashboard"], ["Food Service"], ["Budget"]]}
      homeHref="/dashboard"
    />
  );
}
```

- [ ] Admin page becomes:

```tsx
import { BudgetPageShell } from "@/components/budget/BudgetPageShell";

export default function AdminBudgetPage() {
  return (
    <BudgetPageShell
      apiPrefix="admin"
      canMutate={false}
      crumbs={[["Admin", "/admin/dashboard"], ["Budget"]]}
      homeHref="/admin/dashboard"
    />
  );
}
```

- [ ] In `BudgetPageShell`, show Year Selector, Summary, and Ledger for Admin exactly like RND.

- [ ] In `BudgetPageShell`, render Fiscal Year Setup and Manual Adjustment only when `canMutate === true`.

- [ ] Add Admin sidebar link:

```tsx
<Link href="/admin/budget">...</Link>
```

Use a money/ledger icon from `lucide-react`, such as `Wallet`.

- [ ] Add TopBar title mapping:

```ts
if (pathname.startsWith("/admin/budget")) return "Budget Oversight";
```

- [ ] Test source shape:

```ts
expect(source).toContain('apiPrefix="admin"');
expect(source).toContain('canMutate={false}');
```

- [ ] Run:

```bash
cd frontend
npm test -- app/admin/budget/page.test.ts app/(rnd)/food-service/budget/placement.test.ts
```

Expected: Admin page references shared shell in read-only mode; RND placement tests still pass.

## Task 4: Fix Audit Filter Contract And Event Expectations

**Files:**
- Modify: `frontend/app/admin/audit-logs/page.tsx`
- Modify: `frontend/services/auditLogService.ts` if stricter event types are useful.
- Test: `frontend/app/admin/audit-logs/audit-filter-contract.test.ts`

- [ ] Replace short subject values with backend class names.

```ts
const uniqueSubjectTypes = [
  { label: "Patient", value: "App\\Models\\Patient" },
  { label: "User Account", value: "App\\Models\\User" },
  { label: "NCP Assessment", value: "App\\Models\\Assessment" },
  { label: "NCP Diagnosis", value: "App\\Models\\Diagnosis" },
  { label: "NCP Intervention", value: "App\\Models\\Intervention" },
  { label: "NCP Monitoring", value: "App\\Models\\Monitoring" },
  { label: "Menu Cycle", value: "App\\Models\\MenuCycle" },
  { label: "Purchase Order", value: "App\\Models\\PurchaseOrder" },
  { label: "Budget", value: "App\\Models\\Budget" },
  { label: "Budget Ledger", value: "App\\Models\\BudgetLedger" },
  { label: "Meal Prep Log", value: "App\\Models\\MealPrepLog" },
  { label: "FsItem", value: "App\\Models\\FsItem" },
];
```

- [ ] Keep display code that uses `log.subject_type.split("\\").pop()`; it already converts class names to readable labels.

- [ ] Keep login/logout filters only after backend writes those events in Task 5. If Task 5 is deferred, hide those event options to avoid false promise.

- [ ] Run frontend test for source mapping.

Expected: UI sends values backend can actually filter.

## Task 5: Add Security Audit Events For Login, Logout, Passwords, And Admin User Actions

**Files:**
- Modify: `backend/app/Http/Controllers/Auth/AuthController.php`
- Modify: `backend/app/Http/Controllers/Admin/UserController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/AuthAuditEventTest.php`
- Test: `backend/tests/Feature/AdminUserAuditEventTest.php`

- [ ] In `AuthController::login`, write `login_failed` on invalid credentials/inactive/wrong platform using no password and no raw token.

- [ ] In `AuthController::login`, write `login` after token creation:

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

- [ ] In `logout`, write `logout` before deleting current access token.

- [ ] In `updatePassword`, write `password_changed`, then revoke other tokens for the user except current token or revoke all and force re-login based on product decision. Recommended: revoke all other tokens.

- [ ] In `Admin\UserController::resetPassword`, write `password_reset` caused by Admin and performed on target user.

- [ ] In Admin create/update/delete user methods, write events with changed field names only. Never store password hashes.

- [ ] Add tests asserting activity rows exist and do not contain password values or token strings.

- [ ] Run:

```bash
cd backend
php artisan test --filter=AuthAuditEventTest
php artisan test --filter=AdminUserAuditEventTest
```

Expected: audit events are present and sanitized.

## Task 6: Audit Budget And Food-Service Financial Mutations

**Files:**
- Modify: `backend/app/Models/Budget.php`
- Modify: `backend/app/Models/BudgetLedger.php`
- Modify: `backend/app/Listeners/BudgetLedgerListener.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/BudgetAuditTest.php`

- [ ] Add `AuditsChanges` to `Budget` and `BudgetLedger`, or emit dedicated activity events in controller/listener. Recommended: trait for `Budget`; dedicated events for `BudgetLedger` because it is append-only and benefits from domain descriptions.

- [ ] Add `audit` middleware to `/api/fss` RND-only write subgroup or individual financial mutation routes:

```php
Route::middleware(['role:RND', 'audit'])->group(function () {
    Route::post('budgets/adjust', [BudgetController::class, 'manualAdjust']);
    Route::apiResource('budgets', BudgetController::class)->only(['store']);
});
```

- [ ] In `BudgetLedgerListener`, write an audit event when PO deduction is created:

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

- [ ] Test RND fiscal setup and manual adjust create audit rows.

- [ ] Test PO completion deduction creates one audit row and remains idempotent.

- [ ] Run:

```bash
cd backend
php artisan test --filter=BudgetAuditTest
php artisan test --filter=BudgetLedgerTest
```

Expected: budget domain behavior unchanged, audit coverage added.

## Task 7: Implement Forgot Password And Reset Password

**Files:**
- Create: `backend/app/Http/Requests/Auth/ForgotPasswordRequest.php`
- Create: `backend/app/Http/Requests/Auth/ResetForgottenPasswordRequest.php`
- Modify: `backend/app/Http/Controllers/Auth/AuthController.php` or create `backend/app/Http/Controllers/Auth/PasswordResetController.php`
- Modify: `backend/routes/api.php`
- Create: `frontend/app/forgot-password/page.tsx`
- Create: `frontend/app/reset-password/page.tsx`
- Create: `frontend/app/api/auth/forgot-password/route.ts`
- Create: `frontend/app/api/auth/reset-password/route.ts`
- Modify: `frontend/app/login/page.tsx`
- Modify: `frontend/services/authService.ts`
- Test: `backend/tests/Feature/ForgotPasswordTest.php`
- Test: frontend route/service tests if project pattern supports it.

- [ ] Add unauthenticated routes:

```php
Route::prefix('auth')->group(function () {
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:password-reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset');
});
```

- [ ] Add rate limiter:

```php
RateLimiter::for('password-reset', function (Request $request) {
    $email = Str::transliterate(Str::lower((string) $request->input('email')));
    return Limit::perMinute(3)->by($email.'|'.$request->ip());
});
```

- [ ] Use Laravel password broker:

```php
$status = Password::sendResetLink($request->validated());
```

- [ ] Always return a generic success message for forgot-password to avoid email enumeration:

```json
{ "message": "If that email exists, a password reset link has been sent." }
```

- [ ] On reset success, hash password and revoke all existing tokens for that user.

- [ ] Login page adds a `Forgot password?` link below password field.

- [ ] Reset page accepts `token` and `email` from query string.

- [ ] Run:

```bash
cd backend
php artisan test --filter=ForgotPasswordTest
```

Expected: reset tokens work, expired/invalid tokens fail, response does not reveal unknown emails.

## Task 8: Harden Profile Photo And Profile Contents

**Files:**
- Modify: `backend/app/Http/Requests/Auth/UpdateProfileRequest.php`
- Modify: `backend/app/Http/Controllers/Auth/AuthController.php`
- Consider migration: `backend/database/migrations/YYYY_MM_DD_HHMMSS_change_profile_photo_to_path_on_users_table.php`
- Modify: `frontend/app/(rnd)/profile/page.tsx`
- Modify: `frontend/app/admin/profile/page.tsx`
- Prefer create shared: `frontend/components/profile/ProfilePageShell.tsx`
- Test: `backend/tests/Feature/ProfileTest.php`

- [ ] Stop accepting unlimited arbitrary string for `profile_photo`.

- [ ] Recommended durable approach: store uploaded image file on public disk and save path/URL in `users.profile_photo`.

- [ ] If keeping data URLs short-term, validate exact pattern and max size:

```php
'profile_photo' => ['nullable', 'string', 'max:300000', 'regex:/^data:image\\/(png|jpeg|webp);base64,/'],
```

- [ ] Add profile content fields only if they are useful downstream:
  - `position_title` or `designation` for report signatories.
  - `license_number` for RND if needed on clinical reports.
  - `department` for Admin/FSS display and audit context.

- [ ] Do not add fields that have no display/report/audit consumer.

- [ ] Extract duplicate RND/Admin profile pages into a shared `ProfilePageShell`.

- [ ] Run:

```bash
cd backend
php artisan test --filter=ProfileTest
```

Expected: profile updates still work, oversize/invalid image data fails.

## Task 9: Token And Login Security Cleanup

**Files:**
- Modify: `backend/config/sanctum.php`
- Modify: `backend/app/Http/Controllers/Auth/AuthController.php`
- Modify: `frontend/app/api/auth/logout/route.ts`
- Modify: `frontend/app/api/auth/login/route.ts`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Feature/AuthFeatureTest.php`

- [ ] Set finite Sanctum expiration through env:

```php
'expiration' => env('SANCTUM_EXPIRATION', 60 * 24 * 7),
```

- [ ] Ensure frontend cookie max age does not exceed backend token expiration.

- [ ] In logout proxy, delete both cookies:

```ts
res.cookies.delete("nutriscope_token");
res.cookies.delete("nutriscope_role");
```

- [ ] On password change and admin reset, revoke existing Sanctum tokens.

- [ ] Upgrade password validation from `min:8` to Laravel `Password::defaults()` or explicit rule:

```php
Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised()
```

- [ ] Keep login error message generic.

- [ ] Consider optional 2FA for Admin after above basics are complete. Do not start with 2FA before token reset, audit events, and forgot password are done.

- [ ] Run:

```bash
cd backend
php artisan test --filter=AuthFeatureTest
```

Expected: login/logout still work, old tokens fail after password changes, cookies cleanly clear.

## Task 10: Admin UX Improvements After Security Baseline

**Files:**
- Modify: `frontend/app/admin/dashboard/page.tsx`
- Modify: `frontend/app/admin/users/page.tsx`
- Modify: `frontend/app/admin/settings/page.tsx`
- Modify: `frontend/app/admin/audit-logs/page.tsx`
- Modify: `frontend/services/adminDashboardService.ts`
- Modify backend Admin dashboard resource/controller if new metrics are needed.

- [ ] Add Admin dashboard cards for:
  - Failed logins last 24 hours.
  - Deactivated users count.
  - Password reset count last 7 days.
  - AI token usage and limit status.
  - Budget remaining for selected fiscal year.

- [ ] Add user management filters:
  - Role.
  - Active/deactivated.
  - Search name/email.
  - Recently created.

- [ ] Add high-risk action confirmations:
  - Deactivate user.
  - Role change to Admin.
  - Password reset.

- [ ] Add Admin settings for:
  - AI usage limits, already backed by `/api/admin/ai-usage-limits`.
  - Food-service per-head/day, already present.
  - Branding, already present.
  - Security policy display: token lifetime, password rules, reset throttles. Store in config or read-only UI first.

- [ ] Do not add settings that lack backend enforcement.

## Task 11: Documentation Update

**Files:**
- Modify: `docs/modules/admin.md`
- Modify: `docs/modules/rnd.md`
- Modify or create: `docs/security/security.md`

- [ ] Update Admin doc to reflect:
  - Admin budget page exists and is read-only.
  - Admin audit shows login/logout/password/user/budget events after implementation.
  - Profile photo is supported only under validated storage rules.

- [ ] Update RND doc to remove stale "basic profile stuff" and define exact supported fields.

- [ ] Update security doc with:
  - Login throttling.
  - Forgot-password flow.
  - Token expiration.
  - Token revocation on password change/reset.
  - Audit event inventory.

## Verification Matrix

- [ ] Backend auth/security:

```bash
cd backend
php artisan test --filter=AuthFeatureTest
php artisan test --filter=ForgotPasswordTest
php artisan test --filter=AuthAuditEventTest
```

- [ ] Backend admin/audit/budget:

```bash
cd backend
php artisan test --filter=AdminAuditLogTest
php artisan test --filter=AdminBudgetReadOnlyTest
php artisan test --filter=BudgetAuditTest
php artisan test --filter=BudgetLedgerTest
```

- [ ] Frontend budget and proxy tests:

```bash
cd frontend
npm test -- app/api/admin/budgets/budget-routes.test.ts app/api/fss/budgets/budget-routes.test.ts
npm test -- app/admin/budget/page.test.ts app/(rnd)/food-service/budget/placement.test.ts
```

- [ ] Static checks:

```bash
cd backend
vendor/bin/pint --test
cd ../frontend
npx tsc --noEmit
npm run lint
```

## Priority Order

1. Admin read-only budget routes/proxies/page.
2. Audit contract fixes: subject filter mapping plus login/logout/password/admin-user events.
3. Budget audit coverage.
4. Forgot-password flow.
5. Profile photo validation/storage cleanup.
6. Token/session hardening.
7. Admin UX/settings/dashboard improvements.
8. Docs update.

## Notes From Laravel Boost

Boost exposed recent log entries only in this session. Logs showed testing noise around AI upstream failures/token caps and this warning:

```text
BudgetLedgerListener: No Budget allocation for fiscal year 2099. PO 3 deduction skipped
```

That warning matches existing tests for missing fiscal-year allocation and should not be treated as a production wiring failure by itself.
