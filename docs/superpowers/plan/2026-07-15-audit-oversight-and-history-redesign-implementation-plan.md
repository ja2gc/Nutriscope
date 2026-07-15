# Audit Oversight and Historical-Record Redesign Implementation Plan

**Status:** Approved design translated into executable tasks. Blocked only by the required completion gate in the first/last-name migration plan.

**Authoritative design:** `docs/superpowers/specs/2026-07-15-audit-oversight-and-history-redesign.md`

**Hard dependency:** Do not begin Task 1 implementation until all Wave N1–N3 checkboxes in `docs/superpowers/plan/2026-07-15-first-and-last-name-migration-implementation-plan.md` are complete, pushed to `main`, and remote `main` equals local `HEAD`.

## Target contract

The normal Admin information architecture has exactly five tabs: All Activity, Security & Administration, Nutrition Care, Food Service Operations, and Reports. `module` is a new presentation/oversight taxonomy. It does not replace retention `category` or internal `domain`; fixed retention remains 365 days for security, 2,190 days for clinical, 1,095 days for operations, and 90 days for legacy/unknown records. Existing `category` and `domain` request parameters remain only through the compatibility waves and are removed after every internal consumer and Next.js proxy is migrated and tested. Their DTO/storage fields remain internal compatibility/context data in this release.

Patient identity and actor identity remain separate. Patient-linked Nutrition Care events may expose the encrypted patient display-name snapshot, actual actor/system actor, action, timestamp, clinical record type, stable pseudonymous NCP reference, and changed field names. They never expose clinical values or other prohibited patient fields. The patient snapshot lives only in the dedicated encrypted, non-indexed `patient_display_name_snapshot` activity column. It is never copied into `properties`, revision JSON, application logs, URLs, metrics, exports, filter options, sorting, or search.

Safe nonpatient events present meaningful typed values. Simple changes use a typed drawer. Complex operational records use an append-only event-time revision with a read-only historical page that continues to work after the live record is changed or deleted. No audit API or UI exposes raw JSON.

## Global task protocol

Every task follows this sequence before its commit checkbox can be checked:

1. Add/update the named failing tests first and capture the expected failure.
2. Implement only the bounded task.
3. Run focused verification, then the broader affected suites.
4. Run a spec-compliance review against the authoritative design; fix and repeat until approved.
5. Run a separate code-quality review; fix and repeat until approved.
6. Run fresh verification-before-completion commands and inspect output.
7. Update task, wave, and evidence checkboxes in this file.
8. Stage only listed files, never `.codex/config.toml` or `.superpowers`, and make the listed Conventional Commit.

Only one implementer task runs at a time. Reviewer work starts only after that task's implementation and verification are complete.

## Task 1 — Characterization tests and complete route/event/consumer inventory

- [ ] Extend `backend/tests/Feature/Audit/AuditContractTest.php`, `StructuredAuditApiTest.php`, `AuditRouteCoverageTest.php`, `AuditPrivacyTest.php`, `ClinicalTrailTest.php`, `PurchaseOrderTrailTest.php`, `SecurityAuditTest.php`, `ReportAuditTest.php`, `SharedRndClinicalAccessTest.php`, `backend/tests/Feature/OperationsAuditTest.php`, and `backend/tests/Feature/BudgetAuditTest.php` to characterize every current action, route, writer, context trail, duplicate, privacy behavior, legacy fallback, and authorization rule before production changes.
- [ ] Add `backend/tests/Unit/AuditInventoryContractTest.php` containing an explicit allowlist of audited HTTP mutations/read-sensitive routes, scheduled retention behavior, model observers, listeners, services, and direct `AuditLogger` writers. The test fails if a route/writer is unclassified.
- [ ] Add `frontend/components/audit/audit-consumer-inventory.test.ts` for Admin page, filters, drawer, table, trails, URL state, service DTO, and all Next.js audit/activity proxies.
- [ ] Record the current matrix in this plan's evidence: route, action, subject, context, current category/domain, target module, authoritative writer, duplicate risk, Admin detail mode, RND contextual visibility, retention category, reason requirement, privacy class, and revision requirement.
- [ ] Include current migrations, `AuditActivity`, `AuditsChanges`, `AuditLogger`, `AuditContextResolver`, `AuditQuery`, `AuditEventPresenter`, `AuditFilterMetadata`, `AuditPolicy`, `ActivityController`, Admin controllers, report services, FSS lifecycle/listener writers, RND Food Library writers, model audit traits, Next.js proxies, and demo/base seeders.
- [ ] Capture expected failures for the new five-tab/module/patient-snapshot/history/shared-RND assertions without changing production code.
- [ ] Review gates complete; commit `test: characterize audit oversight baseline`.

**Focused verification:**

```powershell
cd backend
php artisan test --compact tests/Feature/Audit tests/Feature/OperationsAuditTest.php tests/Feature/BudgetAuditTest.php tests/Unit/AuditInventoryContractTest.php
cd ..\frontend
npm test -- components/audit services/auditLogService.test.ts services/activityService.test.ts
```

**Rollback boundary:** Tests/inventory only; revert the task commit.

## Task 2 — Stale-feature and duplicate-event discovery

- [ ] Add `backend/tests/Feature/Audit/AuditDuplicateDiscoveryTest.php` that exercises each user intent and asserts its current event count/sequence, including model-observer plus explicit-writer collisions, PO conversion/ordering/receiving, budget deductions, report generation/view/download/delete, Food Library changes, patient/NCP changes, and authentication/security actions.
- [ ] Add `backend/tests/Unit/AuditStaleFeatureInventoryTest.php` and `frontend/components/audit/audit-stale-feature-inventory.test.ts` with explicit classifications for obsolete filters, labels, event cases, query parameters, compatibility paths, seed data, dead controllers/models/migrations, and UI copy.
- [ ] Search specifically for `IpBlocked`, `IpUnblocked`, `AUDIT_SECURITY_BLOCKS_ENABLED`, IP-blocking routes/controllers/models/migrations, `AccountBlocked`, `AccountUnblocked`, Domain UI/filter text, unknown-action-to-Updated fallback, raw JSON renderers, export controls, creator-ownership authorization, and stale inventory/quantity fields.
- [ ] Prove route, consumer, and database compatibility before marking any item removable. Keep account block/unblock and Admin account deactivation unchanged. Keep `quantity_in_stock`/related compatibility unless the full ReceivingService/costing/report/web/mobile/test dependency scan proves retirement already completed.
- [ ] Record the removal/retention decision and evidence for every discovered item. No cleanup occurs in this task.
- [ ] Review gates complete; commit `test: inventory stale audit behavior`.

