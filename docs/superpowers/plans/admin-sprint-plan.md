# Admin Console Sprint — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Admin role's frontend (RBAC user manager, audit-log browser, settings, announcements, dashboard), and fix the backend gaps that block it (unpaginated/unfiltered audit log, missing password-reset).

**Architecture:** Backend exposes `/api/admin/*` behind `auth:sanctum, role:Admin`. This sprint hardens the audit-log endpoint with pagination + filters, adds an admin password-reset action, then scaffolds a Next.js `(admin)` route group mirroring the existing `(rnd)` layout, with a Dashboard, Users/RBAC page, Audit-Log browser, Settings, and Announcements.

**Tech Stack:** Laravel 13 + Sanctum + Spatie activitylog (backend); Next.js 16 (App Router), Tailwind, lucide-react (frontend).

**Conventions (project):** work on `main`; **NO `Co-Authored-By`** (author = jared). Verify: `cd backend && php artisan test` (sqlite, 442 baseline) + `cd frontend && npx tsc --noEmit`. Dev login `rnd@nutriscope.local` / `nutriscope2024!`.

---

## File Structure

**Backend**
- `backend/app/Http/Controllers/Admin/AuditLogController.php` — add pagination + filters (currently returns `Activity::...->get()` raw — no Resource, no pagination, no redaction).
- `backend/app/Http/Resources/Admin/AuditLogResource.php` (create) — shapes each activity row; does **not** exist yet but Task 1 returns `AuditLogResource::collection(...)`.
- `backend/app/Http/Controllers/Admin/UserController.php` — add `resetPassword` (class is `UserController`, **not** `AdminUserController`).
- `backend/app/Http/Requests/AdminResetPasswordRequest.php` (create) — password rules for the reset action.
- `backend/routes/api.php` — register the password-reset route.
- `backend/tests/Feature/Admin/AuditLogTest.php` (create) — pagination/filter coverage.
- `backend/tests/Feature/Admin/UserManagementTest.php` (create/extend) — RBAC + password reset.

**Frontend** (new `(admin)` route group)
- `frontend/app/(admin)/layout.tsx` — admin shell (clone `(rnd)/layout.tsx`, admin nav).
- `frontend/app/(admin)/dashboard/page.tsx` — Dashboard (KPIs, token usage chart).
- `frontend/app/(admin)/users/page.tsx` — user/RBAC manager.
- `frontend/app/(admin)/audit-logs/page.tsx` — audit browser.
- `frontend/app/(admin)/settings/page.tsx` — hospital branding settings.
- `frontend/app/(admin)/announcements/page.tsx` — system announcements manager.
- `frontend/services/adminUserService.ts` — users CRUD + reset password.
- `frontend/services/auditLogService.ts` — paginated/filtered fetch.

---

## Task 0: Confirm/seed an Admin login (prereq)

- [ ] **Step 1:** Check for an existing Admin user: `cd backend && php artisan tinker --execute="echo App\Models\User::where('role','Admin')->value('email');"`
- [ ] **Step 2:** If none, add an Admin to the dev seeder (mirror the RND demo user) with a known password, run `php artisan db:seed`.
- [ ] **Step 3:** Verify login returns a token: `curl -X POST .../api/login` (or via the app) with the Admin creds.

---

## Task 1: Paginate + filter the audit-log endpoint (Backend)

**Files:** `backend/app/Http/Controllers/Admin/AuditLogController.php`, `backend/tests/Feature/Admin/AuditLogTest.php`

- [ ] **Step 1: Write the failing test**
```php
<?php
namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_is_paginated_and_filterable(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        Patient::factory()->count(20)->create();

        $res = $this->actingAs($admin)->getJson('/api/admin/audit-logs?per_page=10');

        $res->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
        $this->assertCount(10, $res->json('data'));
    }
}
```

- [ ] **Step 2: Run, verify it fails** (`php artisan test --filter=AuditLogTest`).
- [ ] **Step 3: Implement pagination + filters**
```php
public function index(\Illuminate\Http\Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
{
    $data = $request->validate([
        'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        'causer_id'    => ['nullable', 'integer'],
        'subject_type' => ['nullable', 'string'],
        'start'        => ['nullable', 'date'],
        'end'          => ['nullable', 'date'],
    ]);

    $query = \Spatie\Activitylog\Models\Activity::with('causer')
        ->when($data['causer_id'] ?? null, fn ($q, $v) => $q->where('causer_id', $v))
        ->when($data['subject_type'] ?? null, fn ($q, $v) => $q->where('subject_type', 'like', "%{$v}%"))
        ->when($data['start'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
        ->when($data['end'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
        ->latest();

    $page = $query->paginate($data['per_page'] ?? 25);

    // CRITICAL: Redact PHI here (backend) before sending to the frontend.
    // NOTE: 'phi_fields_to_redact' below is a PLACEHOLDER — replace with the real
    // per-model field list (e.g. patient name, contact, address, medical_diagnosis,
    // lab values). Prefer an allow-list of safe keys over a deny-list so a new PHI
    // column added later isn't leaked by default.
    $page->getCollection()->transform(function ($activity) {
        if (in_array($activity->subject_type, [\App\Models\Assessment::class, \App\Models\Intervention::class, \App\Models\Monitoring::class])) {
            $activity->properties = collect($activity->properties)->except(['phi_fields_to_redact'])->toArray();
        }
        return $activity;
    });

    return \App\Http\Resources\Admin\AuditLogResource::collection($page);
}
```

- [ ] **Step 4: Run, verify it passes**, then commit `feat(admin): paginate + filter audit-log endpoint`.

---

## Task 2: Admin password-reset action (Backend)

