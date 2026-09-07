# NCP Visits, Patient History, and Meal Plan Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:test-driven-development and execute inline in this session. Subagents and worktrees are intentionally excluded by the user's guardrails. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add explicit scheduled/walk-in visit tracking across NCP steps, expose safe ADIME cycle lifecycle/history, and complete the requested patient, meal-plan, report, seed, audit, and documentation cleanup.

**Architecture:** Appointments are patient-owned records with an optional NCP-cycle link, explicit source and lifecycle status, and one active visit per RND user. A shared backend workflow service owns transitions and sanitized audit events; a shared frontend visit bar/banner owns start/resume/finish UX across all ADIME pages. Existing clinical completeness, report preparation/PDF preview, pagination, authorization, and audit infrastructure remain authoritative.

**Tech Stack:** Laravel 13/PHP 8.4/MySQL, Sanctum, PHPUnit 12, Next.js 16/React 19/TypeScript/Vitest, existing NutriScope UI components.

---

## Locked behavior

- Appointment fields: patient, optional NCP cycle, RND owner, source (`scheduled`, `walk_in`), status (`scheduled`, `in_progress`, `completed`, `ended_early`, `no_show`, `cancelled`, `rescheduled`), written purpose, scheduled/start/finish timestamps, conditional reason, completion snapshots, and reschedule link.
- Time never starts or completes a visit. An RND explicitly starts and finishes it.
- One active visit per RND user. Navigation/browser exit preserves it server-side; the RND layout offers Resume.
- Empty mistaken starts can be discarded. Scheduled records return to Scheduled; empty walk-ins are removed. Saved clinical work prevents discard.
- Appointments are patient-level when scheduled and bind to the single current cycle when started. A visit never mutates two cycles.
- Appointment completion does not complete an NCP cycle.
- Cycle actions live only under Patient → ADIME Records → Current Cycle → Actions: Complete and Protect, Discontinue, Delete.
- Complete requires clinically complete Assessment, Diagnosis, and Intervention. Discontinue requires a reason. Completed/discontinued/discharged records are Past Records.
- Dashboard is a due-work queue. It shows scheduled/overdue appointments with Open NCP, Reschedule, No-show, and Cancel; visit completion stays inside NCP.
- Monitoring Care Decision remains clinical. Its duplicate date-only follow-up field becomes the shared date/time/purpose scheduler.
- Admin audit receives only canonical appointment/cycle transitions, sanitized field names/references, and no clinical/free-text values.

### Task 1: Appointment persistence and transition contract

**Files:**
- Create: `backend/database/migrations/2026_09_06_000001_create_ncp_appointments_table.php`
- Create: `backend/app/Models/NcpAppointment.php`
- Create: `backend/database/factories/NcpAppointmentFactory.php`
- Create: `backend/app/Http/Resources/NcpAppointmentResource.php`
- Create: `backend/tests/Feature/NcpAppointmentWorkflowTest.php`
- Modify: `backend/app/Models/Patient.php`
- Modify: `backend/app/Models/NcpRecord.php`

- [ ] Write PHPUnit tests proving scheduled creation, walk-in start, one-active-visit enforcement, cycle binding, transition validation, no-show/cancel reasons, reschedule preservation, empty discard, and patient/cycle scoping.
- [ ] Run `php artisan test --compact tests/Feature/NcpAppointmentWorkflowTest.php`; verify failure because the model/routes do not exist.
- [ ] Generate the model/factory/migration using Artisan `--no-interaction`; implement fillable fields, casts, typed relationships, defaults, foreign keys, and indexes for patient history, dashboard status/date queries, active-owner lookup, and reschedule linkage.
- [ ] Return public UUIDs and ISO-safe date strings from `NcpAppointmentResource`; never expose internal foreign keys.
- [ ] Re-run the focused test until green.

Target schema:

```php
Schema::create('ncp_appointments', function (Blueprint $table): void {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
    $table->foreignId('ncp_record_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('rnd_user_id')->constrained('users');
    $table->foreignId('rescheduled_from_id')->nullable()->constrained('ncp_appointments')->nullOnDelete();
    $table->string('source', 16);
    $table->string('status', 24)->default('scheduled');
    $table->string('purpose', 255);
    $table->dateTime('scheduled_at')->nullable();
    $table->dateTime('started_at')->nullable();
    $table->dateTime('finished_at')->nullable();
    $table->string('reason_code', 48)->nullable();
    $table->json('worked_on')->nullable();
    $table->json('newly_completed')->nullable();
    $table->timestamps();
    $table->index(['patient_id', 'scheduled_at']);
    $table->index(['status', 'scheduled_at']);
    $table->index(['rnd_user_id', 'status']);
});
```

