# First-Name/Last-Name Migration Implementation Plan

**Status:** Tasks 1–2 complete; Task 3 model/display contract is next.

**Authoritative design:** `docs/superpowers/specs/2026-07-15-first-and-last-name-migration-design.md`

**Dependency rule:** This plan must be completed through Wave N3, including its push and remote verification, before any audit-redesign implementation begins.

## Objective and compatibility contract

Accounts and patients will gain nullable `first_name` and `last_name` columns while the legacy `name` columns remain in place for this release. Existing rows are backfilled exactly as `first_name = name` and `last_name = null`; no Filipino, compound, or other legacy name is split heuristically. One model-level `display_name` contract will join first/last only when both are nonblank and otherwise fall back to the exact legacy name. API resources will continue to emit deprecated `name` while adding `first_name`, `last_name`, and `display_name`.

New records and deliberate name changes require both split fields. Deprecated `name` remains an accepted compatibility field but cannot satisfy or bypass that pair requirement; it may pass through an unrelated update to an untouched legacy record. An unrelated update to a legacy row with `last_name = null` remains valid. Split input wins when both split and deprecated input are present. Explicit writers synchronize the retained legacy column; no hidden mutator will do so.

Historical actor snapshots and prepared-by snapshots already stored in audit/report records are immutable and will not be rewritten. Future snapshots use `display_name` while retaining their existing payload key where compatibility requires it.

## Global task protocol

Every task below uses this exact sequence and records the evidence in its checkbox notes before commit:

1. Add or update the named test first.
2. Run the focused test and record the expected failure.
3. Implement only the task's bounded behavior.
4. Run the focused test, then all affected backend/frontend/mobile/report checks.
5. Perform a spec-compliance review against the authoritative design; fix findings and repeat until approved.
6. Perform a separate code-quality review; fix findings and repeat until approved.
7. Run fresh verification-before-completion commands and inspect their output.
8. Update this plan's checkboxes and evidence.
9. Stage only the listed task files, excluding `.codex/config.toml` and `.superpowers`, and commit with the listed Conventional Commit subject.

No implementation subagents run concurrently. A reviewer, if used, starts only after implementation and verification for that task have finished.

## Task 1 — Characterize names and freeze compatibility

- [x] Add durable passing backend characterization tests in `backend/tests/Feature/NameCompatibilityTest.php` covering exact compound/legacy name preservation, deprecated `name` output, unrelated legacy updates, soft-deleted-user legacy data, and immutable existing actor/prepared-by snapshots. Split normalization, precedence, pair validation, grouped search, and ordering are added as red tests at the start of Tasks 2–4, immediately before their production changes.
- [x] Verify existing `backend/tests/Feature/PatientFeatureTest.php`, `backend/tests/Feature/AuthFeatureTest.php`, and `backend/tests/Feature/ProfileTest.php`, and add `backend/tests/Feature/AdminUserNameTest.php` for durable current account/profile/patient compatibility contracts before changing them.
- [x] Add durable source/contract tests in `frontend/services/personNameContract.test.ts`, `frontend/app/admin/users/user-name-form-contract.test.ts`, and `frontend/app/(rnd)/ncp/patients/patient-name-form-contract.test.ts` that freeze deprecated response/input and proxy compatibility. Split-form/display behavior is added red-first in Task 5.
- [x] Add `mobile/lib/personName.test.cjs` as a dependency-free characterization of the deprecated mobile profile DTO/output contract. Split display/payload behavior is added red-first in Task 6.
- [x] Add actor and prepared-by report snapshot characterizations to `backend/tests/Feature/NameCompatibilityTest.php`, while retaining the existing dedicated report suites for broader report tasks.
- [x] Run the new durable tests green and run existing name-adjacent suites to establish the baseline. Record the red/green evidence for each new behavior under the later task that owns its implementation; never commit an intentionally failing suite.
- [x] Spec-compliance and code-quality review gates complete; commit `test: characterize split name compatibility`.

