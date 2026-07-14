# Shared RND Access and Audit Controls Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every active RND able to view and edit every patient/NCP while preserving creator and actual-actor attribution, add an Admin-controlled DB-backed scheduled-retention switch, and remove dormant IP-blocking scaffolding.

**Architecture:** `rnd_user_id` remains immutable creator attribution and is removed from authorization decisions; the existing RND role middleware and `AuditPolicy` provide access. A batched clinical-attribution service resolves NCP creator and latest clinical actor without N+1 queries. Scheduled retention reads a single persisted setting with the environment/config value as fallback only while no row exists; Admin changes are explicit, transactional, and audited.

**Tech Stack:** Laravel 13.11, PHP 8.4, MySQL, PHPUnit 12, Next.js 16, React, TypeScript, Vitest.

---

### Task 1: Shared RND Clinical Access and Attribution

**Files:**
- Modify: `backend/app/Policies/AuditPolicy.php`
- Create: `backend/app/Services/Audit/ClinicalAttributionService.php`
- Modify: `backend/app/Http/Controllers/RND/PatientController.php`
- Modify: `backend/app/Http/Resources/PatientResource.php`
- Test: `backend/tests/Feature/Audit/SharedRndClinicalAccessTest.php`
- Modify: `backend/tests/Feature/Audit/ClinicalTrailTest.php`
- Modify: `frontend/services/patientService.ts`
- Modify: `frontend/app/(rnd)/ncp/patients/page.tsx`
- Modify: `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx`
- Create: `frontend/components/ncp/ClinicalAttribution.tsx`
- Test: `frontend/app/(rnd)/ncp/shared-rnd-attribution.test.tsx`

- [x] Write failing backend tests proving RND B can view and update RND A's assessment, intervention, meal plan, and monitoring; upload/delete a screening document; delete draft clinical records; open patient/NCP trails; and leave `rnd_user_id` attributed to RND A.
- [x] Write failing attribution contract tests proving patient rows and NCP cards return both creator and latest clinical actor with public ID, name, role/kind, and action time.
- [x] Run the focused tests and confirm owner-gated access or missing attribution causes failure.
- [x] Change `AuditPolicy::viewPatientTrail()` and `viewNcpTrail()` to authorize every active request user whose role is `RND`; retain the existing route middleware and all non-RND denials.
- [x] Add a batched attribution query over audit-channel clinical events, eager-load/select only safe actor columns, and preserve stored actor snapshots for deleted/system actors.
- [x] Add patient-row `latest_ncp_created_by`/`last_clinical_action` and NCP-card `created_by`/`last_clinical_action` DTO fields without exposing internal IDs or clinical values.
- [x] Render “Created by” and “Last clinical action by” on the patient table and each NCP card using existing typography, theme tokens, and responsive components.
- [x] Run backend clinical authorization/privacy tests plus frontend component tests, typecheck, and lint.
- [x] Perform spec-compliance and code-quality reviews; fix and re-review until approved.
- [x] Commit with `fix: share NCP access across RND users`.

### Task 2: DB-Backed Scheduled Retention Control

