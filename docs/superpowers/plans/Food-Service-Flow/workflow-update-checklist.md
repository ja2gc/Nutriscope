# Food Service Flow Workflow Update Checklist

Source: `workflow update plan.txt`

Use this checklist in order. Do not start a later section until the current section is implemented, tested, and verified.

## Operating Rules



## Preflight Verification

- [ ] Read `yes-to-multi-cycle-list-dapper-acorn.md`.
- [ ] Verify clicking any recipe or food item in a menu plan opens its detail view.
- [ ] Verify detail view shows current scaled servings.
- [ ] Verify detail view shows related information where available: ingredients, quantities, instructions, nutrition.
- [ ] Verify FSS detail view is read-only.
- [ ] Verify RND detail view follows existing edit permissions.
- [ ] Verify existing detail components are reused.
- [ ] Verify the whole menu item box opens the detail view, not only the item name.
- [ ] Verify resolved menu cycle day is single source of truth for draft shopping lists.
- [ ] Verify shopping list generation derives from resolved menu cycle days and active `estimate_population`.
- [ ] Verify recipe scaling derives from resolved menu cycle days and active `estimate_population`.
- [ ] Verify ingredient quantities derive from resolved menu cycle days and active `estimate_population`.
- [ ] Verify estimated procurement cost derives from resolved menu cycle days and active `estimate_population`.
- [ ] Verify Estimated Budget Per Head Per Day derives from resolved menu cycle days and active `estimate_population`.
- [ ] Verify suggested shopping list generation resolves each calendar date to its covering menu cycle.
- [ ] Verify suggested shopping list generation extracts only items assigned to the exact matched date.
- [ ] Verify recipe ingredients expand using current scaled servings.
- [ ] Verify identical ingredients aggregate across the procurement span.
- [ ] Verify active menu cycle appears first on the menu cycle page.
- [ ] Verify saved menu cycles are loadable.
- [ ] Verify menu cycle list is reachable from a button while active menu remains the starting display.

## Phase 1 Shipped-State Verification

- [ ] Verify `generate()` is date-driven and accepts `start_date` plus `end_date`.
- [ ] Verify `generate()` does not require `menu_cycle_id`.
- [ ] Verify `MenuCycle::coveringDate` resolver exists.
- [ ] Verify `MenuCycle::coveringDate` works.
- [ ] Verify `shopping_lists.menu_cycle_id` is dropped.
- [ ] Verify `coverage_status` exists on `shopping_lists`.
- [ ] Verify `uncovered_dates` exists on `shopping_lists`.
- [ ] Verify Fri-to-Mon cross-week span generates correctly from two cycles.
- [ ] Run full backend suite and confirm it passes.
- [ ] Run frontend `tsc --noEmit` and confirm it is clean.
- [ ] Fix any missing or broken shipped-state item before continuing.

## Step 1: Estimated Population And Recipe Scaling

### Field Separation

- [ ] Treat `estimate_population` as RND planning-only population on menu cycle days.
- [ ] Use `estimate_population` for recipe scaling.
- [ ] Use `estimate_population` for food quantity scaling.
- [ ] Use `estimate_population` for shopping list generation quantities.
- [ ] Treat `served_population` as FSS actual prep-day population on meal prep logs.
- [ ] Use `served_population` only for actual budget per head per day after the fact.
- [ ] Ensure RND never touches `served_population`.
- [ ] Ensure changing `estimate_population` never changes `served_population`.
- [ ] Ensure changing `served_population` never changes `estimate_population`.

### Estimate Population Cascade

- [ ] When `estimate_population` changes, rescale recipe servings immediately.
- [ ] When `estimate_population` changes, recalculate ingredient quantities immediately.
- [ ] When `estimate_population` changes, recalculate shopping list quantities immediately.
- [ ] When `estimate_population` changes, recalculate estimated total procurement cost immediately.
- [ ] When `estimate_population` changes, recalculate Estimated Budget Per Head Per Day immediately.
- [ ] Support setting `estimate_population` per menu cycle day.
- [ ] Support setting `estimate_population` at shopping list level for a procurement span.
- [ ] Add timestamp tracking so last write wins across day-level and shopping-list-level writes.
- [ ] When shopping list population is set, update all menu cycle days in the shopping list span.
- [ ] When shopping list population is set, rescale all recipes and food items in the span.
- [ ] When shopping list population is set, recalculate shopping list quantities.
- [ ] When day population is set, update only that menu cycle day.
- [ ] When day population is set, rescale only that day's recipes and food items.
- [ ] When day population is set, recalculate any draft shopping list covering that day for that day only.
- [ ] Use timestamp comparison to decide authoritative value in both directions.