**Task 1 evidence (2026-07-15):** Laravel focused 7 tests/18 assertions passed; broader name-adjacent MySQL suite 66 tests/217 assertions passed; Vitest 3 files/4 tests passed; mobile Node characterization 1 test passed with `--test-isolation=none`; targeted Pint passed. Initial frontend failure was a test regex that assumed direct object arguments while the page uses typed payload variables. Initial mobile `EPERM` was Node's subprocess isolation under the sandbox; the same test passed directly and with isolation disabled. No production file changed. Self spec and quality reviews found no remaining Task 1 issue.

**Focused verification:**

```powershell
cd backend
php artisan test --compact tests/Feature/NameCompatibilityTest.php tests/Feature/AdminUserNameTest.php tests/Feature/PatientFeatureTest.php tests/Feature/AuthFeatureTest.php tests/Feature/ProfileTest.php
cd ..\frontend
npm test -- services/personNameContract.test.ts app/admin/users/user-name-form-contract.test.ts "app/(rnd)/ncp/patients/patient-name-form-contract.test.ts"
cd ..\mobile
node --test --test-isolation=none lib/personName.test.cjs
```

**Rollback boundary:** Tests only. Revert this task commit; no data or schema changes exist.

## Task 2 — Additive schema and conservative backfill

- [x] Add `backend/database/migrations/2026_07_15_000001_add_split_names_to_users_and_patients.php` with nullable `first_name` and `last_name` columns on both tables. Do not drop or rename `name`.
- [x] Add `backend/database/migrations/2026_07_15_000002_backfill_split_names_for_users_and_patients.php` as a separate DML migration using query-builder `chunkById`, including soft-deleted users, setting `first_name = name` only when both split fields are null, and leaving `last_name` null.
- [x] Add `backend/tests/Feature/NameMigrationTest.php` to prove exact legacy values, compound names unsplit, both forms of partial split data preserved, soft-deleted users included, a 500-row chunk boundary crossed, no audit rows emitted, rollback drops only new columns, and re-forward uses legacy `name` as authority.
- [x] Bring the configured MySQL baseline through the pending July 11–14 migrations after proving the retired-inventory migration had no live receiving/cost/report/web/mobile dependency.
- [x] Run forward, rollback of exactly the two new migrations, and re-forward on the configured MySQL database. Inspect `users` and `patients`, column indexes, migration rows, data, and audit count through Laravel Boost read-only queries after each direction.
- [x] Spec-compliance and code-quality review gates complete; commit `feat: add split person name columns`.

**Task 2 evidence (2026-07-15):** Red test failed because both planned migration files were absent. Isolated MySQL round-trip passed with 1 test/21 assertions, including 503 eligible user rows across the 500-row chunk boundary. Configured MySQL applied the pending July 11–14 baseline and both name migrations, rolled back exactly two name migrations, and re-forwarded twice after the bulk-query review fix. `SHOW COLUMNS` confirmed nullable 255-character split fields, `SHOW INDEX` confirmed no split-name index, legacy `name` remained, and activity count stayed zero. Before the pending forward-only stock retirement ran, runtime scans found no `quantity_in_stock` dependency and the retirement/receiving/PO/report batch passed 102 tests/434 assertions plus 3 frontend tests. Quality review replaced per-row updates with one guarded bulk update per chunk; targeted Pint passed.

**Focused verification:**

```powershell
cd backend
php artisan test --compact tests/Feature/NameMigrationTest.php
php artisan migrate --no-interaction
php artisan migrate:rollback --step=2 --no-interaction
php artisan migrate --no-interaction
php artisan migrate:status --no-ansi
```

**Rollback boundary:** Safe before split-name writers are deployed. Roll back the DML migration, then the DDL migration. The retained legacy `name` values remain authoritative. After Task 3 or later is deployed, roll back application commits first, then these migrations.

## Task 3 — Model display contract and explicit legacy synchronization

