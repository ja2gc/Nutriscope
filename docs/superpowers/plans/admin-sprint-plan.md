# Admin Console Sprint Plan — Execution (TDD)

> **For agentic workers:** REQUIRED SUB-SKILL: use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan sprint-by-sprint. Steps use checkbox (`- [ ]`) syntax for tracking.
> **Source of truth for scope:** [`docs/modules/admin.md`](../../modules/admin.md). This file is the **execution plan** (tasks + file references). Read `admin.md` for *what/why* (esp. the IT-scope decision, §5 notification route-move, §6 reports boundary, and the Reconciliation note), then this file for *what to do and where*.
> **Backend convention:** consult `backend/.agents/skills/laravel-best-practices/skills.md` first (follow its own "How to Apply" routing; delegate `rules/` reading to a sub-agent). **Consistency First** — match existing `Admin/*` and `RND/*` controller/page patterns over generic rule defaults.
> **Frontend caveat:** `frontend/AGENTS.md` — this Next.js has breaking changes; read `node_modules/next/dist/docs/` before any routing/layout code.
> **Conventions:** work on `main`; **NO `Co-Authored-By`** (author = jared). Dev login `rnd@nutriscope.local` / `nutriscope2024!` (create an Admin user via `php artisan tinker` or the seeder for Admin testing).

**Goal:** Rebuild the Admin console as a light-theme route group that reuses shared components/services and proves a real backend round-trip on every page, plus the one backend change Admin needs (notification route move).

**Architecture:** Backend is largely done (`Admin/*` controllers + shared `auth:sanctum` endpoints). Work is mostly a frontend rebuild under `frontend/app/admin/*` reusing `Sidebar`/`TopBar`/`Badge`/`Button`/`Card`/`KpiCard` + existing services. One backend edit: move notification routes to the shared group (S5).

**Tech Stack:** Laravel 11 (PHPUnit feature tests), Next.js (light theme, Recharts), Sanctum cookie auth.

---

## ⚠ FUNCTIONAL GATE (applies to EVERY frontend task)
The antigravity/codex Admin UI shipped with little-to-no working backend connection ("soulless UI"). **No Admin page is "done" until a real backend round-trip is proven.** For each page:
- [ ] Page imports and calls its **service** (`adminUserService` / `auditLogService` / `adminDashboardService` / `announcementService` / `notificationService` / `authService` / branding) — no hardcoded/mock data in the render path.
- [ ] The service hits the real endpoint with the correct method, auth, and **payload shape the backend actually validates** (check the Form Request / controller).
- [ ] **Proven in-browser:** create/edit/delete persists across a reload; list reflects server state; an API error surfaces in the UI. Capture it (preview screenshot / network) — never claim done from a type-check alone.
- [ ] Loading + empty + error states wired to real fetch state.

## ⚠ Next.js PROXY LAYER (applies to EVERY admin page — discovered in S1/S2 browser verify)
The frontend does **not** call Laravel directly. Each service calls a same-origin Next.js route handler under `frontend/app/api/**/route.ts`, which proxies to the backend via `frontend/lib/laravelProxy.ts` (`proxy(path, {method,body,search})`, forwards the `nutriscope_token` cookie as a Bearer). RND/FSS/auth handlers already exist; **Admin ones must be created or the page 404s at runtime even with a clean type-check.** A green `tsc` does NOT prove the page works — only the Functional Gate browser round-trip does.
- **Already added (S1/S2):** `app/api/admin/users/route.ts`, `users/[id]/route.ts`, `users/[id]/reset-password/route.ts`, `audit-logs/route.ts`, `dashboard/route.ts`.
- **Still needed per sprint:** S4 announcements (Admin pins → must proxy to `/admin/announcements`, not the shared `/api/announcements` handler that targets `/rnd/announcements`); S5 notifications (`/api/notifications*` after the route move); S6 profile reuses existing `/api/auth/*` handlers (no new proxy); S7 branding reuses existing `app/api/rnd/report-branding` or add `/api/admin/report-branding` pointing at the shared backend route. **Confirm/create the proxy handler as the FIRST frontend task of each sprint.**

## TDD discipline (applies to EVERY backend task)
Per superpowers:test-driven-development: **write the failing test → run it, see it FAIL (RED) → minimal implementation → run it, see it PASS (GREEN) → commit.** Never write implementation before a failing test. Backend verify: `cd backend && php artisan test`. Frontend verify: `cd frontend && npx tsc --noEmit` **plus** the Functional Gate browser round-trip.

---

