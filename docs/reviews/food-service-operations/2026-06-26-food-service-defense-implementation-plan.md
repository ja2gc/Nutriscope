# Document 4 - Food Service Defense-Focused Implementation Plan

Date: 2026-06-26  
Source of truth:
- `docs/reviews/food-service-operations/2026-06-26-food-service-workflow-deep-audit.md`
- `docs/reviews/food-service-operations/2026-06-26-food-service-workflow-audit.md`
- `docs/reviews/food-service-operations/2026-06-26-food-service-current-vs-proposed-gap-analysis.md`

## Goal

Determine the smallest set of changes required to make the Food Service module operationally defensible, logically consistent, and demo-safe for capstone defense.

This plan does not optimize for perfect architecture or enterprise food-service compliance. It optimizes for correctness, workflow consistency, report accuracy, defense presentation quality, and implementation effort.

## Decision Rule

| Classification | Meaning |
| --- | --- |
| Implement Before Defense | Needed for operational correctness, report accuracy, workflow consistency, security boundaries, or demo credibility. |
| Implement If Time Allows | Useful and defensible, but not necessary if the demo story and minimum workflow are tightened. |
| Defer Until After Defense | Large architecture, advanced lifecycle management, future scalability, or deep compliance work. |

## Gap Classification

Each gap from Document 3 is classified exactly once.

| Gap ID | Title | Classification |
| --- | --- | --- |
| FS-GAP-001 | FSS can see RND web pages and navigation | Implement Before Defense |
| FS-GAP-002 | FSS can generate shopping lists through API | Implement If Time Allows |
| FS-GAP-003 | FSS can read budget and insights APIs outside intended FSS scope | Implement If Time Allows |
| FS-GAP-004 | FSS report guard is inconsistent across report actions | Implement Before Defense |
| FS-GAP-005 | Incomplete menu cycles can be activated | Implement Before Defense |
| FS-GAP-006 | Active menu cycles can change after cost snapshot | Implement Before Defense |
| FS-GAP-007 | Template-created cycles lose population values | Defer Until After Defense |
| FS-GAP-008 | Recipe and item costs lack a clear reporting version boundary | Implement If Time Allows |
| FS-GAP-009 | Diet-list counts can duplicate or contradict actual work state | Implement Before Defense |
| FS-GAP-010 | Served population and consumption basis can diverge | Implement Before Defense |
| FS-GAP-011 | Reversed service day cannot be completed again | Implement Before Defense |
| FS-GAP-012 | Shopping lists can be finalized outside approval flow | Implement If Time Allows |
| FS-GAP-013 | Purchase order status lifecycle can produce invalid receiving | Implement Before Defense |
| FS-GAP-014 | FSS proof upload does not complete receiving handoff | Implement If Time Allows |
| FS-GAP-015 | Inventory no-stock numbers disagree between dashboard and inventory list | Implement Before Defense |
| FS-GAP-016 | Manual inventory adjustments are not distinguished from workflow-derived stock | Defer Until After Defense |
| FS-GAP-017 | Budget period validation and overlap rules are weak | Implement If Time Allows |
| FS-GAP-018 | Budget actuals and manual logs have unclear origin and UI coverage | Implement If Time Allows |
| FS-GAP-019 | Report availability allows incomplete, draft, or blank source data | Implement Before Defense |
| FS-GAP-020 | Dietary cash book uses inconsistent date basis for availability vs generation | Implement Before Defense |
| FS-GAP-021 | Accomplishment report capture and report access are not aligned | Implement If Time Allows |
| FS-GAP-022 | Insights scope is inconsistent across API and UI | Defer Until After Defense |
| FS-GAP-023 | Clinical population and Food Service population are not explicitly separated | Defer Until After Defense |
| FS-GAP-024 | Orphaned or API-only Food Service features remain visible in architecture | Defer Until After Defense |
| FS-GAP-025 | Food Service authorization is route-based without model policy depth | Defer Until After Defense |

## Implement Before Defense

