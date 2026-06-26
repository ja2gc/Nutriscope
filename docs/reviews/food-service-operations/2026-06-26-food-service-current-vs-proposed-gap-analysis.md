# Document 3 - Food Service Current vs Proposed Gap Analysis

Date: 2026-06-26  
Source of truth:
- `docs/reviews/food-service-operations/2026-06-26-food-service-workflow-deep-audit.md`
- `docs/reviews/food-service-operations/2026-06-26-food-service-workflow-audit.md`

This document compares the AS-IS Food Service workflow to the TO-BE workflow from Document 2. It does not implement, prescribe code, or redesign beyond the TO-BE workflow already documented.

## Priority Definitions

| Priority | Meaning |
|---|---|
| Must Fix Before Defense | Affects operational correctness, data integrity, report integrity, security/permission boundaries, or workflow completion. |
| Should Fix Before Defense | Improves usability, consistency, or workflow enforcement but has a defensible workaround. |
| Can Wait Until After Defense | Primarily architectural, advanced workflow management, nice-to-have, or future scalability. |

## Gap Index

| Gap ID | Title | Primary section | Priority |
| --- | --- | --- | --- |
| FS-GAP-001 | FSS can see RND web pages and navigation | Permission Gaps | Must Fix Before Defense |
| FS-GAP-002 | FSS can generate shopping lists through API | Shopping List Gaps | Should Fix Before Defense |
| FS-GAP-003 | FSS can read budget and insights APIs outside intended FSS scope | Permission Gaps | Should Fix Before Defense |
| FS-GAP-004 | FSS report guard is inconsistent across report actions | Report Gaps | Must Fix Before Defense |
| FS-GAP-005 | Incomplete menu cycles can be activated | Menu Cycle Gaps | Must Fix Before Defense |
| FS-GAP-006 | Active menu cycles can change after cost snapshot | Menu Planning Gaps | Must Fix Before Defense |
| FS-GAP-007 | Template-created cycles lose population values | Menu Planning Gaps | Can Wait Until After Defense |
| FS-GAP-008 | Recipe and item costs lack a clear reporting version boundary | Recipe Scaling Gaps | Should Fix Before Defense |
| FS-GAP-009 | Diet-list counts can duplicate or contradict actual work state | Population Handling Gaps | Must Fix Before Defense |
| FS-GAP-010 | Served population and consumption basis can diverge | Population Handling Gaps | Must Fix Before Defense |
| FS-GAP-011 | Reversed service day cannot be completed again | Population Handling Gaps | Must Fix Before Defense |
| FS-GAP-012 | Shopping lists can be finalized outside approval flow | Shopping List Gaps | Should Fix Before Defense |
| FS-GAP-013 | Purchase order status lifecycle can produce invalid receiving | Purchase Order Gaps | Must Fix Before Defense |
| FS-GAP-014 | FSS proof upload does not complete receiving handoff | Procurement Gaps | Should Fix Before Defense |
| FS-GAP-015 | Inventory no-stock numbers disagree between dashboard and inventory list | Data Integrity Gaps | Should Fix Before Defense |
| FS-GAP-016 | Manual inventory adjustments are not distinguished from workflow-derived stock | Procurement Gaps | Can Wait Until After Defense |
| FS-GAP-017 | Budget period validation and overlap rules are weak | Budget Gaps | Should Fix Before Defense |
| FS-GAP-018 | Budget actuals and manual logs have unclear origin and UI coverage | Budget Gaps | Should Fix Before Defense |
| FS-GAP-019 | Report availability allows incomplete, draft, or blank source data | Report Gaps | Must Fix Before Defense |
| FS-GAP-020 | Dietary cash book uses inconsistent date basis for availability vs generation | Report Gaps | Should Fix Before Defense |
| FS-GAP-021 | Accomplishment report capture and report access are not aligned | Accomplishment Report Gaps | Should Fix Before Defense |
| FS-GAP-022 | Insights scope is inconsistent across API and UI | Insights Gaps | Can Wait Until After Defense |
| FS-GAP-023 | Clinical population and Food Service population are not explicitly separated | Cross-Module Dependency Gaps | Can Wait Until After Defense |
| FS-GAP-024 | Orphaned or API-only Food Service features remain visible in architecture | Dead-end and Orphaned Feature Gaps | Can Wait Until After Defense |
| FS-GAP-025 | Food Service authorization is route-based without model policy depth | Permission Gaps | Can Wait Until After Defense |

## Expanded C+B / D+E Redesign Gap Index

These gaps are added after reconciling the C+B and D+E redesign prompts against the audit and actual codebase. They extend the implementation roadmap; they do not replace `FS-GAP-001` through `FS-GAP-025`.

