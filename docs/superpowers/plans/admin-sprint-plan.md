# Admin Console Sprint — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

> **For ALL backend tasks (Tasks 0, 1, 2 — anything touching PHP/Laravel code):** consult `backend/.agents/skills/laravel-best-practices/skills.md` before writing or editing backend code. Follow that file's own "How to Apply" routing (map the file type you're touching — controller, Form Request, etc. — to its listed sections; delegate reading the actual rule files under `rules/` to a sub-agent, per the skill's instruction). Don't duplicate that routing logic here; this note only adds project-specific context the skill can't know on its own:
> - **Consistency First (the skill's own first principle, not this plan's addition) matters more than usual on this codebase given its size and history.** If you find an established pattern (e.g. how validation, redaction, or auth checks are already done elsewhere in `Admin/*` or `RND/*` controllers), that pattern wins over the skill's generic default — don't "fix" deliberate, consistent codebase conventions into matching a rule file. Only flag genuine gaps (a practice that's simply missing, not a different-but-consistent approach) as mismatches to apply best-practice fixes for, and note what changed and why in the commit message.
> - **Task 1 (audit-log) and Task 2 (password reset) are both security/PHI-adjacent** — weight the security section accordingly even where the skill's generic controller-routing might not emphasize it as heavily as this project needs.
> - **Task 0 doesn't write application PHP code** (it's a seeder check + manual verification) — this skill likely doesn't apply to it; don't force a consultation where there's no code being written or reviewed.

**Goal:** Build the Admin role's frontend (RBAC user manager, audit-log browser, settings, announcements, dashboard), and fix the backend gaps that block it (unpaginated/unfiltered audit log, missing password-reset).

**Architecture:** Backend exposes `/api/admin/*` behind `auth:sanctum, role:Admin`. This sprint hardens the audit-log endpoint with pagination + filters, adds an admin password-reset action, then scaffolds a Next.js `(admin)` route group mirroring the existing `(rnd)` layout, with a Dashboard, Users/RBAC page, Audit-Log browser, Settings, and Announcements.

> **CHANGE OF MIND — role scope (decided in conversation, not yet reflected in code):** Admin is now scoped as a pure system-administration role (RBAC, accounts, audit, system health) — the IT-department function at RPDH, not a clinical-operations function. Clinical-rules configuration and report-branding settings (originally Task 8, Steps 1–2 below) are being pulled out of Admin's scope; they belong with RND, who holds the clinical authority to govern them. This sprint plan still contains those steps under Task 8 — **do not implement Task 8 Steps 1–2 under the Admin role.** They're left in place below with a note, not deleted, so the file matches what was actually decided rather than silently erasing the original design. See the companion `admin.md` for the full rationale.

**Tech Stack:** Laravel 13 + Sanctum + Spatie activitylog (backend); Next.js 16 (App Router), Tailwind, lucide-react (frontend).

**Conventions (project):** work on `main`; **NO `Co-Authored-By`** (author = jared). Verify: `cd backend && php artisan test` (MySQL — baseline count **NOT YET CONFIRMED** — see open item below; do not trust 442 or 473 until `php artisan test` is actually run once and the real number is recorded) + `cd frontend && npx tsc --noEmit`. Dev login `rnd@nutriscope.local` / `nutriscope2024!`.

**Prereq:** Phase A (`implementation_plan.md` §1–§3, §7) is complete per the handoff doc — this sprint (Phase B) starts clean. Several findings in earlier review docs (Gemini/Opus) were verified false during Phase A closeout; don't re-litigate `complete-day missing`, `ai_usage_logs never written`, or `procurement ignores inventory` — these are retracted, not open.

---

## File Structure

