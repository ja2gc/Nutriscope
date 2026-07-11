# Audit Logs and Trails Revision Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Nutriscope's noisy, incomplete, privacy-risky audit implementation with one purposeful, searchable, privacy-safe event system that powers both the Admin audit page and contextual trails on clinical, budget, procurement, and report pages.

**Architecture:** Keep Spatie Activitylog as the append-only event store, but stop treating raw package properties as the API/UI contract. A central audit service will classify, sanitize, correlate, and present events; explicit domain events will replace broad mutation middleware. Global audit views and per-item trails will query the same rows through different authorized scopes.

**Tech Stack:** Laravel 13.11, PHP 8.4, Sanctum, Spatie Laravel Activitylog 4.12, MySQL, Next.js 16, React 19, TypeScript, Tailwind CSS, Vitest, PHPUnit 12.

---

## 1. Decisions This Plan Locks In

1. **Audit logs and page trails stay.** They serve different workflows but must use one event source. Global logs support oversight, security review, compliance, and investigation. Page trails answer "what happened to this patient/NCP, budget, report, or purchase order?"
2. **Four Admin tabs only:** `All Activity`, `Security`, `Clinical`, and `Operations`. Budget, reports, procurement, food service, accounts, and system are filters within those tabs, not more top-level tabs.
3. **Actions are category-specific.** Clinical supports actions such as `created`, `updated`, `deleted`, `viewed`, `downloaded`, and `exported`. Security supports `login_succeeded`, `login_failed`, `rate_limit_exceeded`, `password_changed`, and account changes. Operations supports domain verbs such as `approved`, `received`, `reversed`, `archived`, and `adjusted`. UI must not offer impossible action/category combinations.
4. **Audit page is read-only by default.** Allowed commands are filter, search, inspect, copy event reference, and permission-controlled export. No edit or delete actions. Security-only temporary IP blocking is a separate, guarded workflow and is disabled until trusted-proxy and incident-response policy checks pass.
5. **No expandable JSON.** API returns a stable, typed detail contract. UI renders labeled metadata, field-change tables, outcomes, actor, timestamp, and event-specific detail components.
6. **Clinical logs contain no clinical values.** They record field names, operation, root patient/NCP reference, actor, outcome, and time. They must not contain names, diagnoses, measurements, notes, OCR text, report snapshots, AI prompts/outputs, uploaded file contents, passwords, reset tokens, access tokens, or verification codes.
7. **Not every request becomes an audit row.** Log business state changes, sensitive-record access/download/export, authentication and authorization failures, rate-limit threshold events, and administrative/configuration changes. Do not log routine polling, list refreshes, notification reads, harmless validation mistakes, or duplicate route/model events.
8. **Root context correlates child records.** NCP child events appear in the patient/NCP trail; PO vendor groups, attachments, corrections, receiving, budget deductions, and lifecycle events appear in the PO trail.
9. **Dates and actors are first-class.** Every event has server time, outcome, actor kind (`user`, `system`, or `anonymous`), and a stable actor snapshot. System jobs may legitimately have no user, but must say which system process acted.
10. **No permanent blind IP blocking.** Optional blocks expire, require reason and authorized admin, support unblock, and audit both actions. Rate-limit logging is deduplicated so an attacker cannot bloat the database.
11. **Inventory stock auditing is retired.** `quantity_in_stock` is not an audit subject. Inventory activity UI/routes are removed first. Database columns and receiving calculations are removed only after replacing their active costing/receiving dependencies.
12. **Compatibility is staged.** Backend adds the new contract before frontend consumes it. Existing proxies and pagination remain until consumers migrate. Route removals happen only after repository-wide reference and route tests pass.

## 2. Current-State Findings

- `backend/app/Http/Middleware/AuditMiddleware.php` logs every authenticated unsafe request in the RND group after the response, including failed validation, uses no `event`, stores full URLs, and duplicates model/manual events.
- `backend/app/Models/Concerns/AuditsChanges.php` logs every fillable field. That allow-list can silently expand when future fillable fields are added.
- `backend/app/Http/Controllers/ActivityController.php` queries only the direct subject. Patient history therefore misses NCP child records; purchase-order history misses related attachments, corrections, receiving, and budget activity.
- `backend/app/Http/Resources/Admin/AuditLogResource.php` returns raw `properties` and internal numeric subject IDs.
- `frontend/app/admin/audit-logs/page.tsx` exposes expandable JSON and hard-coded subject/action filters containing actions or subjects that do not match all producers.
- `frontend/components/HistoryPanel.tsx` converts arbitrary objects to JSON strings, so the panel rule is also violated outside the Admin page.
- `activity_log` has indexes for `log_name`, causer morph, and subject morph, but no index matching event/date listing. `whereDate()` in `Admin/AuditLogController.php` also prevents efficient timestamp range use.
- Admin list queries do not constrain `log_name = audit`, so unrelated Spatie channels can appear.
- No scheduled activity cleanup exists. Spatie's configured/default retention is therefore not operationally enforced.
- Budget setup has no direct `created_by`; archived reports have `user_id` but `ReportResource` and report cards do not show the creator. Report archive/delete, branding, and template changes are not consistently audited.
- Authentication events exist, but profile/recovery-email changes and rate-limit threshold events are incomplete.
- Users are soft-deleted; relying only on the causer relationship can lose the visible actor identity. An actor snapshot is required.
- Local MySQL currently contains zero `activity_log` rows. Bloat cannot be measured from local history, so execution must include seeded volume/query-plan tests and production-safe metrics.
- Inventory is not merely a stale dropdown entry. `ReceivingService` still increments `quantity_in_stock`; controllers, resources, frontend types, and many tests still depend on it. Removal needs its own dependency-safe sequence.

## 3. Privacy, Security, and Compliance Guardrails

This plan provides technical safeguards, not a legal certification. Before production rollout, the organization's Data Protection Officer or privacy owner must approve the retention periods, roles allowed to review clinical metadata, export policy, and legal-hold process.

- Philippine Data Privacy Act Section 20 requires reasonable organizational, physical, and technical controls, vulnerability handling, and regular security monitoring. Health information is sensitive personal information; audit metadata must follow proportionality and data-minimization principles.
- HIPAA applies only if Nutriscope's operator is a covered entity or business associate, but its audit-control standard is a useful design baseline: systems containing ePHI need mechanisms to record and examine activity. HHS leaves exact event selection to risk analysis.
- OWASP recommends recording when/where/who/what, result and reason, authentication and authorization events, sensitive-data access, exports, and administrative actions; it also warns against logging health data, credentials, tokens, secrets, and full URLs carrying sensitive query strings.
- Audit-log access itself must be authorized and audited. Logs must not be editable or deletable through application routes.
- All UI timestamps use server-provided ISO 8601 UTC and display localized Asia/Manila time plus an exact timestamp tooltip. Infrastructure must use synchronized clocks.

