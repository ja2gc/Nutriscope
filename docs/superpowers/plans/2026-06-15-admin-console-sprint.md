# Admin Console Sprint — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Admin role's first frontend (RBAC user manager + audit-log browser), and fix the backend gaps that block it (unpaginated/unfiltered audit log B5, missing password-reset), so an Admin can manage logins and review the change trail in-app.

**Architecture:** Backend already exposes `/api/admin/{users,announcements,audit-logs}` behind `auth:sanctum, role:Admin`. This sprint (1) hardens the audit-log endpoint with pagination + filters, (2) adds an admin password-reset action, then (3) scaffolds a new Next.js `(admin)` route group mirroring the existing `(rnd)` layout, with a Users/RBAC page and an Audit-Log browser. Settings + token-usage are stretch goals deferred to a follow-up.

**Tech Stack:** Laravel 11 + Sanctum + Spatie activitylog (backend); Next.js (App Router, see `frontend/AGENTS.md` — read `node_modules/next/dist/docs/` before writing FE code), Tailwind, lucide-react, the shared `Button`/service patterns under `frontend/services/`.

**Reference docs:** [`docs/modules/admin.md`](../../modules/admin.md) (intended scope), [`docs/reviews/2026-06-14-system-review.md`](../../reviews/2026-06-14-system-review.md) §B5/B6/B8.

**Conventions (project):** work on `main`; **NO `Co-Authored-By`** (author = jared). Verify: `cd backend && php artisan test` (sqlite, 442 baseline) + `cd frontend && npx tsc --noEmit`. Dev login `rnd@nutriscope.local` / `nutriscope2024!`; an Admin login must be confirmed/seeded before FE browser-testing (see Task 0).

---

## File Structure

**Backend**
- `backend/app/Http/Controllers/Admin/AuditLogController.php` — add pagination + filters (date range, causer, model).
- `backend/app/Http/Controllers/Admin/UserController.php` — add `resetPassword` action (or extend `update`).
- `backend/routes/api.php:221-224` — register the password-reset route.
- `backend/tests/Feature/Admin/AuditLogTest.php` (create) — pagination/filter coverage.
- `backend/tests/Feature/Admin/UserManagementTest.php` (create/extend) — RBAC + password reset + is_active gate.

**Frontend** (new `(admin)` route group)
- `frontend/app/(admin)/layout.tsx` — admin shell (clone `(rnd)/layout.tsx`, admin nav).
- `frontend/app/(admin)/dashboard/page.tsx` — minimal landing (KPIs stretch).
- `frontend/app/(admin)/users/page.tsx` — user/RBAC manager.
- `frontend/app/(admin)/audit-logs/page.tsx` — audit browser.
- `frontend/services/adminUserService.ts` — users CRUD + reset password.
- `frontend/services/auditLogService.ts` — paginated/filtered fetch.
- `frontend/components/layout/Sidebar.tsx` — admin nav entries (role-gated).

---

## Task 0: Confirm/seed an Admin login (prereq)

**Files:**
- Inspect: `backend/database/seeders/*`, `backend/database/factories/UserFactory.php`

- [ ] **Step 1:** Check for an existing Admin user: `cd backend && php artisan tinker --execute="echo App\Models\User::where('role','Admin')->value('email');"`
- [ ] **Step 2:** If none, add an Admin to the dev seeder (mirror the RND demo user) with a known password, run `php artisan db:seed`. Record creds in the [dev-login memory](file index).
- [ ] **Step 3:** Verify login returns a token: `curl -X POST .../api/login` (or via the app) with the Admin creds.

---

## Task 1: Paginate + filter the audit-log endpoint (B5)