| Gap ID | Title | Current behavior | Proposed behavior | Priority |
| --- | --- | --- | --- | --- |
| FS-RED-001 | Shopping-list population authority is missing | Population exists on menu-cycle days and meal-prep logs, not shopping lists. | Add draft shopping-list-level `estimate_population` with timestamp authority and per-day override semantics. | Must Fix In Redesign |
| FS-RED-002 | Overlapping menu-linked draft shopping lists are ambiguous | Separate draft lists can cover the same menu dates. | Reject overlapping menu-linked draft lists for the same date; allow supplies-only overlap. | Must Fix In Redesign |
| FS-RED-003 | Shopping-list state model conflicts with conversion workflow | Current states are `draft/finalized`. | Replace normal workflow with `draft/converted`. | Must Fix In Redesign |
| FS-RED-004 | Supplies procurement is incomplete | Supplies tab and supplies-to-PO conversion are not verified as complete. | Add supplies list tab and include supplies in vendor grouping. | Must Fix In Redesign |
| FS-RED-005 | Current PO model is one PO per supplier | Approval creates multiple POs by supplier. | Convert one shopping list into one PO with vendor groups. | Must Fix In Redesign |
| FS-RED-006 | Vendor-group execution model is missing | OR numbers and attachments belong to PO rows. | Add vendor groups with OR number, receipt/proof attachments, totals, and status text. | Must Fix In Redesign |
| FS-RED-007 | PO lifecycle phases are missing | PO status is `draft/ordered/received`. | Use Draft Shopping List, Open Execution PO, Completed/Archived PO. | Must Fix In Redesign |
| FS-RED-008 | PO conversion event hook is missing | No reusable event drives PPA and budget ledger. | Dispatch stable PO-converted and PO-completed events. | Must Fix In Redesign |
| FS-RED-009 | Actual budget per head per procurement event is missing | Cost efficiency is derived from old POs/logs and can be pending. | Calculate actual per-head only at PO Phase 3 using served population. | Must Fix In Redesign |
| FS-RED-010 | Procurement page is not event-based | Current UI is tabbed list/PO/supplier editing. | Use event list -> PO vendor groups -> vendor group detail. | Must Fix In Redesign |
| FS-RED-011 | PPA is manual/menu-cycle report, not auto-generated procurement snapshot | PPA is selected through Reports Browser. | Auto-create PPA planning snapshot at PO conversion and freeze execution at completion. | Must Fix In Redesign |
| FS-RED-012 | Fiscal-year budget allocation is missing | Current budgets are period records. | Add one fiscal-year allocation per year. | D+E Dependency |
| FS-RED-013 | Append-only budget ledger is missing | Adjustments/daily logs are not fiscal-year immutable ledger source of truth. | Add immutable ledger entries for PO deductions and manual adjustments. | D+E Dependency |
| FS-RED-014 | Frozen automatic report workflow is missing | Reports are browsed/rendered/archived manually and can query live data. | Store snapshots automatically from trigger events; report pages read snapshots only. | D+E Dependency |
| FS-RED-015 | Food Service scheduled budget reports are missing | No Food Service scheduled report job exists. | Add monthly budget report generation after snapshot model exists. | D+E Dependency |
| FS-RED-016 | Insights must be rebuilt after vendor groups and fiscal ledger | Current insights use received POs and current budget summaries. | Use vendor groups, procurement spans, fiscal ledger, and Phase 3 actuals. | D+E Dependency |
| FS-RED-017 | Seeder does not prove final freeze/variation behavior | Current seeder targets old PO/report model. | Seed varied cycles/procurement spans after final schema exists. | Final Verification |

## 1. Menu Cycle Gaps

### FS-GAP-005 - Incomplete menu cycles can be activated

- **Priority:** Must Fix Before Defense
- **Current Behavior:** RND can activate a menu cycle even when the cycle has missing day/meal slots or missing estimated populations.
- **Proposed Behavior:** Activation represents a defensible handoff to FSS and should only occur for cycles complete enough to support execution, procurement, and reports.
- **Why the Gap Exists:** The controller changes status and active flags but does not enforce menu-grid completeness or population readiness before activation.
- **Operational Impact (RND or FSS workflow):** RND can hand off an incomplete plan; FSS may see an active menu that cannot support daily service or shopping-list generation.
- **Data Integrity Impact:** Active records can exist without the data needed by downstream services.
- **Reporting Impact:** PPA/menu reports can be generated from a cycle that does not represent a full service plan.
- **UX Impact:** Users may believe activation means the plan is ready, while later procurement/service steps fail or skip days.
- **Migration Impact:** Existing active cycles may need to be reviewed or treated as legacy incomplete records.
- **Risk:** High; affects workflow completion and report credibility.
- **Effort:** Medium.

## 2. Menu Planning Gaps

### FS-GAP-006 - Active menu cycles can change after cost snapshot

- **Priority:** Must Fix Before Defense
- **Current Behavior:** Activation creates a cost snapshot, but RND can still edit the active cycle's day rows afterward.
- **Proposed Behavior:** The menu rows and cost basis used for service/reporting should stay stable after activation, or changes should be clearly treated as a new plan/version.
- **Why the Gap Exists:** The update route remains available for active cycles and the database does not enforce active-cycle immutability.
- **Operational Impact (RND or FSS workflow):** FSS may execute against changed menu rows after procurement or reporting assumptions were frozen.
- **Data Integrity Impact:** Frozen cost data can diverge from current menu rows.
- **Reporting Impact:** PPA and menu reports can show content that no longer matches the frozen cost snapshot.
- **UX Impact:** RND may not understand which values are live and which values are frozen.
- **Migration Impact:** Existing active cycles with post-activation edits may be difficult to distinguish.
- **Risk:** High; affects report integrity and demo defensibility.
- **Effort:** Medium.

### FS-GAP-007 - Template-created cycles lose population values

