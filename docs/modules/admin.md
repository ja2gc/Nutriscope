# Admin Role — Workflow (current state + intended scope)

Admin owns access control and system oversight. Admin routes live under `/api/admin/*` (middleware `auth:sanctum, role:Admin`). The Admin **backend** is built (users, audit-log w/ pagination, dashboard aggregates, announcements, password-reset). An Admin **frontend** was built by codex/antigravity (`baf8fbf`→HEAD) **but strayed from this spec** and must be rebuilt — see the Reconciliation note below. This doc states the intended flow and is the **source of truth**.

> Scope note: this describes **how the system flows** (data origin → backend → DB → frontend consumer → cross-role → downstream). Known gaps/risks live in [`docs/reviews/2026-06-14-system-review.md`](../reviews/2026-06-14-system-review.md). Execution plan (TDD tasks + file refs): [`admin-sprint-plan.md`](../superpowers/plans/admin-sprint-plan.md).

> **Reconciliation (2026-06-18) — codex/antigravity Admin frontend strayed:** the build under `frontend/app/admin/` (and the committed `frontend/app/(admin)/`) does not match the rest of the app and is to be **rebuilt**, not patched on top of. Specific deviations: (1) **theme inverted** — the whole app is **light** (`bg-gray-50`/`bg-zinc-100`, RND dashboard `categoryStyles`), the codex admin console is **dark** (`bg-zinc-950`); (2) **no component reuse** — bespoke pages instead of reusing the shared `Sidebar`/`TopBar`/`Badge`/`Button`/`Card`/`KpiCard` and, for §4, the RND announcement composer; (3) **announcement diverged** — a bespoke dark "Bulletin/Broadcast Manager" card grid, instead of mirroring how RND composes/announces (shared `announcementService`). The **backend** Admin work in that same range is fine and stays. Build direction is in [`admin-sprint-plan.md`](../superpowers/plans/admin-sprint-plan.md).

> **CHANGE OF MIND (this revision):** Admin's scope is now defined as system administration only — RBAC, accounts, audit oversight, system/operational health (including budget and procurement, which are financial/operational data, not clinical) — modeled on RPDH's IT department. Admin has **no standing path to clinical content** (NCP Summary, Menu Plan, or any patient-identified report). This replaces an earlier draft of this doc that gave Admin "full access to all report types across all users." That draft is preserved below, struck through, rather than deleted, so the reasoning is traceable. The underlying legal basis — DPA 2012 (RA 10173) legitimate-purpose and proportionality principles, and the DOH Health Privacy Code's need-to-know standard — is why the original wording was the problem to begin with: a role grant with no declared purpose and no proportionality limit is hard to defend regardless of phrasing. Narrowing what Admin *is* (an IT-department function with no clinical job to do) resolves that more cleanly than trying to police a broader grant after the fact.

---

## 1. Dashboard
Landing page after login (`/admin/dashboard`). System KPIs + charts + an activity feed — the IT-department's at-a-glance view of system health. **All numbers are read live from the API; nothing is hardcoded or seeded.**

- **Source → backend → DB:** `GET /api/admin/dashboard` (`Admin/DashboardController`, cached 300s, key `admin_dashboard`) aggregates five groups, each from a real table populated by real user actions:
  - `users { total, by_role{Admin,RND,FSS} }` — from `users` (created by Admin in §2).
  - `patients { total }` — from `patients` (created by RND).
  - `ai_usage { total_calls, total_tokens, tokens_input, tokens_output, by_endpoint{} }` — from `ai_usage_logs`, written every time RND triggers an AI call (diagnosis suggest, monitoring AI-review, recipe generator — see [`rnd.md`](rnd.md) §2–3).
  - `audit_logs { total, last_7_days }` — from Spatie `activity_log` (§3).
  - `reports { total }` — from `reports`.