## State of play (audit `baf8fbf`→HEAD)
**Backend — DONE and aligned, keep:**
- ✅ Users + password-reset (`throttle:6,1`) — [`Admin/UserController.php`](../../../backend/app/Http/Controllers/Admin/UserController.php), `Admin/{Store,Update,ResetPassword}Request`.
- ✅ Audit-log paginated + filtered, `AuditLogResource` — [`Admin/AuditLogController.php`](../../../backend/app/Http/Controllers/Admin/AuditLogController.php). PHI redaction is **write-time** via `AuditsChanges` (`$auditRedactValues`).
- ✅ Dashboard aggregates `GET /admin/dashboard` (cached) — [`Admin/DashboardController.php`](../../../backend/app/Http/Controllers/Admin/DashboardController.php), `Admin/DashboardResource`.
- ✅ Announcements `apiResource /admin/announcements` (`Admin/AnnouncementController` extends `RND/AnnouncementController`); fan-out via `NotificationService`.
- ✅ Shared: `/api/auth/{me,profile,password}` (`AuthController`), `/api/report-branding` (`ReportBrandingController`), `notifications` table + `NotificationController` + `NotificationService`.
- Tests present: `AdminAuditLogTest`, `AdminDashboardTest`, `AdminSystemTest`, `ProfileTest`, `AuthFeatureTest`, `AnnouncementFeatureTest`, `NotificationTriggersTest` — keep green.

**Frontend — off-spec, REBUILD (do not patch on top):** `frontend/app/admin/*` (and committed `frontend/app/(admin)/*`). Deviations: dark theme vs the app's light theme; no shared-component reuse; bespoke announcement "broadcast manager"; unverified backend wiring. Services `adminUserService.ts` / `auditLogService.ts` / `adminDashboardService.ts` exist and may be salvageable — re-verify each against live endpoints (Functional Gate).

**Backend change needed (S5 only):** notification routes sit in the RND-only group — move to shared `auth:sanctum` so Admin can reach `/api/notifications`.

---

## Sprint S0 — Admin shell + routing (light theme)
**Objective:** A working light-theme `app/admin/*` route group reusing the shared layout, with correct role-guarded routing.
**Scope:** Routing/layout only. **Dependencies:** none.
**Database tasks:** none.
**API tasks:** none (uses existing `/api/auth/me` for the guard).
**Backend tasks:** none.
**Frontend tasks:**
- [ ] **Resolve the path conflict:** committed `(admin)` is a route group (URL `/dashboard`, collides with RND); working-tree `admin/` yields `/admin/dashboard` (matches the login redirect). **Pick `app/admin/`** (real `/admin/*` segment); delete the other tree.
- [ ] Rebuild [`app/admin/layout.tsx`](../../../frontend/app/admin/layout.tsx) cloning [`app/(rnd)/layout.tsx`](../../../frontend/app/(rnd)/layout.tsx) structure + **light theme** (`bg-gray-50`); reuse `Sidebar` + `TopBar`. Recolor away from `bg-zinc-950`.
- [ ] Role guard: redirect non-Admin to `/dashboard` (use `AuthContext`).
- [ ] Login redirect Admin → `/admin/dashboard` in [`app/login/page.tsx`](../../../frontend/app/login/page.tsx) (already present — confirm).
- [ ] `Sidebar` renders the Admin nav (Dashboard, Users, Audit Logs, Announcements, Notifications, Profile, Settings).
**Validation requirements:** non-Admin hitting `/admin/*` is redirected; Admin lands on `/admin/dashboard`.
**Test requirements:** `npx tsc --noEmit` clean; manual browser routing check for an Admin and an RND user.
**Acceptance criteria:** light-theme admin shell renders, shared `Sidebar`/`TopBar` reused, both role redirects verified in-browser. Commit `feat(admin): admin shell + routing (light theme, shared layout)`.

---