**Files:**
- Modify: `backend/app/Http/Controllers/Admin/AuditLogController.php`
- Test: `backend/tests/Feature/Admin/AuditLogTest.php` (create)

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
        // generate >15 activities so a default page can't contain all of them
        Patient::factory()->count(20)->create();

        $res = $this->actingAs($admin)->getJson('/api/admin/audit-logs?per_page=10');

        $res->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
        $this->assertCount(10, $res->json('data'));
        $this->assertGreaterThan(1, $res->json('meta.last_page'));
    }

    public function test_audit_log_filters_by_subject_type(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        Patient::factory()->count(3)->create();

        $res = $this->actingAs($admin)
            ->getJson('/api/admin/audit-logs?subject_type=Patient');

        $res->assertOk();
        foreach ($res->json('data') as $row) {
            $this->assertStringContainsString('Patient', (string) $row['subject_type']);
        }
    }
}
```

- [ ] **Step 2: Run, verify it fails**

Run: `cd backend && php artisan test --filter=AuditLogTest`
Expected: FAIL (no `meta`, returns all rows).

- [ ] **Step 3: Implement pagination + filters**

```php
public function index(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
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

    return response()->json([
        'data' => $page->items(),
        'meta' => [
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
            'per_page'     => $page->perPage(),
        ],
    ]);
}
```

- [ ] **Step 4: Run, verify it passes**

Run: `cd backend && php artisan test --filter=AuditLogTest`
Expected: PASS.

- [ ] **Step 5: Confirm no regression** — `php artisan test` (≥442 + 2 new).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/Admin/AuditLogController.php backend/tests/Feature/Admin/AuditLogTest.php
git commit -m "feat(admin): paginate + filter audit-log endpoint (B5)"
```

---

## Task 2: Admin password-reset action

**Files:**
- Modify: `backend/app/Http/Controllers/Admin/UserController.php`, `backend/routes/api.php`
- Test: `backend/tests/Feature/Admin/UserManagementTest.php` (create/extend)

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