### Task 2: Appointment API, authorization, and canonical audit

**Files:**
- Create: `backend/app/Http/Requests/RND/StoreNcpAppointmentRequest.php`
- Create: `backend/app/Http/Requests/RND/TransitionNcpAppointmentRequest.php`
- Create: `backend/app/Services/NcpAppointmentWorkflow.php`
- Create: `backend/app/Http/Controllers/RND/NcpAppointmentController.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Enums/AuditAction.php`
- Modify: `backend/config/audit.php`
- Modify: `backend/tests/Feature/NcpAppointmentWorkflowTest.php`
- Modify: `backend/tests/Feature/Audit/AuditCanonicalEventTest.php`
- Modify: `backend/tests/Feature/Audit/AuditPrivacyTest.php`
- Modify: `backend/tests/Feature/Audit/AuditRouteCoverageTest.php`

- [ ] Add failing tests for RND-only access, UUID route binding, nested patient ownership, five-item patient history pagination, dashboard pagination, active-visit lookup, allowed transitions, rejected transitions, transactional one-active enforcement, exactly one canonical event, and reason/purpose redaction.
- [ ] Run the four focused test files; confirm expected missing-route/action failures.
- [ ] Implement Form Requests using validated enum values and conditional reasons. Keep controllers thin; put transaction/`lockForUpdate()` transition rules in `NcpAppointmentWorkflow`.
- [ ] Add routes:

```php
Route::get('ncp-appointments/active', [NcpAppointmentController::class, 'active']);
Route::get('ncp-appointments/dashboard', [NcpAppointmentController::class, 'dashboard']);
Route::get('patients/{patient}/appointments', [NcpAppointmentController::class, 'index']);
Route::post('patients/{patient}/appointments', [NcpAppointmentController::class, 'store']);
Route::patch('ncp-appointments/{ncpAppointment}', [NcpAppointmentController::class, 'transition']);
```

- [ ] Record only scheduled, started, completed, ended-early, cancelled, no-show, rescheduled, and cycle-discontinued actions. Details contain safe status/source/field names and public references; exclude purpose, reason text, clinical values, and appointment dates.
- [ ] Classify every unsafe route in `config/audit.php`; suppress duplicate model audit events inside the canonical transaction.
- [ ] Re-run focused workflow/audit tests until green.

### Task 3: NCP cycle lifecycle and current/past history

