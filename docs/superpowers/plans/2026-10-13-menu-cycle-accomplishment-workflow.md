# Menu Cycle and Accomplishment Workflow Implementation Plan

> **For the next session:** Implement this plan in the existing checkout, task by task. Read `.agents/AGENTS.md` and all matching rules again before work. Do not create a second workflow, population source, or approval system. Record evidence beside each completed checkbox. This is new scope after the completed 2026-10-11 Food Service checklist; preserve that work.

**Goal:** Make Menu Cycles apply by service week, repair service-day/population behavior, preserve the Shopping List and PO lifecycle, and add per-FSS semi-monthly accomplishment submission and RND review.

**Architecture:** Keep the existing `MenuCycle`, `MenuCycleDay`, `MealPrepLog`, `ShoppingList`, `PurchaseOrder`, `DietListCount`, and `Report` models and their services. Centralize date-based cycle resolution, keep necessary activation and cost-snapshot state internal, and attach review state to the existing accomplishment report record rather than building a parallel approval system. Extend the current RND Reports and FSS mobile Accomplish screens.

**Tech stack:** Laravel backend, Next.js/React web, Expo/React Native FSS app, existing tests and report PDF pipeline. Read installed framework docs and `.agents/rules/ui-ux.md` before editing screens.

**Spec:** The agreed requirements and decisions in this document are the self-contained source for this implementation. Current code and runtime are the source for existing behavior; recheck them before changes. No separate spec or handoff is needed.

## Agreed requirements and decisions

- A valid planned service date must resolve to its actual Menu Cycle regardless of stale manual activation. Fix the root logic behind “Not in Menu Cycle” and “The service date is not a planned day in this menu cycle.” Remove the former from normal UI only after the logic is fixed.
- The applicable cycle is the **current Monday–Sunday week by Asia/Manila date**, even if the week has no planned items yet. Empty current weeks remain editable by RND. Past weeks stop appearing active; future weeks become applicable when reached. When there truly is no covering cycle, show no active cycle rather than a stale past one. Automatically maintain the current week and **four future weekly cycles**, idempotently and without replacing historical cycles.
- Inspect the existing Activate endpoint, `is_active`, `status`, `activation_date`, cost snapshot, reports, and consumers before changing them. Do not require users to click Activate as the week arrives. Preserve necessary backend state and frozen historical costs. An empty current week cannot be frozen in a way that makes later planning incorrect.
- Once a PO is generated from a cycle's covered menu, prevent normal edits to that cycle through the **existing PO snapshot relationship**. Cover structure, slot/line edits, rename/date changes, delete, and alternate write paths. Keep historical viewing. Actual served-population correction follows the existing PO lifecycle and stays possible until the related completed food PO locks it.
- A planned Menu Calendar day may have its actual served population entered or corrected without a Shopping List or PO. A date must really belong to that cycle and have a planned service item; no procurement prerequisite. Preserve the existing `MealPrepLog.served_population` path and the existing date-associated estimate. Do not add another population source.
- Preserve the **one Estimated servings input in Shopping List**. Preview does not save it; Save applies it to each selected applicable Menu Calendar date. A span may cross cycle boundaries, and later calculations use each date's stored `MenuCycleDay.estimate_population`. Preserve saved-list recalculation, draft/PO boundaries, and existing item quantities.
- RND/FSS can fill a missed **past** week through the same Menu Plan → Shopping List → PO/receiving → completed PO → PPA/Procurement Pack path. Keep actual served counts, vendor evidence, fiscal-year budget checks, frozen snapshots, and real audit/creation/completion times. Do not add a backfill system or fabricate procurement evidence.
- Remove internal/debug copy from normal Menu Cycle screens, including “Not in Menu Cycle,” “Locked to PO,” “Locked to PO · open details,” “line 1 open details,” UUID-style Cycle IDs, and similar implementation terms. Keep backend flags/IDs for API and history where needed. Use useful task language or omit redundant copy.
- Accomplishment review is **per FSS user, per semi-monthly period** (1–15 and 16–month end). Progress is unique dates with saved daily logs, e.g. 1/15 or the actual variable length for the second half. Every calendar date requires a saved log, including an off-duty entry where applicable, before submission. Daily logging must not depend on an unrelated RND Menu Cycle update.
- FSS submits a complete period; submitted periods are read-only to FSS. FSS may **Withdraw submission** while pending, which removes it from RND's queue and unlocks editing. Rejected periods unlock for correction and resubmission. Approved periods and their daily logs lock. Prevent stale/concurrent RND decisions after withdrawal or resubmission.
- RND sees a new FSS accomplishment period only after submission: pending periods appear in **To Review**, and approved periods appear in existing Reports. Unsubmitted daily logs and auto-generated draft report copies are not available to RND through browsing, preview/download, or direct APIs. Withdrawal removes a period from the review queue.
- RND reviews each FSS submission and **Approves** or **Rejects**. Rejection requires a reason of at most **500 characters**, with the existing web `CharacterCountTextarea` counter inside the box and backend validation. FSS sees that reason and can navigate to and correct any day in the period.
- FSS mobile placement is the existing **Accomplish → My Reports** tab: a semi-monthly period list with progress/status, period detail with day traversal and Submit/Withdraw, and links to the existing Daily Log editor. RND placement is the existing **Reports → Accomplishment Report** area with a separate “To Review” view. Approved new reports appear in the existing Reports view. Reports already generated before this workflow remain untouched and visible; do not retroactively approve, remove, or migrate them. No extra top-level page.
- Admin has no accomplishment review queue or Approve/Reject actions. Admin's existing Reports view shows approved new accomplishment reports and pre-workflow generated legacy reports, but no new unapproved periods or logs. Keep Admin access to unrelated report types unchanged.
- Reuse established components and UI patterns; keep the screens focused, responsive, and accessible under `.agents/rules/ui-ux.md`.