### Cross-Cycle Cascade Review Gate

- [ ] Identify current behavior for shopping lists spanning two menu cycles.
- [ ] Identify whether cascading one shopping-list population into both cycles can overwrite draft or inactive cycle work.
- [ ] Identify current behavior for overlapping draft shopping lists on the same dates.
- [ ] Propose how to choose which draft list recalculates when a day population changes.
- [ ] Flag cross-cycle and overlapping-list solution for review before implementing cascade logic.

### Detail Views And Guards

- [ ] Ensure RND can view food and recipe profiles in menu plans.
- [ ] Ensure FSS can view food and recipe profiles in menu plans.
- [ ] Show current scaled servings based on active `estimate_population`.
- [ ] Keep FSS profile views read-only.
- [ ] Block menu cycle activation when any day with assigned recipe or food item has missing `estimate_population`.
- [ ] Activation error must list the specific missing days.
- [ ] Block shopping list generation only when a day has zero or null `estimate_population` and has at least one assigned recipe or food item.
- [ ] Shopping list generation error must list the specific missing dates.
- [ ] Allow zero population for days with no menu items to support manual-only shopping lists.

## Step 2: Shopping List States And Tabs

### State Machine And Permissions

- [ ] Ensure shopping lists have exactly two states: `draft` and `converted`.
- [ ] Remove `finalized` state and other intermediate states.
- [ ] Remove finalize dropdown or option.
- [ ] Add single `Convert to PO` button as the only forward action.
- [ ] Make converted shopping lists read-only for structural data.
- [ ] Remove converted shopping lists from active procurement queue.
- [ ] Ensure RND is the only role that generates shopping lists.
- [ ] Ensure FSS cannot generate, create, or modify shopping lists.
- [ ] Remove FSS shopping list creation UI if present.

### Ingredients Tab

- [ ] Add or verify `Ingredients` tab.
- [ ] Keep auto-generated date-driven flow from Phase 1 unchanged.
- [ ] Support manual ingredient items in the same shopping list.
- [ ] Use existing manual item creation UI for manual ingredients.
- [ ] Allow RND to edit auto-generated items before conversion.
- [ ] Allow RND to remove auto-generated items before conversion.
- [ ] Allow manual items to sit alongside auto-generated items.

### Supplies Tab

- [ ] Check whether Inventory already has a Supplies tab.
- [ ] Check whether supplies model already exists.
- [ ] Flag existing supplies support versus required build work before editing.
- [ ] If supplies model does not exist, add minimal model: name, unit, category, current stock, reorder level.
- [ ] Add Supplies tab to Inventory using existing Ingredients tab UI pattern.
- [ ] Add or verify shopping list `Supplies` tab.
- [ ] Ensure Supplies tab is manual entry only.
- [ ] Use supplies inventory as source for supply items.
- [ ] Let RND add, edit, and remove supplies before conversion.
- [ ] Capture supply quantity, unit, and estimated cost.
- [ ] Group supplies under the same vendor as ingredients in resulting PO.

### Shopping List Population

- [ ] Add shopping-list-level estimated population input.
- [ ] Make shopping-list-level population editable while list is draft.
- [ ] Cascade shopping-list-level population to all covered menu cycle days.
- [ ] Rescale affected menu items using Step 1 rules.

## Step 3: Purchase Order Flow Redesign

### Lifecycle

- [ ] Enforce one PO per shopping list.
- [ ] Phase 1: keep PO absent while shopping list is draft.
- [ ] Phase 1: allow RND to edit items, quantities, population, and tabs.
- [ ] Phase 2: create one PO when `Convert to PO` is clicked.
- [ ] Phase 2: link PO to shopping list.
- [ ] Phase 2: group items by vendor into vendor sub-records.
- [ ] Decide vendor sub-record name using existing conventions, such as `VendorOrder` or `PurchaseGroup`.
- [ ] Phase 2: permanently freeze structural data: items, quantities, estimated base costs, vendor groupings.
- [ ] Phase 2: allow RND and FSS to input OR numbers on vendor groups.
- [ ] Phase 2: allow RND and FSS to upload receipt images on vendor groups.
- [ ] Phase 2: allow RND and FSS to upload proof of purchase photos on vendor groups.
- [ ] Phase 2: fire PO creation event or hook for future budget ledger integration.
- [ ] Phase 2: auto-generate PPA in same transaction.
- [ ] Phase 3: automatically transition when every vendor group has receipts uploaded and every date in the span has `served_population`.
- [ ] Phase 3: calculate `actual_budget_per_head_per_day` as final vendor group totals divided by total `served_population`.
- [ ] Phase 3: allow user to save or archive PO.
- [ ] Phase 3: lock all fields permanently after completion/archive.

