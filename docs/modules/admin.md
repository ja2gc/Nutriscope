# Admin Role — Workflow (current state + intended scope)

Admin owns access control and system oversight. Admin routes live under `/api/admin/*` (middleware `auth:sanctum, role:Admin`). The Admin **backend** is built (users, audit-log w/ pagination, dashboard aggregates, announcements, password-reset). An Admin **frontend** was built by codex/antigravity (`baf8fbf`→HEAD) **but strayed from this spec** and must be rebuilt — see the Reconciliation note below. This doc states the intended flow and is the **source of truth**.

> Scope note: known gaps/risks live in [`docs/reviews/2026-06-14-system-review.md`](../reviews/2026-06-14-system-review.md). Execution plan (tasks + file refs): [`admin-sprint-plan.md`](../superpowers/plans/admin-sprint-plan.md).

> **Reconciliation (2026-06-18) — codex/antigravity Admin frontend strayed:** the build under `frontend/app/admin/` (and the committed `frontend/app/(admin)/`) does not match the rest of the app and is to be **rebuilt**, not patched on top of. Specific deviations: (1) **theme inverted** — the whole app is **light** (`bg-zinc-100`, RND dashboard `categoryStyles`), the codex admin console is **dark** (`bg-zinc-950`); (2) **no component reuse** — bespoke pages instead of reusing the shared `Sidebar`/`TopBar`/`Badge`/`Button` and, for §4, the RND announcement composer; (3) **announcement diverged** — a bespoke dark "Bulletin/Broadcast Manager" card grid with base64 `FileReader` upload, instead of mirroring how RND composes/announces (shared `announcementService`). The **backend** Admin work in that same range is fine and stays. Build direction is in [`admin-sprint-plan.md`](../superpowers/plans/admin-sprint-plan.md).

> **CHANGE OF MIND (this revision):** Admin's scope is now defined as system administration only — RBAC, accounts, audit oversight, system/operational health (including budget and procurement, which are financial/operational data, not clinical) — modeled on RPDH's IT department. Admin has **no standing path to clinical content** (NCP Summary, Menu Plan, or any patient-identified report). This replaces an earlier draft of this doc that gave Admin "full access to all report types across all users." That draft is preserved below, struck through, rather than deleted, so the reasoning is traceable. The underlying legal basis — DPA 2012 (RA 10173) legitimate-purpose and proportionality principles, and the DOH Health Privacy Code's need-to-know standard — is why the original wording was the problem to begin with: a role grant with no declared purpose and no proportionality limit is hard to defend regardless of phrasing. Narrowing what Admin *is* (an IT-department function with no clinical job to do) resolves that more cleanly than trying to police a broader grant after the fact.

---

## 1. RBAC & logins (baseline)
- **User management:** `apiResource /admin/users` (create / read / update / delete). Each user has a `role` ∈ `RND | FSS | Admin` and an `is_active` flag.
- **Roles** are a single string on the user (no separate permissions table); route access is enforced by `RoleMiddleware` (exact-role match) and `is_active` is checked there too (deactivated users are blocked).
- Admin can create accounts, assign/change roles, and activate/deactivate logins. Password reset is an intended admin capability.
- **Note:** account creation / password entry is an admin action performed by a human — the app should not auto-create credentials on a user's behalf.

## 2. Audit logs (oversight)
- `GET /admin/audit-logs` exposes the system-wide change trail (Spatie activity log; Spec 5). Mutations across all roles are recorded.
- **CORRECTED:** clinical models are redacted **at write-time**, not at read-time. The `AuditsChanges` trait (`tapActivity`, `$auditRedactValues` on clinical models) strips PHI before the activity row is ever persisted. There is no redaction step in the read path (`AuditLogController`) because none is needed — by the time Admin queries this endpoint, PHI was never written to the row in the first place. *(An earlier version of this plan assumed redaction needed to happen in the controller at read-time with a per-model field allow-list; that assumption was wrong — the write-time trait already covers it. Don't reintroduce read-time redaction; if PHI is ever found in an audit-log response, the bug is in `AuditsChanges` / a model's `$auditRedactValues` list, not in the controller.)*
- Per-record history is also surfaced in-app (inventory, purchase orders, patients) via `ActivityController`.
- Intended UI: an audit browser with pagination + filters (date range, actor, model). *(Currently the endpoint returns the full log unpaginated — see review doc.)*

## 3. Reports — **no Admin access to clinical reports**

> ~~Admin should have access to **all** report types across **all** users (not owner-scoped like RND/FSS). The Reports browser model is the same (browse → view → download/archive); Admin's view spans the whole facility. Clinical types remain PHI-bearing and Admin access should be deliberate.~~
> *(struck — superseded by the section below)*