### Recommended Retention Defaults

These defaults are implementation inputs, not hidden assumptions:

| Class | Examples | Default | Reason |
|---|---|---:|---|
| Security | login failures, rate limits, authorization failures | 365 days | Investigation window without indefinite IP/email retention |
| Clinical | patient/NCP mutation, access, download, export | 6 years | Conservative healthcare audit baseline; privacy owner must approve local requirement |
| Operations | PO lifecycle, budget, report archive, settings | 3 years | Financial/operational reconstruction |
| Low-value request diagnostics | legacy generic mutation rows | 90 days, then remove producer | Short migration-only usefulness |

A category under legal hold is excluded from pruning until the approved hold is removed. Retention and hold configuration changes are audited.

### Write-Volume Rules

- One successful business transaction produces one primary domain event; low-level model diffs are attached or suppressed, not duplicated.
- Repeated chart opens caused by remounts deduplicate per actor/patient for 15 minutes. Downloads and exports never deduplicate.
- Rate-limit and authorization-failure floods deduplicate for five minutes while cache counters retain recurrence totals.
- Daily monitoring alerts when writes exceed three times the trailing 30-day daily average, `activity_log` exceeds 1 GiB, or database disk use exceeds 70 percent; operators may lower these thresholds in `config/audit.php`.
- Monthly metrics report rows by category/action, retained bytes, prune counts, write failures, and slow audit queries. Metrics contain no event payloads.

## 4. Event Contract

Each event returned by Admin and history APIs uses this shape:

```ts
export type AuditCategory = "security" | "clinical" | "operations";
export type AuditSeverity = "info" | "notice" | "warning" | "critical";
export type AuditOutcome = "success" | "failure" | "blocked";

export interface AuditEventDto {
  id: string;
  category: AuditCategory;
  domain: "accounts" | "patients" | "ncp" | "reports" | "budget" |
    "procurement" | "food_service" | "system";
  action: string;
  action_label: string;
  summary: string;
  severity: AuditSeverity;
  outcome: AuditOutcome;
  actor: null | {
    id: string | null;
    kind: "user" | "system" | "anonymous";
    name: string;
    role: string | null;
  };
  subject: null | { type: string; id: string | null; label: string };
  context: null | { type: string; id: string | null; label: string };
  occurred_at: string;
  details: Array<{
    key: string;
    label: string;
    kind: "text" | "number" | "money" | "date" | "status" | "field_list";
    value: string | number | string[] | null;
  }>;
  changes: Array<{
    field: string;
    label: string;
    old_value: string | number | boolean | null;
    new_value: string | number | boolean | null;
    redacted: boolean;
  }>;
}
```

Clinical events always return `old_value = null`, `new_value = null`, and `redacted = true`. `details` uses an allow-list generated by backend presenters; arbitrary Spatie properties never cross the API.

### Event Coverage Policy

| Surface | Audit | Do not audit |
|---|---|---|
| Authentication/accounts | login success/failure, throttled auth, logout, password/recovery/profile changes, user CRUD, role/active-state changes, token/session rejection, authorization denial | password/token/code values; routine `me` calls |
| Clinical patient/NCP | create/update/delete, chart entry, attachment view/download/upload/delete, saved AI-assisted result/approval, clinical report view/download/export | autosave polls, repeated component GETs, prompts/outputs, OCR/file/clinical values |
| Budget/procurement | budget setup/adjustment, shopping-list approval, PO/vendor/correction/receipt/completion/reversal, budget deduction, food-service limit change | summary/list refreshes and computed previews |
| Reports | archive, generation completion/failure, view, download, export, delete, branding/template changes | live preview refreshes and report snapshot contents |
| Reference/food service | supplier, food item, recipe, FS item, menu cycle/template, shopping list and SOP version create/update/delete; USDA import | USDA search/preview, recipe cost preview, unchanged saves |
| Admin/system | AI usage limit changes, announcement create/update/delete, audit-log access/export, retention/config changes | dashboard/list refreshes, notification read/unread |
| Rate limits | first 429 per dedup window plus post-window recurrence count | every rejected retry as its own row |

Reference-data mutations use `operations`, even when a dietitian performs them. `clinical` is reserved for patient-linked events so clinical access controls and retention do not accidentally cover ordinary catalogs.

## 5. File Structure

### New Backend Files

- `backend/app/Enums/AuditAction.php` - canonical action values and labels.
- `backend/app/Enums/AuditCategory.php` - category and log-channel mapping.
- `backend/app/Enums/AuditDomain.php` - domain/filter mapping.
- `backend/app/Enums/AuditOutcome.php` - success/failure/blocked values.
- `backend/app/Enums/AuditSeverity.php` - display and alert severity.
- `backend/app/Models/AuditActivity.php` - application activity model and read-only query scopes.
- `backend/app/Services/Audit/AuditLogger.php` - only supported manual-write API.
- `backend/app/Services/Audit/AuditSanitizer.php` - allow-list, URL/query stripping, length limits, control-character removal.
- `backend/app/Services/Audit/AuditContextResolver.php` - maps child subjects to patient/NCP, PO, budget, or report roots.
- `backend/app/Services/Audit/AuditEventPresenter.php` - converts stored events to `AuditEventDto`.
- `backend/app/Http/Requests/Admin/ListAuditLogsRequest.php` - filter validation.
- `backend/app/Http/Resources/AuditEventResource.php` - shared global/trail DTO resource.
- `backend/app/Policies/AuditPolicy.php` - global, clinical, export, and item-trail authorization.
- `backend/app/Console/Commands/PruneAuditEvents.php` - category retention and category-level legal-hold-aware pruning.
- `backend/config/audit.php` - taxonomy, retention, dedup windows, category holds, export and IP-block feature flags.
- `backend/config/activitylog.php` - explicit Spatie model/configuration.
- `backend/database/migrations/2026_07_11_000001_add_metadata_and_indexes_to_activity_log_table.php` - metadata and query indexes.
- `backend/database/migrations/2026_07_11_000002_add_created_by_to_budgets_table.php` - page-level budget attribution.
- `backend/tests/Feature/Audit/AuditContractTest.php`
- `backend/tests/Feature/Audit/AuditPrivacyTest.php`
- `backend/tests/Feature/Audit/AuditAuthorizationTest.php`
- `backend/tests/Feature/Audit/AuditRetentionTest.php`
- `backend/tests/Feature/Audit/AuditRouteCoverageTest.php`
- `backend/tests/Feature/Audit/ClinicalTrailTest.php`
- `backend/tests/Feature/Audit/PurchaseOrderTrailTest.php`
- `backend/tests/Feature/Audit/SecurityAuditTest.php`
- `backend/tests/Feature/Audit/ReportAuditTest.php`