### Removed Paths

- [ ] Remove manual Create PO endpoint.
- [ ] Remove manual Create PO UI.
- [ ] Remove standalone vendor PO creation path.
- [ ] Replace `generatePos()` multiple-vendor-PO approach with single PO plus vendor groups.

### Schema Review Gate

- [ ] Plan PO table schema.
- [ ] Plan vendor group table schema.
- [ ] Plan attachment table schema.
- [ ] Plan lifecycle status field.
- [ ] Flag existing PO records affected by migration.
- [ ] Propose handling for existing PO records.
- [ ] Get schema plan reviewed before running migrations.

### Vendor Group Page

- [ ] Show vendor item list with quantities and unit costs.
- [ ] Make items read-only after Phase 2 conversion.
- [ ] Add OR number input editable in Phase 2 and locked in Phase 3.
- [ ] Use `ImageUploadGallery.tsx` for receipt uploads.
- [ ] Support multiple receipt images.
- [ ] Support receipt image removal while editable.
- [ ] Use `ImageUploadGallery.tsx` for proof of purchase uploads.
- [ ] Support multiple proof of purchase images.
- [ ] Support proof of purchase image removal while editable.
- [ ] Show vendor group status as plain text only.
- [ ] Use ghost button variant for Save action.
- [ ] Ensure FSS cannot edit structural data.
- [ ] Ensure FSS cannot create POs.
- [ ] Ensure FSS cannot delete POs.
- [ ] Ensure FSS cannot generate shopping lists.

## Step 4: Procurement Page Restructure

- [ ] Group procurement page by procurement event instead of flat list.
- [ ] Level 1 event row shows procurement span date range.
- [ ] Level 1 event row shows total estimated cost.
- [ ] Level 1 event row shows number of vendors.
- [ ] Level 1 event row shows PO lifecycle phase: Draft, Open Execution, Completed.
- [ ] Level 1 event row shows coverage status from shopping list.
- [ ] Level 2 event detail shows single PO.
- [ ] Level 2 lists vendor groups.
- [ ] Level 2 vendor row shows vendor name.
- [ ] Level 2 vendor row shows item count.
- [ ] Level 2 vendor row shows OR number when entered.
- [ ] Level 2 vendor row shows receipt status.
- [ ] Level 2 vendor row shows vendor total cost.
- [ ] Level 3 vendor group detail shows items, quantities, and costs.
- [ ] Level 3 item data is read-only after Phase 2.
- [ ] Level 3 includes OR number field.
- [ ] Level 3 includes receipt upload.
- [ ] Level 3 includes proof of purchase upload.
- [ ] Remove Settings tab from procurement page.
- [ ] Use breadcrumbs for navigation: Procurement > Event > Vendor.
- [ ] Avoid relying on browser back for level navigation.
- [ ] Use plain text rows at all levels.
- [ ] Use muted gray text for normal status values.
- [ ] Use danger red text only for urgent statuses like Overdue.
- [ ] Do not use badges, colored cells, or background pills.

## Step 5: PPA Auto-Generation

### Review Gate

- [ ] Plan PPA structure based on standard Philippine government health facility format.
- [ ] Flag proposed PPA layout for review before building PDF or print view.

### Generation And Access

- [ ] Auto-generate PPA when `Convert to PO` is triggered.
- [ ] Generate PPA in same transaction as PO creation.
- [ ] Use PO/procurement event as PPA data source.
- [ ] Do not require manual PPA creation step.
- [ ] Allow RND to view PPA.
- [ ] Allow RND to print PPA.
- [ ] Block FSS from seeing PPA.

### Planning Columns Frozen At PO Creation

- [ ] Set Activity to `Food Subsistence for Patients`.
- [ ] Store Menu as chronological text snapshot with day numbers and meal configurations.
- [ ] Store target date range from procurement span.
- [ ] Store estimated total cost from shopping list at conversion.
- [ ] Store estimated output patients from `estimate_population` across procurement span.
- [ ] Freeze planning columns at Phase 2.

