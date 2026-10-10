# Food Service UI and Menu Cycle Usability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve existing Food Service and Menu Cycle workflows while preserving current APIs, calculations, relationships, and state rules.

**Architecture:** Keep current React pages, services, API contracts, shared controls, and separate Menu Cycle template entity. Make focused UI changes and add focused frontend regression coverage. Do not add backend behavior or a second picker/template system.

**Tech Stack:** Next.js 16.3.3, React 19.2.4, TypeScript, Vitest, existing Food Service services and UI components.

**Spec:** User-approved scope and design in this task, dated 2026-10-04.

## Global Constraints

- Preserve Shopping List calculations, recipe scaling, unit conversion, PO snapshots/completion, inventory updates, Menu Cycle state, approvals, locking, and budgets.
- Reuse the existing DatePicker, InfoHint, catalog search, Shopping List/PO services, and Menu Cycle template APIs.
- Do not create a second ingredient or template system.
- Food Service values display at no more than two decimals; input/stored precision stays unchanged.
- Keep every Menu Cycle activation handler and state rule unchanged.
- Match existing interface patterns; keep layouts compact, accessible, and free of decorative clutter.
- Treat later user steering as additive unless the user clearly replaces or cancels this scope; finish the approved product work before handling follow-on meta-scope.

---

### Task 1: Audit Log date range labels

**Files:**
- Modify: `frontend/components/audit/AuditFilters.tsx`
- Test: `frontend/components/audit/AuditFilters.test.tsx`

**Interfaces:**
- Reuse shared `DatePicker` with its `label` and `ariaLabel` props.

- [x] Write a DOM test rendering AuditFilters and asserting visible Start date and End date labels and exactly two date picker groups.
- [x] Run the focused test and confirm it fails because labels are absent.
- [x] Pass matching visible labels to the two existing DatePicker instances; preserve date range fields and filtering callbacks.
- [x] Run the focused test and existing DatePicker tests.

### Task 2: Shopping List create, rename, copy, and draft item flows

**Files:**
- Modify: `frontend/app/(rnd)/food-service/procurement/page.tsx`
- Modify or add pure formatting helpers in `frontend/services/procurementService.ts` only if needed.
- Test: focused Procurement Shopping List tests under `frontend/app/(rnd)/food-service/procurement/`.

**Interfaces:**
- Continue using existing `createShoppingList`, `generateByDates`, `searchCatalog`, `addListItem`, and `updateShoppingList` contracts.

- [x] Add failing tests for plain-text copy output (every item as name, quantity, and unit), required Suggested List name, and Manual List button behavior.
- [x] Add a failing draft-add test proving a selected catalog item sends its existing public UUID through `addListItem` and shows a recoverable error when save fails.
- [x] Run focused tests and confirm failures match missing behaviors.
- [x] Add compact copyable list card for food, manual, and supplies lists with copy confirmation; remove source badges such as Generated from item names.
- [x] Make Manual food list and New supplies list buttons create a draft and open its detail; retain in-detail pencil rename.
- [x] Require non-empty name before Suggested List save and keep in-detail pencil rename.
- [x] Keep item add on existing catalog flow; add pending/error feedback without changing data relationship or API payload.
- [x] Run Procurement Shopping List and existing navigation tests.

### Task 3: Procurement and Inventory table cleanup

**Files:**
- Modify: `frontend/app/(rnd)/food-service/procurement/page.tsx`
- Modify: `frontend/app/(rnd)/food-service/inventory/page.tsx`
- Modify: `frontend/components/foodservice/MenuSlotRecipePage.tsx` for its ingredient behavior column.
- Test: focused Procurement, Inventory, and Menu Slot UI contract/component tests.

**Interfaces:**
- Reuse existing pagination, inventory catalog values, list/PO services, and role checks.

- [x] Add failing assertions for separate Auto grocery/Purchase when needed column, removed Suggested star, Status header/friendly state labels, proper edit icon, and two-decimal display.
- [x] Add failing Inventory form test for a category select with existing catalog categories and a retained legacy category while editing.
- [x] Run focused tests and confirm expected failures.
- [x] Move grocery flags into their own column; remove Generated/source and star decorations; map lifecycle values to user-facing Status without exposing `open_execution`.
- [x] Keep existing pagination; use pencil for editable records and retain completed delete as absent or disabled according to current permission behavior.
- [x] Use Qty, Unit, and Cost/unit terminology; format display-only values to at most two decimals without changing input steps.
- [x] Replace free-text category with select using current catalog values; preserve current legacy value on edit.
- [x] Run focused Inventory/Procurement tests.