- [ ] Add `backend/app/Models/Concerns/HasDisplayName.php` with a Laravel 13 `Attribute` accessor that joins normalized split values once only when both are nonblank, and falls back to the exact legacy `name` whenever the split pair is incomplete.
- [ ] Apply the concern and fillable split fields in `backend/app/Models/User.php` and `backend/app/Models/Patient.php`. Keep the existing legacy field and do not append `display_name` globally; resources own public serialization.
- [ ] Add `backend/app/Support/PersonNameRules.php` for trimming, collapsing repeated internal whitespace, control-character rejection, and current safe maximum lengths without token/surname inference.
- [ ] Add `backend/app/Actions/Identity/SynchronizePersonName.php` as the explicit write-path normalizer. It accepts validated split and deprecated compatibility input, gives split input precedence, synchronizes `name` for new/deliberate split changes, and leaves unrelated legacy edits untouched.
- [ ] Extend `backend/tests/Unit/DisplayNameTest.php` and `backend/tests/Unit/SynchronizePersonNameTest.php` for blank/null parts, repeated whitespace, control characters, compound names, exact incomplete-pair fallback, precedence, and no hidden-mutator behavior.
- [ ] Run Task 1 compatibility tests plus model/action tests.
- [ ] Review gates complete; commit `feat: define person display name contract`.

**Rollback boundary:** Revert this application commit before rolling back schema. Existing legacy reads remain valid.

## Task 4 — Backend validation, writers, resources, search, and sorting

- [ ] Update `backend/app/Http/Requests/Admin/StoreUserRequest.php`, `backend/app/Http/Requests/Admin/UpdateUserRequest.php`, `backend/app/Http/Requests/Auth/UpdateProfileRequest.php`, `backend/app/Http/Requests/RND/StorePatientRequest.php`, and `backend/app/Http/Requests/RND/UpdatePatientRequest.php` with conditional pair validation: both fields on creates and deliberate changes; deprecated `name` is accepted as a compatibility field but is not a substitute for the required pair; unrelated legacy updates are allowed.
- [ ] Update `backend/app/Http/Controllers/Admin/UserController.php`, `backend/app/Http/Controllers/Auth/AuthController.php`, and `backend/app/Http/Controllers/RND/PatientController.php` to call the explicit synchronizer. Audit changed-field names include split fields but do not rewrite historical payloads.
- [ ] Fix patient search in `backend/app/Http/Controllers/RND/PatientController.php` by grouping all name/hospital-number alternatives inside one closure so status filters cannot be bypassed. Search legacy and split fields. Preserve established pagination.
- [ ] Sort users deterministically by normalized `last_name` with documented legacy fallback, then `first_name`, then `id`, with null-aware behavior verified on MySQL. Keep the current patient ordering but add a stable `id` tie-breaker.
- [ ] Update `backend/app/Http/Resources/UserResource.php` and `backend/app/Http/Resources/PatientResource.php` to emit `first_name`, `last_name`, `display_name`, and deprecated `name`. Update person projections/eager-load selects in `backend/app/Services/Audit/ClinicalAttributionService.php`, `backend/app/Http/Controllers/ReportController.php`, `backend/app/Http/Resources/AnnouncementResource.php`, `backend/app/Http/Resources/SopResource.php`, `backend/app/Http/Resources/ReportResource.php`, and `backend/app/Http/Resources/BudgetResource.php` so display access never causes missing-attribute behavior or N+1 queries.
- [ ] Update `backend/app/Services/Audit/AuditSanitizer.php` so future actor snapshots still use the compatibility key `name` but source its value from `display_name`.
- [ ] Extend the Task 1 backend tests for API payloads, compatibility inputs, validation errors, search grouping, ordering, actual actor display, query counts, and no change to non-person entity names.
- [ ] Run route coverage and affected authorization tests in addition to focused name tests.
- [ ] Review gates complete; commit `feat: migrate backend person name flows`.

**Wave N1 integration gate and push:**

- [ ] Run all Task 1–4 focused tests, `php artisan test --compact tests/Feature/Audit/AuditRouteCoverageTest.php`, relevant Pint, MySQL schema inspection, migration rollback/re-forward, and `git diff --check`.
- [ ] Confirm old clients still read deprecated `name`, deprecated input remains recognized without bypassing create/name-change pair validation, new clients submit/read split fields, and unrelated legacy updates work.
- [ ] Push `main` only after every N1 check passes. Verify `git ls-remote origin refs/heads/main` equals local `HEAD`.

**Rollback boundary:** Revert Tasks 4 and 3 first. Because legacy columns and deprecated resource fields remain, old clients keep working. Only then roll back Task 2 migrations if necessary.

## Task 5 — Web types, forms, tables, filters, proxies, and attribution

