# Admin Console Sprint Plan — Execution

> **Source of truth for scope:** [`docs/modules/admin.md`](../../modules/admin.md). This file is the **execution plan** (tasks + file references). Read `admin.md` for *what/why* (esp. the IT-scope decision and the Reconciliation note), then this file for *what to do and where*.
> **For agentic workers:** use superpowers:subagent-driven-development or superpowers:executing-plans. For backend tasks, consult `backend/.agents/skills/laravel-best-practices/skills.md` first (follow its own "How to Apply" routing; delegate `rules/` reading to a sub-agent). **Consistency First** — match existing `Admin/*` and `RND/*` controller/page patterns over generic rule defaults.
> **Conventions:** work on `main`; **NO `Co-Authored-By`** (author = jared). Verify: `cd backend && php artisan test` + `cd frontend && npx tsc --noEmit` + a **browser round-trip** (see Functional Gate). Dev login `rnd@nutriscope.local` / `nutriscope2024!`.

> **CHANGE OF MIND — role scope (in `admin.md`):** Admin = pure system-administration (RBAC, accounts, audit, system health) — RPDH's IT department. **No clinical-content path.** Clinical-rules config and report-branding move to RND (not built yet — follow-up). Reports: Admin sees **census-aggregate + budget/procurement only**, never NCP Summary / Menu Plan.

---

## ⚠ FUNCTIONAL GATE (applies to EVERY frontend task)

The antigravity/codex Admin UI shipped with little-to-no working backend connection ("soulless UI"). **No Admin page is "done" until a real backend round-trip is proven.** For each page, the acceptance criteria are:
- [ ] Page imports and calls its **service** (`adminUserService` / `auditLogService` / `adminDashboardService` / `announcementService`) — no hardcoded/mock data left in the render path.
- [ ] The service hits the real `/api/admin/*` endpoint with the correct method, auth header, and **payload shape the backend actually validates** (check the Form Request / controller — e.g. announcement attachment must match what `AnnouncementController` accepts, NOT a raw base64 data-URL if the backend expects a file/URL).
- [ ] **Proven in-browser:** create/edit/delete persists across a reload; list reflects server state; an error from the API surfaces in the UI. Capture this (preview screenshot / network) — do not claim done from a type-check alone.
- [ ] Loading + empty + error states are wired to real fetch state, not decorative.

---

## State of play (audit `baf8fbf`→HEAD)

**Backend — DONE and aligned, keep:**
- ✅ Audit-log paginated + filtered, `AuditLogResource` — [`Admin/AuditLogController.php`](../../../backend/app/Http/Controllers/Admin/AuditLogController.php), [`Admin/AuditLogResource.php`](../../../backend/app/Http/Resources/Admin/AuditLogResource.php). PHI redaction is **write-time** via `AuditsChanges` (`$auditRedactValues`) — not a controller/UI concern.
- ✅ Password reset `POST /admin/users/{user}/reset-password` (`throttle:6,1`) + `Admin/ResetPasswordRequest` — [`Admin/UserController.php`](../../../backend/app/Http/Controllers/Admin/UserController.php).
- ✅ Dashboard aggregates `GET /admin/dashboard` (cached) — [`Admin/DashboardController.php`](../../../backend/app/Http/Controllers/Admin/DashboardController.php), `Admin/DashboardResource`.
- ✅ Announcements `apiResource /admin/announcements`.
- Tests: `AdminAuditLogTest`, `AdminDashboardTest`, `AdminSystemTest` exist — keep green.

**Frontend — off-spec, REBUILD (do not patch on top):** `frontend/app/admin/*` (and committed `frontend/app/(admin)/*`). Deviations per `admin.md` Reconciliation note: dark theme vs the app's light theme; no shared-component reuse; bespoke announcement "broadcast manager" with base64 `FileReader` upload; and **unverified backend wiring**. Services `adminUserService.ts` / `auditLogService.ts` / `adminDashboardService.ts` exist and may be salvageable — but re-verify each against the live endpoints (Functional Gate).

---

## Task F0: Decide route group + shell, discard off-spec pages

**Files:** `frontend/app/(admin)/` vs `frontend/app/admin/`, `frontend/components/layout/Sidebar.tsx`, `frontend/app/login/page.tsx`.

- [ ] **Resolve the path conflict:** committed `(admin)` is a route group (URL `/dashboard`, collides with RND); working-tree `admin/` yields `/admin/dashboard` (matches the login redirect). **Pick `admin/`** (real `/admin/*` segment) unless the team prefers a different scheme — confirm, then delete the other to avoid duplicate/competing trees.
- [ ] **Rebuild the shell** cloning [`(rnd)/layout.tsx`] structure and **light theme** — reuse `Sidebar` + `TopBar`. Role guard: redirect non-Admin to `/dashboard`. (Current [`admin/layout.tsx`](../../../frontend/app/admin/layout.tsx) is dark `bg-zinc-950` — recolor to match RND.)
- [ ] **Login redirect:** Admin → `/admin/dashboard`, else `/dashboard` ([`login/page.tsx`](../../../frontend/app/login/page.tsx)).
- [ ] Verify routing for Admin and RND users.
- [ ] Commit `feat(admin): admin shell + routing (light theme, shared layout)`.