### Execution Columns Frozen At Phase 3

- [ ] Store actual total cost as sum of submitted OR amounts from vendor groups.
- [ ] Store actual output patients as sum of daily `served_population` across span.
- [ ] Update execution columns when PO reaches Phase 3.
- [ ] Freeze PPA permanently after Phase 3.

## Step 6: Menu Cycle List View

- [ ] Add menu cycle list view accessible to RND and FSS.
- [ ] Check for existing route before creating a new route.
- [ ] Extend existing route if one exists.
- [ ] If no route exists, create it under existing food-service routing convention.
- [ ] Show all cycles in chronological order.
- [ ] Show currently active cycle with left border accent or inline text label.
- [ ] Avoid badge inside row for active indicator.
- [ ] Show past cycles.
- [ ] Show upcoming cycles.
- [ ] Show draft cycles.
- [ ] Each row shows week date range.
- [ ] Each row shows status.
- [ ] Each row shows per-day plan existence for each day of the week.
- [ ] RND can click any cycle to open it.
- [ ] RND opened cycle uses same UI as current active cycle.
- [ ] RND opened cycle shows full menu plan data.
- [ ] RND opened cycle shows current scaled servings per day.
- [ ] RND can edit cycles that are not yet active.
- [ ] RND can manage menu plan with existing CRUD behavior.
- [ ] RND can set `estimate_population` per day from this view.
- [ ] FSS can view list and cycles read-only.
- [ ] FSS can see menu plan details.
- [ ] FSS can see current scaled servings.
- [ ] FSS cannot edit cycles.
- [ ] FSS cannot create cycles.
- [ ] FSS cannot delete cycles.
- [ ] FSS cannot change population values.

## Step 7: Tests And Verification

### Baseline

- [ ] Run full backend test suite.
- [ ] Confirm all existing backend tests pass.
- [ ] Confirm full suite remains at 637+ passing tests.
- [ ] Run frontend type check.
- [ ] Confirm no new TypeScript errors.

### Phase 1 Baseline Tests

- [ ] Test `generate()` is date-driven.
- [ ] Test cross-week menu cycle resolution.
- [ ] Test `coverage_status`.
- [ ] Test `uncovered_dates`.

### Population And Scaling Tests

- [ ] Test shopping-list-level population cascades to all menu cycle days in span.
- [ ] Test individual-day population overrides shopping-list value when newer.
- [ ] Test shopping-list population overrides individual-day value when newer.
- [ ] Test timestamp last-write-wins in both directions.
- [ ] Test recipe quantities rescale when population changes.
- [ ] Test shopping list quantities recalculate after population cascade.
- [ ] Test menu cycle activation blocks missing population for days with menu items.
- [ ] Test shopping list generation blocks zero/null population only when day has menu items.
- [ ] Test zero population is allowed when day has no menu items.
- [ ] Test `served_population` never affects `estimate_population`.
- [ ] Test `estimate_population` never affects `served_population`.
- [ ] Test cross-cycle cascade edge case works per reviewed solution.

### Shopping List Tests

- [ ] Test state machine allows `draft` to `converted` only.
- [ ] Test no other shopping list transitions exist.
- [ ] Test FSS cannot generate shopping lists at API level.
- [ ] Test FSS cannot create shopping lists at API level.
- [ ] Test supplies tab items are included in PO conversion.
- [ ] Test manual ingredient items are included with auto-generated items.
- [ ] Test shopping list population field cascades with timestamp.

### PO Lifecycle Tests

- [ ] Test Phase 1 to Phase 2 creates correct PO structure.
- [ ] Test structural data is read-only after Phase 2.
- [ ] Test RND can input OR numbers in Phase 2.
- [ ] Test FSS can input OR numbers in Phase 2.
- [ ] Test RND can upload receipts in Phase 2.
- [ ] Test FSS can upload receipts in Phase 2.
- [ ] Test Phase 3 triggers when all receipts are uploaded and all `served_population` exists.
- [ ] Test `actual_budget_per_head_per_day` calculation at Phase 3.
- [ ] Test all fields lock after Phase 3.
- [ ] Test PO creation fires event hook with correct budget ledger payload.
- [ ] Test FSS cannot create POs at API level.
- [ ] Test FSS cannot delete POs at API level.
- [ ] Test manual Create PO endpoint is gone.
- [ ] Test no dead manual PO routes remain.