- [ ] Add `frontend/lib/personName.ts` as the single frontend adapter for `display_name` with deprecated `name` fallback, plus `frontend/lib/personName.test.ts`.
- [ ] Update person DTOs and payloads in `frontend/services/authService.ts`, `frontend/services/adminUserService.ts`, and `frontend/services/patientService.ts`. Keep nested historical/audit author `name` fields where their API contract intentionally remains a snapshot.
- [ ] Keep proxy compatibility and add forwarding tests for `frontend/app/api/auth/profile/route.ts`, `frontend/app/api/admin/users/route.ts`, `frontend/app/api/admin/users/[id]/route.ts`, `frontend/app/api/patients/route.ts`, and `frontend/app/api/patients/[id]/route.ts`; request bodies must pass split and deprecated fields unchanged.
- [ ] Replace the single full-name account form with required first/last inputs in `frontend/app/admin/users/page.tsx`; display `display_name`, preserve edit behavior for legacy null-last-name rows, and use existing Nutriscope inputs, modals, tables, spacing, colors, and responsive patterns.
- [ ] Replace the profile name field in `frontend/components/profile/ProfilePageShell.tsx` with first/last inputs and deliberate-change validation; keep unrelated profile edits possible for legacy rows.
- [ ] Replace patient creation/edit name inputs and displays in `frontend/app/(rnd)/ncp/patients/page.tsx`, `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx`, `frontend/app/(rnd)/ncp/_components/NcpPatientHeader.tsx`, `frontend/app/(rnd)/dashboard/page.tsx`, `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx`, `frontend/app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx`, and `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx` with the adapter, without changing food/recipe/supplier/menu names.
- [ ] Update user display in `frontend/components/layout/TopBar.tsx`; image alt text uses the same display contract.
- [ ] Add/extend Vitest behavior tests for create/edit payloads, legacy display fallback, validation, tables, filters, attribution, proxies, and responsive accessible labels. Use the existing design system; touch targets are at least 44 px, focus is visible, and both inputs have semantic labels.
- [ ] Before code changes, read the applicable local Next.js 16 documentation under `frontend/node_modules/next/dist/docs` for route handlers and client/server boundaries and record the file paths in the task evidence.
- [ ] Review gates complete; commit `feat: migrate web person name flows`.

**Focused verification:**

```powershell
cd frontend
npm test -- lib/personName.test.ts services/personNameContract.test.ts app/admin/users/user-name-form-contract.test.ts "app/(rnd)/ncp/patients/patient-name-form-contract.test.ts"
npx tsc --noEmit
npm run lint
npm run build
```

**Rollback boundary:** Revert the web commit independently. Backend deprecated `name` compatibility remains available.

## Task 6 — Mobile login/profile/types/consumers

- [ ] Read the exact Expo SDK 56 official documentation required by `mobile/AGENTS.md` for form inputs, secure authentication state, and build/export validation. Record URLs and applicable guidance; do not upgrade the installed Expo 54 dependency set without separate owner approval.
- [ ] Add `mobile/lib/personName.ts` and a shared person/profile DTO in `mobile/lib/auth.ts` so login/profile consumers understand `first_name`, `last_name`, `display_name`, and deprecated `name`.
- [ ] Update `mobile/app/profile.tsx` to show required first/last inputs for deliberate changes, submit split fields, preserve unrelated legacy profile edits, and use the established mobile styling and accessible labels.
- [ ] Update `mobile/app/login.tsx` only where its authenticated-user type/consumer requires the new contract. Do not rename food, recipe, supplier, menu, or snapshot entity fields in `mobile/app/(tabs)/*` or `mobile/lib/reports.ts`.
- [ ] Expand `mobile/lib/personName.test.cjs` and add `mobile/lib/profilePayload.test.cjs` for fallback, split precedence, validation, and payloads.
- [ ] Run Node tests, TypeScript, and the affected Expo export/build check without adding packages.
- [ ] Review gates complete; commit `feat: migrate mobile profile names`.

**Focused verification:**

```powershell
cd mobile
node --test --test-isolation=none lib/*.test.cjs
npx tsc --noEmit
npx expo export --platform android --output-dir .expo-name-verification
```

The generated verification directory is removed after inspection and is never committed.