- **Priority:** Can Wait Until After Defense
- **Current Behavior:** Menu-cycle template instantiation copies recipe/item/quantity data but does not carry estimated population.
- **Proposed Behavior:** Template-created cycles should either carry intended population defaults or clearly require RND to enter population before activation/procurement.
- **Why the Gap Exists:** Template day data does not preserve population in the instantiation path.
- **Operational Impact (RND or FSS workflow):** RND must re-enter population before costing and procurement work cleanly.
- **Data Integrity Impact:** Draft cycles can look filled but still lack demand quantities.
- **Reporting Impact:** Reports and shopping-list generation may skip or understate cycles with missing population.
- **UX Impact:** RND can miss that template-derived cycles still need population entry.
- **Migration Impact:** Existing templates may not contain population data.
- **Risk:** Low to medium; workaround is manual population entry.
- **Effort:** Low to medium.

## 3. Recipe Scaling Gaps

### FS-GAP-008 - Recipe and item costs lack a clear reporting version boundary

- **Priority:** Should Fix Before Defense
- **Current Behavior:** Recipe and item costs update as catalog/receiving data changes, while activated cycles rely on a cost snapshot for reporting.
- **Proposed Behavior:** Planning costs may remain live, but activated/reporting costs should have a clear, explainable boundary.
- **Why the Gap Exists:** Cost recalculation and cost snapshot behavior coexist without a visible lifecycle explanation in the workflow.
- **Operational Impact (RND or FSS workflow):** RND may see live costs while reports use frozen values.
- **Data Integrity Impact:** The same recipe/item can appear to have different values depending on context.
- **Reporting Impact:** Defense questions may arise if displayed planning cost differs from report cost.
- **UX Impact:** Users may not know whether they are looking at current cost or report cost.
- **Migration Impact:** No major data migration is implied if existing snapshots are kept as historical values.
- **Risk:** Medium.
- **Effort:** Low to medium.

## 4. Population Handling Gaps

### FS-GAP-009 - Diet-list counts can duplicate or contradict actual work state

- **Priority:** Must Fix Before Defense
- **Current Behavior:** Multiple diet-list count rows can be recorded for the same staff/date/ward context, and off-duty rows can still carry population/task values.
- **Proposed Behavior:** Accomplishment/diet-list rows should represent one defensible work entry per intended context and avoid contradictory off-duty/population states.
- **Why the Gap Exists:** The request and database do not enforce uniqueness or off-duty consistency rules.
- **Operational Impact (RND or FSS workflow):** FSS can accidentally inflate actual headcount or create contradictory accomplishment rows.
- **Data Integrity Impact:** Served population totals can be wrong.
- **Reporting Impact:** Accomplishment reports and budget per-head metrics can be inaccurate.
- **UX Impact:** Staff can submit entries that look accepted but make totals questionable.
- **Migration Impact:** Existing duplicate rows may need review if reports are regenerated.
- **Risk:** High; affects actual population and reports.
- **Effort:** Medium.

### FS-GAP-010 - Served population and consumption basis can diverge

- **Priority:** Must Fix Before Defense
- **Current Behavior:** Complete-day can record a population override, but ingredient usage is computed from menu-cycle day estimates. Served population may also be missing if diet-list rows do not exist.
- **Proposed Behavior:** The workflow should clearly distinguish planned population, actual served population, and the quantity basis used for stock deduction.
- **Why the Gap Exists:** ConsumptionService records one population value while MenuCycleCostService usage calculation reads per-day estimates.
- **Operational Impact (RND or FSS workflow):** FSS can record a service count that does not match consumed quantities.
- **Data Integrity Impact:** Consumption lines and served population can describe different operational realities.
- **Reporting Impact:** Budget per-head and variance reporting can be wrong or pending.
- **UX Impact:** Users may believe entered population drove the stock deduction when it did not.
- **Migration Impact:** Historical meal-prep logs may need to be interpreted according to existing behavior.
- **Risk:** High.
- **Effort:** Medium.

### FS-GAP-011 - Reversed service day cannot be completed again

- **Priority:** Must Fix Before Defense
- **Current Behavior:** Reversing a meal-prep log restores stock and marks the log reversed, but the unique `(menu_cycle_id, service_date)` key blocks a new completion row for the same day.
- **Proposed Behavior:** A mistaken completion should have a recoverable correction path.
- **Why the Gap Exists:** The reversal state shares the same unique key as completed logs and complete-day inserts a new log.
- **Operational Impact (RND or FSS workflow):** FSS can get stuck after reversing a day by mistake.
- **Data Integrity Impact:** The system can preserve a reversed state without a way to record the corrected completed state.
- **Reporting Impact:** Budget actuals and service reports can miss a real service day.
- **UX Impact:** User sees reversal succeed, then cannot finish the corrected workflow.
- **Migration Impact:** Existing reversed logs may need handling.
- **Risk:** High; direct workflow completion failure.
- **Effort:** Medium.

## 5. Shopping List Gaps

### FS-GAP-002 - FSS can generate shopping lists through API