| Order | Gap | Why it must be fixed | User-visible impact | Risk if left unfixed | Estimated complexity | Dependencies |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | FS-GAP-001 - FSS can see RND web pages and navigation | Visible role leakage is the easiest workflow defect to expose during demo. | FSS users stop seeing RND-only planning/admin screens. | Wrong-role pages, 403 errors, and confusing demo navigation. | Low to medium | Auth user role data and web routing/layout behavior |
| 2 | FS-GAP-004 - FSS report guard is inconsistent across report actions | Report authorization must be defensible before any report demo. | FSS can only access allowed report types through every report action. | FSS can generate/download non-accomplishment Food Service reports by direct path. | Low to medium | ReportController guard behavior and report tests |
| 3 | FS-GAP-005 - Incomplete menu cycles can be activated | Activation is the main RND-to-FSS handoff; it must mean the plan is usable. | RND gets blocked or warned before handing off an incomplete active menu. | Active menus can fail procurement, service, or reports. | Medium | Menu-cycle validation rules and existing menu-cycle editor expectations |
| 4 | FS-GAP-006 - Active menu cycles can change after cost snapshot | Reports and procurement need stable source data once a cycle is active. | RND understands active cycles are locked for reporting/service, or edits follow a controlled path. | Frozen costs and displayed menu rows can diverge. | Medium | Activation state, cost snapshot behavior, menu-cycle update flow |
| 5 | FS-GAP-013 - Purchase order status lifecycle can produce invalid receiving | Receiving updates inventory and budget/report source data; it must be idempotent. | RND cannot accidentally double-receive or receive through an invalid state. | Inflated stock, incorrect budget actuals, inaccurate procurement reports. | Medium | PO update validation, ReceivingService, existing PO tests |
| 6 | FS-GAP-009 - Diet-list counts can duplicate or contradict actual work state | Actual served population is report-critical and budget-critical. | FSS diet-list/accomplishment entries produce cleaner totals. | Inflated served population and inaccurate accomplishment/budget reports. | Medium | Diet-list request rules, database uniqueness or equivalent validation |
| 7 | FS-GAP-010 - Served population and consumption basis can diverge | The system must explain what population drives consumption and per-head reports. | Meal-prep completion produces understandable population and stock results. | Budget per-head figures and inventory consumption can be challenged. | Medium | ConsumptionService, MenuCycleCostService usage basis, meal-prep tests |
| 8 | FS-GAP-011 - Reversed service day cannot be completed again | Correction workflow must not trap FSS in an unfinished service day. | FSS can recover after a mistaken complete/reverse action. | Demo can dead-end if a service day is reversed. | Medium | Meal-prep unique key behavior and reverse/complete service logic |
| 9 | FS-GAP-015 - Inventory no-stock numbers disagree between dashboard and inventory list | Dashboard KPIs should reconcile with the detailed inventory screen. | Dashboard no-stock count matches inventory list. | Presenter can be challenged when KPI and list disagree. | Low | FssDashboardService and inventory row counting logic |
| 10 | FS-GAP-019 - Report availability allows incomplete, draft, or blank source data | Reports are defense artifacts; they should only appear when source data is defensible. | RND sees only report instances backed by valid enough source records. | Blank or incomplete reports can be generated during defense. | Medium | ReportBrowser source checks and report generator minimum data rules |
| 11 | FS-GAP-020 - Dietary cash book uses inconsistent date basis for availability vs generation | Cash book period accuracy must match report availability. | RND sees cash-book periods that match the generated report content. | Valid received POs can be hidden from browse or periods can appear inconsistent. | Low | Dietary cash book source query and generator date basis |

### Before-Defense Dependency Notes

| Build group | Covers | Dependency logic |
| --- | --- | --- |
| Role and report access | FS-GAP-001, FS-GAP-004 | Do first because these are high-visibility security/demo issues and do not depend on data lifecycle changes. |
| Menu handoff stability | FS-GAP-005, FS-GAP-006 | Do before procurement/report readiness because shopping lists and PPA depend on activated menu-cycle integrity. |
| Procurement/inventory accuracy | FS-GAP-013, FS-GAP-015, FS-GAP-020 | Do before report readiness because procurement, inventory, cash book, and budget outputs depend on received PO and inventory numbers. |
| FSS service-day correctness | FS-GAP-009, FS-GAP-010, FS-GAP-011 | Do before budget/report readiness because actual population and meal-prep logs feed accomplishment and per-head reporting. |
| Report source gating | FS-GAP-019 | Do after the source workflows are stabilized so report eligibility checks reflect the corrected source states. |

## Implement If Time Allows