## Current code: preserve or repair

- `ShoppingListPopulationService::planRange` resolves dates across cycles; Save cascades the single estimate to covered menu days, and draft recalculation uses per-day values. The 2026-10-11 checklist records prior tests and live evidence. Re-verify current revision; change only on a failing case.
- `PurchaseOrderController::approve` converts a saved list, creates the PO/PPA snapshot and menu-day snapshots. `PurchaseOrderLifecycleService` requires vendor evidence, actuals, served population for every covered date, and the period's budget before completion; it prepares PPA and Procurement Pack reports. Preserve this path for past spans.
- `MealPrepLogController::setServed` has no Shopping List/PO precondition, but checks `MenuCycle::coveringDate` and a weekday row. Its error can arise from incorrect cycle selection. Its completed-PO lock is intentional.
- `MenuCycle::coveringDate` currently prefers `is_active` on overlap. `MenuCycleController::index`, `FssDashboardService`, web Menu Cycle landing, and mobile menu/prep use raw `is_active`; stale past cycles can look current. `activate` also freezes a first cost snapshot. `structuralDaysLocked` treats `is_active` as a lock, which conflicts with an empty editable current week.
- PO conversion writes snapshots, but current guards vary by write path; some depend on `po_snapshot_locked`, which is set at completion. Audit every Menu Cycle mutation route and enforce the agreed generated-PO boundary consistently.
- `DietListCountController::store` writes the current FSS daily log and `AccomplishmentReportArchiveService` refreshes a semi-monthly `Report` after each save. Existing report `status=completed` means a generated PDF, **not RND approval**. `ReportBrowser` lists semi-monthly periods; `ReportController` allows FSS to view own accomplishment reports. Preserve old report rows and their files. Do not overload PDF/archive status with review meaning unless all dependencies prove it safe.
- The exact “Not in Menu Cycle” text was not found in the current source search. Reproduce it in the actual UI/API before fixing; do not claim a specific origin from the label alone.

## Implementation checklist