**Rollback boundary:** Discovery tests/docs only; revert independently.

## Task 3 — Additive module, encrypted patient snapshot, revisions, indexes, and compatibility schema

- [ ] Add `backend/app/Enums/AuditModule.php` with only `security_administration`, `nutrition_care`, `food_service_operations`, and `reports`; All Activity remains an unfiltered UI state, not a stored module. Add the internal `nutrition_library` case to `backend/app/Enums/AuditDomain.php`.
- [ ] Add `backend/database/migrations/2026_07_15_100001_add_module_and_patient_snapshot_to_activity_log.php` with nullable `module`, encrypted-storage-capable nullable text `patient_display_name_snapshot`, and a query-aligned `module, created_at, id` index. Do not index the patient name.
- [ ] Add `backend/database/migrations/2026_07_15_100002_create_audit_revisions_table.php` with immutable `AuditRevision` rows: numeric key, public revision UUID, one-to-one audit-activity foreign key with retention-compatible cascade, module, internal domain, allow-listed subject type/public ID, action, serializer schema version, bounded typed `before` JSON, bounded typed `after` JSON, timestamp, and indexes for public/activity lookup.
- [ ] Add `backend/database/migrations/2026_07_15_100003_backfill_audit_modules_and_patient_snapshots.php` as a separate chunked DML migration. Backfill modules from category/domain/subject policy; resolve patient-linked events through `root_patient_id`/NCP/context relations; encrypt the current patient's `display_name`; leave null when unresolved; never write the name into `properties`.
- [ ] Add `backend/app/Models/AuditRevision.php` with guarded append-only behavior parallel to `AuditActivity`; extend `backend/app/Models/AuditActivity.php` with `AuditModule`, encrypted `patient_display_name_snapshot`, and revision relation. Extend retention deletion so foreign-key cascade is intentional and tested.
- [ ] Add `backend/tests/Feature/Audit/AuditRedesignMigrationTest.php` for deterministic module/domain backfill, explicit `legacy_unclassified` presentation for ambiguous/null values, deleted/unresolvable patient fallback, encrypted at-rest name, no index on patient name, no JSON copy, revision constraints/size bounds, retention cascade, and exact forward/rollback/re-forward.
- [ ] First bring the configured MySQL database through all pending baseline migrations. Run the three new migrations forward, roll back exactly three steps, and re-forward; inspect schema and ciphertext with Laravel Boost read-only tools.
- [ ] Review gates complete; commit `feat: add audit oversight schema`.

**Rollback boundary:** Before new writers deploy, roll back DML, revision DDL, then activity DDL. After Task 4+, revert application writers/readers first, then schema. Legacy category/domain/properties remain intact.

## Task 4 — Canonical event policy, privacy boundary, and duplicate removal

- [ ] Add the canonical `Imported` case to `backend/app/Enums/AuditAction.php`. Add `backend/app/Services/Audit/AuditEventPolicy.php` as the single registry mapping subject/action to module, retention category, internal domain, privacy class, canonical writer, detail mode, reason rule, and revision serializer.
- [ ] Add `backend/app/Services/Audit/AuditPatientSnapshot.php` to resolve current patient identity, encrypt only through the model cast, and return null when unresolvable. It must not expose search/filter/sort helpers.
- [ ] Add `backend/app/Services/Audit/AuditPseudonymousReference.php` for a stable non-identifying NCP reference derived from existing safe public identifiers; never expose hospital number or patient UUID as a patient lookup surface.
- [ ] Update `backend/app/Services/Audit/AuditLogger.php`, `backend/app/Models/Concerns/AuditsChanges.php`, `backend/app/Services/Audit/AuditContextResolver.php`, and `backend/app/Services/Audit/AuditSanitizer.php` to apply the policy, dedicated patient snapshot, actual actor, retention category, module, and prohibited-field sentinels synchronously in the same database transaction.
- [ ] Remove one side of every proven duplicate writer. Keep automatic model auditing only where the policy names it authoritative; keep explicit service/controller events for lifecycle/user-intent actions. Never collapse unknown legacy actions into `updated`.
- [ ] Add `backend/tests/Feature/Audit/AuditCanonicalEventTest.php` and expand privacy tests for one-intent/one-event, actual actor, module classification, field-name-only clinical changes, encrypted patient name, no clinical or patient-identifying values in storage/API/export/logs/revisions, and account-block compatibility.
- [ ] Review gates complete; commit `refactor: enforce canonical audit events`.

**Wave A1 integration gate and push:**

- [ ] Tasks 1–4 focused suites, route inventory, duplicate tests, privacy storage sentinels, migration rollback/re-forward, relevant Pint, MySQL schema/index inspection, and `git diff --check` pass.
- [ ] Legacy category/domain API parameters and Next.js pass-through still work in A1.
- [ ] Push `main`; verify remote `main` equals local `HEAD`.

**Rollback boundary:** Revert Task 4 writers/readers, then Task 3 schema. Compatibility fields make the old presenter/query usable.

## Task 5 — Typed event DTO/presenter and event-specific summaries