**Files:**
- Create: `backend/database/migrations/2026_09_06_000002_add_discontinued_status_to_ncp_records.php`
- Create: `backend/app/Http/Requests/RND/TransitionNcpRecordRequest.php`
- Modify: `backend/app/Http/Controllers/RND/NcpRecordController.php`
- Modify: `backend/app/Http/Controllers/RND/PatientController.php`
- Modify: `backend/app/Services/ClinicalCompletenessService.php` only if a reusable aggregate helper is absent
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/NcpCycleLifecycleTest.php`
- Modify: `backend/tests/Feature/PatientFeatureTest.php`

- [ ] Write failing tests proving: complete rejects incomplete ADI; complete protects and moves the record to past; discontinue requires an allow-listed reason; start-new rejects an open cycle but accepts terminal prior cycles; delete follows clinical completeness rather than row existence; current and past endpoints never overlap; past pagination is two items and returns page metadata even when empty.
- [ ] Run the two focused tests and verify expected failures.
- [ ] Add `discontinued` to the NCP status database constraint with a reversible migration.
- [ ] Add one transition endpoint (`complete` or `discontinue`) using a Form Request. Use `ClinicalCompletenessService`, transactions, existing patient/NCP authorization, and canonical sanitized audit.
- [ ] Change patient NCP history response to explicit `current` plus paginated `past` rather than one mixed ten-item page. Keep raw cycle IDs out of response/display and normalize nested meal-plan dates/resources.
- [ ] Re-run focused tests until green.

### Task 4: Clinical visit attribution and scheduling source of truth

**Files:**
- Create: `backend/app/Services/ActiveNcpVisitRecorder.php`
- Modify: `backend/app/Http/Controllers/RND/AssessmentController.php`
- Modify: `backend/app/Http/Controllers/RND/DiagnosisController.php`
- Modify: `backend/app/Http/Controllers/RND/InterventionController.php`
- Modify: `backend/app/Http/Controllers/RND/MonitoringController.php`
- Modify: `backend/app/Http/Requests/RND/MonitoringRequest.php`
- Modify: `backend/app/Models/Monitoring.php`
- Modify: `backend/app/Http/Resources/MonitoringResource.php`
- Modify: `backend/app/Http/Resources/InterventionResource.php`
- Create: `backend/tests/Feature/NcpVisitAttributionTest.php`
- Modify: `backend/tests/Feature/NcpMonitoringTest.php`

- [ ] Add failing tests showing successful persisted Assessment/Diagnosis/Intervention/Monitoring mutations append each section once to the active visit; failed/unauthorized saves do not; finishing compares completeness-at-start to completeness-at-finish for `newly_completed`.
- [ ] Add failing tests showing monitoring Care Decision remains, while appointment scheduling uses the appointment API and no new `next_monitoring_date` is required.
- [ ] Implement the smallest shared recorder called after successful clinical mutations. Do not log page views, typing, failed saves, or clinical values.
- [ ] Stop writing new intervention `session_type`/`next_followup_date` and monitoring `next_monitoring_date`; retain legacy columns for report/history compatibility during this release.
- [ ] Re-run focused tests until green.

### Task 5: Dashboard appointments and reminder migration

**Files:**
- Create: `backend/database/migrations/2026_09_06_000003_backfill_ncp_appointments_from_followup_dates.php`
- Modify: `backend/app/Http/Controllers/RND/PatientController.php`
- Modify: `backend/app/Http/Resources/PatientResource.php`
- Modify: `backend/app/Console/Commands/SendFollowUpReminders.php`
- Modify: `backend/tests/Feature/PatientFollowUpPaginationTest.php`
- Modify: `backend/tests/Feature/NotificationTriggersTest.php`

- [ ] Write failing tests proving dashboard rows come only from scheduled appointments, overdue rows persist until resolved, terminal rows disappear, and reminders use scheduled appointments without duplicate notifications.
- [ ] Run focused tests; verify they fail against intervention dates.
- [ ] Add a separate data migration that creates one Scheduled `Nutrition follow-up` appointment from each legacy unresolved follow-up date, without modifying the original clinical record.
- [ ] Switch dashboard patient filtering/resource data and reminder queries to appointments. Preserve existing pagination and notification deduplication.
- [ ] Re-run focused tests until green.

### Task 6: Frontend appointment service, shared visit controls, and persistence banner

**Files:**
- Create: `frontend/services/ncpAppointmentService.ts`
- Create: `frontend/services/ncpAppointmentService.test.ts`
- Create: `frontend/components/ncp/NcpVisitBar.tsx`
- Create: `frontend/components/ncp/NcpVisitBar.test.tsx`
- Create: `frontend/components/ncp/ActiveVisitBanner.tsx`
- Create: `frontend/components/ncp/ActiveVisitBanner.test.tsx`
- Modify: `frontend/app/(rnd)/layout.tsx`
- Modify: `frontend/app/(rnd)/ncp/_components/NcpPatientHeader.tsx`
- Modify: four NCP step pages under `frontend/app/(rnd)/ncp/[patientId]`
- Delete: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/EncounterContextTab.tsx`

- [ ] Write failing service/component tests for date/source/status/purpose rendering, scheduled and walk-in confirmation, Resume navigation, Finish, End Early, empty discard, one-active conflict, and persisted banner behavior.
- [ ] Run the focused Vitest files and confirm missing-module/behavior failures.
- [ ] Implement API types/functions and a single reusable visit bar. Mount it through the existing shared NCP patient header so it appears on Assessment, Diagnosis, Intervention, and Monitoring without four duplicated forms.
- [ ] Mount the active-visit banner once in the RND layout. Opening another patient offers Resume; it never silently changes the active patient.
- [ ] Remove the Intervention Encounter Context tab and its state/payload. Keep `Finish Visit` primary; put Schedule Next, End Early, and eligible Discard in the overflow menu.
- [ ] Re-run focused tests until green.

### Task 7: Patient page cleanup, ADIME history, appointments, and dashboard actions