| Gap | Why it helps | Why it is not required |
| --- | --- | --- |
| FS-GAP-002 - FSS can generate shopping lists through API | Tightens procurement ownership so RND clearly owns planning artifacts. | No normal FSS mobile UI for generation was found; demo can avoid direct API calls. |
| FS-GAP-003 - FSS can read budget and insights APIs outside intended FSS scope | Aligns API scope with the narrower FSS execution story. | Read-only access does not corrupt data, and mobile FSS does not expose these screens. |
| FS-GAP-008 - Recipe and item costs lack a clear reporting version boundary | Helps explain current cost vs frozen report cost. | Activation cost snapshot already gives a defensible baseline if active-cycle edits are controlled. |
| FS-GAP-012 - Shopping lists can be finalized outside approval flow | Makes procurement status semantics cleaner. | Demo can use the RND conversion path and avoid direct manual finalization. |
| FS-GAP-014 - FSS proof upload does not complete receiving handoff | Clarifies receipt/proof upload as shared execution evidence and removes the need for any FSS receive action. | The minimum demo can stabilize the existing RND status path, but the expanded workflow replaces it with system-driven procurement-span completion. |
| FS-GAP-017 - Budget period validation and overlap rules are weak | Reduces budget setup errors and ambiguous summaries. | Demo can use one clean budget period with complete dates. |
| FS-GAP-018 - Budget actuals and manual logs have unclear origin and UI coverage | Makes budget summaries easier to explain. | Demo can rely on received POs and meal-prep actuals, avoiding manual daily logs. |
| FS-GAP-021 - Accomplishment report capture and report access are not aligned | Gives FSS a clearer visible report flow. | Source data is real, and RND can already render the report through the existing reports surface. |

## Defer Until After Defense

Deferred items need no further planning here.

| Gap | Title |
| --- | --- |
| FS-GAP-007 | Template-created cycles lose population values |
| FS-GAP-016 | Manual inventory adjustments are not distinguished from workflow-derived stock |
| FS-GAP-022 | Insights scope is inconsistent across API and UI |
| FS-GAP-023 | Clinical population and Food Service population are not explicitly separated |
| FS-GAP-024 | Orphaned or API-only Food Service features remain visible in architecture |
| FS-GAP-025 | Food Service authorization is route-based without model policy depth |

## Recommended Final Food Service Scope

List only the changes that should actually be built before defense.

1. Protect the web role boundary so FSS users cannot enter RND planning/admin pages.
2. Apply consistent FSS report authorization to all report actions, including deprecated/shared paths.
3. Require menu cycles to be defensibly complete before activation.
4. Prevent active/reporting menu cycles from diverging from their frozen cost snapshot.
5. Make the current/legacy purchase-order receiving status a valid, single-effect transition until C+B replaces it with procurement-span completion.
6. Prevent duplicate or contradictory diet-list/accomplishment rows from corrupting served population.
7. Make meal-prep population and consumption behavior consistent enough to defend.
8. Ensure reversed service days can be corrected and completed again.
9. Align dashboard no-stock count with the inventory list.
10. Restrict report availability to valid source data for demo-critical report types.
11. Align dietary cash book browse availability with its generation date basis.

## Changes Explicitly Deferred

These changes are not part of the recommended before-defense build scope.

1. FSS shopping-list generation API scope cleanup unless time remains.
2. FSS read access cleanup for budget and insights unless time remains.
3. Recipe/item cost version labeling beyond the activation snapshot unless time remains.
4. Shopping-list direct finalization cleanup unless time remains.
5. FSS proof-upload/system-completion redesign unless time remains in the minimum defense scope.
6. Budget overlap validation unless time remains.
7. Budget source/manual-log UI cleanup unless time remains.
8. FSS-facing accomplishment report UI unless time remains.
9. Template population defaults.
10. Full inventory movement/audit ledger.
11. Full insights role/UI restructuring.
12. Clinical-vs-Food-Service population integration work.
13. Orphaned/API-only feature cleanup outside the demo path.
14. Full model-policy authorization architecture.

## Suggested Implementation Order