### PPA Tests

- [ ] Test PPA auto-generates at PO creation.
- [ ] Test PPA generation happens in same transaction as PO creation.
- [ ] Test PPA planning columns freeze at creation.
- [ ] Test PPA execution columns update at Phase 3.
- [ ] Test PPA freezes after Phase 3.
- [ ] Test FSS cannot access PPA at API level.

### Procurement Page Tests

- [ ] Test Level 1 API response.
- [ ] Test Level 2 API response.
- [ ] Test Level 3 API response.
- [ ] Test Settings tab is absent from procurement page.
- [ ] Test breadcrumb navigation works at all levels.

### Menu Cycle List Tests

- [ ] Test menu cycles show in chronological order.
- [ ] Test active cycle indicator is correct.
- [ ] Test per-day plan existence is correct for each day of week.
- [ ] Test FSS read-only enforcement at API level.

### Blast Radius

- [ ] Verify existing procurement tests still pass.
- [ ] Verify inventory Ingredients tab is unaffected.
- [ ] Verify budget auto-deduction hook exists and is documented for D+E.
- [ ] Verify reports page is unaffected by C+B work.
- [ ] Verify mobile app is unaffected.
- [ ] Verify no code path allows `served_population` and `estimate_population` to affect each other.

## Prompt D+E: Budget Redesign, Reports, And Insights

### Preflight

- [ ] Verify Prompt C+B is fully merged.
- [ ] Verify Prompt C+B is green.
- [ ] Verify PO lifecycle exposes completed/finalized event hook.
- [ ] Verify budget ledger can deduct only after PO reaches Phase 3 and final PO total is locked.

### Step 1: Budget Model Redesign

- [ ] Remove New Budget records concept.
- [ ] Ensure Budget page is not a list of budget records.
- [ ] Remove graphs from Budget page.
- [ ] Create one fiscal year allocation record per fiscal year.
- [ ] Store only `fiscal_year`, `allocated_amount`, and `per_head_day_limit` on fiscal year allocation.
- [ ] Remove population field from Budget setup.
- [ ] Keep each fiscal year independent.
- [ ] Reset allocation on new calendar or fiscal year.
- [ ] Do not carry over remaining budget.
- [ ] Notify RND when current year has no allocation.
- [ ] Prevent PO finalization deduction from silently proceeding without current year allocation.

### Budget Ledger

- [ ] Build append-only budget ledger.
- [ ] Support `po_deduction`.
- [ ] Support `manual_addition`.
- [ ] Support `manual_deduction`.
- [ ] Store `fiscal_year`.
- [ ] Store `type`.
- [ ] Store `amount`.
- [ ] Store `reason`.
- [ ] Store `reference`.
- [ ] Store nullable `purchase_order_id`.
- [ ] Store nullable `procurement_span`.
- [ ] Store `created_by`.
- [ ] Store `created_at`.
- [ ] Calculate remaining balance as allocation plus manual additions minus manual deductions minus PO deductions.

### Manual Budget Adjustment

- [ ] Restrict manual budget adjustment to RND.
- [ ] Add funds creates `manual_addition`.
- [ ] Deduct funds creates `manual_deduction`.
- [ ] Require amount.
- [ ] Require reason.
- [ ] Prevent editing ledger entries.
- [ ] Prevent deleting ledger entries.
- [ ] Use offsetting entries for corrections.

### PO Auto-Deduction

- [ ] Create `po_deduction` ledger entry when PO reaches Phase 3.
- [ ] Use final locked PO total.
- [ ] Do not use draft estimate for deduction.
- [ ] Create deduction in same transaction as PO finalization.
- [ ] Include PO reference.
- [ ] Include procurement span.
- [ ] Include finalized/generated date.
- [ ] Include total cost.
- [ ] If fiscal year allocation is missing, show clear warning.
- [ ] If fiscal year allocation is missing, block or hold finalization in recoverable state.
- [ ] Never silently skip deduction.

### Actual Budget Per Head Per Day

- [ ] Calculate only after PO reaches Phase 3.
- [ ] Formula: final PO total divided by total `served_population` across procurement span.
- [ ] Never use `estimate_population` for actual calculation.
- [ ] Show pending before Phase 3.

### Step 2: Budget Page UI