**Files:** `backend/app/Http/Controllers/Admin/UserController.php`, `backend/routes/api.php`, `backend/tests/Feature/Admin/UserManagementTest.php`

- [ ] **Step 1: Write the failing test**
```php
public function test_admin_can_reset_user_password(): void
{
    $admin = \App\Models\User::factory()->create(['role' => 'Admin']);
    $user  = \App\Models\User::factory()->create();

    $res = $this->actingAs($admin)->postJson("/api/admin/users/{$user->id}/reset-password", [
        'password' => 'NewPass2026!',
    ]);

    $res->assertOk();
    $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPass2026!', $user->fresh()->password));
}
```

- [ ] **Step 2: Add route** inside `backend/routes/api.php` admin group (ensure rate limiting):
```php
Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
    ->middleware('throttle:6,1');
```

- [ ] **Step 3: Add controller action with Form Request**
```php
// In Admin/UserController.php
public function resetPassword(\App\Http\Requests\AdminResetPasswordRequest $request, \App\Models\User $user): \Illuminate\Http\JsonResponse
{
    $user->update(['password' => \Illuminate\Support\Facades\Hash::make($request->validated('password'))]);
    return response()->json(['message' => 'Password reset.']);
}
```

- [ ] **Step 4: Run, verify it passes**, then commit `feat(admin): admin password-reset for users`.

---

## Task 3: Frontend Service Layer

**Files:** `frontend/services/adminUserService.ts`, `frontend/services/auditLogService.ts`

- [ ] **Step 1:** Create `adminUserService.ts` copying the fetch/auth wrapper from `announcementService.ts`. Add `listUsers`, `createUser`, `updateUser`, `resetPassword`, `setActive`.
- [ ] **Step 2:** Create `auditLogService.ts` for `listAuditLogs` returning paginated meta.
- [ ] **Step 3:** Run `cd frontend && npx tsc --noEmit` to ensure clean types.
- [ ] **Step 4: Commit** `feat(admin): user + audit-log frontend services`.

---

## Task 4: Login Redirect & Admin Shell

**Files:** `frontend/app/login/page.tsx`, `frontend/app/(admin)/layout.tsx`, `frontend/components/layout/Sidebar.tsx`

- [ ] **Step 1: Role-based redirect:** In `login/page.tsx`, redirect `Admin` to `/admin/dashboard`, else `/dashboard`.
- [ ] **Step 2: Admin Shell:** Clone `(rnd)/layout.tsx` to `(admin)/layout.tsx`; add role guard `if (user?.role !== 'Admin') router.replace('/dashboard')`.
- [ ] **Step 3: Admin Sidebar:** Sidebar already has Admin nav entries. Verify they render correctly for Admin users.
- [ ] **Step 4: Verify** login routing works for Admin and RND.
- [ ] **Step 5: Commit** `feat(admin): login routing and admin shell`.

---

## Task 5: Admin Dashboard

**Files:** `frontend/app/(admin)/dashboard/page.tsx`

- [ ] **Step 1: KPI Cards:** Active Users, Total Logins, Error Rates. (Backend aggregate endpoints required).
- [ ] **Step 2: Token Usage Chart:** Use Recharts mapping AI API costs over time from `ai_usage_logs`.
- [ ] **Step 3: Activity Feed:** Recent system actions feed from audit logs.
- [ ] **Step 4: Commit** `feat(admin): dashboard kpis and charts`.

---

## Task 6: Users / RBAC Manager Page

**Files:** `frontend/app/(admin)/users/page.tsx`

- [ ] **Step 1: Data Table:** Name, Email, Role badge, Active/Inactive toggle. Reuse table styling from `budget/page.tsx`.
- [ ] **Step 2: Modal:** Create/Edit form. Name, Email, Password, Role select (Admin/RND/FSS), Active toggle.
- [ ] **Step 3: Security:** Never auto-create credentials; force explicit Admin creation.
- [ ] **Step 4: Commit** `feat(admin): user manager page`.

---

## Task 7: Audit-log Browser Page

**Files:** `frontend/app/(admin)/audit-logs/page.tsx`

- [ ] **Step 1: Filter bar:** Date range, Actor, Model type dropdown.
- [ ] **Step 2: Paginated Table:** UI driven by `meta.last_page`. Expandable rows for JSON payloads.
- [ ] **Step 3: Security:** Ensure PHI (Protected Health Information) is redacted safely in the UI (description field only).
- [ ] **Step 4: Commit** `feat(admin): audit log browser`.

---

## Task 8: Settings & Announcements

**Files:** `frontend/app/(admin)/settings/page.tsx`, `frontend/app/(admin)/announcements/page.tsx`

- [ ] **Step 1: Settings:** Hospital Info tab (Name, Logo upload, Address). Wire to `report-branding` endpoints so PDF headers update.
- [ ] **Step 2: Clinical Rules Configuration:** CRUD page for `clinical_rules` table so chief dietitians can dynamically update disease-to-nutrient mappings.
- [ ] **Step 3: Announcements:** List view of active/past announcements. Form: Title, Content, Visibility (FSS/Admin/All), Pin toggle.
- [ ] **Step 4: Type check:** `npx tsc --noEmit`.
- [ ] **Step 5: Commit** `feat(admin): settings, clinical rules and announcements UI`.

---

## Self-Review notes
- **Next.js caveat:** `frontend/AGENTS.md` says this Next.js has breaking changes — read `node_modules/next/dist/docs/` before writing any FE routing/layout code.
- **Verify gate every task:** backend tasks run `php artisan test`; frontend tasks run `npx tsc --noEmit` + a browser check.