1. Lock web role boundary so FSS cannot enter RND web pages (`FS-GAP-001`).
2. Lock report authorization across all FSS report actions and retrieval paths (`FS-GAP-004`).
3. Add menu-cycle activation completeness checks (`FS-GAP-005`).
4. Prevent active/reporting menu-cycle edits from diverging from activation cost snapshot (`FS-GAP-006`).
5. Stabilize current/legacy PO receiving as valid and single-effect until the expanded procurement-span completion model replaces it (`FS-GAP-013`).
6. Align dashboard no-stock count with inventory list logic (`FS-GAP-015`).
7. Align dietary cash book browse and generation date basis (`FS-GAP-020`).
8. Add diet-list duplicate/off-duty consistency rules (`FS-GAP-009`).
9. Clarify and enforce meal-prep population/consumption basis (`FS-GAP-010`).
10. Make reversed service days recoverable (`FS-GAP-011`).
11. Gate report availability against defensible source states (`FS-GAP-019`).

## Defense Story After These Changes

After the before-defense scope is complete, the Food Service module is defensible as a focused RND-to-FSS operations workflow:

1. RND creates a complete menu cycle, activates it as the handoff to FSS, and the active plan remains stable for reports and execution.
2. RND owns procurement planning and conversion, while RND/FSS can upload receipt/proof against issued POs or vendor groups.
3. The minimum path keeps the current receiving/status update single-effect; the expanded path completes procurement spans automatically once receipt/proof evidence and served-population requirements are complete.
4. FSS records diet-list/accomplishment rows and completes service days without corrupting actual population or getting trapped by reversal.
5. Inventory dashboard counts reconcile with the inventory screen.
6. Reports are role-appropriate and only appear from defensible source states.

The module should not be presented as full enterprise food-service compliance, full inventory ledgering, or full cross-module clinical census integration before defense. It should be presented as a coherent capstone workflow for planning, procurement, service execution, inventory updates, and report generation.

## Expanded C+B / D+E Redesign Plan

The sections above remain the minimum defense-safe scope. The C+B and D+E redesign prompts expand the plan because several flaws are obvious in testing and would be natural defense questions. This expanded plan must still be executed in sequence.

### Expanded Decision Rule

| Classification | Meaning |
| --- | --- |
| Phase 0 Baseline | Verify the current date-driven generation baseline and green test/typecheck state before changing behavior. |
| C+B Foundation | Required before the PO redesign: population authority, shopping-list state, supplies, and overlap prevention. |
| C+B Procurement Event Core | Required to replace the old vendor-split PO workflow with one procurement event, vendor groups, and stable events. |
| D+E Budget/Report/Insights Core | Depends on C+B events; covers fiscal-year budget ledger, frozen reports, and final insights. |
| Final Verification/Data Proof | Seeder and full regression work after the final data model exists. |

### Expanded Gap Classification

| Gap ID | Title | Classification |
| --- | --- | --- |
| FS-RED-001 | Shopping-list population authority is missing | C+B Foundation |
| FS-RED-002 | Overlapping menu-linked draft shopping lists are ambiguous | C+B Foundation |
| FS-RED-003 | Shopping-list state model conflicts with conversion workflow | C+B Foundation |
| FS-RED-004 | Supplies procurement is incomplete | C+B Foundation |
| FS-RED-005 | Current PO model is one PO per supplier | C+B Procurement Event Core |
| FS-RED-006 | Vendor-group execution model is missing | C+B Procurement Event Core |
| FS-RED-007 | PO lifecycle phases are missing | C+B Procurement Event Core |
| FS-RED-008 | PO conversion event hook is missing | C+B Procurement Event Core |
| FS-RED-009 | Actual budget per head per procurement event is missing | C+B Procurement Event Core |
| FS-RED-010 | Procurement page is not event-based | C+B Procurement Event Core |
| FS-RED-011 | PPA is manual/menu-cycle report, not auto-generated procurement snapshot | C+B Procurement Event Core |
| FS-RED-012 | Fiscal-year budget allocation is missing | D+E Budget/Report/Insights Core |
| FS-RED-013 | Append-only budget ledger is missing | D+E Budget/Report/Insights Core |
| FS-RED-014 | Frozen automatic report workflow is missing | D+E Budget/Report/Insights Core |
| FS-RED-015 | Food Service scheduled budget reports are missing | D+E Budget/Report/Insights Core |
| FS-RED-016 | Insights must be rebuilt after vendor groups and fiscal ledger | D+E Budget/Report/Insights Core |
| FS-RED-017 | Seeder does not prove final freeze/variation behavior | Final Verification/Data Proof |