- [ ] Remove tabs from Budget page.
- [ ] Remove graphs from Budget page.
- [ ] Show fiscal year setup.
- [ ] Show summary.
- [ ] Show manual adjustment controls.
- [ ] Show ledger.
- [ ] Add fiscal year dropdown.
- [ ] Show allocated amount for year.
- [ ] Show per-head/day limit for year.
- [ ] Show remaining balance.
- [ ] Show total PO deductions.
- [ ] Show total manual additions.
- [ ] Show total manual deductions.
- [ ] RND sees Add funds ghost button.
- [ ] RND sees Deduct funds ghost button.
- [ ] FSS sees no budget management UI.
- [ ] Add/Deduct forms require amount and reason.
- [ ] Ledger table sorts reverse chronological.
- [ ] Ledger columns: date created/generated, source/type, amount, reason, reference, created by.
- [ ] Add ledger filter: all.
- [ ] Add ledger filter: manual only.
- [ ] Add ledger filter: purchase orders only.
- [ ] Add ledger filter: manual_addition.
- [ ] Add ledger filter: manual_deduction.
- [ ] Add ledger filter: po_deduction.
- [ ] Manual rows show manual reason and creator.
- [ ] PO rows show PO reference, procurement span, finalized/generated date.
- [ ] Use plain text only.
- [ ] Avoid badges and colored pills.
- [ ] Deductions use danger red text only.
- [ ] Additions use primary green text only.
- [ ] Add RND-only new fiscal year setup form.
- [ ] Form fields: `fiscal_year`, `allocated_amount`, `per_head_day_limit`.
- [ ] Add Set up year or Reset dashboard for new year action.
- [ ] Create new year record instead of overwriting prior year.
- [ ] Show notice when current year setup is missing.

### Step 3: Insights UI

- [ ] Keep all graphs in Insights only.
- [ ] Ensure no graphs remain on Budget page.
- [ ] Ensure no graphs remain on Procurement page.
- [ ] Ensure no graphs remain on Settings.
- [ ] Require user to select fiscal year first.
- [ ] Require user to select insight category after fiscal year.
- [ ] Display January through December for selected year.
- [ ] Keep months with no data visible as blank, zero, or pending.
- [ ] Show useful summaries at top of each insight.
- [ ] Budget burn shows allocated budget flat line.
- [ ] Budget burn shows cumulative deductions.
- [ ] Budget burn uses full fiscal year timeline.
- [ ] Budget burn stamps PO deductions on finalized/generated date.
- [ ] Budget burn stamps manual additions/deductions on created date.
- [ ] Budget per-head/day insight shows each procurement span.
- [ ] Budget per-head/day insight shows actual only after PO Phase 3.
- [ ] Budget per-head/day insight shows fiscal year `per_head_day_limit`.
- [ ] Budget per-head/day insight shows estimated per-head/day when available.
- [ ] Budget per-head/day insight marks Phase 2 POs as pending.
- [ ] Procurement deduction timeline uses full fiscal year timeline.
- [ ] Procurement deduction timeline stamps finalized PO reference.
- [ ] Procurement deduction timeline stamps procurement span.
- [ ] Procurement deduction timeline stamps finalized/generated date.
- [ ] Procurement deduction timeline stamps total cost.
- [ ] Procurement deduction timeline stamps actual budget per head/day.
- [ ] Procurement deduction timeline stamps estimated budget per head/day when available.
- [ ] Procurement deduction timeline includes manual additions/deductions as separate markers.
- [ ] Spend by supplier shows total spend per vendor for selected year.
- [ ] Spend by supplier uses new PO vendor group model.
- [ ] Do not collapse missing months or days.
- [ ] Do not add forecasting unless explicitly requested later.
- [ ] Do not add variance percentage unless explicitly requested later.
- [ ] Do not add projected overrun unless explicitly requested later.

### Budget Tests

- [ ] Test fiscal year allocation has only year, allocated amount, and per-head/day limit.
- [ ] Test new year has no carryover.
- [ ] Test current year missing allocation notifies RND.
- [ ] Test manual addition requires reason.
- [ ] Test manual deduction requires reason.
- [ ] Test manual additions are RND-only.
- [ ] Test manual deductions are RND-only.
- [ ] Test ledger entries are immutable.
- [ ] Test remaining balance across mixed entries.
- [ ] Test PO ledger auto-deduction fires on Phase 3 finalization.
- [ ] Test PO ledger auto-deduction uses final PO total.
- [ ] Test no deduction happens on PO creation/open execution.
- [ ] Test missing fiscal year allocation blocks or safely holds PO finalization.
- [ ] Test FSS budget management UI is absent.
- [ ] Test FSS budget management routes are absent.