**Rollback boundary:** Revert the mobile commit independently; backend deprecated compatibility remains.

## Task 7 — Reports, prepared-by displays, and historical snapshots

- [ ] Update future patient/prepared-by rendering in `backend/resources/views/reports/patient-menu-plan.blade.php`, `backend/app/Services/Reports/Generators/PatientMenuPlanGenerator.php`, `backend/app/Services/Reports/Generators/NcpSummaryGenerator.php`, `backend/app/Services/FSS/AccomplishmentReportArchiveService.php`, and `backend/app/Http/Controllers/ReportController.php` to source current person labels from `display_name`.
- [ ] Keep already archived report fields, audit actor snapshots, prepared-by snapshots, and accomplishment staff snapshots unchanged. Future snapshots may retain the compatibility JSON/key name but must capture `display_name` at creation.
- [ ] Update report-oriented select lists to include split and legacy columns without adding N+1 queries.
- [ ] Extend `backend/tests/Feature/PatientMenuPlanGeneratorTest.php`, `backend/tests/Feature/NcpSummaryReportTest.php`, `backend/tests/Feature/AccomplishmentReportTest.php`, `backend/tests/Feature/ReportControllerTest.php`, and `backend/tests/Feature/Audit/ReportAuditTest.php` for current display, immutable old snapshots, future snapshots, PDF/render output, and query counts.
- [ ] Review gates complete; commit `feat: use display names in reports`.

**Rollback boundary:** Revert report code. Stored snapshots remain untouched in either direction.

## Task 8 — Seeders and factories

- [ ] Update `backend/database/factories/UserFactory.php` and `backend/database/factories/PatientFactory.php` with realistic separate Filipino first/last names while continuing to synchronize the deprecated `name` column.
- [ ] Update `backend/database/seeders/AdminUserSeeder.php` and every explicit account/patient writer found by the stale scan to provide split fields.
- [ ] Update `backend/database/seeders/PatientSeeder.php` to identify/update deterministic patients by hospital number or another established stable key, never by display name.
- [ ] Extend `backend/tests/Unit/SeederIntegrityTest.php` and add `backend/tests/Feature/PersonNameSeederTest.php` for idempotence, stable cleanup, split fields, legacy synchronization, and no anonymous audit noise.
- [ ] Run the relevant seeders twice against a disposable/test transaction and compare counts/keys.
- [ ] Review gates complete; commit `test: update seeded person names`.

**Rollback boundary:** Revert seeder/factory code. Do not attempt to reverse already generated demo data by matching names.

## Task 9 — Stale-consumer and non-person-name scan

- [ ] Scan backend, frontend, and mobile for direct `User::name`, `Patient::name`, `user.name`, and `patient.name` reads/writes; classify every hit as migrated current-person display, retained deprecated compatibility, immutable historical snapshot, or test fixture.
- [ ] Scan API Resources, query projections, report templates, Next.js proxies, mobile DTOs, seeders, factories, exports, sorting, filters, and audit attribution for missing split/display handling.
- [ ] Prove unrelated entity names remain untouched: foods, RND/FSS recipes, ingredients, suppliers, menus, cycles, shopping lists, and report titles.
- [ ] Add `backend/tests/Unit/PersonNameStaleConsumerTest.php`, `frontend/services/personNameStaleConsumer.test.ts`, and `mobile/lib/personNameStaleConsumer.test.cjs` with explicit allowlists for retained compatibility locations so future stale reads fail loudly.
- [ ] Review gates complete; commit `test: guard split name consumers`.

**Rollback boundary:** Tests and bounded missed-consumer fixes only; revert this commit independently if needed.

## Task 10 — Full name-migration integration verification