**Files:**
- Create: `backend/database/migrations/2026_07_14_000001_create_audit_settings_table.php`
- Create: `backend/app/Models/AuditSetting.php`
- Create: `backend/app/Services/Audit/AuditRetentionState.php`
- Create: `backend/app/Actions/Audit/SetAuditRetentionState.php`
- Create: `backend/app/Http/Requests/Admin/UpdateAuditRetentionRequest.php`
- Create: `backend/app/Http/Controllers/Admin/AuditRetentionController.php`
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/config/audit.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Services/Audit/AuditFilterMetadata.php`
- Modify: `backend/tests/Feature/Audit/AuditRetentionTest.php`
- Modify: `backend/tests/Feature/AdminAuditLogTest.php`
- Modify: `frontend/types/audit.ts`
- Modify: `frontend/services/auditLogService.ts`
- Create: `frontend/components/audit/AuditRetentionControl.tsx`
- Create: `frontend/components/audit/AuditRetentionControl.test.tsx`
- Modify: `frontend/app/admin/audit-logs/page.tsx`
- Create: `frontend/app/api/admin/audit-retention/route.ts`
- Test: `frontend/app/api/admin/audit-retention/route.test.ts`

- [x] Write failing tests for config fallback with no DB row, DB override, Admin-only mutation, explicit boolean validation, and audit old/new actor attribution.
- [x] Write failing UI tests for read-only category periods, current state, explicit enable confirmation text/action, and immediate disable without confirmation.
- [x] Generate a focused reversible migration containing one unique setting row shape but no seeded data; absence of a row preserves the environment fallback.
- [x] Implement the model, state reader, transactional row-locked update action, Form Request, controller, route-coverage entry, and response metadata.
- [x] Gate only the scheduled `audit:prune --force` execution through the state reader; retain `daily()`, `withoutOverlapping()`, and `onOneServer()`.
- [x] Emit one sanitized operations `settings_changed` event with actor, `retention_enabled` old/new booleans, and server timestamp; never store clinical data.
- [x] Implement the Admin toggle and exact enable warning; show security 365 days, clinical 2,190 days, operations 1,095 days, and legacy 90 days as read-only values.
- [x] Run migrations, rollback/re-forward, retention, authorization, route coverage, privacy, frontend tests, typecheck, lint, and build.
- [x] Perform spec-compliance and code-quality reviews; fix and re-review until approved.
- [x] Commit with `feat: add audit retention control`.

### Task 3: Remove IP-Blocking Scaffolding

**Files:**
- Modify: `backend/config/audit.php`
- Modify: `backend/app/Enums/AuditAction.php`
- Modify: `backend/app/Services/Audit/AuditFilterMetadata.php`
- Modify: `backend/tests/Feature/AdminAuditLogTest.php`
- Modify: `backend/tests/Feature/Audit/AuditContractTest.php`
- Modify: `backend/tests/Feature/Audit/SecurityAuditTest.php`
- Modify: `frontend/types/audit.ts`
- Modify: `frontend/services/auditLogService.ts`
- Modify: `frontend/components/audit/useAuditEventList.ts`
- Modify: relevant frontend audit tests
- Modify: `backend/.env.example`
- Modify: `docs/architecture/audit-logging.md`
- Modify: `docs/audit-logs-and-trails-implementation-report.md`

- [x] Add a failing repository contract test proving no IP-block model, migration, controller, middleware, route, capability, environment flag, or UI command remains.
- [x] Remove only `IpBlocked`, `IpUnblocked`, and `AUDIT_SECURITY_BLOCKS_ENABLED` scaffolding while preserving ordinary rate-limit telemetry and the unrelated `AccountBlocked`/`AccountUnblocked` administration events.
- [x] Document temporary IP blocking only as considered future work requiring a separate approved design.
- [x] Run security audit, API contract, route coverage, frontend tests, typecheck, lint, and stale scans.
- [x] Perform spec-compliance and code-quality reviews; fix and re-review until approved.
- [x] Commit with `refactor: remove IP-blocking scaffolding`.

### Task 4: Final Integration, Report, and Push

**Files:**
- Modify: `docs/audit-logs-and-trails-implementation-report.md`
- Modify: `docs/superpowers/plan/2026-07-11-audit-logs-and-trails-revision.md`
- Modify: this plan's completed checkboxes
- Preserve: untracked `.superpowers/`

- [ ] Isolate and commit the already verified Pint-only sweep as `style: format backend`.
- [ ] Commit remaining Task 14 compatibility/docs work as `docs: document audit operations`.
- [ ] Update the report with the approved owner decisions, final workflow, behavioral changes, verification evidence, and remaining future work.
- [ ] Run full backend tests, Pint, routes, privacy/authorization/performance/migration checks, frontend tests/typecheck/lint/build, proxy compatibility, stale scans, and `git diff --check`.
- [ ] Commit report/plan completion with `docs: finalize audit implementation report`.
- [ ] Push the verified integration wave to `main`.
- [ ] Verify `origin/main` resolves to the final local commit and report unrelated/untracked files separately.