### Insights Tests

- [ ] Test fiscal year must be selected before insight category.
- [ ] Test all insights show January through December.
- [ ] Test Budget burn stamps PO deductions on correct dates.
- [ ] Test Budget burn stamps manual adjustments on correct dates.
- [ ] Test Budget per-head/day shows actual vs estimated per procurement span.
- [ ] Test Phase 2 POs show pending markers.
- [ ] Test Spend by supplier uses vendor group model.
- [ ] Test no graphs remain on Budget page.

### D+E Blast Radius

- [ ] Verify PO creation still generates PPA snapshot.
- [ ] Verify PO Phase 3 triggers `actual_budget_per_head_per_day`.
- [ ] Verify PO Phase 3 freezes PPA execution.
- [ ] Verify PO Phase 3 freezes accomplishment report.
- [ ] Verify PO Phase 3 freezes report snapshots.
- [ ] Verify PO Phase 3 creates budget ledger deduction.
- [ ] Verify reports remain frozen snapshots.
- [ ] Verify `estimate_population` never affects actual budget calculation.

## Current Flow To Replace

- [ ] RND creates FS recipes.
- [ ] RND creates menu cycle days.
- [ ] RND sets `estimate_population` per day.
- [ ] RND generates suggested shopping list by date range.
- [ ] System checks whether every date has menu plan and population.
- [ ] Existing partial shopping list behavior creates `uncovered_dates` when coverage is missing.
- [ ] Existing draft shopping list can be approved/finalized.
- [ ] Existing system creates multiple vendor POs.
- [ ] Existing PO status moves through ordered/received.
- [ ] FSS inputs `diet_list_counts.population`.
- [ ] Meal prep log syncs `served_population`.
- [ ] Actual per-head waits for received POs and completed meal prep.
- [ ] Budget/report currently uses live services instead of fully frozen ledger.

## Target Flow

- [ ] RND creates recipes and ingredients.
- [ ] RND creates menu cycle/week.
- [ ] RND sets `estimate_population` per planned day.
- [ ] Activation blocks when planned days are missing `estimate_population`.
- [ ] Active cycle becomes available.
- [ ] RND generates shopping list for procurement span.
- [ ] System resolves each calendar date to covering menu cycle.
- [ ] System flags missing cycle, day, menu, or population by exact date.
- [ ] Draft shopping list is created.
- [ ] Draft shopping list converts to one PO.
- [ ] PO contains vendor groups.
- [ ] PPA planning snapshot auto-generates from PO span, items, and costs.
- [ ] Open execution accepts OR, receipts, and proof uploads.
- [ ] FSS inputs numeric diet list headcount per ward/date.
- [ ] `served_population` is derived from diet list counts.
- [ ] Completion requires all vendor receipts and all span dates with `served_population`.
- [ ] PO remains pending/open until completion requirements are met.
- [ ] Completed PO finalizes actual budget per head/day.
- [ ] Completed PO creates budget ledger `po_deduction`.
- [ ] Completed PO freezes PPA execution and report snapshots.
- [ ] Insights graphs use selected fiscal year.

## End-To-End Flow Clarifications

- [ ] Suggested shopping list generation resolves each calendar date to covering menu cycle.
- [ ] Suggested shopping list generation extracts ingredients only from matched menu cycle day for exact date.
- [ ] Flag exact date and reason when there is no covering menu cycle.
- [ ] Flag exact date and reason when menu cycle day is not created.
- [ ] Flag exact date and reason when menu cycle day has no menu items.
- [ ] Flag exact date and reason when menu cycle day has menu items but no `estimate_population`.
- [ ] Support Fri-Mon and any procurement span crossing two menu cycles.
- [ ] Each date uses its own matched menu cycle.
- [ ] Shopping list converts to one PO with vendor groups.
- [ ] PPA is generated from PO/procurement event, not from whole menu cycle.
- [ ] PPA inherits procurement span.
- [ ] PPA inherits items.
- [ ] PPA inherits estimated cost.
- [ ] PPA inherits vendor grouping.
- [ ] PPA later receives final execution values.
- [ ] FSS inputs diet list as numeric headcount, not text.
- [ ] Use `diet_list_counts.population` per ward/date/FSS user.
- [ ] Derive or backfill `served_population` for each service date.
- [ ] Calculate actual budget per head/day only after PO finalization.
- [ ] Formula: final locked PO total divided by total `served_population` across procurement span.
- [ ] Create budget ledger `po_deduction` only when PO reaches Phase 3.
- [ ] Use final PO total for budget ledger deduction.
- [ ] Keep Budget page as setup plus ledger only.
- [ ] Keep all charts in Insights.
- [ ] Freeze report snapshots.
- [ ] Do not let Reports page live-query changing records for frozen reports.