### Task 4: Purchase Order item detail simplification

**Files:**
- Modify: `frontend/app/(rnd)/food-service/procurement/page.tsx`
- Modify: `mobile/app/(tabs)/procurement.tsx`
- Read: `mobile/AGENTS.md` and installed Expo docs before mobile edits.
- Update: `frontend/components/foodservice/receiving-contract.test.ts`
- Test: focused PO detail and mobile receiving tests.

**Interfaces:**
- Preserve PO snapshot, actual quantity/price, vendor groups, receiving, and completion APIs.

- [x] Add failing UI test asserting Item, Actual Quantity, locked Unit, Actual Cost/unit, Actual Total, and retained collapsed planned/actual details with only the three requested comparison values absent.
- [x] Run focused test and confirm failure.
- [x] Keep the requested actual PO fields in the row; source Unit from the Shopping List item and show it read-only. Keep Planned purchase and Actual purchased in collapsed Calculation details.
- [x] Keep vendor/receiving workflow controls and underlying calculations unchanged.
- [x] Run PO tests and check whether PurchaseValueComparison has any remaining consumers before modifying/removing it.

### Task 5: Menu Cycle templates and name edit

**Files:**
- Modify: `frontend/app/(rnd)/food-service/menu-cycle/page.tsx`
- Modify: `frontend/services/menuCycleService.ts` only for a narrowly required existing-service wrapper.
- Test: extend `frontend/app/(rnd)/food-service/menu-cycle/menu-template-crud-contract.test.ts`; add focused cycle list/editor tests.

**Interfaces:**
- Reuse `listTemplates`, `getTemplate`, `saveTemplate`, `saveCycle`, and existing template entity/routes.

- [x] Add failing tests that template save from an unsaved draft does not create a cycle; loading a template updates the current draft; cycle list name edit sends a name-only save and updates the displayed name.
- [x] Run focused tests and confirm failures.
- [x] Add Templates list/modal action in CycleEditor; view/load template into the current unsaved grid without calling `instantiateTemplate`.
- [x] Save current plan directly with `saveTemplate` when Save as Template is used, so templates stay outside cycle records.
- [x] Add pencil name editor in cycle list; commit automatically on blur/Enter/back and show saved name after response.
- [x] Keep Activate logic and state transitions unchanged; keep plan Save action and existing separate template management.
- [x] Run menu template and served-population tests.

### Task 6: Menu slot tooltip and ingredient addition

**Files:**
- Modify: `frontend/components/foodservice/MenuSlotRecipePage.tsx`
- Test: `frontend/components/foodservice/MenuSlotRecipePage.test.tsx`

**Interfaces:**
- Reuse `InfoHint`, `searchCatalog`, and `updateMenuSlotRecipe`; keep current catalog public UUID flow.

- [x] Add a failing test showing the exact tooltip copy is inside existing InfoHint and selecting an Inventory result adds it to the draft.
- [x] Run the focused test and confirm failures.
- [x] Replace inline tooltip-like copy with existing InfoHint using exact approved sentence.
- [x] Fix only the broken existing catalog-add behavior revealed by the test; preserve payload and save flow.
- [x] Run MenuSlotRecipePage tests.

### Task 7: Full verification and final review

**Files:**
- Review all changed frontend files, tests, and affected existing Food Service documentation/storyboard.

- [x] Run all affected Vitest tests, full frontend suite, and mobile tests using the matching local mobile dependency install.
- [x] Run frontend and mobile TypeScript checks, ESLint, production Webpack build, and `git diff --check`.
- [x] Review diff for backend/state/calculation changes, accessible labels, internal status exposure, and error paths; no backend or service implementation files changed.
- [x] Update canonical Food Service storyboard because the Menu Cycle, Shopping List, and PO demo steps changed.
- [x] Check authenticated localhost screens and capture screenshots in the isolated preview environment.
- [x] Return a checklist covering every user scope item, each changed behavior, exact verification evidence, and the final browser location.