---

## Task F1: Users / RBAC manager (functional)

**Files:** `frontend/app/admin/users/page.tsx`, `frontend/services/adminUserService.ts`.

- [ ] Recolor to light theme, reuse `Badge`/`Button` and table styling from an existing RND table (e.g. `(rnd)/food-service/budget/page.tsx`).
- [ ] Data table: Name, Email, Role badge, Active toggle. Modal: create/edit (Name, Email, Password, Role select, Active). Password reset modal → `resetPassword`.
- [ ] **Functional Gate:** `listUsers/createUser/updateUser/deleteUser/setActive/resetPassword` each hit `/api/admin/users*` and persist across reload; verify reset-password actually changes the hash (login as the user, or backend test already covers). Never auto-generate credentials — Admin types them.
- [ ] Commit `feat(admin): user/RBAC manager (wired)`.

---

## Task F2: Audit-log browser (functional)

**Files:** `frontend/app/admin/audit-logs/page.tsx`, `frontend/services/auditLogService.ts`.

- [ ] Light theme. Filter bar: date range, actor, model type → query params the backend validates (`per_page`, `causer_id`, `subject_type`, `start`, `end`). Paginated table driven by `meta.last_page`; expandable rows for the `properties` JSON.
- [ ] **PHI:** no client-side redaction — confirm the `properties` payload arrives already clean (write-time `AuditsChanges`). If PHI appears, the bug is backend, fix there.
- [ ] **Functional Gate:** pagination + each filter produce different, server-driven result sets (prove in browser/network).
- [ ] Commit `feat(admin): audit-log browser (wired)`.

---

## Task F3: Dashboard (functional)

**Files:** `frontend/app/admin/dashboard/page.tsx`, `frontend/services/adminDashboardService.ts`.

- [ ] Light theme. KPI cards + token-usage chart (Recharts) + activity feed — **all sourced from `GET /admin/dashboard`** (`AdminDashboardController` cached aggregates) and the audit-log feed. KPIs stay count/rate-level (no patient-identifying detail).
- [ ] **Functional Gate:** numbers come from the API, not literals; chart maps real `ai_usage_logs`-derived series; empty/loading wired.
- [ ] Commit `feat(admin): dashboard (wired to aggregates)`.

---

## Task F4: Announcements — reuse RND pattern (functional)

**Files:** `frontend/app/admin/announcements/page.tsx`, `frontend/services/announcementService.ts` (shared).

- [ ] **Discard the bespoke dark "Bulletin/Broadcast Manager."** Mirror how RND composes announcements (`(rnd)/dashboard/page.tsx` uses the shared `announcementService`); reuse the same composer/feed presentation and **light** `categoryStyles`. Admin gets author + **pin** controls; visibility select `FSS | Admin | All`.
- [ ] **Attachment:** match the backend's accepted shape — verify `AnnouncementController` / its Form Request before sending. If it doesn't accept a base64 data-URL (the codex approach), switch to the supported upload (multipart/URL). **This is the most likely broken wiring — fix it explicitly.**
- [ ] **Functional Gate:** create/edit/delete/pin persist server-side and reflect on the RND/FSS feeds.
- [ ] Commit `feat(admin): announcements (shared component, wired)`.

---

## Task F5: Settings (branding only) + Reports scope

**Files:** `frontend/app/admin/settings/page.tsx`, the reports browser Admin view.

- [ ] **Settings:** hospital branding/letterhead fields only (metadata for PDF headers). **Do NOT** build clinical-rules CRUD here — moved to RND (`admin.md` §5, follow-up task not yet written; raise before picking up).
- [ ] **Reports (Admin view):** restrict to **census-aggregate + budget/procurement** types only; NCP Summary / Menu Plan never reachable by Admin (`admin.md` §3). Before building, confirm no budget/procurement report line ties a cost to an individual patient; confirm census stays a true aggregate (no narrow drill-down).
- [ ] Commit `feat(admin): branding settings + scoped reports view`.

---

## Follow-up (out of this sprint, tracked here)
- **Clinical-rules CRUD → RND route group** — pulled out of Admin; no RND task written yet. Confirm source of truth first: Phase A wired the *read* path through `config/clinical.php`, not the `clinical_rules` table — a CRUD page must write where the engine reads (`admin.md` §5, `fss-admin-plan-review.md` §3).

## Self-Review notes
- **Next.js caveat:** `frontend/AGENTS.md` — this Next.js has breaking changes; read `node_modules/next/dist/docs/` before FE routing/layout code.
- **Verify gate every task:** backend `php artisan test`; frontend `npx tsc --noEmit` **plus the Functional Gate browser round-trip** — a green type-check is NOT proof of a working page.
- **Review-doc caveat:** the Gemini/Opus review docs were frequently wrong — verify claims against code (as Task 1's write-time-redaction discovery overturned the original plan's assumption).
</content>