### New Frontend Files

- `frontend/types/audit.ts` - shared `AuditEventDto` contract.
- `frontend/components/audit/AuditEventTable.tsx` - stable table/list shell.
- `frontend/components/audit/AuditEventDrawer.tsx` - structured event details.
- `frontend/components/audit/AuditChangeList.tsx` - typed field diffs; no object serialization.
- `frontend/components/audit/AuditFilters.tsx` - tab-aware filters.
- `frontend/components/audit/AuditTrail.tsx` - shared contextual timeline.
- `frontend/components/audit/auditLabels.ts` - presentation labels only; backend remains taxonomy authority.
- `frontend/components/audit/AuditEventDrawer.test.tsx`
- `frontend/app/admin/audit-logs/audit-page-contract.test.tsx`

### Existing Files With Coordinated Changes

- Backend core: `backend/routes/api.php`, `backend/bootstrap/app.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/app/Models/Concerns/AuditsChanges.php`, `backend/app/Http/Middleware/AuditMiddleware.php`, `backend/app/Http/Controllers/ActivityController.php`, `backend/app/Http/Controllers/Admin/AuditLogController.php`, `backend/app/Http/Resources/Admin/AuditLogResource.php`, `backend/app/Http/Controllers/Admin/DashboardController.php`.
- Auth/security: `backend/app/Http/Controllers/Auth/AuthController.php`, `backend/app/Http/Controllers/Auth/PasswordResetController.php`, `backend/app/Http/Controllers/Auth/RecoveryEmailController.php`, `backend/app/Http/Controllers/Admin/UserController.php`.
- Clinical models: `backend/app/Models/Patient.php`, `backend/app/Models/NcpRecord.php`, `backend/app/Models/Assessment.php`, `backend/app/Models/Diagnosis.php`, `backend/app/Models/Intervention.php`, `backend/app/Models/MealPlan.php`, `backend/app/Models/Monitoring.php`, and `backend/app/Models/ScreeningDocument.php`.
- Clinical controllers: `backend/app/Http/Controllers/RND/AssessmentController.php`, `backend/app/Http/Controllers/RND/ScreeningDocumentController.php`, `backend/app/Http/Controllers/RND/AiDiagnosisController.php`, `backend/app/Http/Controllers/RND/MonitoringController.php`, and `backend/app/Http/Controllers/RND/MealPlanController.php`.
- Operations: `backend/app/Http/Controllers/FSS/BudgetController.php`, `backend/app/Http/Controllers/FSS/FoodServiceSettingController.php`, `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`, `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`, `backend/app/Services/FSS/ReceivingService.php`, and `backend/app/Listeners/BudgetLedgerListener.php`.
- Reports: `backend/app/Models/Report.php`, `backend/app/Models/ReportBranding.php`, `backend/app/Models/ReportTemplate.php`, `backend/app/Http/Controllers/ReportController.php`, `backend/app/Http/Controllers/ReportBrandingController.php`, `backend/app/Http/Controllers/ReportTemplateController.php`, and `backend/app/Http/Resources/ReportResource.php`.
- Frontend contracts: `frontend/services/auditLogService.ts`, `frontend/services/activityService.ts`, `frontend/components/HistoryPanel.tsx`, `frontend/app/admin/audit-logs/page.tsx`, `frontend/app/admin/dashboard/page.tsx`, `frontend/services/adminDashboardService.ts`, `frontend/components/reports/ReportsBrowser.tsx`, `frontend/components/budget/BudgetPageShell.tsx`.
- Next proxies: `frontend/app/api/admin/audit-logs/route.ts`, `frontend/app/api/rnd/patients/[id]/activity/route.ts`, `frontend/app/api/fss/purchase-orders/[id]/activity/route.ts`, and inventory activity proxy removal.
- New trail proxies: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/activity/route.ts`, `frontend/app/api/fss/budgets/[id]/activity/route.ts`, `frontend/app/api/admin/budgets/[id]/activity/route.ts`, `frontend/app/api/rnd/reports/[id]/activity/route.ts`, and `frontend/app/api/admin/reports/[id]/activity/route.ts`.

## 6. Implementation Tasks

### Task 1: Freeze Existing API and Route Behavior With Characterization Tests

**Files:**
- Create: `backend/tests/Feature/Audit/AuditRouteCoverageTest.php`
- Modify: `backend/tests/Feature/AdminAuditLogTest.php`
- Modify: `backend/tests/Feature/AuditTrailTest.php`
- Modify: `frontend/app/admin/audit-logs/audit-filter-contract.test.ts`

- [ ] Write tests that enumerate every unsafe route from `php artisan route:list --json` and classify it as `explicit_event`, `model_event`, or `intentionally_not_audited` with a reason.
- [ ] Add tests proving current pagination metadata and proxy query forwarding before changing DTO fields.
- [ ] Add failing tests showing patient and PO trails currently omit child-subject events.
- [ ] Add failing tests showing clinical raw values and arbitrary properties must never appear in Admin or trail responses.
- [ ] Run:

```powershell
cd backend
php artisan test tests/Feature/Audit/AuditRouteCoverageTest.php tests/Feature/AdminAuditLogTest.php tests/Feature/AuditTrailTest.php
cd ../frontend
npm test -- app/admin/audit-logs/audit-filter-contract.test.ts
```

Expected: characterization assertions pass; new contract/privacy/root-context assertions fail for the intended missing behavior.

- [ ] Commit:

```powershell
git add backend/tests/Feature/Audit frontend/app/admin/audit-logs/audit-filter-contract.test.ts
git commit -m "test: define audit coverage contract"
```

### Task 2: Add Taxonomy, Metadata, and Query Indexes

**Files:**
- Create enums and `backend/app/Models/AuditActivity.php` listed in Section 5.
- Create: `backend/config/audit.php`
- Create: `backend/config/activitylog.php`
- Create: `backend/database/migrations/2026_07_11_000001_add_metadata_and_indexes_to_activity_log_table.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`

- [ ] Define backed enums. Action values must include:

```php
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Viewed = 'viewed';
    case Downloaded = 'downloaded';
    case Exported = 'exported';
    case Approved = 'approved';
    case Received = 'received';
    case Reversed = 'reversed';
    case Archived = 'archived';
    case Adjusted = 'adjusted';
    case Uploaded = 'uploaded';
    case Generated = 'generated';
    case Completed = 'completed';
    case PriceCorrected = 'price_corrected';
    case ProfileChanged = 'profile_changed';
    case SettingsChanged = 'settings_changed';
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    case AuthenticationFailed = 'authentication_failed';
    case Logout = 'logout';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case RecoveryEmailChanged = 'recovery_email_changed';
    case RecoveryEmailVerified = 'recovery_email_verified';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case AuthorizationDenied = 'authorization_denied';
    case AuditLogViewed = 'audit_log_viewed';
    case AccountBlocked = 'account_blocked';
    case AccountUnblocked = 'account_unblocked';
    case IpBlocked = 'ip_blocked';
    case IpUnblocked = 'ip_unblocked';
}
```

- [ ] Add nullable `category`, `domain`, `severity`, `outcome`, `context_type`, and `context_id` columns. Add indexes matching `(log_name, created_at, id)`, `(category, created_at, id)`, `(event, created_at, id)`, and `(context_type, context_id, created_at, id)`. Reuse Spatie's existing `batch_uuid` as interaction/correlation ID instead of adding another UUID column. Do not modify the original package migrations.
- [ ] Configure Spatie to use `App\Models\AuditActivity` and preserve existing table name.
- [ ] Add query scopes `auditOnly()`, `forCategory()`, `forContext()`, and timestamp-range filters that use `where('created_at', '>=', startOfDay)` rather than `whereDate()`.
- [ ] Set defaults for old rows at presentation time. Do not run a large blocking data backfill in the schema migration.
- [ ] Run migration and index tests:

```powershell
cd backend
php artisan migrate
php artisan test tests/Feature/Audit/AuditContractTest.php
```

Expected: migration succeeds; new fields/index assertions pass; old rows remain readable.

- [ ] Commit with `feat: add audit event taxonomy`.

### Task 3: Centralize Writing, Sanitization, and Actor Snapshots

**Files:**
- Create: `backend/app/Services/Audit/AuditLogger.php`
- Create: `backend/app/Services/Audit/AuditSanitizer.php`
- Create: `backend/app/Services/Audit/AuditContextResolver.php`
- Modify: `backend/app/Models/Concerns/AuditsChanges.php`
- Test: `backend/tests/Feature/Audit/AuditPrivacyTest.php`

- [ ] Implement one manual logger API:

```php
public function record(
    AuditAction $action,
    AuditCategory $category,
    AuditDomain $domain,
    Model|null $subject = null,
    Model|null $context = null,
    AuditOutcome $outcome = AuditOutcome::Success,
    AuditSeverity $severity = AuditSeverity::Info,
    array $details = [],
    Authenticatable|null $actor = null,
    string|null $systemActor = null,
): AuditActivity;
```

- [ ] Sanitize before storage: reject forbidden keys matching `password`, `token`, `secret`, `authorization`, `cookie`, `verification_code`, `snapshot`, `prompt`, `response`, `ocr`, `body`, and clinical-value keys. Strip URL query/fragment, remove CR/LF/control characters, cap user agent and detail values, and normalize IP through trusted Laravel request handling.
- [ ] Store actor snapshot `{public_id, name, role, kind}` so soft-deleted users remain attributable. Anonymous login failures store normalized/partially masked email identifier, not a full recovery email.
- [ ] Replace `logOnly($this->getFillable())` with explicit per-model `auditAttributes()` methods. Clinical models return field names only; operations models return approved before/after values.
- [ ] Add a test that adds a fake secret to a model's fillable list and proves it does not enter the log.
- [ ] Add tests for CR/LF injection, oversized fields, full URL query removal, clinical redaction, deleted actors, and system actors.
- [ ] Run `php artisan test tests/Feature/Audit/AuditPrivacyTest.php` and expect all tests to pass.
- [ ] Commit with `feat: centralize sanitized audit writes`.

### Task 4: Remove Generic Request Noise and Create Explicit Coverage Matrix

**Files:**
- Modify: `backend/routes/api.php`
- Delete after references are gone: `backend/app/Http/Middleware/AuditMiddleware.php`
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/app/Http/Controllers/RND/FoodItemController.php`
- Modify: `backend/app/Http/Controllers/RND/RecipeController.php`
- Modify: `backend/app/Http/Controllers/RND/UsdaController.php`
- Modify: `backend/app/Http/Controllers/RND/AnnouncementController.php`
- Modify: `backend/app/Http/Controllers/FSS/SupplierController.php`
- Modify: `backend/app/Http/Controllers/FSS/FsItemController.php`
- Modify: `backend/app/Http/Controllers/FSS/ShoppingListController.php`
- Modify: `backend/app/Http/Controllers/FSS/MenuCycleController.php`
- Modify: `backend/app/Http/Controllers/FSS/MenuCycleTemplateController.php`
- Modify: `backend/app/Http/Controllers/Admin/AiUsageLimitController.php`
- Modify: `backend/app/Http/Controllers/Admin/AnnouncementController.php`
- Modify: `backend/app/Http/Controllers/SopController.php`
- Modify: `backend/tests/Feature/AuditMiddlewareTest.php`
- Modify: `backend/tests/Feature/Audit/AuditRouteCoverageTest.php`