- [ ] Expand `backend/app/Data/AuditEventDto.php` with module, retained category/internal domain, record type, patient identity object containing only `display_name`, NCP reference, changed field labels, typed detail mode, safe typed details/changes, optional historical-view reference, and optional authorized current-record URL. Ambiguous legacy rows present `legacy_unclassified`; unknown action strings retain a sanitized original label.
- [ ] Add `backend/app/Data/AuditValueDto.php` and `backend/app/Data/AuditHistoryLinkDto.php` so scalar/date/currency/quantity/boolean/enum/reference values are explicit and JSON structures cannot leak to presentation.
- [ ] Split `backend/app/Services/Audit/AuditEventPresenter.php` into bounded collaborators: `AuditEventSummaryFormatter.php`, `AuditValuePresenter.php`, `AuditEntityPresenter.php`, and `AuditFieldLabels.php`. Summaries name the actual intent and safe entity, not generic `Updated record` text.
- [ ] Update `backend/app/Http/Resources/AuditEventResource.php`, `backend/app/Services/Audit/AuditQuery.php`, `backend/app/Services/Audit/AuditFilterMetadata.php`, and `backend/app/Http/Controllers/ActivityController.php` for the typed contract without raw JSON.
- [ ] Add `backend/tests/Unit/AuditEventPresenterTest.php` and expand structured API tests for every known action including Imported, unknown legacy action preservation, semantic security/system subjects, safe created values, before/after values, clinical redaction, patient/actor separation, deleted subjects, system actor, authorized current-record links, and no query growth.
- [ ] Review gates complete; commit `feat: present typed audit events`.

**Rollback boundary:** Revert DTO/presenter/resources together. A1 storage stays valid.

## Task 6 — Five-tab Admin information architecture and updated filters

- [ ] Read applicable local Next.js 16 route-handler/client-boundary docs before code and record paths. Preserve Nutriscope typography, palette, spacing, tables, drawers, modals, tabs, and responsive behavior.
- [ ] Update `frontend/types/audit.ts`, `frontend/services/auditLogService.ts`, `frontend/app/api/admin/audit-logs/route.ts`, `frontend/components/audit/useAuditUrlState.ts`, `useAuditEventList.ts`, `AuditFilters.tsx`, `AuditEventTable.tsx`, and `frontend/app/admin/audit-logs/page.tsx` for module tabs, module-specific contextual subfilters, module-aware active action metadata, and typed DTOs.
- [ ] Render exactly All Activity, Security & Administration, Nutrition Care, Food Service Operations, and Reports. Remove the normal Domain filter and any category tab. Module tab, contextual subfilter, action, actor, outcome, severity, and dates are URL-addressable; tab/subfilter changes reset pagination without erasing valid secondary filters.
- [ ] Add paginated/searchable actor lookup through `backend/app/Http/Controllers/Admin/AuditActorController.php`, `backend/app/Http/Requests/Admin/ListAuditActorsRequest.php`, `backend/routes/api.php`, `frontend/app/api/admin/audit-actors/route.ts`, and `frontend/services/auditActorService.ts`. Only actor names are searchable; patient snapshots are not.
- [ ] Keep backend `category`/`domain` request validation and Next.js proxy pass-through temporarily for compatibility, but stop emitting them from the normal page.
- [ ] Compute optional tab counts with one conditional aggregate, never per-tab queries. Update `frontend/app/admin/audit-logs/audit-page-contract.test.tsx`, `audit-filter-contract.test.ts`, `frontend/components/audit/useAuditUrlState.test.tsx`, event-list tests, service/proxy tests, and backend actor/module/subfilter/action/count tests. Verify 44 px targets, visible focus, keyboard tab selection, labels, loading/empty/unauthorized/retry states, and responsive table behavior.
- [ ] Review gates complete; commit `feat: add five-tab audit oversight`.

**Rollback boundary:** Revert frontend/module-query changes; A1 backend compatibility parameters remain.

## Task 7 — Simple before/after drawer presentation

- [ ] Update `frontend/components/audit/AuditEventDrawer.tsx` and `AuditChangeList.tsx` to display field label, typed before value, typed after value, safe created values, actor, patient (when permitted), NCP reference, action, timestamp, record type, and reason.
- [ ] Add `frontend/components/audit/AuditValue.tsx` for typed currency/date/quantity/boolean/enum/reference rendering. It must reject nested arrays/objects rather than stringify them.
- [ ] Keep the existing accessible drawer focus trap, Escape behavior, return-focus behavior, mobile layout, typography, and color tokens. Clearly distinguish read-only content.
- [ ] Remove misleading `Safe request metadata` detail rendering; request metadata remains storage/debug context only if already permitted and is not mixed with business details.
- [ ] Extend `AuditEventDrawer.test.tsx`, `AuditEventInteractions.test.tsx`, and backend presenter tests for created/updated/deleted safe entities, redaction, null transitions, no raw JSON, and screen-reader labels.
- [ ] Review gates complete; commit `feat: show typed audit changes`.

**Wave A2 integration gate and push:** Tasks 5–7 backend/frontend focused tests, typecheck, lint, production build, privacy render sentinels, accessibility interactions, and query-count tests pass. Push `main`; verify remote equality.

**Rollback boundary:** Revert Tasks 7, 6, then 5. A1 storage remains compatible.

## Task 8 — Complex event-time read-only historical views