## Sprint S1 — Users / RBAC manager
**Objective:** A functional user/RBAC manager wired to `/api/admin/users*`.
**Scope:** Frontend (backend done). **Dependencies:** S0.
**Database tasks:** none (`users` table done; cols name,email,password,role,is_active,deleted_at).
**API tasks:** none — reuse `GET/POST /api/admin/users`, `GET/PATCH/DELETE /api/admin/users/{user}`, `POST /api/admin/users/{user}/reset-password`.
**Backend tasks (TDD only if a gap is found):** none expected; `AdminSystemTest` covers CRUD. If reset-password lacks a test, add one (RED→GREEN) asserting the hash changes.
**Frontend tasks:**
- [ ] Recolor to light; reuse `Badge` (role), `Button`, table styling from an existing RND table (e.g. `app/(rnd)/food-service/budget/page.tsx`).
- [ ] Data table: Name, Email, Role badge, Active toggle. Create/edit modal (Name, Email, Password, Role select RND|FSS|Admin, Active). Reset-password modal → `resetPassword`.
- [ ] All actions via `adminUserService` (`listUsers/createUser/updateUser/deleteUser/setActive/resetPassword`). Never auto-generate credentials — Admin types them.
**Validation requirements:** form mirrors `StoreUserRequest`/`UpdateUserRequest` (email unique, password confirmed min:8, role in enum); surface 422 errors in the UI.
**Test requirements:** backend `php artisan test` green; Functional Gate — create/edit/delete/activate/reset persist across reload (network/screenshot proof). Verify reset actually changes the hash (login as the user, or backend test).
**Acceptance criteria:** every action round-trips and persists; 422s surface; no mock data. Commit `feat(admin): user/RBAC manager (wired)`.

---

## Sprint S2 — Audit-log browser
**Objective:** A paginated, filterable audit browser proving server-driven results.
**Scope:** Frontend (backend done). **Dependencies:** S0.
**Database tasks:** none (Spatie `activity_log`).
**API tasks:** none — reuse `GET /api/admin/audit-logs` (`per_page`, `causer_id`, `subject_type`, `event`, `start`, `end`).
**Backend tasks:** none (`AdminAuditLogTest` covers pagination + filters + write-time redaction). Do **not** add read-time redaction.
**Frontend tasks:**
- [ ] Light theme. Filter bar (date range, actor, model type, event) → query params the backend validates. Paginated table driven by `meta.last_page`; expandable rows for the `properties` JSON, via `auditLogService.listAuditLogs(params)`.
- [ ] **PHI:** no client-side redaction — confirm `properties` arrives clean. If PHI appears, fix backend (`AuditsChanges`/`$auditRedactValues`), not the UI.
**Validation requirements:** invalid filter params handled gracefully; `end >= start`.
**Test requirements:** backend green; Functional Gate — pagination + each filter produce different, server-driven result sets (network proof).
**Acceptance criteria:** filters + pagination demonstrably hit the server; properties clean. Commit `feat(admin): audit-log browser (wired)`.

---

## Sprint S3 — Dashboard
**Objective:** Dashboard sourced entirely from `GET /admin/dashboard` + the audit feed.
**Scope:** Frontend (backend done). **Dependencies:** S0, S2 (activity feed reuse).
**Database tasks:** none.
**API tasks:** none — reuse `GET /api/admin/dashboard` + `GET /api/admin/audit-logs` (feed).
**Backend tasks:** none (`AdminDashboardTest` covers aggregate shape).
**Frontend tasks:**
- [ ] Light theme. KPI cards (reuse `KpiCard`) from `users{by_role}`, `patients{total}`, `audit_logs{total,last_7_days}`, `reports{total}`. Token-usage chart (Recharts) maps `ai_usage.by_endpoint`. Activity feed reuses the audit-log list. All via `adminDashboardService.fetchDashboard()`.
**Validation requirements:** KPIs are count/rate-level only (no patient-identifying detail).
**Test requirements:** backend green; Functional Gate — numbers come from the API not literals; chart maps real `ai_usage_logs`-derived series; empty/loading wired (screenshot proof).
**Acceptance criteria:** no literal KPI values; chart + feed driven by live data. Commit `feat(admin): dashboard (wired to aggregates)`.

---

## Sprint S4 — Announcements (reuse RND composer)
**Objective:** Admin authoring/pinning via the shared composer + service, with fan-out proven on other roles' feeds.
**Scope:** Frontend (backend done). **Dependencies:** S0.
**Database tasks:** none (`announcements` table done).
**API tasks:** none — reuse `apiResource /api/admin/announcements`.
**Backend tasks:** none (`AnnouncementFeatureTest` covers create/visibility/pin/authorization).
**Frontend tasks:**
- [ ] **Discard the bespoke dark "Bulletin/Broadcast Manager."** Mirror how RND composes (`app/(rnd)/dashboard/page.tsx` uses the shared `announcementService` + light `categoryStyles`). Admin gets author + **pin** controls; visibility select `FSS | Admin | All`.
- [ ] **Attachment:** match the backend's accepted shape — `attachment` is `nullable|string` (longText) in `StoreAnnouncementRequest`; send the same shape RND sends. Verify against the request before wiring.
**Validation requirements:** mirror `StoreAnnouncementRequest`/`UpdateAnnouncementRequest` (title, body, category enum, visibility enum, pinned bool).
**Test requirements:** backend green; Functional Gate — create/edit/delete/pin persist; a `visibility=All` post appears on an RND user's notification feed (cross-role proof).
**Acceptance criteria:** shared component reused (no bespoke manager); fan-out verified. Commit `feat(admin): announcements (shared component, wired)`.