- **Priority:** Should Fix Before Defense
- **Current Behavior:** The shopping-list generation endpoint is shared by FSS and RND.
- **Proposed Behavior:** Procurement planning artifacts should be generated by RND as part of planning, while FSS executes against issued POs/proof upload.
- **Why the Gap Exists:** The route is in the shared FSS/RND group instead of the nested RND-only group.
- **Operational Impact (RND or FSS workflow):** FSS can create suggested procurement records outside the intended handoff.
- **Data Integrity Impact:** Shopping-list records may exist without clear RND planning ownership.
- **Reporting Impact:** Procurement reports could include artifacts originated outside the intended role story.
- **UX Impact:** Direct API behavior conflicts with the visible FSS mobile scope.
- **Migration Impact:** Existing FSS-created lists may need to be identified if ownership matters.
- **Risk:** Medium.
- **Effort:** Low.

### FS-GAP-012 - Shopping lists can be finalized outside approval flow

- **Priority:** Should Fix Before Defense
- **Current Behavior:** RND can update shopping-list status directly to finalized, separate from the approval action that creates POs.
- **Proposed Behavior:** Finalized/converted should mean the list has passed the RND conversion path and created or linked procurement records.
- **Why the Gap Exists:** Status update validation allows finalized without coupling it to conversion semantics.
- **Operational Impact (RND or FSS workflow):** RND can create finalized lists with no matching PO handoff.
- **Data Integrity Impact:** List status may not reflect procurement state.
- **Reporting Impact:** Procurement traceability from list to PO can be incomplete.
- **UX Impact:** Users may think finalized means ready for procurement when no PO exists.
- **Migration Impact:** Existing finalized lists without POs may need review.
- **Risk:** Medium.
- **Effort:** Low to medium.

## 6. Purchase Order Gaps

### FS-GAP-013 - Purchase order status lifecycle can produce invalid receiving

- **Priority:** Must Fix Before Defense
- **Current Behavior:** RND can mark a PO received without an enforced ordered state or proof attachment, and status cycling can cause receiving logic to run more than once.
- **Proposed Behavior:** PO receiving should have a defensible single transition that updates inventory exactly once.
- **Why the Gap Exists:** The update request accepts broad status changes, and ReceivingService runs when previous status is not `received`.
- **Operational Impact (RND or FSS workflow):** Inventory can be updated before expected procurement evidence exists, or updated twice after status cycling.
- **Data Integrity Impact:** Stock quantities and item costs can become overstated.
- **Reporting Impact:** Procurement, inventory, budget, and cash-book reports can show inflated or premature values.
- **UX Impact:** Users can make a status change that appears normal but has irreversible inventory effects.
- **Migration Impact:** Existing received POs may need checking if status cycling occurred.
- **Risk:** High.
- **Effort:** Medium.

## 7. Procurement Gaps

### FS-GAP-014 - FSS proof upload does not complete receiving handoff

- **Priority:** Should Fix Before Defense
- **Current Behavior:** FSS can upload receipt/proof attachments, but inventory and budget effects only happen when RND marks the PO received.
- **Proposed Behavior:** The handoff should be explicit: RND/FSS proof upload is execution evidence, there is no FSS receive action, and the expanded workflow completes the procurement span automatically once evidence and served-population requirements are complete.
- **Why the Gap Exists:** Attachment upload and PO receiving are separate controller actions owned by different route permissions.
- **Operational Impact (RND or FSS workflow):** FSS can complete visible procurement work, but stock remains unchanged until RND takes a separate action.
- **Data Integrity Impact:** Attachments can exist on POs that are not reflected in inventory.
- **Reporting Impact:** Budget and procurement reports should depend on completed procurement-span events, not on proof upload alone.
- **UX Impact:** FSS may think uploading a receipt completes procurement.
- **Migration Impact:** Existing attached-but-not-received POs may need clear presentation.
- **Risk:** Medium.
- **Effort:** Low to medium.

### FS-GAP-016 - Manual inventory adjustments are not distinguished from workflow-derived stock

- **Priority:** Can Wait Until After Defense
- **Current Behavior:** FSS/RND can manually create/update/restock inventory rows, while receiving and consumption also change inventory.
- **Proposed Behavior:** Procurement-derived, consumption-derived, and manual adjustment origins should be distinguishable in a mature workflow.
- **Why the Gap Exists:** Inventory stores current quantity and notes but does not use a full stock movement ledger for every change source.
- **Operational Impact (RND or FSS workflow):** Staff can correct stock, but historical origin is limited.
- **Data Integrity Impact:** Current stock can be valid while audit trail is weak.
- **Reporting Impact:** Inventory report shows current state, not complete movement history.
- **UX Impact:** Users cannot easily explain why stock changed unless notes are maintained.
- **Migration Impact:** A movement-ledger model would require historical data decisions.
- **Risk:** Low to medium.
- **Effort:** High.

## 8. Budget Gaps

### FS-GAP-017 - Budget period validation and overlap rules are weak

- **Priority:** Should Fix Before Defense
- **Current Behavior:** Budget request validation allows nullable period dates while the database requires them, and overlapping budget periods are not blocked.
- **Proposed Behavior:** The minimum path should require valid, explicit, unambiguous budget dates; the final D+E workflow should remove overlapping period budgets by using one fiscal-year allocation per year.
- **Why the Gap Exists:** Request validation and database constraints are not fully aligned; overlap rules are not encoded.
- **Operational Impact (RND or FSS workflow):** RND can create confusing budget contexts or hit database failure behavior.
- **Data Integrity Impact:** Budget coverage can be ambiguous.
- **Reporting Impact:** Budget reports and daily planned caps can be difficult to defend when periods overlap.
- **UX Impact:** Users may get late failures or unclear summaries.
- **Migration Impact:** Existing overlapping budgets may need business interpretation.
- **Risk:** Medium.
- **Effort:** Low to medium.