- [ ] Remove `audit` from the full RND group and budget subgroup. Remove middleware alias only after no route references remain.
- [ ] Delete generic "Accessed path" production. Failed security/authorization outcomes are recorded by dedicated handlers; business mutations are recorded by model/domain events.
- [ ] Maintain a machine-readable route coverage map in `backend/config/audit.php`. Each unsafe route must map to a named event source or an exclusion reason. Notification read markers are excluded; report archive, clinical attachment changes, user/config changes, and PO lifecycle are included.
- [ ] Add sanitized operations events for supplier, food item, recipe, FS item, menu cycle/template, shopping list, USDA import, announcement, SOP version, and AI usage-limit mutations. Log identifiers, action, actor, date, and safe changed fields; exclude announcement/SOP bodies and imported API payloads.
- [ ] Add a test that fails whenever a new unsafe route lacks a coverage-map entry.
- [ ] Verify no frontend proxy endpoint changes in this task.
- [ ] Run route and middleware tests; then commit with `refactor: replace request audit middleware`.

### Task 5: Security Events, Rate Limits, and Optional Temporary Blocks

**Files:**
- Modify auth/admin controllers and `backend/app/Providers/AppServiceProvider.php` listed in Section 5.
- Modify: `backend/bootstrap/app.php`
- Create: `backend/app/Services/Audit/SecurityAuditDeduplicator.php`
- Optional, feature-flagged create: `backend/app/Models/SecurityBlock.php`
- Optional migration: `backend/database/migrations/2026_07_11_000003_create_security_blocks_table.php`
- Optional middleware: `backend/app/Http/Middleware/RejectSecurityBlocks.php`
- Optional controller: `backend/app/Http/Controllers/Admin/SecurityBlockController.php`
- Optional proxies: `frontend/app/api/admin/security-blocks/route.ts` and `frontend/app/api/admin/security-blocks/[id]/route.ts`
- Test: `backend/tests/Feature/Audit/SecurityAuditTest.php`