- [ ] Run fresh Laravel name, auth, patient, audit, report, seeder, authorization, and route-coverage suites, then the full Laravel suite.
- [ ] Run relevant Pint and inspect its diff; run all frontend tests, TypeScript, lint, and production build.
- [ ] Run mobile Node tests, TypeScript, and affected Expo export/build check.
- [ ] On configured MySQL: verify baseline plus new migrations forward, rollback the two name migrations, re-forward, inspect legacy backfill (including soft-deleted users), and run MySQL query plans for patient search and user ordering. Add an index only if measured plans prove it is useful and its migration/rollback is tested.
- [ ] Render report fixtures/PDFs and assert current displays plus historical snapshot immutability.
- [ ] Run seeder idempotence, stale-consumer scans, query-count/N+1 tests, and `git diff --check`.
- [ ] Update `docs/architecture/person-name-compatibility.md` with the display contract, validation matrix, API compatibility, migration/deployment/rollback order, consumer map, and verification evidence.
- [ ] Review gates complete; commit `docs: document split name compatibility`.

**Wave N2 integration gate and push:** Tasks 5–7, with all backend/web/mobile/report focused verification passing. Push `main`; verify remote equality.

**Wave N3 final name gate and push:** Tasks 8–10, full suites passing, documentation current, MySQL rollback/re-forward proven, no unresolved stale consumers. Push `main`; verify remote equality. Record exact local and remote commit IDs here before opening the audit plan.

## Per-task execution controls

| Task | Touched workflow and failure mode | Exact focused verification | Rollback |
|---|---|---|---|
| 1 | Characterization across backend/web/mobile/reports. Failure: a new assertion passes before production behavior exists or an old compatibility baseline is not understood. | `cd backend; php artisan test --compact tests/Feature/NameCompatibilityTest.php tests/Feature/AdminUserNameTest.php tests/Feature/PatientFeatureTest.php tests/Feature/AuthFeatureTest.php tests/Feature/ProfileTest.php`; `cd ../frontend; npm test -- services/personNameContract.test.ts app/admin/users/user-name-form-contract.test.ts "app/(rnd)/ncp/patients/patient-name-form-contract.test.ts"`; `cd ../mobile; node --test --test-isolation=none lib/personName.test.cjs` | Revert Task 1 tests only. |
| 2 | MySQL schema/backfill. Failure: legacy bytes change, soft-deleted users are skipped, partial rows overwrite, audit noise appears, or rollback/re-forward diverges. | `cd backend; php artisan test --compact tests/Feature/NameMigrationTest.php; php artisan migrate --no-interaction; php artisan migrate:rollback --step=2 --no-interaction; php artisan migrate --no-interaction` | Roll back DML then DDL before app deployment. |
| 3 | Model display/synchronization. Failure: incomplete split data renders instead of exact legacy fallback, validation normalization changes compound names, or unrelated saves mutate names. | `cd backend; php artisan test --compact tests/Unit/DisplayNameTest.php tests/Unit/SynchronizePersonNameTest.php tests/Feature/NameCompatibilityTest.php` | Revert Task 3 before schema rollback. |
| 4 | Laravel requests/writers/resources/search/order/attribution. Failure: deprecated compatibility bypasses pair rules, status filters leak, ordering is unstable, projections trigger missing attributes/N+1, or snapshots rewrite. | `cd backend; php artisan test --compact tests/Feature/NameCompatibilityTest.php tests/Feature/AdminUserNameTest.php tests/Feature/PatientFeatureTest.php tests/Feature/AuthFeatureTest.php tests/Feature/ProfileTest.php tests/Feature/Audit/AuditRouteCoverageTest.php` | Revert Task 4, then Task 3; retain additive columns. |
| 5 | Next.js proxies, web forms, types, tables, headers. Failure: payload shape changes at proxy, legacy rows become uneditable, current displays use stale `name`, accessibility/design regress, or production build fails. | `cd frontend; npm test -- lib/personName.test.ts services/personNameContract.test.ts app/admin/users/user-name-form-contract.test.ts "app/(rnd)/ncp/patients/patient-name-form-contract.test.ts"; npx tsc --noEmit; npm run lint; npm run build` | Revert web commit; backend compatibility remains. |
| 6 | Expo login/profile/types. Failure: profile cannot preserve an unrelated legacy edit, split payload is wrong, food/entity name types change, or Expo validation fails. | `cd mobile; node --test --test-isolation=none lib/*.test.cjs; npx tsc --noEmit; npx expo export --platform android --output-dir .expo-name-verification` | Revert mobile commit and remove generated verification output. |
| 7 | Reports/prepared-by/current-versus-stored snapshots. Failure: old archives change, current PDFs show legacy stale text, or report queries gain N+1 behavior. | `cd backend; php artisan test --compact tests/Feature/PatientMenuPlanGeneratorTest.php tests/Feature/NcpSummaryReportTest.php tests/Feature/AccomplishmentReportTest.php tests/Feature/ReportControllerTest.php tests/Feature/Audit/ReportAuditTest.php` | Revert report code; never rewrite stored snapshots. |
| 8 | Factories/seeders. Failure: names are unsynchronized, patient cleanup uses mutable display, reruns duplicate data, or base setup emits audit noise. | `cd backend; php artisan test --compact tests/Unit/SeederIntegrityTest.php tests/Feature/PersonNameSeederTest.php` | Revert code only; never delete seeded data by name. |
| 9 | Stale-consumer boundary. Failure: an unclassified direct person-name access remains or a non-person entity is renamed. | `cd backend; php artisan test --compact tests/Unit/PersonNameStaleConsumerTest.php; cd ../frontend; npm test -- services/personNameStaleConsumer.test.ts; cd ../mobile; node --test --test-isolation=none lib/personNameStaleConsumer.test.cjs` | Revert bounded missed-consumer fixes/tests. |
| 10 | Whole integration. Failure: any required suite/check is skipped or fails, MySQL plans regress, migration round-trip changes data, remote push diverges, or documentation evidence is stale. | `cd backend; php artisan test --compact; vendor/bin/pint --dirty --format agent; cd ../frontend; npm test; npx tsc --noEmit; npm run lint; npm run build; cd ../mobile; node --test --test-isolation=none lib/*.test.cjs; npx tsc --noEmit` | Stop before push; revert the failing wave app-first, schema last. |

