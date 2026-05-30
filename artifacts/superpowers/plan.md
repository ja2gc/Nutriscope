# M2B Regression Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:test-driven-development for backend behavior changes and superpowers:verification-before-completion before claiming completion.

**Goal:** Resolve M2B regressions in announcements, navigation persistence, patient workflow entry, patient profile actions, risk-score terminology, and documentation sync.

**Architecture:** Keep the existing Laravel announcement backend and Next.js route structure. Fix schema drift by applying existing migrations, tighten the current sidebar state logic, and route patient workflow entry through existing NCP assessment shells without forcing demographic creation. Documentation in `docs/` and execution notes in `artifacts/superpowers/` must match the final code.

**Tech Stack:** Laravel 13, Sanctum, PHPUnit 12, MySQL runtime schema, SQLite in-memory tests, Next.js 16, React 19, Tailwind CSS, Lucide React.

---

## File Map

- `backend/database/migrations/2024_01_01_000003_create_ncp_records_table.php`: create the canonical `risk_score` column for fresh installs.
- `backend/database/migrations/2026_05_31_000001_rename_ai_risk_score_to_risk_score_on_ncp_records_table.php`: keep compatibility migration for databases that still have `ai_risk_score`.
- `backend/tests/Feature/PatientFeatureTest.php`: protect `risk_score` resource output and NCP workflow behavior.
- `frontend/components/layout/Sidebar.tsx`: preserve expanded section visibility on child routes.
- `frontend/app/(rnd)/ncp/patients/page.tsx`: remove the patient creation modal and navigate directly into the assessment workflow.
- `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx`: replace start-cycle creation actions with workflow navigation actions for existing NCP records.
- `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx`: make placeholder assessment route usable as the OCR-first intake entry surface.
- `docs/database-schema.md`, `docs/milestones/milestones.md`, `docs/modules/rnd.md`, `docs/modules/admin.md`, `docs/overview.md`, `docs/Nutriscope Forms/implementation_plan.md`: align docs with implementation.
- `artifacts/superpowers/execution.md`: record what was changed and verified.

## Tasks

### Task 1: Backend Schema and Risk Score Safety

- [x] Add/adjust a Patient feature test proving `risk_score` is exposed from the latest NCP record and no `ai_risk_score` field is needed.
- [x] Run the targeted backend test and verify the current schema/resource state.
- [x] Update the base NCP records migration to create `risk_score` for clean installs while preserving the later rename migration for existing databases.
- [x] Run targeted backend tests for patients.

### Task 2: Announcements Runtime Schema

- [x] Confirm the runtime MySQL schema is missing `announcements` through Laravel Boost.
- [x] Run `php artisan migrate` in `backend` to apply the existing announcement and risk-score migrations.
- [x] Re-check Laravel Boost schema for `announcements` and `ncp_records.risk_score`.
- [x] Verify announcement feature tests pass.

### Task 3: Navigation Persistence

- [x] Refactor `Sidebar.tsx` so NCP and Food Service groups are considered open whenever the current route belongs to that group.
- [x] Keep manual toggles working when users are outside those route groups.
- [x] Ensure submenu active states use `startsWith` for child pages so links do not disappear when navigating deeper.

### Task 4: Patient Workflow Entry

- [x] Remove modal state, form state, patient creation imports, and modal JSX from `frontend/app/(rnd)/ncp/patients/page.tsx`.
- [x] Change `Create Patient & Start Assessment` to navigate to `/ncp/select-patient/assessment/select-ncp`.
- [x] Update placeholder assessment copy so it behaves like the OCR-first assessment intake entry point instead of sending the user back to the patient directory.

### Task 5: Existing Patient Profile Actions

- [x] Remove `createNcpRecord`, `handleStartCycle`, and `startingCycle` from the patient profile page.
- [x] If a latest record exists, show direct workflow management links for assessment, diagnosis, intervention, and monitoring.
- [x] If no record exists, show a neutral workflow status message instead of a create-cycle action.

### Task 6: Documentation Sync

- [x] Update `docs/database-schema.md` to confirm `announcements` exists and `ncp_records.risk_score` is system-calculated.
- [x] Update `docs/milestones/milestones.md` so implemented M2B work is marked complete, announcement backend is not left in M10 future work, risk-score rename is complete, and PatientResource/PatientFeatureTest fixes are complete.
- [x] Update module and overview docs to remove stale `ai_risk_score` and AI-risk wording where the system-calculated score is intended.
- [x] Update `docs/Nutriscope Forms/implementation_plan.md` where it still contains old `ai_risk_score` mapping.

### Task 7: Verification

- [x] Run `php artisan test` in `backend`.
- [x] Run `npm run build` in `frontend`.
- [x] Use Laravel Boost schema inspection to verify `announcements` exists and `ncp_records` has `risk_score`.
- [x] Record final verification output in `artifacts/superpowers/execution.md`.