### 1. Diagnose the cycle and service-day bugs

**Entry points:** `backend/app/Models/MenuCycle.php`, `backend/app/Http/Controllers/FSS/MenuCycleController.php`, `backend/app/Http/Controllers/FSS/MealPrepLogController.php`, `backend/app/Services/FSS/FssDashboardService.php`, web Menu Cycle page, mobile menu/prep, relevant feature tests.

- [ ] Reproduce the displayed “Not in Menu Cycle” and the pencil/edit validation failure with exact service dates, weekday rows, overlapping/stale cycles, and API requests; record root cause and affected consumers.
- [ ] Write failing backend tests for date coverage, week boundaries/year rollover, stale/overlapping activation, planned vs empty weekday, and served edit with no Shopping List/PO; confirm failures before edits.
- [ ] Correct shared date-to-cycle/service-day resolution and all affected callers. Keep unplanned dates invalid; keep a planned date valid without procurement. Verify public UUID/API contracts and Asia/Manila date behavior.

### 2. Automatic current week and future-cycle maintenance

**Entry points:** `MenuCycle`, controller/resource, cost snapshot service, backend schedule in `backend/bootstrap/app.php`, FSS dashboard, web and mobile cycle consumers, menu-cycle tests.

- [ ] Map every `is_active`/`status`/activation/cost-snapshot consumer and existing duplicate-week records before choosing the smallest compatible internal transition. Preserve historical snapshots and audit meaning.
- [ ] Test current empty week, past/future week rollover, no applicable cycle, year rollover, repeated maintenance, and concurrent creation. Implement one idempotent current-plus-four-week maintenance path using existing cycle structure and RND ownership conventions; do not delete or overwrite existing history.
- [ ] Make date applicability authoritative in API and web/mobile presentation; reconcile necessary internal activation state automatically. Remove manual Activate as a user prerequisite while retaining any backend capability required by existing dependencies.
- [ ] Allow RND to populate an empty **current** cycle; active-by-date alone must not cause the structural edit lock. Preserve the PO-generated lock.

### 3. PO lock, populations, and past-week procurement

**Entry points:** Menu Cycle mutation endpoints, `MenuCycleDay` snapshots, `ShoppingListPopulationService`, Shopping List controller/UI, `PurchaseOrderLifecycleService`, related procurement/report tests.

- [ ] Write failing tests for every cycle mutation after PO conversion and for historical read access; guard via the existing PO/snapshot relationship at conversion, including alternate edit/delete paths. Keep actual served correction possible until completed PO as currently intended.
- [ ] Re-verify the single Shopping List estimate input, preview/save behavior, per-date calculations across cycle boundaries, saved-list recalculation, and no duplicate population fields. Fix only reproduced regressions.
- [ ] Exercise an unplanned past week end to end in focused integration tests: create/populate past cycle, set estimate, Save list, convert PO, enter true historical service counts and vendor evidence/actuals, complete under the correct fiscal-year budget, and inspect PPA/Procurement Pack dates and frozen data. Preserve real event timestamps.
- [ ] Use focused commits for independently verified changes. Before starting accomplishment review, record passing cycle/procurement evidence and checkpoint the completed work.

### 4. FSS period submission and RND review on existing reports

**Entry points:** `DietListCountController`, `StoreDietListCountRequest`, `AccomplishmentReportArchiveService`, `Report`, `ReportController`, `ReportBrowser`, `PrepareSavedReport`, report generator/resource, routes, forward-only migration only if needed.