## Blast-radius matrix

| Area | Required handling | Principal files/tests |
|---|---|---|
| Database | Nullable additive columns, separate conservative backfill, legacy retention, forward/rollback/re-forward on MySQL | two new migrations; `NameMigrationTest.php` |
| Laravel models | One `display_name` contract; explicit synchronization; no global serialization | `User.php`, `Patient.php`, `HasDisplayName.php`, `SynchronizePersonName.php` |
| Validation/writers | Paired split fields only on create/deliberate change; unrelated legacy edits valid | five Form Requests; three controllers |
| API/resources | Split/display fields plus deprecated `name`; projections include needed columns | `UserResource.php`, `PatientResource.php`, nested person resources/services |
| Search/sort | Grouped search predicates; status isolation; deterministic MySQL ordering | `PatientController.php`, `UserController.php`, query-plan tests |
| Next.js proxy | Forward old and new fields unchanged | auth/admin/patient route handlers and tests |
| Web UI | Split forms; display adapter; current person labels migrated; existing design preserved | admin users, profiles, patients, NCP/dashboard headers |
| Mobile | Shared DTO/display adapter; split profile flow; no unrelated entity migration | `mobile/lib/auth.ts`, `mobile/lib/personName.ts`, profile/login |
| Reports | Current/future displays use contract; stored historical snapshots unchanged | generators, archive service, Blade, report tests |
| Audit attribution | Future actor snapshot key remains compatible but value comes from `display_name` | `AuditSanitizer.php`, audit tests |
| Seed/factory | Realistic split names; stable patient cleanup key; idempotence | user/patient factories and seeders |
| Authorization/privacy | Names do not alter policies; no extra sensitive exposure | existing authorization matrices, API assertions |
| Performance | No N+1; projections complete; index only from MySQL evidence | query-count and EXPLAIN checks |

## Completion gate before audit redesign

The audit implementation plan remains blocked until all of these are checked:

- [ ] Wave N1, N2, and N3 commits exist locally and are pushed to `main`.
- [ ] Remote `main` equals final local `HEAD`.
- [ ] Deprecated input/output compatibility is proven across Laravel and Next.js proxies.
- [ ] Web and mobile migrated consumers pass their checks.
- [ ] Historical actor/prepared-by snapshots are proven unchanged.
- [ ] MySQL forward/rollback/re-forward and legacy backfill pass.
- [ ] Full backend/frontend suites and affected mobile/report checks pass.
- [ ] Remaining unrelated `.codex/config.toml` and `.superpowers` state is reported separately and untouched.
