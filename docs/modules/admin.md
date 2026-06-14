# Admin Role — Workflow (current state + intended scope)

Admin owns access control and oversight. Admin routes live under `/api/admin/*` (middleware `auth:sanctum, role:Admin`). Frontend admin pages are **not built yet** — the endpoints exist but there is no `(admin)` UI; this doc states the intended flow.

> Scope note: known gaps/risks live in [`docs/reviews/2026-06-14-system-review.md`](../reviews/2026-06-14-system-review.md).

---

## 1. RBAC & logins (baseline)
- **User management:** `apiResource /admin/users` (create / read / update / delete). Each user has a `role` ∈ `RND | FSS | Admin` and an `is_active` flag.
- **Roles** are a single string on the user (no separate permissions table); route access is enforced by `RoleMiddleware` (exact-role match) and `is_active` is checked there too (deactivated users are blocked).
- Admin can create accounts, assign/change roles, and activate/deactivate logins. Password reset is an intended admin capability.
- **Note:** account creation / password entry is an admin action performed by a human — the app should not auto-create credentials on a user's behalf.

## 2. Audit logs (oversight)
- `GET /admin/audit-logs` exposes the system-wide change trail (Spatie activity log; Spec 5). Mutations across all roles are recorded; clinical models log field names only with PHI values redacted (Decision A).
- Per-record history is also surfaced in-app (inventory, purchase orders, patients) via `ActivityController`.
- Intended UI: an audit browser with pagination + filters (date range, actor, model). *(Currently the endpoint returns the full log unpaginated — see review doc.)*

## 3. Reports (full access)
Admin should have access to **all** report types across **all** users (not owner-scoped like RND/FSS). The Reports browser model is the same (browse → view → download/archive); Admin's view spans the whole facility. Clinical types remain PHI-bearing and Admin access should be deliberate.

## 4. Announcements
- `apiResource /admin/announcements` — create / edit / delete / pin posts with visibility `FSS | Admin | All`. Admin sees all announcements (no role filter) and is the only role that can pin.

## 5. Intended admin extras
- **Dashboard:** system KPIs + charts (admissions, NCP completion, budget, inventory) + an activity feed.
- **Settings:** hospital info / branding (shared with report letterhead), budget thresholds, notification rules.
- **Token usage:** daily/monthly chart from `ai_usage_logs` (AI cost oversight).

## 6. Calendar & Notifications
Same backend as the other roles ([`rnd.md`](rnd.md) §6–7). Admin oversight could include facility-wide events and notification-rule configuration (planned).

---

## Build status summary
| Capability | Backend | Frontend |
|---|---|---|
| User management / RBAC | ✅ endpoints | ❌ no UI |
| Audit logs | ✅ endpoint (unpaginated) | ❌ no UI |
| Announcements | ✅ endpoints | partial (shared) |
| Reports (all) | ✅ via shared browser | ✅ browser exists; Admin-wide scope not yet distinct |
| Dashboard / Settings / Token usage | ❌ | ❌ |

The first build priority for Admin is a frontend: a user/RBAC manager and an audit-log browser, then settings + token-usage.