- [ ] Normalize existing `login` to `login_succeeded`; keep a legacy presentation alias so old rows display correctly.
- [ ] Record login success/failure, logout, password change/reset, profile change, recovery-email change/verification, admin account create/update/deactivate/role change/password reset, and authorization denial.
- [ ] In `backend/bootstrap/app.php`, record deduplicated `authentication_failed` and `authorization_denied` events for rejected protected requests using actor/public reference, safe route name, outcome, and status only. Do not log headers, cookies, bearer tokens, or request bodies.
- [ ] Use Laravel 13 `Limit::response()` callbacks for named limiters to record a `rate_limit_exceeded` event when a 429 is produced. Record limiter name, safe route name, actor/public account reference when known, IP, retry-after seconds, and outcome `blocked`; never record request body.
- [ ] Deduplicate repeated 429 events by `(limiter, actor-or-IP, route)` for five minutes and increment a cache counter. Emit a new row only on first threshold and after cooldown, preventing attacker-driven DB growth.
- [ ] Keep permanent IP blocking out. Implement optional expiring blocks only when `AUDIT_SECURITY_BLOCKS_ENABLED=true`; require expiry, reason, creator, and revoke fields. Never auto-block from one rate-limit event.
- [ ] Verify `TrustProxies`/deployment proxy configuration before enabling IP actions. If source IP cannot be trusted, keep feature disabled.
- [ ] Add tests for 429 logging/dedup, shared-NAT safety, missing user, no secrets, block expiry, unblock, self-auditing block actions, and disabled feature behavior.
- [ ] Run `php artisan test tests/Feature/AuthAuditEventTest.php tests/Feature/ForgotPasswordTest.php tests/Feature/Audit/SecurityAuditTest.php`.
- [ ] Commit with `feat: add security audit events`.

### Task 6: Clinical Audit Coverage and Privacy-Safe Root Trails

**Files:**
- Modify audited clinical models listed in Section 5.
- Modify: `backend/app/Http/Controllers/RND/AssessmentController.php`
- Modify: `backend/app/Http/Controllers/RND/ScreeningDocumentController.php`
- Modify: `backend/app/Http/Controllers/RND/AiDiagnosisController.php`
- Modify: `backend/app/Http/Controllers/RND/MonitoringController.php`
- Modify: `backend/app/Http/Controllers/RND/MealPlanController.php`
- Modify: `backend/app/Http/Controllers/ActivityController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Audit/ClinicalTrailTest.php`

- [ ] Resolve every Patient, NcpRecord, Assessment, Diagnosis, Intervention, MealPlan, Monitoring, ScreeningDocument, and clinical report event to a root patient plus optional NCP context.
- [ ] Add explicit events for attachment upload/view/download/delete, patient chart open, NCP report view/download/export, AI suggestion/approval, and meal-plan generation. Log IDs/status/field names only; never file names containing patient names, file contents, OCR text, clinical measurements, prompts, or outputs.
- [ ] Do not log every nested GET/poll. Log deliberate chart entry and protected file/report access. Use a short request/session dedup only for repeated chart-open events caused by frontend remounts; never dedup downloads or exports.
- [ ] Replace patient-only direct-subject history with context query, newest first, 100-row cursor/`before_id` pagination. Keep the existing route during migration: `GET /api/rnd/patients/{patient}/activity`.
- [ ] Add `GET /api/rnd/ncp-records/{ncpRecord}/activity` for an NCP-specific timeline. Add `frontend/app/api/rnd/ncp-records/[ncpRecordId]/activity/route.ts`; preserve patient route for whole-chart history.
- [ ] Authorize patient and NCP history through `AuditPolicy`; role membership alone is insufficient. Admin audit view receives pseudonymous clinical references and field labels only. RND page trail may show patient context already visible on that authorized page, but API still excludes clinical values.
- [ ] Record audit-log access when an authorized user opens clinical audit detail or exports clinical audit results.
- [ ] Test all clinical models and controllers with unique sentinel PHI strings; assert those strings are absent from `activity_log`, JSON API, application logs, and rendered DTOs.
- [ ] Run clinical audit, NCP, attachment, and report tests. Commit with `feat: add privacy-safe clinical trails`.