- **Frontend consumer:** `frontend/app/admin/dashboard/page.tsx` via `adminDashboardService.fetchDashboard()`. KPI cards reuse the shared `KpiCard`; the **token-usage chart** (Recharts) maps the `ai_usage.by_endpoint` series; the **activity feed** reuses the audit-log list (§3).
- **Constraint:** KPIs stay **count/rate-level only** — no patient-identifying detail (same boundary as the census report in §6). Aggregates are computed in SQL (`COUNT`/`SUM`/`groupBy`), never per-patient rows.
- **Downstream:** none — read-only oversight surface.

## 2. User Management & RBAC (baseline)
The core IT-department function: who exists, what role they hold, whether they can log in. **Every account is created by a human Admin action — the app never auto-provisions credentials.**

- **Source → backend → DB:** `apiResource /api/admin/users` (`Admin/UserController`, create/read/update/delete) writes the `users` table. Columns: `name`, `email`, `password` (hashed), `role` ∈ `RND | FSS | Admin`, `is_active` (bool, default true), soft-deletes (`deleted_at`). Validation: `StoreUserRequest` (name, email unique, password confirmed min:8, role in RND|FSS|Admin, is_active nullable bool); `UpdateUserRequest` (all nullable, email unique-ignoring-self, password optional). Password reset: `POST /api/admin/users/{user}/reset-password` (`Admin/ResetPasswordRequest`, password confirmed min:8) rate-limited `throttle:6,1`.
- **Roles** are a single string on the user (no separate permissions table); route access is enforced by `RoleMiddleware` (exact-role match) and `is_active` is checked there too. A deactivated user is blocked at the middleware **and** at login.
- **Frontend consumer:** `frontend/app/admin/users/page.tsx` via `adminUserService` (`listUsers/createUser/updateUser/deleteUser/setActive/resetPassword`). Reuse shared `Badge` (role), `Button`, and table styling. Admin types passwords directly — no auto-generation.
- **Cross-role / downstream:** the `role` field is what scopes every other role's entire surface (RND, FSS) via `RoleMiddleware`; `is_active=false` immediately denies all their requests. This is the single switch that gates the rest of the system.

## 3. Audit / Activity Tracking (oversight)
- **Source → backend → DB:** every **mutating** request (non-GET) across all roles is logged by `AuditMiddleware` (log_name `audit`; captures url, method, ip, causer) into Spatie's `activity_log` table. Model changes are logged by the `AuditsChanges` trait (`LogsActivity`, fillable-only, dirty-only, no-empty). Read endpoint: `GET /api/admin/audit-logs` (`Admin/AuditLogController`, paginated, `AuditLogResource`).
- **CORRECTED — PHI redaction is write-time, not read-time:** clinical models set `protected bool $auditRedactValues = true`, and `AuditsChanges` strips PHI **values** (field names kept, values replaced with `••• redacted`) before the activity row is ever persisted. There is no redaction step in the read path because none is needed. *(An earlier plan assumed read-time controller redaction with a per-model allow-list — that was wrong. Don't reintroduce read-time redaction; if PHI ever appears in an audit-log response, the bug is in `AuditsChanges` / a model's `$auditRedactValues` list, not the controller.)*
- **Filters / pagination:** `per_page` (1–100, default 25), `causer_id`, `subject_type`, `event`, `start`, `end` (`end >= start`) — all validated inline in the controller.
- **Frontend consumer:** `frontend/app/admin/audit-logs/page.tsx` via `auditLogService.listAuditLogs(params)`; paginated table driven by `meta.last_page`, expandable rows for the `properties` JSON. No client-side redaction (payload arrives already clean).
- **Cross-role:** per-record history is also surfaced inside the operational modules (inventory, purchase orders, patients) via `ActivityController` — Admin's view is the system-wide superset.