- [ ] Task 8A: add `backend/app/Services/Audit/Revisions/AuditRevisionWriter.php`, `AuditRevisionRegistry.php`, typed historical DTOs, size-cap enforcement, Admin history route/resource/proxy/page shell, authorization, immutability, and privacy refusal. Complete the full test/review/verification protocol; commit `feat: add audit revision framework`.
- [ ] Task 8B: add `RndRecipeRevisionSerializer.php` plus read-only RND recipe version UI/tests; commit `feat: add RND recipe history`.
- [ ] Task 8C: add `FoodServiceRecipeRevisionSerializer.php` plus read-only FSS recipe version UI/tests; commit `feat: add FSS recipe history`.
- [ ] Task 8D: add `MenuCycleRevisionSerializer.php` plus read-only weekly menu version UI/tests; commit `feat: add menu audit history`.
- [ ] Task 8E: add `ShoppingListRevisionSerializer.php` and `PurchaseOrderRevisionSerializer.php` plus shopping/PO/receiving version UI/tests; commit `feat: add procurement audit history`.
- [ ] Task 8F: add `BudgetRevisionSerializer.php` plus contextual fiscal-year/ledger version UI/tests; commit `feat: add budget audit history`.
- [ ] Serializers capture complete event-time operational structure, safe scalar values, child rows, units, totals, statuses, and stable operational references before deletion or after committed mutation as policy requires. They refuse clinical/patient-linked subjects and prohibited keys.
- [ ] Add `backend/app/Http/Controllers/Admin/AuditHistoryController.php`, `backend/app/Http/Resources/AuditHistoryResource.php`, and `GET /api/admin/audit-logs/{event}/history` in `backend/routes/api.php`, authorized Admin-only by `AuditPolicy`. Resolve by public audit UUID; never accept patient names/identifiers in the URL.
- [ ] Add `frontend/app/api/admin/audit-logs/[id]/history/route.ts`, `frontend/services/auditHistoryService.ts`, `frontend/types/auditHistory.ts`, `frontend/app/admin/audit-logs/[id]/history/page.tsx`, and typed read-only components under `frontend/components/audit/history/` for menu plans, both recipe types, POs, shopping lists, receiving, and budgets. Default updates render After with highlighted added/changed/removed sections and an accessible Before/After toggle.
- [ ] Link complex events from the drawer/table. Deleted live records still render from revision data; no link redirects to the mutable live record as historical proof.
- [ ] Add `backend/tests/Feature/Audit/AuditHistoricalRevisionTest.php` and `frontend/components/audit/history/audit-history.test.tsx` for event-time fidelity, child structures, delete survival, immutability, authorization, current-record link authorization, privacy refusal, schema versions, per-type size caps, Before/After highlighting, and no raw JSON. Each 8A–8F slice receives its own spec and quality review before commit.

**Rollback boundary:** Revert history routes/UI/writers before dropping the revisions table. Existing activity rows remain.

## Task 9 — Required change-reason flows

- [ ] Task 9A: add `backend/app/Http/Requests/Audit/DestructiveActionRequest.php`, `CorrectiveActionRequest.php`, and `backend/app/Services/Audit/AuditChangeReason.php` with trimmed, bounded, nonblank reason validation and safe storage/presentation; commit `feat: define audit change reasons`.
- [ ] Require a reason on destructive endpoints in `Admin/UserController.php`; RND `PatientController.php`, `NcpRecordController.php`, `DiagnosisController.php`, `MonitoringController.php`, `MealPlanController.php`, `MealPlanItemController.php`, `ScreeningDocumentController.php`, `FoodItemController.php`, and `RecipeController.php`; FSS `FsItemController.php`, `FoodServiceRecipeController.php`, `SupplierController.php`, `MenuCycleController.php`, `MenuCycleTemplateController.php`, `ShoppingListController.php`, `PurchaseOrderController.php`; and `ReportController.php`. Announcement/content deletion remains classified and included only if the policy marks it an audited destructive record.
- [ ] Require reasons for existing corrective actions, including PO line price corrections and budget manual adjustments/edits, without inventing a new correction or reversal workflow.
- [ ] Tasks 9B–9F migrate and independently test/review/commit Admin/account, clinical, RND Food Library, FSS operations, and report deletion/correction clients and endpoints in that order. Update affected frontend services, Next.js DELETE proxies, and existing confirmation modals/pages under admin users, patients/NCP, Food Library, FSS inventory/recipes/suppliers/menu/procurement, and reports to collect and forward the reason. Update affected mobile procurement attachment deletion only if its classified endpoint requires a reason.
- [ ] Add `backend/tests/Feature/Audit/AuditChangeReasonTest.php` plus frontend service/proxy/modal tests proving 422 for missing/blank reason, successful audited reason, no reason in clinical payload beyond permitted metadata, authorization unchanged, and no duplicate event.
- [ ] Every 9A–9F slice completes the global test/spec-review/quality-review protocol; final integration commit, if needed, is `feat: require audit change reasons`.

**Rollback boundary:** Revert clients first only if backend reason enforcement is also reverted in the same release rollback. Historical reasons remain immutable.

## Task 10 — Budget audit coverage

- [ ] Update `backend/app/Http/Controllers/FSS/BudgetController.php`, `backend/app/Listeners/BudgetLedgerListener.php`, `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`, `backend/app/Models/Budget.php`, `BudgetLedger.php`, and budget revision serializer/policy for new fiscal year, opening allocation, per-head/day, manual ledger input, existing corrections, PO deductions, actor, reason, amount, balance, fiscal year, and safe linked operational reference.
- [ ] Preserve current ledger behavior. Do not add approval/rejection, flags, mandatory Admin review, immutable-ledger reversal, or any new budget workflow.
- [ ] Keep Admin budget routes/presentation read-only; update `backend/app/Http/Resources/BudgetResource.php`, `frontend/components/budget/BudgetPageShell.tsx`, and contextual `ActivityController`/`AuditTrail.tsx` only for safe audit presentation.
- [ ] Extend `backend/tests/Feature/BudgetAuditTest.php`, `BudgetLedgerTest.php`, `BudgetLedgerRestructureTest.php`, `AdminBudgetReadOnlyTest.php`, `Audit/PurchaseOrderTrailTest.php`, and frontend budget/audit tests for exact balances, canonical events, reasons, revisions, Admin read-only, and no workflow additions.
- [ ] Review gates complete; commit `feat: complete budget audit coverage`.

**Rollback boundary:** Revert presentation/writer changes; never mutate existing ledger or audit history during rollback.

## Task 11 — RND Food Library and recipe audit coverage