## Final Implementation Sequence

### Phase 0 - Baseline Verification

Run this before modifying behavior.

Required verification:
1. Confirm `ShoppingListController::generate()` is date-driven with `start_date` and `end_date`.
2. Confirm `MenuCycle::coveringDate()` resolves cross-week spans.
3. Confirm `shopping_lists.menu_cycle_id` is absent.
4. Confirm `coverage_status` and `uncovered_dates` exist and serialize.
5. Confirm Fri-to-Mon cross-week generation works.
6. Run the backend suite.
7. Run frontend `tsc --noEmit`.

If any baseline item fails, fix that baseline regression before starting C+B or D+E.

### Phase 1 - Original Before-Defense Fixes

Build in the existing order:
1. Web role boundary (`FS-GAP-001`).
2. Report authorization guard consistency (`FS-GAP-004`).
3. Menu-cycle activation completeness (`FS-GAP-005`).
4. Active-cycle stability after cost snapshot (`FS-GAP-006`).
5. PO receiving idempotency while the old PO model still exists (`FS-GAP-013`).
6. Diet-list integrity and population basis (`FS-GAP-009`, `FS-GAP-010`).
7. Reversed service-day recovery (`FS-GAP-011`).
8. Inventory no-stock consistency (`FS-GAP-015`).
9. Existing report source gating and cash-book date fix (`FS-GAP-019`, `FS-GAP-020`).

Reason: these remove the obvious current flaws before introducing the larger redesign.

### Phase 2 - Population And Menu Cycle Authority

Build C+B Step 1.

Rules:
1. `estimate_population` is RND planning data.
2. `served_population` is FSS actual execution data.
3. The two fields never overwrite each other.
4. Shopping-list-level population is allowed only on draft menu-linked shopping lists.
5. A menu-linked draft shopping list cannot overlap another menu-linked draft shopping list on the same date.
6. Manual supplies-only lists may overlap because they do not cascade menu-cycle population.
7. Per-day estimate edits after a list-level cascade become authoritative for that day until a newer list-level cascade occurs.
8. Shopping-list generation blocks planned menu dates with menu items but missing/null/zero `estimate_population`.
9. Days with no menu items may have zero population and still allow manual/supplies-only procurement.

### Phase 3 - Shopping List Redesign

Build C+B Step 2.

Rules:
1. Replace normal shopping-list states with exactly `draft` and `converted`.
2. Remove finalize dropdowns and status editing from the normal UI.
3. RND is the only role that can generate, create, edit, or convert shopping lists.
4. Draft shopping lists have Ingredients and Supplies tabs.
5. Ingredients support auto-generated and manual lines.
6. Supplies are manual only and included in the same procurement event.
7. Converted shopping lists are read-only historical references.
8. Existing `finalized` records must be migrated or presented as legacy converted records.

### Phase 4 - PO Event Model And Vendor Groups

Build C+B Step 3.

Rules:
1. One shopping list converts into one PO.
2. Vendor grouping exists under that PO, not as separate PO records.
3. Structural item data freezes at conversion.
4. Vendor groups own OR number, receipt attachments, proof attachments, totals, and execution completeness.
5. FSS and RND can update operational vendor-group fields during open execution.
6. FSS cannot create/delete POs, edit structural data, or generate shopping lists.
7. Conversion emits a stable PO-converted event from the transaction.
8. Completion emits a stable PO-completed event after all receipts and served population requirements are met.

The PO-converted event payload must include PO ID, shopping-list ID, fiscal year/date span, vendor groups, estimated total, estimated population, and line snapshot. D+E cannot start until this event is verified.

### Phase 5 - Procurement Page And Menu Cycle List

Build C+B Steps 4 and 6.

Procurement page:
1. Level 1: procurement event list.
2. Level 2: event detail with one PO and vendor groups.
3. Level 3: vendor group detail with read-only items and operational inputs.
4. Use breadcrumbs.
5. Remove procurement settings tab.
6. Use plain text statuses, not badge pills.

Menu-cycle list:
1. Shared RND/FSS chronological list.
2. Active cycle uses a simple accent/inline label, not badge pill.
3. RND can edit non-active cycles.
4. FSS is read-only.
5. Per-day plan existence is shown per day.

### Phase 6 - PPA Auto-Generation