---

## Sprint S5 — Notifications (route move + Admin UI)
**Objective:** Make notifications reachable by all roles via the shared group and surface them in the Admin shell, reusing RND components.
**Scope:** Backend (route move) + frontend. **Dependencies:** S0; coordinate with RND `notificationService` path.
**Database tasks:** none (`notifications` table + `NotificationService` done).
**API tasks:** move the three notification routes **out** of the `role:RND` prefix into the shared `auth:sanctum` group:
- `GET /api/notifications`, `PATCH /api/notifications/read-all`, `PATCH /api/notifications/{notification}/read` (controller unchanged — `NotificationController` already scopes by `Auth::id()`).
**Backend tasks (TDD):**
- [ ] **RED:** add to `tests/Feature/NotificationTriggersTest.php` (or a new `NotificationAccessTest`) a test: an **Admin** user with a `notifications` row can `GET /api/notifications` and `PATCH /api/notifications/{id}/read` (200, `read=true`); a non-owner gets 403. Run — FAIL (route is RND-only / 403/404 for Admin).
- [ ] **GREEN:** in [`backend/routes/api.php`](../../../backend/routes/api.php) move lines 145–148 from the `role:RND` group into the shared `auth:sanctum` group (alongside `/auth/*`). Run — PASS.
- [ ] **Regression:** keep existing RND follow-up + fan-out tests green; adjust any test asserting the old `/api/rnd/notifications` path.
- [ ] Commit `feat(notifications): move routes to shared auth group`.
**Frontend tasks:**
- [ ] Update RND `frontend/services/notificationService.ts` path `/api/rnd/notifications` → `/api/notifications` (all three calls).
- [ ] Extend the `TopBar` bell + unread badge to render for **Admin** (currently RND-only).
- [ ] Build `app/admin/notifications/page.tsx` reusing the RND notifications page presentation (icons by type, mark-one/mark-all, empty state) + `notificationService`.
**Validation requirements:** mark-read owner-only (403 otherwise).
**Test requirements:** `php artisan test` green (incl. new Admin access test + unchanged RND triggers); `npx tsc --noEmit`; Functional Gate — Admin sees an announcement notification (created via S4 cross-role) and can mark it read across a reload.
**Acceptance criteria:** RND notifications still work on the new path; Admin can list/mark; bell badge shows for Admin. Commit `feat(admin): notifications (shared, wired)`.

---

## Sprint S6 — Profile
**Objective:** Admin self-service profile reusing the shared auth endpoints and RND page.
**Scope:** Frontend (backend done). **Dependencies:** S0.
**Database tasks:** none (no profile-photo column — do not add one).
**API tasks:** none — reuse `PATCH /api/auth/profile`, `POST /api/auth/password`, `GET /api/auth/me`.
**Backend tasks (TDD only if gap):** `ProfileTest` covers update/password for an authenticated user. If it doesn't assert an **Admin-role** user specifically, add a RED→GREEN test that an Admin can update name/email and change password.
**Frontend tasks:**
- [ ] Build `app/admin/profile/page.tsx` reusing the RND profile layout + `authService` (`updateProfile`, `changePassword`, `refreshUser`). Two cards: Account Details (name/email) + Change Password. **No photo field.**
**Validation requirements:** mirror `UpdateProfileRequest` (name req, email unique-ignoring-self) + `UpdatePasswordRequest` (current_password correct, password confirmed min:8); surface 422.
**Test requirements:** backend green; Functional Gate — name/email update persists across reload; password change then re-login works; wrong current_password surfaces an error.
**Acceptance criteria:** profile + password round-trip; `users.name` change reflects on report "prepared-by". Commit `feat(admin): profile (shared endpoints, wired)`.

---