**Backend**
- `backend/app/Http/Controllers/Admin/AuditLogController.php` — add pagination + filters. ~~currently returns `Activity::...->get()` raw — no Resource, no pagination, no redaction~~ **CORRECTED:** returns raw + unpaginated, but redaction already happened at write-time via `AuditsChanges` trait — see Task 1.
- `backend/app/Http/Resources/Admin/AuditLogResource.php` (create) — shapes each activity row; does **not** exist yet but Task 1 returns `AuditLogResource::collection(...)`.
- `backend/app/Http/Controllers/Admin/UserController.php` — add `resetPassword` (class is `UserController`; routes alias it as `AdminUserController`).
- `backend/app/Http/Requests/AdminResetPasswordRequest.php` (create) — password rules for the reset action.
- `backend/routes/api.php` — register the password-reset route.
- `backend/tests/Feature/Admin/AuditLogTest.php` (create) — pagination/filter coverage **+ PHI-redaction regression test (see Task 1, Step 0)**. *Note: a draft `AdminAuditLogTest` was started earlier and removed as premature per the handoff doc — this is the rewrite, not a duplicate.*
- `backend/tests/Feature/Admin/UserManagementTest.php` (create/extend) — RBAC + password reset. *Note: `AdminSystemTest::test_admin_can_list_audit_logs` already exists and only asserts `['data']` — it stays green through this task and doesn't need touching.*

**Frontend** (new `(admin)` route group)
- `frontend/app/(admin)/layout.tsx` — admin shell (clone `(rnd)/layout.tsx`, admin nav).
- `frontend/app/(admin)/dashboard/page.tsx` — Dashboard (KPIs, token usage chart).
- `frontend/app/(admin)/users/page.tsx` — user/RBAC manager.
- `frontend/app/(admin)/audit-logs/page.tsx` — audit browser.
- `frontend/app/(admin)/settings/page.tsx` — hospital branding settings. **Scope note: branding/letterhead fields only (Task 8 Step 1) — see change-of-mind note above re: clinical-rules.**
- `frontend/app/(admin)/announcements/page.tsx` — system announcements manager.
- `frontend/services/adminUserService.ts` — users CRUD + reset password.
- `frontend/services/auditLogService.ts` — paginated/filtered fetch.

---

## Task 0: Confirm/seed an Admin login (prereq)

- [ ] **Step 1:** Check for an existing Admin user: `cd backend && php artisan tinker --execute="echo App\Models\User::where('role','Admin')->value('email');"`
- [ ] **Step 2:** If none, add an Admin to the dev seeder (mirror the RND demo user) with a known password, run `php artisan db:seed`.
- [ ] **Step 3:** Verify login returns a token: `curl -X POST .../api/login` (or via the app) with the Admin creds.

---

## Task 1: Paginate + filter the audit-log endpoint, verify redaction (Backend)

> **CHANGE OF MIND:** the original Step 3 below implemented controller-level PHI redaction with a placeholder field list (`phi_fields_to_redact`). That redaction layer was **wrong, not just incomplete** — PHI is already redacted at write-time by the `AuditsChanges` trait (`tapActivity`, `$auditRedactValues` on clinical models), before it ever reaches the activity table. Adding controller-level redaction on top would be redundant at best and, with a placeholder key that matches nothing real, could have created false confidence that redaction was happening when it wasn't. **Do not implement controller-level redaction.** Step 3 below is corrected to remove it; Step 0 adds the regression test the handoff doc asks for instead.

**Files:** `backend/app/Http/Controllers/Admin/AuditLogController.php`, `backend/tests/Feature/Admin/AuditLogTest.php`

- [ ] **Step 0 (new): Write the PHI-redaction regression test, before touching pagination.** Confirm `AuditsChanges` is actually redacting clinical models at write-time — write a test that creates/updates an `Assessment` (or `Intervention`/`Monitoring`), hits `/api/admin/audit-logs`, and asserts the known-PHI fields (per `$auditRedactValues` on that model) are absent from the response `properties`. This is the test the handoff doc calls for; it did not exist before this task.
- [ ] **Step 1: Write the failing pagination/filter test**
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