## 4. Announcements
- **Source → backend → DB:** `apiResource /api/admin/announcements` (`Admin/AnnouncementController`, which **extends `RND/AnnouncementController`** — no logic fork) writes the `announcements` table: `title`, `body`, `category` (General|Event|Operational|Urgent), `attachment` (nullable longText), `pinned` (bool — Admin-only), `visibility` (FSS|Admin|All). Requests `StoreAnnouncementRequest`/`UpdateAnnouncementRequest`. Admin sees **all** announcements (no visibility filter) and is the only role that can pin.
- **Reuse:** the **UI must mirror RND's composer and reuse the shared `announcementService`** (`fetchAnnouncements/createAnnouncement/updateAnnouncement/deleteAnnouncement`) — do not build a bespoke "broadcast manager" (the codex dark card grid is a deviation, see Reconciliation note). The `attachment` field is a `nullable|string` column (longText) — send the same shape RND sends; verify against `StoreAnnouncementRequest` before wiring.
- **Cross-role / downstream:** on store, `NotificationService::fanOutAnnouncement()` (§5) creates one `notifications` row per recipient matching the announcement's `visibility` (excluding the author). FSS reads its feed (`visibility FSS|All`) via `GET /api/fss/announcements`; RND reads via its dashboard. So an Admin announcement with `visibility=All` lands on every active user's notification feed.

## 5. Notifications
Kept **simple and aligned with RND** ([`rnd.md`](rnd.md) §7) — same table, same controller, same frontend components. Two write-triggers exist system-wide; Admin reuses the read/mark side.

- **The one backend change Admin needs:** the notification routes currently live **inside the RND-only group** (`/api/rnd/notifications`, `routes/api.php`), so Admin cannot reach them. `NotificationController` already scopes strictly by `Auth::id()` (it is role-agnostic). **Resolution:** move the three notification routes out of the `role:RND` prefix into the shared `auth:sanctum` group → `/api/notifications`, `/api/notifications/read-all`, `/api/notifications/{notification}/read`. RND, FSS, and Admin then reuse one controller and one frontend service. (RND's `notificationService.ts` path updates `/api/rnd/notifications` → `/api/notifications`; covered by `NotificationTriggersTest`.) This is the single backend edit in Admin's scope — see [`admin-sprint-plan.md`](../superpowers/plans/admin-sprint-plan.md) S5.
- **Source → backend → DB:** `notifications` table (`user_id`, `title`, `message`, `type`, `source_module`, `source_id`, `read`). Writes come only from real events via `NotificationService`:
  - **Trigger A — announcement posted** (`fanOutAnnouncement`): one row per active recipient matching visibility, `type='announcement'`, `source_module='announcements'`. This is Admin's realistic notification source (e.g. an RND posts a `visibility=All`/`Admin` announcement — fan-out excludes the author, so an Admin only sees announcements they didn't write).
  - **Trigger B — upcoming follow-up** (`SendFollowUpReminders` command, 1 day before `next_followup_date`, idempotent): targets the **RND owning the NCP** only. Admin owns no NCPs, so this trigger does not target Admin — documented for completeness, not an Admin path. Requires the Laravel scheduler running in the backend container.
- **Frontend consumer:** reuse RND's `notificationService` (`fetchNotifications/markNotificationRead/markAllNotificationsRead`), the notifications page, and the **TopBar bell + unread badge** (currently RND-only — extend to render for Admin in the rebuilt shell). No new components.
- **Downstream:** marking read flips `read=true` (owner-only, 403 otherwise); the bell badge recomputes unread count.

## 6. Reports — **no Admin access to clinical reports**

> ~~Admin should have access to **all** report types across **all** users (not owner-scoped like RND/FSS). The Reports browser model is the same (browse → view → download/archive); Admin's view spans the whole facility. Clinical types remain PHI-bearing and Admin access should be deliberate.~~
> *(struck — superseded by the section below)*