public function test_non_admin_cannot_reach_admin_users(): void
{
    $rnd = \App\Models\User::factory()->create(['role' => 'RND']);
    $this->actingAs($rnd)->getJson('/api/admin/users')->assertForbidden();
}
```

- [ ] **Step 2: Run, verify it fails** — `php artisan test --filter=UserManagementTest` → FAIL (route missing).

- [ ] **Step 3: Add route** in `backend/routes/api.php` inside the admin group (after line 223):

```php
Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
```

- [ ] **Step 4: Add controller action**

```php
public function resetPassword(\Illuminate\Http\Request $request, \App\Models\User $user): \Illuminate\Http\JsonResponse
{
    $data = $request->validate(['password' => ['required', 'string', 'min:8']]);
    $user->update(['password' => \Illuminate\Support\Facades\Hash::make($data['password'])]);
    return response()->json(['message' => 'Password reset.']);
}
```

- [ ] **Step 5: Run, verify pass; then full suite.**

- [ ] **Step 6: Commit**

```bash
git commit -am "feat(admin): admin password-reset for users"
```

---

## Task 3: Frontend service layer

**Files:**
- Create: `frontend/services/adminUserService.ts`, `frontend/services/auditLogService.ts`

- [ ] **Step 1:** Read an existing service (e.g. `frontend/services/announcementService.ts`) to copy the fetch/auth wrapper exactly (base URL, token header, error shape).
- [ ] **Step 2:** `adminUserService.ts` — `listUsers()`, `createUser(payload)`, `updateUser(id, payload)`, `deleteUser(id)`, `setActive(id, bool)`, `resetPassword(id, password)` hitting `/admin/users*`. Types: `AdminUser { id; name; email; role: "RND"|"FSS"|"Admin"; is_active: boolean }`.
- [ ] **Step 3:** `auditLogService.ts` — `listAuditLogs({ page, per_page, causer_id?, subject_type?, start?, end? })` returning `{ data: ActivityRow[]; meta: { current_page; last_page; total; per_page } }`.
- [ ] **Step 4:** `cd frontend && npx tsc --noEmit` → clean.
- [ ] **Step 5: Commit** `feat(admin): user + audit-log frontend services`.

---

## Task 4: `(admin)` route group + shell

**Files:**
- Create: `frontend/app/(admin)/layout.tsx`, `frontend/app/(admin)/dashboard/page.tsx`
- Modify: `frontend/components/layout/Sidebar.tsx`

- [ ] **Step 1:** Read `frontend/app/(rnd)/layout.tsx` and `Sidebar.tsx` to learn the shell + role-gating pattern (how `useAuth().user.role` drives nav).
- [ ] **Step 2:** Clone the layout to `(admin)/layout.tsx`; gate to `role === "Admin"` (redirect otherwise, mirroring the RND guard).
- [ ] **Step 3:** Add Admin nav items (Dashboard, Users, Audit Logs) to `Sidebar.tsx`, shown only when `role === "Admin"`.
- [ ] **Step 4:** Minimal `dashboard/page.tsx` (heading + "Admin console" placeholder; KPIs are a stretch goal).
- [ ] **Step 5:** `npx tsc --noEmit` clean; browser-check that an Admin login lands on `/dashboard` with admin nav and an RND login cannot reach `/users`.
- [ ] **Step 6: Commit** `feat(admin): scaffold (admin) route group + shell`.

---

## Task 5: Users / RBAC manager page

**Files:**
- Create: `frontend/app/(admin)/users/page.tsx`

- [ ] **Step 1:** Table of users (name, email, role, active). Reuse the table styling from `budget/page.tsx` records tab for consistency.
- [ ] **Step 2:** Create/edit form (name, email, role select RND|FSS|Admin, password on create). Reuse the `Button` + input classes.
- [ ] **Step 3:** Row actions: activate/deactivate toggle (`setActive`), reset-password modal (`resetPassword`), delete (guard self-delete in UI).
- [ ] **Step 4:** Wire to `adminUserService`. Loading/empty/error states like other pages.
- [ ] **Step 5:** `npx tsc --noEmit` clean; browser-test full CRUD + role change + deactivate (confirm a deactivated user is blocked by `RoleMiddleware` on next request).
- [ ] **Step 6: Commit** `feat(admin): user/RBAC manager page`.

---

## Task 6: Audit-log browser page

**Files:**
- Create: `frontend/app/(admin)/audit-logs/page.tsx`

- [ ] **Step 1:** Filter bar (date range, causer, subject-type) + paginated table (when/who/event/subject). Note clinical rows redact PHI values (description field only — Decision A).
- [ ] **Step 2:** Pagination controls driven by `meta.last_page`.
- [ ] **Step 3:** Wire to `auditLogService`. Empty/loading/error states.
- [ ] **Step 4:** `npx tsc --noEmit` clean; browser-test filters + paging against seeded activity.
- [ ] **Step 5: Commit** `feat(admin): audit-log browser page`.

---

## Stretch (defer to follow-up sprint, out of scope for this one)
- **Admin dashboard KPIs + activity feed** (admin.md §5).
- **Settings** (hospital info / branding shared with report letterhead, budget thresholds, notification rules).
- **Token usage** chart from `ai_usage_logs` (admin.md §5).
- **Audit coverage gaps (B8):** surface suppliers / menu cycles / budgets in history panels.
- **Reports Admin-wide scope** (admin.md §3): Admin sees all users' reports, not owner-scoped.

---

## Self-Review notes
- **Spec coverage:** admin.md §1 (RBAC) → Tasks 2/5; §2 (audit logs) → Tasks 1/6; §5/§6 → Stretch. B5 → Task 1; B6 → Tasks 4–6.
- **Next.js caveat:** `frontend/AGENTS.md` says this Next.js has breaking changes — read `node_modules/next/dist/docs/` before writing any FE routing/layout code (Tasks 4–6).
- **Verify gate every task:** backend tasks run `php artisan test`; frontend tasks run `npx tsc --noEmit` + a browser check.