- [ ] Classify `backend/app/Models/FoodItem.php` and `Recipe.php` plus `backend/app/Http/Controllers/RND/FoodItemController.php` and `RecipeController.php` as Nutrition Care, including USDA/imported/custom foods and RND clinical recipes.
- [ ] Present meaningful safe created values and before/after values for simple foods; create event-time recipe revisions including ingredient structure, units, servings, meal types, nutrients, and import source where safe.
- [ ] Update `backend/app/Http/Resources/FoodItemResource.php`, `RecipeResource.php`, relevant import services/controllers, `frontend/app/(rnd)/food-library/page.tsx`, `frontend/services/foodLibraryService.ts`, and audit typed labels/history components.
- [ ] Extend `backend/tests/Feature/FoodItemControllerTest.php`, `RecipeControllerTest.php`, `RecipeProfileTest.php`, `AuditCanonicalEventTest.php`, and frontend Food Library/audit tests for module classification, imports, create/update/delete, reason, one-event policy, revision fidelity, and no clinical leakage.
- [ ] Review gates complete; commit `feat: audit RND food library`.

**Rollback boundary:** Revert module/writer/history UI; live Food Library behavior and stored events remain usable.

## Task 12 — Food Service Operations audit coverage

- [ ] Cover hospital-wide catalog, FSS recipes/ingredients, suppliers, menu cycles/templates/plans, population served, estimated servings, shopping lists/items, purchase orders/vendor groups/attachments/corrections, receiving/costing, operational inventory, meal-prep completion, budgets, and ledger events.
- [ ] Update canonical writers in FSS controllers/services/listeners: `FsItemController.php`, `FoodServiceRecipeController.php`, `SupplierController.php`, `MenuCycleController.php`, `MenuCycleTemplateController.php`, `DietListCountController.php`, `FoodServiceSettingController.php`, `ShoppingListController.php`, `PurchaseOrderController.php`, `MealPrepLogController.php`, `BudgetController.php`, `PurchaseOrderLifecycleService.php`, `ReceivingService.php`, `ConsumptionService.php`, and `BudgetLedgerListener.php`.
- [ ] Update involved models/resources and revision serializers so simple values are typed and complex structures are event-time snapshots. Preserve quantity/receiving compatibility fields unless the Task 2 evidence proves all dependencies retired.
- [ ] Extend `backend/tests/Feature/OperationsAuditTest.php`, `FoodServiceOpsTest.php`, `FoodShoppingListGenerationTest.php`, `MenuCycleWorkflowGuardTest.php`, `MenuCyclePoSnapshotTest.php`, `PurchaseOrderExecutionLockTest.php`, `PurchaseOrderCompletionPatternTest.php`, `Audit/PurchaseOrderTrailTest.php`, plus frontend FSS/audit tests for complete coverage, canonical counts, reasons, safe values, history, and concurrency locks.
- [ ] Review gates complete; commit `feat: audit food service operations`.

**Rollback boundary:** Revert audit instrumentation/presentation without reversing live operational transactions or stored audit rows.

## Task 13 — Reports coverage and shared-RND authorization corrections

- [ ] Remove attribution-as-ownership gates from `backend/app/Services/Reports/ReportBrowser.php`, `backend/app/Http/Controllers/ReportController.php`, and `backend/app/Policies/AuditPolicy.php`. `rnd_user_id`, `audit_owner_id`, report `user_id`, `created_by`, and similar fields remain attribution only.
- [ ] Verify and correct shared-RND read/write/delete behavior in `AssessmentController.php`, `InterventionController.php`, `MonitoringController.php`, `MealPlanController.php`, `MealPlanItemController.php`, `ScreeningDocumentController.php`, related policies/routes, report browse/instances/archive/render/view/download/delete, and NCP contextual trails.
- [ ] Every timeline/resource uses actual action actor via `backend/app/Services/Audit/ClinicalAttributionService.php`, not original creator. Show both created-by and last-clinical-action-by in `backend/app/Http/Resources/PatientResource.php`, relevant NCP resource, `frontend/app/(rnd)/ncp/patients/page.tsx`, patient detail/NCP card/header components, and types.
- [ ] Classify all report create/generate/archive/view/download/delete/failure events under Reports. Keep export disabled and keep clinical report parameters/content out of Admin audit output/revisions.
- [ ] Expand `backend/tests/Feature/Audit/SharedRndClinicalAccessTest.php`, `ReportAuditTest.php`, `ReportControllerTest.php`, `ReportsBrowseTest.php`, clinical controller tests, and frontend report/NCP tests. Prove RND B can view and successfully edit/delete permitted assessment, intervention, monitoring, patient meal plan, screening document, NCP/report context, and report endpoints created by RND A; prove actual actor attribution.
- [ ] Review gates complete; commit `fix: enforce shared RND audit access`.

**Wave A3 integration gate and push:** Tasks 8–13 history, reasons, budget, RND/FSS/report coverage, authorization matrix, privacy sentinels, report rendering, frontend tests/typecheck/lint/build, and affected mobile checks pass. Push `main`; verify remote equality.

**Rollback boundary:** Authorization fixes are approved behavior and should not be rolled back independently. If release rollback is unavoidable, revert A3 application commits together while preserving audit/revision rows.

## Task 14 — Deterministic demo audit seeders

- [ ] Add `Illuminate\Database\Console\Seeds\WithoutModelEvents` to `backend/database/seeders/DatabaseSeeder.php` so base seeders never generate anonymous audit noise.
- [ ] Add opt-in local/demo-only `backend/database/seeders/AuditDemoSeeder.php`, not called by the base production seeder and guarded from production. It uses a deterministic scenario marker, controlled clock, explicit named Admin/RND/FSS/system actors, and supported audit/domain services rather than direct raw activity inserts; reruns are idempotent without deleting immutable events.
- [ ] Include understandable examples for all five tabs, simple before/after changes, complex history, clinical patient-name-plus-actual-actor events without values, RND Food Library/recipes, menu/PO flow, fiscal year, per-head/day, ledger activity, reports, and retention toggles.
- [ ] Never store fake raw clinical values or patient names in JSON. Retention examples use safe old/new enabled values and explicit actor/timestamp.
- [ ] Add `backend/tests/Feature/Audit/AuditDemoSeederTest.php` and extend `backend/tests/Unit/SeederIntegrityTest.php` and `FoodServiceDemoSeederSourceTest.php` for two-run idempotence, stable counts/UUIDs, all modules, history links, privacy storage/API, and zero base-seeder audit noise.
- [ ] Review gates complete; commit `feat: seed deterministic audit demos`.