## Additions after the first live-browser pass

The user extended the approved scope with these follow-up UI details:

- [x] Keep Shopping List names plain; the row pencil opens the draft, and the detail heading pencil renames it. Keep rows compact and reflow them into labeled cards at mobile widths.
- [x] Reuse the existing collapsible component for the copy card, closed by default; render compact item/quantity pairs in four, three, two, then one column as available width decreases.
- [x] Remove the Per-day Plan column from the Menu Cycle list without changing cycle state or plan data.
- [x] Replace Menu Slot unit text fields with the shared `CATALOG_UNIT_OPTIONS` select; retain any existing unit value that is outside the shared catalog set.
- [x] Size shared DatePicker controls to their longest label/value using `ch` units so every consumer scales with text and font size.
- [x] Keep Audit Logs date groups compact and left-aligned with responsive wrapping; use visible `MM`, `DD`, and `YYYY` placeholders with full accessible field names.
- [x] In PO-locked menu details, show only the scaled quantity needed and a plain unit value; hide the ingredient recipe-quantity control. Keep recipe quantity and unit editing only when the slot is editable.
- [x] Hide the Recipe makes line in read-only menu details and remove baseline cost/helper text from the user-facing menu details view.
- [x] Remove the decorative star/magic icon from the Suggest from Menu button.
- [x] Reflow the estimated population / budget / total procurement card into a stable responsive grid and keep it visible by default; remove the “Applies uniformly across the selected span” and calculation formula helper copy.
- [x] Format numeric displays to at most two decimals by default while preserving full stored precision and unrestricted edited precision.
- [x] Verify list actions, responsive layout, default-collapsed copy card, Menu Cycle columns, shared units, and locked Menu Slot display with tests and the authenticated browser.

The user also asked for the Procurement estimate card’s total cost to remain in a normal flow at responsive widths rather than sticking at the lower-right edge. This is included in the responsive grid item above.

### Live browser evidence

- Worktree revision: branch `food-service-ui`, base commit `1de0c417`, task changes uncommitted.
- Isolated preview: `http://127.0.0.1:3001/food-service/procurement`; an earlier 390×844 pass confirmed the compact list and one-column procurement summary. Latest action regression and live desktop AX checks confirm the row pencil opens the draft and the name is plain text. Copy card starts collapsed. “Suggest from Menu” contains no icon.
- Locked Menu Slot: `http://127.0.0.1:3001/food-service/menu-cycle/f6cb2600-bcf8-46c8-90e5-98508a7b246a/lines/58f30aba-a863-4429-b832-a7c25650a3ac`. Read-only view shows 344 pc and 2.58 kg; no Recipe makes, recipe quantity input, unit picker, or scaling helper copy. Unit values are static.
- PO `PO-1002-100526`: Planned total started collapsed and expanded to the PO snapshot total; the vendor item view showed `Actual Cost/unit` and a static Shopping List Unit.
- Empty Manual Food List: no “Before PO release” helper or blocker copy appeared, and Create and release PO remained disabled.
- Audit Logs: Start and End groups sit together instead of stretching across the date-range fieldset. Controls show MM, DD, YYYY; full accessible names remain. `flex-wrap` stacks both groups when space is narrow. Final browser location is `http://127.0.0.1:3001/admin/audit-logs`.
- Preview server log showed Google Fonts requests blocked by the local network sandbox; Next.js used its fallback font and all tested pages/API requests returned 200. No list items, PO values, menu records, or inventory records were saved during verification.

### Follow-up scope added during final review

- [x] Keep the Procurement summary visible by default while preserving its responsive layout. The user requested no additional live-browser test for this follow-up; the focused Procurement regression test covers the final behavior.
- [x] Remove the “Before PO release” blocker text panel while leaving PO release readiness and its disabled state unchanged.
- [x] Add a default-collapsed Planned total disclosure to web and native PO details, using the PO snapshot total; label actual item price “Actual Cost/unit” to match Inventory terminology.
- [x] Restore the per-item Calculation details disclosure on web and native PO views. Keep Planned purchase and Actual purchased there; remove only Calculated need, Quantity difference, and Cost difference. Keep existing Actual Total visible.