- [ ] Write failing tests for per-user 1–15 and 16–month-end progress (including February/leap year), one saved log per date, off-duty days, missing-day submit rejection, and daily logging without an applicable Menu Cycle.
- [ ] Add minimal review metadata to the **existing** accomplishment report/period record. Keep PDF generation/archive status and existing reports intact; distinguish legacy rows so they remain visible without a false approval claim.
- [ ] Implement authorized FSS Submit, Withdraw, edit-after-reject, and Resubmit; RND Approve/Reject with required 1–500-character rejection reason; server-enforce ownership, role, period identity, valid transitions, and immutable approved daily logs.
- [ ] Make submitted content exactly what RND reviews: block FSS edits while pending, reflect changed daily logs after withdrawal/rejection before resubmission, and make stale review actions fail cleanly. Approved PDF/report content must remain frozen and visible in existing Reports. Test concurrent transition cases, duplicate requests, and direct API edits.
- [ ] Test that after approval, daily-log save attempts and archive refresh/regeneration cannot change the content RND reviewed, the approved Report, or its stored PDF; cover direct API and service paths.
- [ ] Keep old generated accomplishment reports/files unchanged and readable. Ensure new unapproved periods do not appear as approved reports; RND's review queue identifies the FSS user and period.
- [ ] Enforce and test RND/Admin visibility on report lists, instances, generation, preview/download, and direct APIs: RND cannot access unsubmitted new logs or draft report copies; Admin can access approved new and legacy accomplishment reports but not the review queue or new unapproved records. Existing report `status=completed` alone must not grant approval visibility.

### 5. Focused UI and copy

**Entry points:** `frontend/app/(rnd)/food-service/menu-cycle/page.tsx`, `frontend/components/foodservice/MenuSlotRecipePage.tsx`, `frontend/components/reports/ReportsBrowser.tsx`, `frontend/components/ui/CharacterCountTextarea.tsx`, `mobile/app/(tabs)/accomplishments.tsx`, `mobile/components/ReportsScreen.tsx`, existing API helpers and tests.

- [ ] Remove specified internal labels/IDs and misleading stale-active UI only after backend behavior is correct; keep clear read-only historical viewing and responsive current/empty states.
- [ ] Add RND “To Review” within the existing Accomplishment Report Reports area, with per-FSS periods, review details, Approve/Reject, required counted note, loading/error/empty states, and keyboard/accessibility coverage.
- [ ] Keep Admin's existing Reports page limited to approved new and legacy accomplishment reports, with no “To Review” tab or review actions; keep other Admin report types as they are.
- [ ] Enhance FSS Accomplish → My Reports with progress/status and period-day traversal; link days to the existing Daily Log with the date selected. Show Submit/Withdraw only in valid states, rejection reason when relevant, and approved PDF in the existing report view. Make pending/approved daily logs visibly read-only and enforce it server-side.
- [ ] Check narrow/wide web layouts and Android phone layout with realistic long names, 13–16-day periods, text scaling, touch targets, and safe areas. Reuse existing components/helpers; do not add a new top-level page.

### 6. Documentation, verification, release, final audit

- [ ] Update only affected existing Help/FAQ and the matching canonical Food Service and FSS storyboards. Check links, role actions, dates, and report wording against the rendered app.
- [ ] Run focused backend tests first, then full backend suite, Pint, and route/cache checks for route changes; affected frontend tests, TypeScript, ESLint, and production build; affected mobile tests/type checks and Android build/export per repo rules. Record exact commands, outputs, and unrelated failures separately.
- [ ] Generate real accomplishment, PPA, and Procurement Pack PDFs from fictional records; inspect identity, status, date coverage, page count, clipping, and frozen approved/completed content.
- [ ] Deploy through the existing authorized release workflow if needed for live acceptance. Test the deployed RND web flow at phone and desktop widths and the authenticated FSS Android emulator flow, including current empty week, past backfill, population edit without procurement, submit/withdraw/reject/resubmit/approve, locks, and historical viewing. Record URL, revision, screenshots/scenarios, pass/fail; do not call local testing live acceptance.
- [ ] Audit every requirement above against code, tests, rendered UI, API authorization, reports, and actual runtime. State separately what was changed, locally verified, pushed, deployed, and live-accepted; leave unchecked items explicit. Preserve unrelated work.

## Evidence log

Add dated test and runtime evidence here during implementation. No implementation or live acceptance has been performed for this plan yet.