**Rollback boundary:** Revert seeder code. Do not delete already seeded immutable demo events automatically; document their deterministic IDs for explicit environment cleanup only.

## Task 15 — Legacy backfill, compatibility retirement, and stale-feature cleanup

- [ ] Re-run the Task 2 inventory and prove every web/mobile/backend consumer migrated before removing `category`/`domain` from `backend/app/Http/Requests/Admin/ListAuditLogsRequest.php`, `AuditQuery.php` public filters, `AuditFilterMetadata.php`, `frontend/services/auditLogService.ts`, `frontend/components/audit/useAuditUrlState.ts`, and Next.js proxy compatibility tests. Keep the storage columns and legacy presentation fallback for historical records.
- [ ] Remove normal Domain/category labels, obsolete filter state, stale event cases, disabled export actions/capabilities from active filter metadata, dead seed data, unused compatibility branches, and proven duplicate writers. Preserve unknown legacy action text instead of relabeling it Updated. Map account `is_active` true-to-false to Account Blocked and false-to-true to Account Unblocked; account deletion remains Deleted.
- [ ] Remove any remaining `IpBlocked`, `IpUnblocked`, `AUDIT_SECURITY_BLOCKS_ENABLED`, and IP-blocking-specific model/migration/controller scaffolding only where Task 2 proves it exists and is unused. Keep `AccountBlocked`, `AccountUnblocked`, and Admin account deactivation behavior/tests.
- [ ] Backfill module/patient snapshots missed during live compatibility waves using an idempotent chunked command `backend/app/Console/Commands/BackfillAuditOversight.php`; unresolved patients retain only NCP pseudonymous reference. Deterministically reclassify legacy RND `FoodItem`/`Recipe` rows, preserve ambiguous rows as Legacy/Unclassified, and never overwrite an existing snapshot/revision or write names to JSON/logs.
- [ ] Update `backend/config/audit.php`, route coverage registry, scheduled command registration, frontend copy, and explicit stale allowlists. Do not remove quantity compatibility without complete dependency proof.
- [ ] Add `backend/tests/Feature/Audit/AuditLegacyCompatibilityTest.php` and update stale-consumer/proxy tests for mixed legacy/current rows, idempotent backfill, no patient resolution, removed params returning validation errors, unknown actions, account block compatibility, and zero stale UI strings.
- [ ] Review gates complete; commit `refactor: retire audit compatibility paths`.

**Rollback boundary:** Reintroduce parameter acceptance/client forwarding by reverting this commit; do not reverse backfilled module or encrypted snapshot values.

## Task 16 — Performance, authorization, privacy, migration, and full integration verification

- [ ] Run route-coverage verification and the complete Admin/RND/FSS authorization matrix, including all shared-RND read/write/delete/report cases and Admin read-only budget/history rules.
- [ ] Run privacy sentinels at storage, API, disabled export, application logs, UI render/source, typed drawer, historical revisions, URLs, filters, actor search, sorting, and metrics. Verify patient snapshot ciphertext at rest and permitted decrypted presentation only.
- [ ] Run migration forward, rollback, and re-forward on configured MySQL for all redesign migrations, including legacy rows and backfill command idempotence.
- [ ] Use Laravel Boost/MySQL `EXPLAIN` for All Activity, each module, contextual subfilter, action/date/actor combinations, contextual trail, and history lookup. Verify intended indexes and newest-first deterministic order. Add a separate reversible index migration only if real plans demonstrate a missing index.
- [ ] Run query-count/N+1 tests, conditional module-count query tests, per-type revision payload/lookup bounds, and the existing 100,000-row performance gate in `backend/tests/Feature/Audit/StructuredAuditApiTest.php` on MySQL.
- [ ] Run duplicate-event tests, retention-state/toggle/prune tests, scheduled deletion behavior, legal-hold behavior if present, seeder idempotence, report rendering, and stale-consumer scans.
- [ ] Run relevant Pint, full Laravel suite, full frontend tests, frontend typecheck/lint/production build, and affected mobile tests/typecheck/export checks.
- [ ] Confirm export remains disabled, no external append-only sink/hash chain was added, no per-category retention UI exists, and no budget approval/reversal workflow was added.
- [ ] Review gates complete; commit `test: verify audit redesign integration`.

**Wave A4 integration gate and push:** Tasks 14–16 pass in full on configured MySQL and all app surfaces. Push `main`; verify remote equality.

**Rollback boundary:** A4 cleanup may be reverted without deleting backfilled/seeded immutable records. Schema rollback still requires application rollback first.

## Task 17 — Final documentation and implementation report

- [ ] Update `docs/architecture/audit-logging.md` with the exact current writer/privacy/storage/retention/history architecture.
- [ ] Replace/update `docs/audit-logs-and-trails-implementation-report.md` with a complete implementation summary, exact workflow, every audited event/action and Admin/RND view, five-tab classification, filters/drawers/trails/history usage, clinical privacy boundary and patient-name exception, shared-RND behavior, budget coverage, retention toggle, demo seeders, migrations/compatibility, blast radius/mitigations, overscope, unrelated bugs fixed and impact, owner-authorized decisions, unresolved/future work, architectural advantages/disadvantages, and verification evidence.
- [ ] Add `docs/architecture/audit-event-catalog.md` as the maintained event matrix with action, module, retention category, actor, subject/context, safe Admin fields, RND trail fields, reason, history mode, and route/writer.
- [ ] Include a Mermaid flowchart covering event creation, canonical writer selection, privacy classification, patient snapshot encryption, storage, DTO presentation, simple-versus-complex history selection, Admin/RND authorization, retention, and legal hold.
- [ ] Document the fixed 365/2,190/1,095/90-day mapping; DB-backed toggle and environment fallback; confirmation rules; audited toggle changes; encryption-key backup/rotation requirement for retained patient snapshots; export-disabled posture; and absence of external sink/hash chain.
- [ ] Run documentation link/path checks, `git diff --check`, and a final spec/quality review. Commit `docs: complete audit redesign report`.