## Sprint S7 — Settings (branding + appearance) + Reports scope
**Objective:** Branding editor (shared endpoint), device-local appearance prefs, and an Admin reports view restricted to non-clinical types.
**Scope:** Frontend + a one-time data check. **Dependencies:** S0.
**Database tasks:** none (`report_branding` singleton done).
**API tasks:** none — reuse `GET/POST /api/report-branding`.
**Backend tasks:** none (`ReportBrandingController` done). **Do NOT** build clinical-rules CRUD here — moved to RND (`admin.md` §7; follow-up not yet written — raise before building it anywhere).
**Frontend tasks:**
- [ ] **Settings — branding:** form for `hospital_name`, `address`, `accreditation`, `service_name`, `province`, `lgu`, `logo_left`, `logo_right` → `POST /api/report-branding`. Logos `image|max:2048`.
- [ ] **Settings — appearance:** density (`comfortable|compact`) + reduce-motion via `lib/preferences` (localStorage), mirroring the RND settings page; plus a mark-all-read button (`notificationService.markAllNotificationsRead`).
- [ ] **Reports (Admin view):** restrict to **census-aggregate + budget/procurement** types only; NCP Summary / Menu Plan never reachable by Admin (`admin.md` §6). Confirm census stays a true aggregate (no narrow drill-down) and no budget/procurement line ties a cost to an individual patient before exposing.
**Validation requirements:** branding form validation matches the controller; logo uploads constrained to images ≤2MB.
**Test requirements:** `npx tsc --noEmit`; Functional Gate — branding save persists and a re-rendered report letterhead reflects it; appearance prefs survive reload; Admin reports list shows only census/budget/procurement.
**Acceptance criteria:** branding round-trips and changes report letterhead downstream; appearance prefs local-only; no clinical report type reachable by Admin. Commit `feat(admin): branding settings + appearance prefs + scoped reports view`.

---

## Follow-up (out of this sprint, tracked here)
- **Clinical-rules CRUD → RND route group** — pulled out of Admin; no RND task written yet. Confirm source of truth first: the *read* path is wired through `config/clinical.php`, not a `clinical_rules` table — a CRUD page must write where the engine reads (`admin.md` §7).

## ✅ SHIPPED (S0–S8 complete + consistency pass)
All sprints implemented and browser-verified on `main`. Key outcomes beyond the original task text:
- **S1:** `UserResource` did not expose `is_active` (Active toggle had nothing to bind) — fixed via TDD.
- **Proxy layer (discovered in S1/S2 browser verify):** every Admin page needs a Next.js proxy handler under `frontend/app/api/admin/**` (and shared `app/api/{auth,notifications}/**`) forwarding the `nutriscope_token` cookie via `lib/laravelProxy.ts`. A clean `tsc` does **not** prove a page works — only the Functional Gate browser round-trip does. Handlers added for users/audit-logs/dashboard/announcements/notifications/auth(profile,password)/report-branding/reports.
- **S8 Reports (built):** one `ReportController` + `ADMIN_ALLOWED_TYPES = [demographic_census, budget_report, procurement_pack]` enforced **server-side** on every entry point (`index/instances/render/archive/store/show/download/view`) → 403 for Admin on any other type (verified via the admin proxy). Frontend: shared `components/reports/ReportsBrowser.tsx` (`catalog`+`apiPrefix`); RND and Admin pages are thin wrappers.

### Consistency pass (post-build, per user review)
- **Shared `AnnouncementsBoard`** (`components/announcements/AnnouncementsBoard.tsx`, `variant` admin|rnd): admin page **and** a new RND `/announcements` page are thin wrappers; `categoryStyles` defined once; RND dashboard imports it (no duplicate). RND nav gained an Announcements link.
- **Nav/topbar mirror RND:** admin "All Reports"→"Reports"; Profile + Notifications removed from the admin sidebar and reached from the **TopBar** (profile card → `/admin/profile`, bell → `/admin/notifications`); desktop collapse button hidden on mobile (fixes the broken collapse/scroll).
- **Dashboard:** neutral `KpiCard` tones (no per-card color); **AI Usage** card = month-to-date, chart = tokens/day (`ai_usage.month_*` + `daily[]` added to `DashboardController`/resource, real `ai_usage_logs` rows).

## Self-Review checklist (run after the plan is executed)
- **No orphans:** every Admin page (S0–S7) ↔ a real route in `routes/api.php` ↔ a controller ↔ a migration column ↔ a frontend service. No table/API/UI without a consumer.
- **No duplication:** notifications use one shared route + one service (not an admin-prefixed copy); announcements/profile/branding reuse shared controllers/components.
- **No contradiction with RND:** notification route-move applied in both docs; clinical-rules stay out of Admin; reports PHI boundary (§6) unchanged.
- **Verify gate every sprint:** backend `php artisan test`; frontend `npx tsc --noEmit` **plus** the Functional Gate browser round-trip — a green type-check is NOT proof of a working page.
- **Review-doc caveat:** prior Gemini/Opus review docs were frequently wrong — verify claims against code (as the write-time-redaction discovery overturned an earlier assumption).