Build C+B Step 5.

Rules:
1. PPA planning snapshot is created in the same transaction as PO conversion.
2. Planning columns freeze at PO conversion.
3. Execution columns update during open execution and freeze when PO completes.
4. RND can view/print PPA.
5. FSS cannot access PPA.
6. PPA no longer depends on manual Reports Browser generation for the normal Food Service workflow.

Do not build the final PDF/print view until the stored PPA snapshot structure is fixed.

### Phase 7 - Fiscal-Year Budget And Ledger

Build D+E Steps 1 and 2 after C+B is green.

Rules:
1. Budget is one fiscal-year allocation per year.
2. Ledger entries are append-only and immutable.
3. Remaining balance is calculated from fiscal allocation plus ledger entries.
4. PO deduction comes from the PO-converted event.
5. If no fiscal-year allocation exists for the PO date, show a clear RND warning and do not silently skip budget impact.
6. Actual budget per head comes from PO completion and uses served population only.
7. FSS has no budget management UI.

Existing period budgets are legacy data. The implementation plan must decide whether to migrate the active year into one fiscal allocation or leave old budgets read-only.

### Phase 8 - Frozen Reports

Build D+E Step 3.

Rules:
1. Reports page reads stored snapshots only.
2. No live preview.
3. No generate button.
4. No manual archive button for the redesigned Food Service reports.
5. Budget report is generated by scheduled month-end job.
6. PPA/procurement snapshot is generated by PO conversion and completed by PO Phase 3.
7. Accomplishment report is per FSS user and freezes when the procurement span closes.
8. RND sees budget, procurement, and PPA reports.
9. FSS sees only their own accomplishment reports.

Existing live report generators can remain only as legacy/admin internals if hidden from the redesigned Food Service workflow and guarded.

### Phase 9 - Insights

Build D+E Step 4 after vendor groups, fiscal ledger, and snapshots exist.

Rules:
1. Spend by supplier uses vendor groups, not old per-supplier PO rows.
2. Procurement span impact shows full selected date range.
3. Per-head/day trend uses Phase 3 actuals only.
4. Phase 2 spans appear as pending markers rather than disappearing.
5. Budget burn uses fiscal allocation plus ledger.
6. Graphs live in Insights, not procurement settings or removed budget sections.

### Phase 10 - Seeder And Full Verification

Build D+E Step 5 last.

Rules:
1. Seeded weekly cycles vary menus and costs.
2. At least three procurement spans have different actual budget per head values.
3. Seeded data proves report freezing and graph variation.
4. `migrate:fresh --seed` remains green.

## Hard Dependencies

| Dependency | Blocks |
| --- | --- |
| Phase 0 green baseline | Every redesign phase |
| Menu activation and population authority | Shopping-list generation and cascade |
| Shopping-list `draft/converted` state | PO conversion |
| Single PO with vendor groups | Procurement page, PPA event, budget ledger, insights |
| PO-converted event | PPA planning snapshot and budget ledger auto-deduction |
| PO-completed event | Actual per-head value, PPA execution freeze, accomplishment freeze, insights actuals |
| Frozen snapshot storage | New Reports page |
| Fiscal-year ledger | Budget page and budget reports |

## Expanded Scope Guardrails

1. Do not start D+E until C+B is fully merged and green.
2. Do not build budget auto-deduction before the PO-converted event exists.
3. Do not build PPA auto-generation before the PO snapshot payload is stable.
4. Do not build final Insights graphs before vendor groups and fiscal ledger exist.
5. Do not use `served_population` for planning. It remains actual execution data.
6. Do not allow overlapping menu-linked draft shopping lists; this is the selected solution to the last-write ambiguity.
7. Do not preserve the old per-vendor PO model inside the new normal workflow. If legacy records remain, mark them legacy/read-only or migrate them.
8. Do not leave report generation half-live and half-frozen in the final Food Service UI.
9. Do not give FSS any shopping-list generation/create/edit/convert path. FSS procurement work is receipt/proof photo upload only.
10. Do not add a separate FSS receive action. Both RND and FSS can upload photos; procurement completion is automatic from evidence and served-population completion.
11. Do not require AM/PM snacks for menu-cycle readiness. Breakfast, lunch, and dinner are required for each selected service date.
12. Do not use served population to scale stock deduction. At procurement-span completion, consume the frozen planned/procured items; users manually increase inventory if leftovers remain.
13. Do not include missing catalog/no-stock items in the inventory report. Inventory reports list existing inventory records only.
14. Do not introduce RND owner/facility scoping for capstone. RND can see all Food Service records and reports.
15. Do not build overlapping period-budget behavior. The D+E model is one fiscal-year allocation per year.