**Files:**
- Create: `frontend/components/ncp/PatientAppointments.tsx`
- Create: `frontend/components/ncp/PatientAppointments.test.tsx`
- Modify: `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx`
- Modify: `frontend/app/(rnd)/ncp/patients/[patientId]/patient-profile-attachments.test.ts`
- Modify: `frontend/app/(rnd)/ncp/patients/page.tsx`
- Modify: `frontend/app/(rnd)/dashboard/page.tsx`
- Create or modify focused patient/dashboard contract tests beside those pages
- Modify: `frontend/services/patientService.ts`

- [ ] Write failing tests for all removals: patient/NCP activity panels absent in Overview and ADIME; NS UUID absent; cycle ID absent from current snapshot and patient cards; System score badge absent.
- [ ] Write failing tests for the reused `InfoHint` containing the three approved deletion/cycle rules.
- [ ] Write failing tests for always-visible Past Records with two-item pagination/empty state, Current Cycle separation, terminal status badges, single Current Cycle Actions menu, and no raw UUID.
- [ ] Write failing tests for Appointments tab: upcoming/past status, source, written purpose, current/past-cycle filter, five-item pagination, schedule/start/reschedule/no-show/cancel actions, and cycle-scoped link behavior.
- [ ] Write failing dashboard tests for scheduled/overdue rows and the compact Open NCP/Reschedule/No-show/Cancel menu.
- [ ] Implement only those views using existing Button, Dialog/Popover, InfoHint, Pagination, status badge, and error/loading patterns. Do not replace removed content.
- [ ] Re-run focused tests until green.

### Task 8: Meal-plan labels and report preview reuse

**Files:**
- Modify: `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx`
- Modify: `frontend/components/reports/ReportsBrowser.tsx`
- Create or modify focused meal-plan/report tests

- [ ] Write failing tests proving plans are labeled `Meal Plan 1`, `Meal Plan 2` within each cycle, contain no AI/System badge, and are clickable.
- [ ] Write failing tests proving click calls existing `prepareReport('patient_menu_plan', { meal_plan_id })`, then opens existing `ReportPreview` with existing view/download URLs.
- [ ] Write a failing Reports page test proving the requested instructional paragraph is absent.
- [ ] Implement the smallest reuse path; create no new report route, generator, preview, or PDF abstraction.
- [ ] Re-run focused tests until green.

### Task 9: Workflow documentation

**Files:**
- Modify: `docs/modules/rnd.md`
- Modify: `docs/ROLE-HOW-TO.md`
- Modify: `docs/FAQ.md` only where existing answers cover NCP/follow-up workflow
- Create: `C:/Users/jared/Documents/Storyboarding/RND NCP Video Storyboard.md`

- [ ] Update existing RND role/workflow documentation for Appointments, explicit visit start/finish, Past Records, cycle completion/discontinuation, and appointment-vs-monitoring semantics. Do not create duplicate workflow documents or read `deployment.md`.
- [ ] Create one NCP recording checklist and narration script in the existing external Storyboarding format. Cover patient selection, current/past ADIME, scheduled and walk-in visits, persistent Resume, multi-step work, appointment outcomes, monitoring, meal-plan PDF preview/download, and cycle completion/discontinuation. Use demo data and privacy-safe recording guidance.
- [ ] Do not modify `PatientSeeder` or any seeder test; seeded historical-cycle realism is reserved for the user's next session.

### Task 10: Integration verification, review, commit, and push

**Files:** all task-scoped changes above.

- [ ] Run `php artisan route:list --path=api/rnd --except-vendor` and verify every new route, verb, UUID binding, and middleware.
- [ ] Run all affected backend feature/unit tests, then `php artisan test --compact`.
- [ ] Run `vendor/bin/pint --dirty --format agent` and repeat affected backend tests.
- [ ] Run focused frontend Vitest files, then `npm test`, `npx tsc --noEmit`, and `npm run lint`.
- [ ] Run `npm run build` because shared layout and App Router client components changed.
- [ ] Inspect `git diff --check`, `git diff --stat`, and task-only diff. Confirm `.codex/config.toml`, `frontend/AGENTS.md`, and the unrelated seeded-profile-photo plan remain unstaged/unmodified by this work.
- [ ] Perform a fresh same-model self-review without a subagent: requirements matrix, route ownership, transition edge cases, audit privacy/duplication, pagination empty state, and report URL correctness. Fix findings with new failing tests first.
- [ ] Stage only task files, create a concise conventional commit, verify commit contents, push the current `main` branch, and report exact verification/push evidence.