### Task 7: Purchase Order, Food Service, and Budget Trails

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`
- Modify: `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`
- Modify: `backend/app/Services/FSS/ReceivingService.php`
- Modify: `backend/app/Models/PurchaseOrder.php`
- Modify: `backend/app/Models/PurchaseOrderItemCorrection.php`
- Modify: `backend/app/Listeners/BudgetLedgerListener.php`
- Modify: `backend/app/Http/Controllers/FSS/BudgetController.php`
- Modify: `backend/app/Http/Controllers/FSS/FoodServiceSettingController.php`
- Modify: `backend/app/Http/Resources/BudgetResource.php`
- Create: `backend/database/migrations/2026_07_11_000002_add_created_by_to_budgets_table.php`
- Test: `backend/tests/Feature/Audit/PurchaseOrderTrailTest.php`

- [ ] Correlate PO, shopping-list approval, vendor-group edits, attachments, item-price corrections, receiving, completion, reversal, and budget deduction to the root PO context.
- [ ] Use business actions (`approved`, `received`, `completed`, `reversed`, `price_corrected`) instead of generic `updated` where workflow meaning exists.
- [ ] Keep PO completion and budget deduction atomic. Because `PurchaseOrderCompleted` currently runs inside the DB transaction and the ledger is a required state change, do not convert it to an after-commit queued listener. Add rollback tests proving PO, ledger, and audit rows all roll back together.
- [ ] Add `created_by` to budgets, set it from authenticated user during setup, expose creator public ID/name and `created_at` in `BudgetResource`, and show system/manual actor on ledger rows.
- [ ] Add authorized `GET /api/fss/budgets/{budget}/activity` and `GET /api/admin/budgets/{budget}/activity` routes plus matching Next proxies. Both use budget context rows; Admin endpoint remains read-only.
- [ ] Audit food-service setting changes with old/new limit and actor; do not audit reads.
- [ ] Keep direct model events for low-level diffs only when they do not duplicate a richer lifecycle event. Disable model logging around service updates that already emit one complete domain event.
- [ ] Run purchase-order, receiving, budget audit, and ledger tests. Commit with `feat: unify operations audit trails`.

### Task 8: Reports, Branding, Templates, and Page Attribution

**Files:**
- Modify report models/controllers/resource listed in Section 5.
- Modify: `frontend/components/reports/ReportsBrowser.tsx`
- Modify: `frontend/services/reportService.ts`
- Test: `backend/tests/Feature/Audit/ReportAuditTest.php`
- Test: `frontend/app/admin/reports/page.test.ts`

- [ ] Treat archived reports as immutable records. User actions are `archived`, `viewed`, `downloaded`, `exported`, and `deleted`; background generation status/file updates are one system lifecycle event, not user modifications.
- [ ] Record report type, period/instance reference, outcome, and report public ID. Never store filters/snapshot fields that contain patient data in the audit event.
- [ ] Audit branding and report-template changes using explicit safe allow-lists. Signatory changes show field-level labels and safe names only when policy permits; images/data URLs never enter activity rows.
- [ ] Eager-load report creator in index/show and return `created_by: { id, name }`, `created_at`, `generated_at`, and `updated_at`. Display creator and archive date in report cards/detail.
- [ ] Add contextual report history or structured metadata block from the same event DTO; do not create a separate report-history table.
- [ ] Add authorized `GET /api/rnd/reports/{report}/activity` and `GET /api/admin/reports/{report}/activity` routes plus matching Next proxies. Place static/specific activity routes so resource binding cannot shadow them.
- [ ] Remove deprecated `ReportController::store()` and `generateAll()`, their `POST reports` / `POST reports/generate-all` routes, `backend/app/Http/Requests/StoreReportRequest.php`, and `frontend/app/api/rnd/reports/generate-all/route.ts` only after repository search proves no caller remains. Their removal is a separate commit after route tests.
- [ ] Run report backend/frontend tests and commit with `feat: audit report lifecycle`.

### Task 9: Retire Stale Inventory Audit and Stock Surface Safely

**Files:**
- Modify: `backend/app/Models/Inventory.php`
- Modify: `backend/app/Http/Controllers/ActivityController.php`
- Modify: `backend/routes/api.php`
- Create after dependency replacement: `backend/database/migrations/2026_07_11_000004_drop_retired_inventory_stock_fields.php`
- Delete: `frontend/app/api/fss/inventory/[id]/activity/route.ts`
- Modify: `frontend/services/inventoryService.ts`
- Modify: `frontend/app/(rnd)/food-service/inventory/page.tsx`
- Modify: `backend/app/Services/FSS/ReceivingService.php`
- Modify: `backend/app/Http/Controllers/FSS/InventoryController.php`
- Delete after caller removal: `backend/app/Http/Requests/FSS/StoreInventoryRequest.php`, `UpdateInventoryRequest.php`
- Modify: `backend/tests/Feature/AuditTrailTest.php`, `FoodServiceOpsTest.php`

- [ ] Immediately remove `AuditsChanges` from `Inventory`, remove inventory from Admin audit filters, and remove inventory activity route/proxy/history UI. Replace audit tests with `FsItem` or PO lifecycle tests.
- [ ] Remove unreachable `store`, `update`, `destroy`, and `restock` methods from `InventoryController` after route-list proof confirms they are not wired.
- [ ] Replace recipe/procurement catalog naming and TypeScript `StockStatus`, `quantity_in_stock`, `in_stock`, and `no_stock` fields with catalog/receiving concepts actually used by current workflows.
- [ ] Before dropping stock columns, replace `ReceivingService` quantity increments with immutable receipt/price history or PO receiving quantities already owned by PO items/corrections. Prove menu costing, recipe unit validation, procurement selection, dashboard, and reports still work.
- [ ] Create forward-only migration `backend/database/migrations/2026_07_11_000004_drop_retired_inventory_stock_fields.php` to drop `quantity_in_stock` and dead inventory columns only after `rg` finds no runtime references and full food-service tests pass. Do not combine data backfill and column drop in one migration.
- [ ] Run `rg -n "quantity_in_stock|StockStatus|in_stock|no_stock" backend/app frontend` and expect no stock-management runtime references before column removal.
- [ ] Commit first audit/UI retirement as `refactor: retire inventory audit trail`; commit data-model retirement separately as `refactor: remove stock management fields`.

### Task 10: Build Authorized Audit Query and Structured API

**Files:**
- Create: `backend/app/Http/Requests/Admin/ListAuditLogsRequest.php`
- Create: `backend/app/Http/Resources/AuditEventResource.php`
- Delete after compatibility response is removed: `backend/app/Http/Resources/Admin/AuditLogResource.php`
- Create: `backend/app/Services/Audit/AuditEventPresenter.php`
- Create: `backend/app/Policies/AuditPolicy.php`
- Modify: `backend/app/Http/Controllers/Admin/AuditLogController.php`
- Create: `backend/app/Http/Controllers/Admin/AuditLogExportController.php`
- Modify: `backend/app/Http/Controllers/ActivityController.php`
- Modify: `backend/app/Http/Controllers/Admin/DashboardController.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`

- [ ] Validate filters against enum allow-lists: `category`, `domain`, `action`, `severity`, `outcome`, actor public UUID, subject/context public ID, `start`, `end`, page, and per-page.
- [ ] Constrain all global audit queries to audit channels. Eager-load causer with selected columns and avoid N+1 subject loading by using stored safe labels/context snapshots.
- [ ] Preserve offset pagination for Admin page/dashboard during frontend migration. Context trails may adopt `before_id` pagination separately.
- [ ] Return only `AuditEventDto`; remove `properties`, raw model class names, internal numeric IDs, email addresses, full URLs, and `updated_at` from public response.
- [ ] Add gates for `viewAny`, `viewClinical`, `viewSecurity`, `export`, and `viewTrail`. Deduplicate `audit_log_viewed` to one event per actor per 15 minutes, while always logging exports and clinical-detail access; this satisfies oversight without recursively logging each pagination request.
- [ ] Add feature-flagged `GET /api/admin/audit-logs/export` through `AuditLogExportController`. It streams the same authorized/filter-scoped DTO fields as CSV, caps exports at 50,000 rows, and never includes `properties`, PHI, email, full IP for clinical rows, or internal IDs. Default `AUDIT_EXPORT_ENABLED=false`.
- [ ] Update dashboard counts to audit-only channels and define whether counts represent retained rows or time-window activity. Keep existing keys until frontend dashboard migrates.
- [ ] Use `EXPLAIN` in a test or documented verification query for default list, category/date, event/date, actor/date, and context/date queries.
- [ ] Run API authorization/contract tests and commit with `feat: expose structured audit API`.

### Task 11: Replace Admin JSON UI With Four Purposeful Views

**Files:**
- Create frontend audit components listed in Section 5.
- Modify: `frontend/app/admin/audit-logs/page.tsx`
- Modify: `frontend/services/auditLogService.ts`
- Modify: `frontend/app/api/admin/audit-logs/route.ts`
- Create: `frontend/app/api/admin/audit-logs/export/route.ts`
- Modify: `frontend/app/admin/audit-logs/audit-filter-contract.test.ts`
- Create: `frontend/app/admin/audit-logs/audit-page-contract.test.tsx`

- [ ] Add four tabs only. Filters inside tabs: date range, domain, action, actor, outcome, severity. Return allowed category/domain/action options in the audit-list response `meta.filters`; render those options rather than maintaining a second hard-coded frontend taxonomy.
- [ ] Table columns: time, action, actor, subject/context, outcome, severity, summary. Row click opens structured drawer.
- [ ] Drawer sections: event summary, actor, subject/context, result, safe request metadata, field changes. Clinical change rows say "Value hidden; field changed" and never render placeholder bullets as a value.
- [ ] Remove every `JSON.stringify`, `<pre>`, raw properties expander, and hard-coded class-name dropdown.
- [ ] Security tab may show a temporary-block command only when API capability says enabled and user has permission. Require confirmation, reason, and expiry; never place block buttons on all rows.
- [ ] Add loading, empty, error, unauthorized, and no-results states. Keep filters in URL search params for review links, excluding sensitive free text.
- [ ] Test longest actor/subject labels, mobile table alternative, keyboard drawer access, category/action compatibility, and absence of raw JSON.
- [ ] Run:

```powershell
cd frontend
npm test -- app/admin/audit-logs components/audit
npx tsc --noEmit
npm run lint -- app/admin/audit-logs components/audit services/auditLogService.ts
```

- [ ] Commit with `feat: redesign audit log interface`.

### Task 12: Replace Contextual History Panels With Shared Structured Trails

**Files:**
- Create: `frontend/components/audit/AuditTrail.tsx`
- Modify: `frontend/components/HistoryPanel.tsx`
- Modify: `frontend/services/activityService.ts`
- Modify: `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx`
- Modify: `frontend/app/(rnd)/food-service/procurement/page.tsx`
- Modify: `frontend/components/budget/BudgetPageShell.tsx`
- Modify: `frontend/components/reports/ReportsBrowser.tsx`
- Modify: `frontend/app/api/rnd/patients/[id]/activity/route.ts`
- Create: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/activity/route.ts`
- Modify: `frontend/app/api/fss/purchase-orders/[id]/activity/route.ts`
- Create: `frontend/app/api/fss/budgets/[id]/activity/route.ts`
- Create: `frontend/app/api/admin/budgets/[id]/activity/route.ts`
- Create: `frontend/app/api/rnd/reports/[id]/activity/route.ts`
- Create: `frontend/app/api/admin/reports/[id]/activity/route.ts`