## Resolved Decision Locks

| Decision | Implementation impact |
| --- | --- |
| FSS only uploads receipt/proof photos for procurement | Remove or block all FSS shopping-list generation/create/edit/convert paths; allow FSS upload endpoints on vendor groups. |
| Both RND and FSS can upload receipt/proof photos | Vendor-group operational uploads are shared; structural PO/shopping-list data remains RND-owned. |
| Menu readiness requires breakfast, lunch, and dinner | Activation and procurement-span generation must validate required meals per selected date; snacks are optional. |
| Active execution data remains editable/loggable | Served population/accomplishment entries remain allowed after activation and during procurement execution; frozen structural snapshots remain immutable. |
| Stock deduction is span-completion based | PO completion posts consumption for frozen procured items; served population is used for actual per-head/day, not deduction scaling. |
| Accomplishment entry is staff/date based | Enforce one accomplishment entry per FSS staff member per service date; store numeric diet-list count and numeric apportioned/distributed patient count. |
| Inventory report excludes missing no-stock catalog rows | Keep no-stock dashboard/list logic separate from inventory report content. |
| FSS report scope is own accomplishment only | FSS report APIs/UI must scope to authenticated staff's accomplishment reports; RND can see all Food Service reports and all staff accomplishments. |
| Budget overlap is eliminated | Replace overlapping period budgets with one fiscal-year allocation per year. |
| RND broad access is acceptable | Do not spend capstone scope on full RND owner/facility policy architecture. |

## Expanded Testing Strategy

| Phase | Required verification |
| --- | --- |
| Phase 0 | Existing backend suite, frontend `tsc --noEmit`, date-driven generation, coverage metadata, cross-week generation. |
| Phase 1 | Role guard, report guard, activation, receiving, diet-list, reversal, no-stock, report source gating. |
| Phase 2 | Population cascade, per-day override, last-write-wins, no overlap, estimate/served isolation, breakfast/lunch/dinner readiness, procurement-span missing-plan guard. |
| Phase 3 | `draft/converted` state machine, RND-only generation, FSS blocked from all shopping-list writes, supplies tab, manual and auto ingredient lines, converted read-only behavior. |
| Phase 4 | Single PO creation, vendor groups, frozen structural data, receipt/proof uploads editable by RND/FSS, no FSS receive action, event payload, FSS restrictions. |
| Phase 5 | Procurement event API/UI levels, breadcrumbs, no settings tab, menu-cycle list chronology, active indicator, FSS read-only. |
| Phase 6 | PPA created at conversion, planning snapshot frozen, execution columns update/freeze, FSS blocked. |
| Phase 7 | Fiscal-year allocation, no overlapping period budgets, immutable ledger, auto-deduction, no fiscal-year warning, remaining balance, FSS budget UI absence. |
| Phase 8 | Snapshot-only reports, no live preview/generate/archive in final UI, scheduled budget report, FSS own accomplishment scope, RND all-report/all-staff accomplishment scope. |
| Phase 9 | Vendor-group spend-by-supplier, full-range chart data, pending Phase 2 markers, fiscal-year budget burn. |
| Phase 10 | Seeder variation, at least three distinct actual per-head values, `migrate:fresh --seed`, full regression suite. |

## Expanded Defense Story

After the expanded C+B/D+E plan, the Food Service module can be defended as:

1. A complete RND planning workflow that produces activated menu cycles and draft procurement events.
2. A procurement workflow where one shopping list converts into one immutable procurement-event PO with vendor groups.
3. An FSS execution workflow where staff upload proof and record actual served population without changing planning estimates.
4. A budget workflow where fiscal-year allocation and immutable ledger entries explain remaining balance.
5. A reports workflow where Food Service reports are frozen snapshots created by operational events or schedules.
6. An insights workflow that reads stable procurement, ledger, and actual-per-head sources.

This is the logical end-state needed to address the obvious flaws found during testing. The old minimal defense plan remains the first phase, not the final destination.