**Wave A5 final gate and push:**

- [ ] Run a fresh full verification, not cached evidence.
- [ ] Confirm every plan checkbox is complete or explicitly marked as a genuine blocker.
- [ ] Confirm all intended task commits exist in order.
- [ ] Push final verified `main`.
- [ ] Verify `git ls-remote origin refs/heads/main` exactly equals local `HEAD`.
- [ ] Report `.codex/config.toml`, `.superpowers`, and any other unrelated/untracked files separately and untouched.
- [ ] Treat every skipped required check as a blocker; do not claim completion.

## Per-task execution controls

| Task | Touched workflow and failure mode | Exact focused verification | Rollback |
|---|---|---|---|
| 1 | All audit routes/writers/consumers. Failure: an event source or consumer is unclassified, or a new-design assertion does not first fail. | `cd backend; php artisan test --compact tests/Feature/Audit tests/Feature/OperationsAuditTest.php tests/Feature/BudgetAuditTest.php tests/Unit/AuditInventoryContractTest.php`; `cd ../frontend; npm test -- components/audit services/auditLogService.test.ts services/activityService.test.ts` | Revert tests/inventory only. |
| 2 | Stale/duplicate discovery. Failure: a remove/retain decision lacks route, consumer, and database evidence. | `cd backend; php artisan test --compact tests/Feature/Audit/AuditDuplicateDiscoveryTest.php tests/Unit/AuditStaleFeatureInventoryTest.php`; `cd ../frontend; npm test -- components/audit/audit-stale-feature-inventory.test.ts` | Revert discovery tests only. |
| 3 | MySQL module/snapshot/revision schema. Failure: classification is nondeterministic, plaintext name/index/JSON copy exists, revision orphans, or round-trip diverges. | `cd backend; php artisan test --compact tests/Feature/Audit/AuditRedesignMigrationTest.php; php artisan migrate --no-interaction; php artisan migrate:rollback --step=3 --no-interaction; php artisan migrate --no-interaction` | Application first if deployed; otherwise DML, revision DDL, activity DDL. |
| 4 | Canonical writer/privacy policy. Failure: one intent emits duplicate primary events, clinical values/name copies leak, actual actor is wrong, or unknown action is rewritten. | `cd backend; php artisan test --compact tests/Feature/Audit/AuditCanonicalEventTest.php tests/Feature/Audit/AuditPrivacyTest.php tests/Feature/Audit/AuditRouteCoverageTest.php` | Revert writers/policy; leave additive schema. |
| 5 | DTO/presenter/summaries. Failure: a known action has generic text, semantic subject is missing, raw/nested data escapes, or query count grows. | `cd backend; php artisan test --compact tests/Unit/AuditEventPresenterTest.php tests/Feature/Audit/StructuredAuditApiTest.php tests/Feature/Audit/ClinicalTrailTest.php` | Revert presenter/DTO/resources together. |
| 6 | Five tabs/subfilters/actor search. Failure: tab count/names differ, Domain appears, URL state is lost, actor list truncates, or count queries multiply. | `cd backend; php artisan test --compact tests/Feature/AdminAuditLogTest.php tests/Feature/Audit/StructuredAuditApiTest.php; cd ../frontend; npm test -- app/admin/audit-logs components/audit services/auditLogService.test.ts; npx tsc --noEmit` | Revert web/module query changes; compatibility params remain. |
| 7 | Typed drawer. Failure: raw JSON appears, Created/Updated/Deleted semantics are wrong, clinical values render, or focus/accessibility regresses. | `cd frontend; npm test -- components/audit/AuditEventDrawer.test.tsx components/audit/AuditEventInteractions.test.tsx; npx tsc --noEmit` | Revert drawer/value components. |
| 8A–8F | Revision framework and each complex type. Failure: live mutable state is mistaken for event-time state, deleted records disappear, serializer crosses privacy/size boundary, Before/After diff is false, or current link bypasses policy. | `cd backend; php artisan test --compact tests/Feature/Audit/AuditHistoricalRevisionTest.php; cd ../frontend; npm test -- components/audit/history/audit-history.test.tsx; npx tsc --noEmit` after each slice | Revert only the failing serializer/API/UI slice; hide its historical links. |
| 9A–9F | Reason validation and each destructive/corrective surface. Failure: missing reason succeeds, routine drafts become blocked, arbitrary request data enters audit, or proxy/client omits reason. | `cd backend; php artisan test --compact tests/Feature/Audit/AuditChangeReasonTest.php; cd ../frontend; npm test -- services components app/api` with each affected slice's explicit test files | Revert client and backend enforcement for the same slice together. |
| 10 | Budget. Failure: balances/reasons/actors are misleading, Admin gains write access, or unapproved workflow appears. | `cd backend; php artisan test --compact tests/Feature/BudgetAuditTest.php tests/Feature/BudgetLedgerTest.php tests/Feature/BudgetLedgerRestructureTest.php tests/Feature/AdminBudgetReadOnlyTest.php tests/Feature/Audit/PurchaseOrderTrailTest.php` | Revert audit/presentation only; never reverse ledger data. |
| 11 | RND Food Library/recipes. Failure: module/domain is wrong, raw USDA data stores, patient-linked content becomes operational, duplicate events appear, or recipe history is incomplete. | `cd backend; php artisan test --compact tests/Feature/FoodItemControllerTest.php tests/Feature/RecipeControllerTest.php tests/Feature/RecipeProfileTest.php tests/Feature/Audit/AuditCanonicalEventTest.php` | Revert module/writer/history slice. |
| 12 | FSS operations. Failure: parent action explodes into child noise, locked records mutate, quantity compatibility breaks, or historical totals differ. | `cd backend; php artisan test --compact tests/Feature/OperationsAuditTest.php tests/Feature/FoodServiceOpsTest.php tests/Feature/FoodShoppingListGenerationTest.php tests/Feature/MenuCycleWorkflowGuardTest.php tests/Feature/Audit/PurchaseOrderTrailTest.php` | Revert audit instrumentation/presentation; preserve transactions. |
| 13 | Reports/shared RND. Failure: attribution still gates access, RND B cannot perform permitted actions, actual actor is wrong, or Admin reaches patient report content. | `cd backend; php artisan test --compact tests/Feature/Audit/SharedRndClinicalAccessTest.php tests/Feature/Audit/ReportAuditTest.php tests/Feature/ReportControllerTest.php tests/Feature/ReportsBrowseTest.php` | Roll back A3 coherently; do not restore owner gates selectively. |
| 14 | Base/demo seeding. Failure: base seeds emit anonymous noise, demo rerun duplicates, production guard fails, chronology/actors are false, or clinical values store. | `cd backend; php artisan test --compact tests/Feature/Audit/AuditDemoSeederTest.php tests/Unit/SeederIntegrityTest.php tests/Unit/FoodServiceDemoSeederSourceTest.php` | Revert seeder code; deterministic rows remain until explicit cleanup. |
| 15 | Compatibility retirement/backfill/stale cleanup. Failure: a consumer still sends removed params, ambiguous history is misclassified, IP cleanup touches account blocking, or backfill overwrites data. | `cd backend; php artisan test --compact tests/Feature/Audit/AuditLegacyCompatibilityTest.php tests/Feature/Audit/ProxyRouteCompatibilityTest.php tests/Feature/Audit/IpBlockingRemovalContractTest.php; cd ../frontend; npm test -- components/audit services/auditLogService.test.ts` | Revert parameter retirement/cleanup; retain safe backfilled fields. |
| 16 | Full integration/performance/privacy/auth. Failure: any required check is skipped/fails, MySQL ignores indexes, 100k gate regresses, N+1 appears, or any privacy boundary leaks. | `cd backend; php artisan test --compact; vendor/bin/pint --dirty --format agent; cd ../frontend; npm test; npx tsc --noEmit; npm run lint; npm run build; cd ../mobile; node --test --test-isolation=none lib/*.test.cjs; npx tsc --noEmit` | Stop before push; revert failing wave app-first. |
| 17 | Documentation/final remote state. Failure: catalog/workflow/evidence differs from code, Mermaid omits a required stage, a checkbox is unresolved, or remote differs. | `git diff --check`; fresh Task 16 command set; `git log --oneline`; `git ls-remote origin refs/heads/main` | Do not claim completion or push stale docs; fix evidence/docs then rerun. |