## Accomplishment Report

- [ ] Store diet list input as numeric `population` integer.
- [ ] Store `service_date`.
- [ ] Store `ward`.
- [ ] Store FSS user from auth.
- [ ] Store task checkboxes.
- [ ] Base accomplishment report on `diet_list_counts` rows.
- [ ] Make accomplishment report per FSS user.
- [ ] Generate or update accomplishment report when FSS submits diet list counts.
- [ ] Generate or update accomplishment report when FSS submits `served_population` for dates in a procurement span.
- [ ] Freeze accomplishment report when related PO/procurement span reaches Phase 3.
- [ ] Prevent later diet list edits from changing frozen accomplishment report snapshot.
- [ ] Prevent later backfills from changing frozen accomplishment report snapshot.

## Inventory Stock-In, Stock-Out, And Latest Price Sync

### Stock-In

- [ ] Tie inventory stock-in directly to PO execution.
- [ ] Stock items into inventory when PO vendor group is received/confirmed during Phase 2.
- [ ] If Convert to PO means purchased and received, stock in at conversion instead.
- [ ] Otherwise stock in when OR or receipt is saved for vendor group.
- [ ] Add received quantities to `inventory.quantity_in_stock` in base units.
- [ ] Make stock-in idempotent.
- [ ] Prevent same vendor group from stocking same PO lines twice.
- [ ] Skip free-text PO lines without `fs_item_id`.
- [ ] Log skipped free-text PO lines.

### Stock-Out

- [ ] Tie inventory stock-out directly to meal-prep completion.
- [ ] Deduct ingredients when FSS completes meal prep for each service date.
- [ ] Deduction uses matched menu cycle day for exact service date.
- [ ] Deduction uses estimate/prepared population that drove menu quantities.
- [ ] Do not use `served_population` to rescale stock deduction.
- [ ] Use `served_population` only for actual budget per head/day.
- [ ] At PO Phase 3, verify every service date in span has completed meal-prep log.
- [ ] Block Phase 3 if any date has not deducted inventory.
- [ ] Notify exact missing dates when blocking Phase 3.
- [ ] Do not silently deduct all inventory at the end.
- [ ] Treat daily meal-prep deduction as source of truth.

### PO Price And Unit Editing

- [ ] During Phase 2, allow RND/FSS to edit `purchase_qty`.
- [ ] During Phase 2, allow RND/FSS to edit `purchase_unit`.
- [ ] During Phase 2, allow RND/FSS to edit `purchase_price`.
- [ ] During Phase 2, allow RND/FSS to edit `units_per_purchase`.
- [ ] During Phase 2, allow RND/FSS to edit `unit_price` if used as base-unit fallback.
- [ ] Recalculate PO line total after edits.
- [ ] Recalculate vendor group total after edits.
- [ ] Lock line prices and units permanently at Phase 3.

### Latest Catalog Price Sync

- [ ] When received PO lines stock into inventory, update related FS item latest `purchase_price`.
- [ ] When received PO lines stock into inventory, update related FS item latest `purchase_unit`.
- [ ] When received PO lines stock into inventory, update related FS item latest `units_per_purchase`.
- [ ] Update `inventory.unit_price` as latest base-unit cost.
- [ ] Recalculate dependent recipe costs after latest price changes.
- [ ] Keep historical PO lines as frozen snapshots.
- [ ] Do not mutate old PO lines when latest catalog prices change.
- [ ] Do not mutate frozen reports when latest catalog prices change.
- [ ] Block changing `base_unit` from PO unless a dedicated unit-conversion migration/workflow exists.

## Reports Reproducibility

- [ ] Update all Reports page reports to match current system behavior.
- [ ] Remove stale report data.
- [ ] Remove dead-end report values.
- [ ] Ensure every reported value comes from a clear input.
- [ ] Ensure every reported value is processed by the system through documented logic.
- [ ] Ensure report calculations are reproducible.