RPDH produces three report types under the NCP umbrella, with different sensitivity:
- **NCP Summary** (patient-identified clinical record — assessment/diagnosis/intervention/monitoring) — RND-only. Never Admin.
- **Menu Plan** (patient-identified; reveals clinical information indirectly even though it's a meal plan, e.g. a renal diet implies kidney involvement) — RND-only. Never Admin.
- **Census report** (facility-level aggregate statistics, no patient-identifying fields) — Admin may access this one, **conditional on it staying a true aggregate**: fixed/coarse breakdowns only, no drill-down to a filter narrow enough to isolate a small number of patients. If the census report ever gains a filter capability (by ward + condition + date, etc.), a minimum-cell-size floor (e.g., suppress any breakdown under N patients) must be added before Admin's access to it remains safe — small-cell counts can re-identify a person even with zero patient fields in the output, especially in a facility small enough that staff already know who's on which ward.

**Budget and procurement reports are not PHI and are not subject to the above.** These are financial/operational data (spending, inventory, supplier terms) — Admin has full access. The one thing worth a one-time check: confirm no budget/procurement report line ever ties a cost figure to an individual patient (e.g., "spend per patient" rather than per-ward/per-period) — if one does, that line indirectly discloses something about that patient's care and should be treated as patient-identified, not financial, data. Based on the system's module structure (Procurement and Budget operate at inventory/supplier/threshold level), this is believed not to be the case, but hasn't been explicitly verified against the schema.

**Rationale for "no Admin access to clinical reports," not "Admin access requires a stated purpose":** an earlier draft of this section handled clinical-report access by requiring Admin to declare a purpose per access (a "break-the-glass"-style log). That's a workable pattern in general, but it's unnecessary complexity here once Admin's job description is scoped to system administration — there's no administrative duty in that job description that requires reading a patient's NCP Summary or Menu Plan, so there's nothing to gate. If a future capability genuinely needs cross-role clinical-report access (e.g. a billing-dispute investigation), that should be designed as its own narrow, logged exception path when the need is concrete — not as a standing capability on the Admin role today.

## 4. Announcements
- `apiResource /admin/announcements` — create / edit / delete / pin posts with visibility `FSS | Admin | All`. Admin sees all announcements (no role filter) and is the only role that can pin.
- **UI:** mirror RND's announcement presentation and **reuse the shared announcement component / `announcementService`** — do not build a bespoke "broadcast manager". The codex build's standalone dark card grid is a deviation (see Reconciliation note). FSS gets a read-only feed (FSS|All); Admin gets the authoring/pin controls.

## 5. Intended admin extras
- **Dashboard:** system KPIs + charts (admissions count, NCP completion rate, budget, inventory) + an activity feed. KPI aggregates should stay count/rate-level, not patient-identifying detail — same constraint as the census report in §3.
- **Settings:** hospital info / branding. *(CHANGE OF MIND: branding/letterhead is fine under Admin as a system-config field — it's metadata about how a PDF header looks, not clinical content. But the **clinical_rules CRUD** that was originally bundled into "Settings" in the sprint plan is being moved out — see §3 rationale; clinical-rule configuration belongs with RND, who holds the clinical authority to govern disease-to-nutrient mappings, not with an IT-scoped Admin role.)* Budget thresholds, notification rules stay under Admin (operational, not clinical).
- **Token usage:** daily/monthly chart from `ai_usage_logs` (AI cost oversight).

## 6. Calendar & Notifications
Same backend as the other roles ([`rnd.md`](rnd.md) §6–7). Admin oversight could include facility-wide events and notification-rule configuration (planned).

## 7. Role mapping at RPDH

Admin maps to RPDH's IT department, not an administrative officer — confirmed RPDH has a dedicated IT department, and this role's job description (RBAC, accounts, audit, system health) is the IT department's existing core function elsewhere, not a new kind of responsibility being handed to a non-technical role. This also independently supports §3: an IT department isn't the body with clinical authority to govern clinical content even if it wanted to, so "Admin = IT, no clinical-content path" reflects how the hospital is actually organized, not just a security policy invented for this system. **Open question, not yet confirmed:** whether RPDH's IT function is an in-house, permanent hospital position or a shared/outsourced DOH-region resource — this affects how conservative the access boundaries here should default to be (a shared/rotating role argues for less assumed continuity of trust than a permanent in-house one), and is worth confirming with the RND contact.

---

## Build status summary
| Capability | Backend | Frontend |
|---|---|---|
| User management / RBAC | ✅ endpoints + password-reset (`throttle:6,1`) | ⚠ codex build off-spec — **rebuild** |
| Audit logs | ✅ endpoint (now **paginated + filtered**, `AuditLogResource`; redaction is write-time via `AuditsChanges`) | ⚠ codex build off-spec — **rebuild** |
| Announcements | ✅ endpoints | ⚠ codex bespoke "broadcast manager" — **rebuild to reuse shared component** |
| Dashboard (KPIs) | ✅ `AdminDashboardController` (cached aggregates) | ⚠ codex build off-spec — **rebuild** |
| Reports (census + budget/procurement only) | ✅ via shared browser, scope-check needed (§3) | scope not yet distinct — currently shares the same browser as RND/FSS; needs Admin's view restricted to non-clinical report types only |
| Clinical-rules config | — | **moved out of Admin scope, not yet placed under RND** (follow-up task needed) |
| Settings (branding) / Token usage | ❌ | ❌ |

Admin's **backend** is essentially done (the codex/antigravity range built the audit pagination, password-reset, dashboard aggregates, and announcements endpoints — all aligned). The build priority is now the **frontend rebuild**: discard the off-spec codex pages and rebuild a `(admin)`-style route group that matches the app's light theme and reuses shared components (see Reconciliation note + [`admin-sprint-plan.md`](../superpowers/plans/admin-sprint-plan.md)). Before the Reports browser's Admin view is built, confirm it restricts to census/budget/procurement only — do not implement an Admin-wide view across all report types (see §3).