### FS-GAP-018 - Budget actuals and manual logs have unclear origin and UI coverage

- **Priority:** Should Fix Before Defense
- **Current Behavior:** Budget actuals are derived from meal-prep logs when available or purchases otherwise; manual daily logs exist through backend API but no clear reviewed UI entry point was found.
- **Proposed Behavior:** Budget summaries should clearly present which source is driving actuals, and any manual actual source should have an intentional UI story or be out of demo scope.
- **Why the Gap Exists:** BudgetActualService supports multiple sources, while frontend coverage is partial.
- **Operational Impact (RND or FSS workflow):** RND may not know why actuals changed or why manual logs are unavailable in UI.
- **Data Integrity Impact:** Actual totals can mix derived and manual sources without strong user-facing explanation.
- **Reporting Impact:** Budget report values can be questioned if source is not clear.
- **UX Impact:** Budget page may expose results without enough source context.
- **Migration Impact:** Existing daily logs, if any, may need source labels.
- **Risk:** Medium.
- **Effort:** Low to medium.

## 9. Report Gaps

### FS-GAP-004 - FSS report guard is inconsistent across report actions

- **Priority:** Must Fix Before Defense
- **Current Behavior:** FSS is restricted to accomplishment report for many report actions, but `generateAll` lacks the same guard and show/download/view do not apply the FSS type guard.
- **Proposed Behavior:** Every report action should enforce the same role/type scope.
- **Why the Gap Exists:** Deprecated/shared report actions use different guard paths than render/archive/store.
- **Operational Impact (RND or FSS workflow):** FSS can bypass intended report scope through direct API behavior.
- **Data Integrity Impact:** Report ownership can include records a role should not have produced.
- **Reporting Impact:** Non-accomplishment Food Service reports can be generated by FSS, weakening defense of report roles.
- **UX Impact:** Visible UI may look restricted while API behavior is not.
- **Migration Impact:** Existing FSS-generated non-accomplishment reports may need review or exclusion.
- **Risk:** High.
- **Effort:** Low to medium.

### FS-GAP-019 - Report availability allows incomplete, draft, or blank source data

- **Priority:** Must Fix Before Defense
- **Current Behavior:** PPA/menu reports can use draft/incomplete cycles, budget report can render with minimal budget data, inventory report is always available even when blank, and some direct generator paths allow weaker source status.
- **Proposed Behavior:** Report availability should match defensible source data for each report type.
- **Why the Gap Exists:** ReportBrowser source checks are permissive and report generators validate different minimums.
- **Operational Impact (RND or FSS workflow):** Users can generate reports before completing the workflow the report implies.
- **Data Integrity Impact:** Reports can formalize incomplete operational data.
- **Reporting Impact:** Defense output can contain blank or logically incomplete reports.
- **UX Impact:** Users may assume a listed report instance is ready and valid.
- **Migration Impact:** Existing archived reports may need source-state review if they were generated from incomplete data.
- **Risk:** High.
- **Effort:** Medium.

### FS-GAP-020 - Dietary cash book uses inconsistent date basis for availability vs generation

- **Priority:** Should Fix Before Defense
- **Current Behavior:** ReportBrowser checks received purchase orders by `order_date`, while the generator uses `COALESCE(received_date, order_date)`.
- **Proposed Behavior:** Availability and generation should use the same date basis.
- **Why the Gap Exists:** Browser source and generator query logic diverged.
- **Operational Impact (RND or FSS workflow):** RND may not see an available period even when received POs belong there by generator logic.
- **Data Integrity Impact:** Period inclusion can be inconsistent.
- **Reporting Impact:** Cash book coverage can omit valid received procurement data from the browse step.
- **UX Impact:** Users see confusing report availability.
- **Migration Impact:** None beyond interpreting existing period reports.
- **Risk:** Medium.
- **Effort:** Low.

## 10. PPA Gaps

PPA-specific gaps are covered by:
- FS-GAP-005 because inactive or incomplete menu cycles can become report sources.
- FS-GAP-006 because menu rows can diverge from frozen costs after activation.
- FS-GAP-019 because report availability does not require a fully defensible report source.

No separate PPA gap is added because the PPA issue is the report-facing result of menu-cycle lifecycle and report-availability gaps.

## 11. Accomplishment Report Gaps

### FS-GAP-021 - Accomplishment report capture and report access are not aligned

- **Priority:** Should Fix Before Defense
- **Current Behavior:** FSS can record diet-list/accomplishment data through mobile Prep, RND can render reports through web Reports Browser, and FSS can call backend report endpoints directly, but no normal FSS report UI was found.
- **Proposed Behavior:** The accomplishment report workflow should have a clear owner and visible access path consistent with the final scope.
- **Why the Gap Exists:** Data capture and report rendering were implemented on different surfaces with no FSS report browser.
- **Operational Impact (RND or FSS workflow):** FSS can create source data but may not complete the report workflow from visible UI.
- **Data Integrity Impact:** Source data exists, but duplicate diet-list rows remain a related integrity issue.
- **Reporting Impact:** Accomplishment report is real, but presentation of who generates it is unclear.
- **UX Impact:** FSS users may not find a way to view/download their own report.
- **Migration Impact:** Existing diet-list counts remain valid source rows.
- **Risk:** Medium.
- **Effort:** Low to medium.