RPDH produces three report types under the NCP umbrella, with different sensitivity:
- **NCP Summary** (patient-identified clinical record — assessment/diagnosis/intervention/monitoring) — RND-only. Never Admin.
- **Menu Plan** (patient-identified; reveals clinical information indirectly even though it's a meal plan, e.g. a renal diet implies kidney involvement) — RND-only. Never Admin.
- **Census report** (facility-level aggregate statistics, no patient-identifying fields) — Admin may access this one, **conditional on it staying a true aggregate**: fixed/coarse breakdowns only, no drill-down to a filter narrow enough to isolate a small number of patients. If the census report ever gains a filter capability (by ward + condition + date, etc.), a minimum-cell-size floor (e.g., suppress any breakdown under N patients) must be added before Admin's access to it remains safe — small-cell counts can re-identify a person even with zero patient fields in the output, especially in a facility small enough that staff already know who's on which ward.

**Budget and procurement reports are not PHI and are not subject to the above.** These are financial/operational data (spending, inventory, supplier terms) — Admin has full access. The one thing worth a one-time check: confirm no budget/procurement report line ever ties a cost figure to an individual patient (e.g., "spend per patient" rather than per-ward/per-period) — if one does, that line indirectly discloses something about that patient's care and should be treated as patient-identified, not financial, data. Based on the system's module structure (Procurement and Budget operate at inventory/supplier/threshold level), this is believed not to be the case, but hasn't been explicitly verified against the schema.

- **Source → backend → frontend:** reports use the shared browser model (browse → view → download/archive) over the `reports` table and `$reportRoutes` (`ReportController`). Admin's view must be **restricted to census-aggregate + budget/procurement types only**; NCP Summary / Menu Plan must never be reachable. The Reports browser is currently shared with RND/FSS and not yet scoped for Admin — see [`admin-sprint-plan.md`](../superpowers/plans/admin-sprint-plan.md) S7.
- **Analytics — token usage:** the daily/monthly token-usage chart (`ai_usage_logs`, AI cost oversight) is non-PHI operational analytics and lives on the Dashboard (§1).

**Rationale for "no Admin access to clinical reports," not "Admin access requires a stated purpose":** an earlier draft handled clinical-report access by requiring Admin to declare a purpose per access (a "break-the-glass"-style log). That's a workable pattern in general, but it's unnecessary complexity here once Admin's job description is scoped to system administration — there's no administrative duty in that job description that requires reading a patient's NCP Summary or Menu Plan, so there's nothing to gate. If a future capability genuinely needs cross-role clinical-report access (e.g. a billing-dispute investigation), that should be designed as its own narrow, logged exception path when the need is concrete — not as a standing capability on the Admin role today.

## 7. Settings
Two distinct kinds, mirroring how RND's settings page is built ([`rnd.md`](rnd.md) §8) — **build frontend only against what the backend already supports.**

- **(a) Hospital branding / letterhead (system-level, persisted):** `GET/POST /api/report-branding` (`ReportBrandingController`) over the single-row `report_branding` table (`hospital_name`, `address`, `accreditation`, `service_name`, `province`, `lgu`, `logo_left_path`, `logo_right_path`; logos validated `image|max:2048`, stored on the `public` disk). This is metadata about how a PDF header looks, not clinical content. **Downstream / cross-role:** editing it changes the letterhead on **every** RND/FSS report and archive at render time (only the live letterhead reflects current branding; frozen report values do not re-price). This endpoint is shared, not Admin-prefixed — Admin reuses it.
- **(b) Appearance preferences (device-local):** density (`comfortable|compact`) and reduce-motion, persisted to `localStorage` via `lib/preferences` and applied by the layout — exactly like the RND settings page. No backend storage (none exists, none added). The page also offers **mark-all-read** for notifications (reuses `notificationService.markAllNotificationsRead`).
- **Out of scope — clinical_rules CRUD:** *(CHANGE OF MIND)* the `clinical_rules` configuration originally bundled into Admin "Settings" is **moved out** — disease-to-nutrient mappings are clinical authority and belong with RND, not an IT-scoped Admin role (see §6 rationale). Not yet placed under RND (follow-up task). Budget thresholds / notification rules (operational, not clinical) may stay under Admin if/when built.

## 8. Profile
Self-service account management, **identical to RND** ([`rnd.md`](rnd.md) §9) — shared endpoints, no Admin fork.

- **Source → backend → DB:** `GET /api/auth/me`, `PATCH /api/auth/profile` (`UpdateProfileRequest`: `name` required, `email` required + unique-ignoring-self), `POST /api/auth/password` (`UpdatePasswordRequest`: `current_password` validated against the hash, `password` confirmed min:8). `AuthController`, shared `auth:sanctum` group. Touches `users.name`/`users.email`/`users.password`.
- **Frontend consumer:** reuse RND's `frontend/app/(rnd)/profile/page.tsx` layout and `authService` (`updateProfile`, `changePassword`, `refreshUser`). Two cards: Account Details (name/email) + Change Password.
- **No profile photo** — verified: the `users` table has no photo/avatar column and there is no upload endpoint. Do not add a photo field to the frontend.
- **Downstream:** `users.name` is the same identity rendered as the **"prepared-by"** signatory on reports — changing it here changes how this user appears on documents they file.

## 9. Calendar (preserved, hidden)
Same backend as the other roles ([`rnd.md`](rnd.md) §6): `calendar_events` table, controller, and routes exist; the frontend is hidden from the Admin nav for the demo. Auto-event wiring (facility-wide events, deadlines) is a post-defense task. No Admin-specific work now.

## 10. Role mapping at RPDH
Admin maps to RPDH's IT department, not an administrative officer — confirmed RPDH has a dedicated IT department, and this role's job description (RBAC, accounts, audit, system health) is the IT department's existing core function elsewhere, not a new responsibility handed to a non-technical role. This independently supports §6: an IT department isn't the body with clinical authority to govern clinical content even if it wanted to, so "Admin = IT, no clinical-content path" reflects how the hospital is actually organized, not just a security policy invented for this system. **Open question, not yet confirmed:** whether RPDH's IT function is an in-house permanent hospital position or a shared/outsourced DOH-region resource — this affects how conservative the access boundaries should default to be (a shared/rotating role argues for less assumed continuity of trust), and is worth confirming with the RND contact.

---

## Build status summary
| Capability | Backend | Frontend |
|---|---|---|
| Dashboard (KPIs + token chart + activity feed) | ✅ `Admin/DashboardController` (cached aggregates) | ⚠ codex build off-spec — **rebuild** (light, reuse `KpiCard`/Recharts) |
| User management / RBAC | ✅ endpoints + password-reset (`throttle:6,1`) | ⚠ codex build off-spec — **rebuild** |
| Audit logs | ✅ paginated + filtered, `AuditLogResource` (redaction write-time via `AuditsChanges`) | ⚠ codex build off-spec — **rebuild** |
| Announcements | ✅ endpoints (Admin extends RND ctrl) | ⚠ codex bespoke "broadcast manager" — **rebuild to reuse shared composer** |
| Notifications | ✅ table/model/`NotificationService`/`NotificationController` (role-agnostic) — **needs route move to shared `auth:sanctum`** (one edit) | ❌ extend bell + reuse RND notifications page |
| Profile | ✅ shared `/api/auth/*` (no photo) | ❌ reuse RND profile page |
| Settings — branding | ✅ shared `/api/report-branding` (`report_branding` singleton) | ❌ build (branding form) |
| Settings — appearance prefs | n/a (client-side `lib/preferences`) | ❌ mirror RND settings page |
| Reports (census + budget/procurement only) | ✅ via shared browser, scope-check needed (§6) | ⚠ restrict Admin view to non-clinical types |
| Clinical-rules config | — | **moved out of Admin scope, not yet placed under RND** (follow-up) |

Admin's **backend** is essentially done. The one outstanding backend change is the **notification route move** (§5). The build priority is the **frontend rebuild**: discard the off-spec codex pages and rebuild a light-theme `app/admin/*` route group that reuses shared components and services. Before the Reports browser's Admin view is built, confirm it restricts to census/budget/procurement only — do not implement an Admin-wide view across all report types (see §6).