## Classification and presentation matrix

| Module tab | Included records/actions | Detail behavior |
|---|---|---|
| All Activity | Every canonical event, no stored `all` module | Same typed/detail policy as its real module |
| Security & Administration | Authentication, authorization, rate limiting, account activation/deactivation/block/unblock, profile/password/recovery changes, AI limits, announcements, SOPs, Admin/system settings, retention/pruning | Typed safe security/admin details; no credentials/tokens/raw request payloads |
| Nutrition Care | Patient/NCP lifecycle, assessments, diagnoses, interventions, monitoring, meal plans, screening documents, RND Food Library, USDA/imported foods, RND recipes | Patient-linked events show only allowed patient snapshot/actor/action/time/type/NCP ref/field names; Food Library values/revisions are safe operational content |
| Food Service Operations | FSS catalog/recipes/ingredients/suppliers/menu plans, shopping/PO/receiving/costing, population/servings/inventory, budgets/ledger | Typed before/after for simple records; event-time history for complex structures |
| Reports | Generate/archive/view/download/delete/failure, branding, and templates for all report families | Safe report metadata only; patient-linked events may use the dedicated name snapshot but never patient-specific parameters/content |

## Required privacy sentinels

- [ ] No clinical old/new values in activity columns, properties, revisions, API, logs, export, UI, or seed data.
- [ ] No previous/new patient-name values anywhere in audit output.
- [ ] Dedicated `patient_display_name_snapshot` ciphertext is non-indexed and absent from arbitrary JSON.
- [ ] No hospital number, DOB, sex, address/contact, ward, physician, diagnosis/admission, screening/risk, meal-plan, clinical content, files/OCR/AI prompts/outputs, or patient-report parameters/content.
- [ ] No patient name in URL, metric, export, filter, sort, search, or logs.
- [ ] Actor and patient identity are independently labeled and tested.
- [ ] Historical serializers reject all clinical/patient-linked content.

## Required authorization matrix

- [ ] Admin can list/filter all five modules, view permitted typed details/history, and manage retention toggle; budget remains read-only; export is 404/disabled.
- [ ] RND sees contextual trails permitted by policy but never the global Admin audit index.
- [ ] RND B can view/edit permitted assessment, intervention, monitoring, meal plan, screening document, NCP/report context, and permitted deletion/report endpoints created by RND A.
- [ ] FSS operational access remains role-scoped as before; attribution fields never become ownership gates.
- [ ] Unauthenticated/wrong-role requests are denied and denial events remain canonical/privacy-safe.

## Final verification commands

```powershell
cd backend
php artisan migrate:status --no-ansi
php artisan test --compact
vendor/bin/pint --dirty --format agent
php artisan db:seed --class=AuditDemoSeeder --no-interaction
php artisan db:seed --class=AuditDemoSeeder --no-interaction

cd ..\frontend
npm test
npx tsc --noEmit
npm run lint
npm run build

cd ..\mobile
node --test --test-isolation=none lib/*.test.cjs
npx tsc --noEmit
npx expo export --platform android --output-dir .expo-audit-verification
```

The migration rollback/re-forward and 100,000-row performance commands are run separately against the configured MySQL environment with exact output captured in the implementation report. Generated Expo verification directories are inspected, removed, and never committed.