## 12. Insights Gaps

### FS-GAP-022 - Insights scope is inconsistent across API and UI

- **Priority:** Can Wait Until After Defense
- **Current Behavior:** Insights endpoints allow FSS/RND, RND web budget page shows insights, and FSS mobile has no insights tab. Secondary FSS docs say insights are not FSS scope.
- **Proposed Behavior:** Insights should have one clear role scope and visible surface.
- **Why the Gap Exists:** API routes are shared while frontend/mobile scope is narrower.
- **Operational Impact (RND or FSS workflow):** Analytics ownership is unclear.
- **Data Integrity Impact:** Read-only; no direct data mutation.
- **Reporting Impact:** Insights are not formal reports, but they can influence explanation of budget and procurement data.
- **UX Impact:** FSS direct API access and visible UI do not match.
- **Migration Impact:** None.
- **Risk:** Low.
- **Effort:** Low.

## 13. Permission Gaps (RND/FSS Role Boundary Gaps Specifically)

### FS-GAP-001 - FSS can see RND web pages and navigation

- **Priority:** Must Fix Before Defense
- **Current Behavior:** Web middleware/layout checks authentication but not RND role, and sidebar gives every non-admin user RND navigation.
- **Proposed Behavior:** FSS should not be presented with RND planning/admin pages.
- **Why the Gap Exists:** Frontend role gating is incomplete.
- **Operational Impact (RND or FSS workflow):** FSS can reach planning screens that fail on backend writes or expose shared reads.
- **Data Integrity Impact:** Backend still blocks many writes, but shared endpoints can be accessed through inappropriate UI context.
- **Reporting Impact:** FSS may reach report/planning pages outside the intended defense story.
- **UX Impact:** High confusion and demo risk from 403s or wrong-role screens.
- **Migration Impact:** None.
- **Risk:** High.
- **Effort:** Low to medium.

### FS-GAP-003 - FSS can read budget and insights APIs outside intended FSS scope

- **Priority:** Should Fix Before Defense
- **Current Behavior:** Budget list/show/summary and insights endpoints are shared by FSS/RND, although secondary FSS scope treats budget and insights as RND-owned.
- **Proposed Behavior:** Final scope should clearly decide whether FSS can read these endpoints; visible UI and backend access should match.
- **Why the Gap Exists:** Shared `/api/fss` route group exposes read endpoints broadly.
- **Operational Impact (RND or FSS workflow):** FSS can read planning/analytics information beyond execution workflow.
- **Data Integrity Impact:** Read-only; no direct data mutation.
- **Reporting Impact:** Budget/insight data visibility may conflict with role story.
- **UX Impact:** Mobile omits these screens while API allows them; web role leak can expose them.
- **Migration Impact:** None.
- **Risk:** Medium.
- **Effort:** Low.

### FS-GAP-025 - Food Service authorization is route-based without model policy depth

- **Priority:** Can Wait Until After Defense
- **Current Behavior:** Role checks are mostly route-level; no Food Service model policy layer was found.
- **Proposed Behavior:** Mature authorization would combine route role checks with record-level policy rules where ownership or scope matters.
- **Why the Gap Exists:** Controllers and routes currently rely on role middleware and broad shared access patterns.
- **Operational Impact (RND or FSS workflow):** Broad RND/FSS access is simpler but less precise.
- **Data Integrity Impact:** No direct mutation gap if route checks are correct, but record-level boundaries are weak.
- **Reporting Impact:** Report ownership and cross-user access rules rely on controller logic.
- **UX Impact:** Users may see broad records rather than scoped records.
- **Migration Impact:** Policy introduction could require ownership assumptions for existing data.
- **Risk:** Low to medium for capstone; higher for production.
- **Effort:** High.

## 14. Data Integrity Gaps

### FS-GAP-015 - Inventory no-stock numbers disagree between dashboard and inventory list

- **Priority:** Should Fix Before Defense
- **Current Behavior:** Dashboard no-stock count checks existing inventory rows with quantity at or below zero; inventory rows endpoint also treats catalog items with no inventory row as no-stock.
- **Proposed Behavior:** Dashboard and inventory list should use the same no-stock definition.
- **Why the Gap Exists:** Dashboard service and inventory rows query use different counting logic.
- **Operational Impact (RND or FSS workflow):** FSS may see a dashboard count that does not match inventory list.
- **Data Integrity Impact:** Underlying stock rows are unchanged, but displayed aggregate is inconsistent.
- **Reporting Impact:** Inventory report/dashboard comparison can be challenged.
- **UX Impact:** Users lose confidence in dashboard KPIs.
- **Migration Impact:** None.
- **Risk:** Medium.
- **Effort:** Low.

## 15. Cross-Module Dependency Gaps (Clinical Care Touchpoints)

### FS-GAP-023 - Clinical population and Food Service population are not explicitly separated