- [ ] **Step 2: Run, verify both new tests fail** (`php artisan test --filter=AuditLogTest`).
- [ ] **Step 3: Implement pagination + filters — NO controller-level redaction**
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

    // PHI redaction is NOT done here. It already happened at write-time via the
    // AuditsChanges trait ($auditRedactValues) when the activity row was created.
    // If Step 0's regression test fails, the bug is in AuditsChanges / the model's
    // $auditRedactValues list — fix it there, not by adding redaction in this controller.

    return \App\Http\Resources\Admin\AuditLogResource::collection($page);
}
```

- [ ] **Step 4: Run, verify all three tests (Step 0 + Step 1 + existing `AdminSystemTest::test_admin_can_list_audit_logs`) pass**, then commit `feat(admin): paginate + filter audit-log endpoint`.

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

- [ ] **Step 1: KPI Cards:** Active Users, Total Logins, Error Rates. (Backend aggregate endpoints required — see `AdminDashboardController` per handoff §6, `Cache::remember()` aggregates).
- [ ] **Step 2: Token Usage Chart:** Use Recharts mapping AI API costs over time from `ai_usage_logs`. *Note: `ai_usage_logs` is already populated — no `AiTokenObserver` needs to be added, per handoff.*
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
- [ ] ~~**Step 3: Security:** Ensure PHI (Protected Health Information) is redacted safely in the UI (description field only).~~ **CHANGE OF MIND — corrected:** redaction is a backend (write-time) concern, already handled by `AuditsChanges` before Task 1 even runs its query. This step is **not** a frontend implementation task. Replace with a verification step: confirm the `properties` payload arriving from the API is already clean (per Task 1 Step 0's regression test) — if PHI appears in this UI, that's a bug in `AuditsChanges`/`$auditRedactValues`, to be fixed there, not patched over with client-side filtering here.
- [ ] **Step 4: Commit** `feat(admin): audit log browser`.

---

## Task 8: Settings & Announcements

> **CHANGE OF MIND — Steps 1 and 2 below stay in this file as a record of the original plan, but are out of scope for the Admin role.** See `admin.md` for the full reasoning: Admin is scoped to system administration (no clinical-content path). Clinical-rules configuration and report-branding both touch clinical-adjacent content and should sit under an RND-gated route instead. **Do not implement Steps 1–2 under `role:Admin` middleware.** This needs a follow-up task (not yet written) to relocate them — raise with whoever's tracking the RND route group before picking this up.

**Files:** `frontend/app/(admin)/settings/page.tsx`, `frontend/app/(admin)/announcements/page.tsx`

- [ ] ~~**Step 1: Settings:** Hospital Info tab (Name, Logo upload, Address). Wire to `report-branding` endpoints so PDF headers update.~~ **HOLD — re-route to RND, not Admin. See change-of-mind note above.**
- [ ] ~~**Step 2: Clinical Rules Configuration:** CRUD page for `clinical_rules` table so chief dietitians can dynamically update disease-to-nutrient mappings.~~ **HOLD — re-route to RND, not Admin. See change-of-mind note above. Also note: `clinical_rules` already has a working consumer as of Phase A §7 (`RND/InterventionController::mapGoalTypeToConditions` now reads `config/clinical.php`) — confirm whether this CRUD page should write to `clinical_rules` table directly or to `config/clinical.php`, since Phase A's fix changed which one is actually live.**
- [ ] **Step 3: Announcements:** List view of active/past announcements. Form: Title, Content, Visibility (FSS/Admin/All), Pin toggle. *(Unaffected by the above — announcements stay under Admin.)*
- [ ] **Step 4: Type check:** `npx tsc --noEmit`.
- [ ] **Step 5: Commit** `feat(admin): announcements UI` *(scope reduced from original `settings, clinical rules and announcements UI` — see holds above)*.

---

## Self-Review notes
- **Next.js caveat:** `frontend/AGENTS.md` says this Next.js has breaking changes — read `node_modules/next/dist/docs/` before writing any FE routing/layout code.
- **Verify gate every task:** backend tasks run `php artisan test`; frontend tasks run `npx tsc --noEmit` + a browser check.
- **Review-doc caveat (from handoff):** the Gemini/Opus review docs were frequently wrong — verify every claim against actual code before building on it, the same way Task 1's redaction discovery overturned an assumption this plan was originally built on.