- [ ] Make `activityService` consume the same `AuditEventDto` as Admin service; keep a shared type module to prevent drift.
- [ ] Remove arbitrary object formatting and JSON serialization from `HistoryPanel`; migrate callers to `AuditTrail` and then delete the old component when `rg` finds no imports.
- [ ] Patient/NCP trail shows root-correlated clinical events with field-name-only changes. PO trail shows complete workflow. Budget page shows creator/date plus ledger/domain events. Reports show creator/archive date plus lifecycle events.
- [ ] Preserve existing route paths until each page and proxy is migrated; remove only inventory activity route.
- [ ] Add component tests for user/system actors, exact dates, redacted clinical fields, deletion events whose subject no longer exists, and pagination.
- [ ] Run frontend tests/typecheck and backend trail tests. Commit with `feat: unify contextual audit trails`.

### Task 13: Retention, Integrity, Monitoring, and Failure Behavior

**Files:**
- Create: `backend/app/Console/Commands/PruneAuditEvents.php`
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/config/audit.php`
- Modify: `docs/security/security.md`
- Test: `backend/tests/Feature/Audit/AuditRetentionTest.php`

- [ ] Implement category-specific pruning in chunks using indexed timestamp ranges. `config/audit.php` contains `legal_hold` booleans per category; pruning refuses a held category. Dry-run reports counts; `--force` performs deletion.
- [ ] Schedule daily with `withoutOverlapping()` and `onOneServer()`. Emit a system event for prune completion/failure containing counts only.
- [ ] Do not run `OPTIMIZE TABLE` automatically; Spatie warns it can lock MySQL. Document maintenance-window use only.
- [ ] Deny application update/delete of audit rows outside prune service. No HTTP mutation route exists for logs.
- [ ] Add optional integrity export/hash-chain or external append-only sink as production-hardening phase. The minimum release must monitor unauthorized row mutation/deletion, log-writer failures, and sudden event-volume spikes.
- [ ] Define failure behavior: required financial/clinical mutation audit writes participate in transaction and fail the mutation if logging cannot persist; non-critical security telemetry reports failure without exposing secrets or recursively flooding logs.
- [ ] Add volume test with at least 100,000 generated events and assert default/date/context queries use intended indexes, avoid full table scans, and complete with p95 at or below 250 ms in the documented staging environment.
- [ ] Commit with `feat: enforce audit retention and monitoring`.

### Task 14: Compatibility Cleanup, Documentation, and Full Verification

**Files:**
- Modify: `docs/security/security.md`
- Modify: `docs/modules/admin.md`
- Modify: `docs/modules/rnd.md`
- Modify: `docs/modules/fss.md`
- Create: `docs/architecture/audit-logging.md`
- Modify/delete old audit tests only after replacement coverage exists.

- [ ] Document event taxonomy, category/action matrix, actor/system semantics, retention, privacy redaction, export policy, incident workflow, route coverage rule, and page-trail behavior.
- [ ] Add an operator runbook: review cadence, high-severity alert owner, legal hold, export handling, prune monitoring, clock synchronization, and emergency temporary-block reversal.
- [ ] Run stale-code scans:

```powershell
rg -n "JSON\.stringify|<pre|properties" frontend/app/admin/audit-logs frontend/components/audit frontend/components/HistoryPanel.tsx
rg -n "AuditMiddleware|'audit' =>.*AuditMiddleware|middleware\('audit'\)" backend
rg -n "quantity_in_stock|StockStatus|in_stock|no_stock" backend/app frontend
rg -n "App\\Models\\Inventory|login_failed.*login|subject_type.*App\\Models" frontend/app/admin/audit-logs frontend/services
```

Expected: no raw JSON audit UI, no generic audit middleware wiring, no stock-management runtime surface after stock-removal phase, and no stale hard-coded model/action filters.

- [ ] Run full backend verification:

```powershell
cd backend
php artisan route:list --except-vendor
php artisan test
vendor/bin/pint --test
```

- [ ] Run full frontend verification:

```powershell
cd frontend
npm test
npx tsc --noEmit
npm run lint
npm run build
```

- [ ] Review migration rollback on a fresh MySQL test database and forward migration against a copy containing legacy audit rows.
- [ ] Verify no route/proxy mismatch by comparing Laravel route list with every `laravelProxy` target under `frontend/app/api`.
- [ ] Verify git diff contains no unrelated changes and no commit metadata/message prohibited by repository policy.
- [ ] Commit final docs/cleanup with `docs: document audit operations`.

## 7. Blast-Radius Checklist

- **Routes:** preserve patient and PO activity URLs during DTO migration; remove middleware and inventory route only with route tests.
- **Next proxies:** backend path or query changes must update matching `frontend/app/api/**/route.ts` in same commit.
- **Frontend types:** Admin table, dashboard recent events, and contextual trails share one DTO; update all three before removing legacy resource fields.
- **Pagination:** Admin remains offset-paginated until consumers migrate; trails can use `before_id` independently.
- **Dashboard metrics:** scope counts to audit channels and document retained-row semantics.
- **Deleted users/subjects:** actor and subject labels remain through safe snapshots; API never depends on loading deleted models.
- **Transactions:** PO, receiving, budget ledger, and required audit row remain atomic. Report generation system events must reflect actual completed/failed job state.
- **Queues:** events that represent completed queued work are written after the work succeeds; do not claim completion when merely queued.
- **Clinical privacy:** sentinel-value tests cover DB rows, API, UI DTO, exports, and application logs.
- **Database performance:** new list/context indexes, timestamp ranges, chunked pruning, and volume test address growth.
- **Database storage:** deduplicated 429s and removal of request-level rows control write volume. Retention and monitoring control long-term size.
- **Exports:** exports are permission-controlled, watermarked/identified, themselves audited, and exclude raw properties/PHI.
- **IP accuracy:** blocking stays disabled until reverse-proxy trust is verified; shared NAT cannot cause permanent denial.
- **Seeds/tests:** seeders and factories disable audit recording unless a test explicitly needs audit rows, preventing polluted environments.
- **Stale data:** legacy rows get presentation aliases and safe fallback classification; no destructive backfill is required for initial deployment.
- **Inventory:** audit retirement is immediate, but stock column removal waits for receipt/costing replacement and complete FSS tests.
- **Rollback:** feature flags allow new UI, security telemetry, and IP block capability to be disabled independently. Schema additions remain backward compatible during rollout.

## 8. Rollout Order

1. Deploy additive schema/index/config and new writer/presenter behind flags.
2. Dual-read legacy/new rows through new resource; keep old UI contract temporarily.
3. Replace generic middleware and instrument explicit domain/security/clinical events.
4. Deploy structured API, then Admin UI and contextual trail consumers.
5. Enable category retention after privacy-owner approval and backup verification.
6. Remove legacy resource/properties contract and stale filters after telemetry shows no old consumer.
7. Retire inventory audit/UI; complete stock data-model removal only after FSS dependency tests pass.
8. Evaluate optional temporary IP blocking after trusted-proxy and incident-response approval.

## 9. External References

- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html) - event purpose, when/where/who/what, outcomes, sensitive-data exclusions, access control, tamper protection, monitoring, and disposal.
- [NIST SP 800-92: Guide to Computer Security Log Management](https://csrc.nist.gov/pubs/sp/800/92/final) - organization-wide log management, infrastructure, and operational processes.
- [Philippine Data Privacy Act of 2012](https://privacy.gov.ph/data-privacy-act/) and [Implementing Rules and Regulations](https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/) - sensitive personal information safeguards, proportional security, monitoring, confidentiality, and data-subject rights.
- [HHS HIPAA Security Rule Summary](https://www.hhs.gov/hipaa/for-professionals/security/laws-regulations/index.html) - access control, audit controls, integrity, authentication, and transmission safeguards for ePHI systems when applicable.
- [Laravel 13 Rate Limiting](https://laravel.com/docs/13.x/routing#defining-rate-limiters) - named limiters and custom 429 response callbacks.
- [Laravel 13 Authorization](https://laravel.com/docs/13.x/authorization) - policies and gates for global and record-level audit access.
- [Laravel 13 Events and Transactions](https://laravel.com/docs/13.x/events#dispatching-events-after-database-transactions) - after-commit semantics; this plan deliberately keeps required PO/ledger audit state atomic inside the transaction.
- [Laravel 13 Task Scheduling](https://laravel.com/docs/13.x/scheduling#preventing-task-overlaps) - scheduled pruning with overlap protection.
- [Spatie Laravel Activitylog 4: Cleaning the Log](https://spatie.be/docs/laravel-activitylog/v4/basic-usage/cleaning-up-the-log) - package cleanup command and MySQL maintenance warning.
- [Spatie Laravel Activitylog 4: Logging Model Events](https://spatie.be/docs/laravel-activitylog/v4/advanced-usage/logging-model-events) - explicit attributes, dirty-only changes, empty-log suppression, and activity tapping.

## 10. Owner Approval Gates Before Production Enablement

Implementation can proceed with feature flags and defaults above. Production enablement requires explicit recorded approval for:

1. Clinical/security/operations retention periods and legal-hold owner.
2. Which existing roles may view clinical audit metadata and export it.
3. Whether report/audit exports are required at all; default is disabled.
4. Whether temporary IP blocking is needed; default is disabled.
5. Confirmation that `quantity_in_stock` has no remaining product purpose after receiving/costing replacement.