- **Priority:** Can Wait Until After Defense
- **Current Behavior:** Food Service uses RND menu estimates and FSS diet-list counts. Clinical reports and patient menu planning use separate clinical records. The relationship is not explicitly explained in workflow/data boundaries.
- **Proposed Behavior:** Planned Food Service population, actual served population, and clinical census/patient menu population should be explicitly separated in documentation and UI language.
- **Why the Gap Exists:** Food Service and Clinical Care modules have separate data sources and report types.
- **Operational Impact (RND or FSS workflow):** Users may confuse census/admission population with Food Service planned or served population.
- **Data Integrity Impact:** No direct database conflict, but numbers can be compared incorrectly.
- **Reporting Impact:** Defense questions may challenge why report populations differ across modules.
- **UX Impact:** Labels may not fully explain the source of each population figure.
- **Migration Impact:** None.
- **Risk:** Low to medium.
- **Effort:** Low.

## 16. Dead-end and Orphaned Feature Gaps

### FS-GAP-024 - Orphaned or API-only Food Service features remain visible in architecture

- **Priority:** Can Wait Until After Defense
- **Current Behavior:** Event allocation is supported in backend but not normally authorable in reviewed UI; manual budget daily logs exist through API/service without clear UI; no Food Service scheduled job exists; cleaning-log routes are intentionally removed/404.
- **Proposed Behavior:** API-only and removed features should either be intentionally hidden from the defense story or given a clear user-facing path in a later scope.
- **Why the Gap Exists:** Some backend capabilities are ahead of or separate from current frontend/demo scope.
- **Operational Impact (RND or FSS workflow):** Users cannot complete these feature flows through normal UI.
- **Data Integrity Impact:** API-only data can exist without a visible maintenance path.
- **Reporting Impact:** Event allocations and manual logs can affect reports only when data is inserted through non-obvious paths.
- **UX Impact:** Feature availability is inconsistent across backend and frontend.
- **Migration Impact:** Future activation of these features may require data cleanup or UI decisions.
- **Risk:** Low for defense if left out of the demo path.
- **Effort:** Medium to high depending on feature.

## Prioritization Matrix

| Priority | Gaps |
| --- | --- |
| Must Fix Before Defense | FS-GAP-001, FS-GAP-004, FS-GAP-005, FS-GAP-006, FS-GAP-009, FS-GAP-010, FS-GAP-011, FS-GAP-013, FS-GAP-015, FS-GAP-019, FS-GAP-020 |
| Should Fix Before Defense | FS-GAP-002, FS-GAP-003, FS-GAP-008, FS-GAP-012, FS-GAP-014, FS-GAP-017, FS-GAP-018, FS-GAP-021 |
| Can Wait Until After Defense | FS-GAP-007, FS-GAP-016, FS-GAP-022, FS-GAP-023, FS-GAP-024, FS-GAP-025 |

## Expanded Redesign Prioritization Matrix

| Redesign priority | Gaps |
| --- | --- |
| C+B Foundation | FS-RED-001, FS-RED-002, FS-RED-003, FS-RED-004 |
| C+B Procurement Event Core | FS-RED-005, FS-RED-006, FS-RED-007, FS-RED-008, FS-RED-009, FS-RED-010, FS-RED-011 |
| D+E Budget/Report/Insights Core | FS-RED-012, FS-RED-013, FS-RED-014, FS-RED-015, FS-RED-016 |
| Final Verification/Data Proof | FS-RED-017 |

Ordering rule: D+E gaps must not start until C+B Procurement Event Core is implemented and verified, because budget ledger, frozen reports, and final insights need the new PO/vendor-group event payload.

## Minimum Changes Required

Smallest set needed to make the Food Service workflow operationally defensible, report-safe, and demo-safe:

1. Block FSS from RND web navigation/pages so the visible role boundary matches the defense story (`FS-GAP-001`).
2. Apply consistent report authorization to all FSS report actions and retrieval paths (`FS-GAP-004`).
3. Require menu-cycle completeness before activation (`FS-GAP-005`).
4. Prevent active/reporting menu cycles from silently diverging from their activation cost snapshot (`FS-GAP-006`).
5. Prevent duplicate or contradictory diet-list/accomplishment rows from corrupting actual population (`FS-GAP-009`).
6. Make service-day population and stock consumption behavior consistent and explainable (`FS-GAP-010`).
7. Make reversed service days recoverable so FSS can complete corrected service logs (`FS-GAP-011`).
8. Make PO receiving a valid, single-effect transition that cannot double-add stock (`FS-GAP-013`).
9. Align dashboard no-stock count with the inventory list's no-stock definition (`FS-GAP-015`).
10. Restrict report availability to source data that supports the report's meaning (`FS-GAP-019`).
11. Align dietary cash book browse availability and generation date basis (`FS-GAP-020`).

Expanded redesign minimum after the defense fixes:

1. Add shopping-list-level population authority with overlap protection (`FS-RED-001`, `FS-RED-002`).
2. Replace `draft/finalized` with `draft/converted` for the normal shopping-list workflow (`FS-RED-003`).
3. Add complete ingredients/supplies shopping-list flow before PO conversion (`FS-RED-004`).
4. Replace vendor-split POs with one procurement-event PO and vendor groups (`FS-RED-005`, `FS-RED-006`).
5. Add explicit PO lifecycle phases and conversion/completion events (`FS-RED-007`, `FS-RED-008`).
6. Calculate actual per-head only from Phase 3 completion and served population (`FS-RED-009`).
7. Rebuild procurement page around procurement events (`FS-RED-010`).
8. Auto-generate PPA from PO conversion snapshots (`FS-RED-011`).
9. Replace period budgets with fiscal-year allocation and immutable ledger (`FS-RED-012`, `FS-RED-013`).
10. Replace live/manual Food Service reports with frozen snapshots (`FS-RED-014`, `FS-RED-015`).
11. Rebuild insights on vendor groups, ledger, full ranges, and Phase 3 actuals (`FS-RED-016`).
12. Update seeders to prove varied final behavior (`FS-RED-017`).

## Recommended Changes

Changes that improve workflow quality and reduce defense friction but are not strictly required if the demo path is controlled:

1. Move shopping-list generation fully into the RND planning role (`FS-GAP-002`).
2. Align FSS budget/insights API access with the final FSS execution scope (`FS-GAP-003`).
3. Clarify planning cost vs frozen report cost in UI/report language (`FS-GAP-008`).
4. Prevent shopping-list finalization outside the RND conversion path (`FS-GAP-012`).
5. Make receipt/proof upload a shared RND/FSS evidence step and make procurement-span completion system-driven in the expanded workflow (`FS-GAP-014`).
6. Tighten budget date validation and avoid ambiguous demo budget periods (`FS-GAP-017`).
7. Label budget actual source and hide/defer manual daily logs if there is no UI path (`FS-GAP-018`).
8. Add a visible FSS accomplishment report access path only if the final defense story requires FSS to download it directly (`FS-GAP-021`).

## Nice-to-Have Changes

Defer until after defense:

1. Carry population defaults through template instantiation (`FS-GAP-007`).
2. Add a full inventory movement ledger distinguishing receiving, consumption, and manual adjustments (`FS-GAP-016`).
3. Redesign insights role scope and FSS analytics UI (`FS-GAP-022`).
4. Build explicit Clinical Care to Food Service population integration semantics (`FS-GAP-023`).
5. Clean up or fully productize API-only/orphaned features such as event allocation and manual daily logs (`FS-GAP-024`).
6. Add full model policy and record-level authorization architecture (`FS-GAP-025`).

## Suggested Implementation Order

1. **Secure visible and report role boundaries:** FS-GAP-001, FS-GAP-004.
2. **Stabilize planning handoff:** FS-GAP-005, FS-GAP-006.
3. **Protect procurement and inventory numbers:** FS-GAP-013, FS-GAP-015, then FS-GAP-020.
4. **Stabilize FSS execution data:** FS-GAP-009, FS-GAP-010, FS-GAP-011.
5. **Make reports defensible:** FS-GAP-019 after the source lifecycle fixes above.
6. **Promote obvious tested workflow flaws into C+B:** FS-GAP-002, FS-GAP-008, FS-GAP-012, FS-GAP-014, FS-RED-001 through FS-RED-011.
7. **Build D+E only after C+B is green:** FS-GAP-003, FS-GAP-017, FS-GAP-018, FS-GAP-021, FS-GAP-022, FS-RED-012 through FS-RED-016.
8. **Update demo data last:** FS-RED-017.
9. **Keep advanced architecture behind the redesign:** FS-GAP-007, FS-GAP-016, FS-GAP-023, FS-GAP-024, FS-GAP-025 unless they become necessary for the implemented workflow.

## Defense Triage Summary

For defense, the safest Food Service story is not "the module is enterprise-complete." The safest story is:

1. RND owns planning: menu cycles, recipes, budgets, shopping-list generation/conversion, procurement structure, and formal reports.
2. FSS owns execution: viewing the active menu, logging diet-list/accomplishment rows, completing service days, uploading procurement proof, and performing allowed operational inventory corrections.
3. Activation is the handoff from RND planning to FSS execution, so it must represent a complete and stable enough plan.
4. Completed procurement spans, vendor-group receipt/proof evidence, frozen planning snapshots, and completed service/accomplishment logs are the trusted sources for inventory, budget actuals, cash book, procurement pack, and insights.
5. Reports must only appear final when their source workflow is complete enough to defend.
6. Advanced governance, full policy architecture, inventory ledgers, and clinical population integration can wait until after defense.

For the expanded C+B/D+E redesign, the safest story becomes:

1. RND planning creates a complete active menu and a draft procurement event.
2. Shopping-list population is the planning authority for a procurement span, but it cannot overlap another menu-linked draft list.
3. Conversion creates one procurement-event PO with vendor groups and freezes structural data.
4. FSS execution fills operational proof and actual served population; it never changes RND planning estimates.
5. Completion events freeze actual per-head, PPA execution values, accomplishment outputs, budget ledger effects, and report snapshots.
6. Insights and reports read from event/snapshot/ledger sources, not mutable live workflow rows.

## Gap Analysis Summary

The Must Fix gaps cluster around role boundary, report integrity, menu-cycle lifecycle, meal-prep completion, and PO receiving. The Should Fix gaps mostly improve consistency and reduce demo confusion. The Can Wait gaps are mostly architectural maturity, advanced auditability, or features that can be excluded from the defense narrative without breaking the minimum operational story.
