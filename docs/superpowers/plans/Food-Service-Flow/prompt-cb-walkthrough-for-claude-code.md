# Prompt C+B Walkthrough And Claude Code Handoff

Date finished: 2026-06-27

Scope completed: Prompt C+B through Phase 1 Step 7. D+E budget ledger/report rewrites were not started. C+B only adds the PO completion hooks and frozen values D+E needs.

## Step Status

- [x] Preflight / Phase 1 shipped-state review
  - Verified the existing date-driven shopping-list flow in code and tests before extending it.
  - Kept `MenuCycle::coveringDate()` as the procurement date resolver.
  - Preserved `shopping_lists.menu_cycle_id` removal and `coverage_status` / `uncovered_dates`.

- [x] Step 1: Estimated Population + Recipe Scaling
  - Added shopping-list estimate population tracking and timestamp cascade.
  - Added `ShoppingListPopulationService` as the shared calculation/cascade path.
  - Menu cycle activation now blocks planned days missing `estimate_population`.
  - Shopping-list generation now blocks only dates that have menu items and missing/zero population.
  - Individual day population changes recalculate covering draft lists.
  - Files: `backend/app/Services/FSS/ShoppingListPopulationService.php`, `ShoppingListController.php`, `MenuCycleController.php`, `MenuCycleDay.php`, `ShoppingList.php`, migration `2026_06_26_000001_*`.

- [x] Step 2: Shopping List States + Tabs
  - Shopping lists now use only `draft` and `converted`.
  - FSS cannot generate shopping lists; generation is RND-only.
  - Converted shopping lists are structurally read-only.
  - Procurement UI has Ingredients and Supplies item tabs backed by inventory row filtering.
  - Convert button replaces status-forwarding UI for PO conversion.
  - Files: shopping-list requests/resources/controllers, `backend/routes/api.php`, `frontend/app/(rnd)/food-service/procurement/page.tsx`, `frontend/services/procurementService.ts`, migration `2026_06_26_000002_*`.

- [x] Step 3: Purchase Order Flow Redesign
  - Convert now creates one PO per shopping list.
  - Vendor sub-record model name chosen: `PurchaseOrderVendorGroup`, matching existing `PurchaseOrder*` naming.
  - Vendor groups hold supplier, OR number, receipt/proof attachments, status, totals, and stock-in timestamps.
  - PO lifecycle states added: `open_execution`, `completed`, `archived`.
  - PO structural lines freeze at conversion; Phase 2 operational edits are on vendor groups.
  - Both RND and FSS can update vendor group OR/status and upload receipt/proof images.
  - FSS still cannot create/delete POs or generate shopping lists.
  - Completion triggers when all vendor groups have receipts and every planned date in the span has `served_population`.
  - Completion calculates `actual_budget_per_head_per_day = sum(vendor_group.total_amount) / sum(served_population)`.
  - Event hooks added for D+E: `PurchaseOrderConverted` and `PurchaseOrderCompleted`.
  - Files: `PurchaseOrderController.php`, `PurchaseOrderLifecycleService.php`, `ReceivingService.php`, PO models/resources, routes, migration `2026_06_26_000003_*`.

- [x] Step 4: Procurement Page Restructure
  - Procurement UI now exposes procurement events from shopping lists and their single linked PO.
  - Level 1 shows span, estimated total, vendor count, lifecycle, coverage, actions.
  - Level 2 shows the one PO and vendor group rows.
  - Level 3 shows one vendor group with read-only items, OR input, receipt upload, and proof upload.
  - Settings tab was not present and remains absent.
  - Breadcrumb-style in-page navigation is used for event -> vendor group.
  - Status values are plain text, not badge pills.
  - Files: `frontend/app/(rnd)/food-service/procurement/page.tsx`, `frontend/services/procurementService.ts`, new Next proxy routes under `frontend/app/api/fss/purchase-order-vendor-groups/` and `purchase-orders/[id]/ppa/`.

- [x] Step 5: PPA Auto-Generation
  - `program_project_activities` table added.
  - PPA snapshot auto-generates in the same transaction as shopping-list conversion.
  - Planning fields freeze at Phase 2: activity, menu snapshot, target date range, estimated total cost, estimated output patients.
  - Execution fields update and freeze at Phase 3: actual total cost and actual output patients.
  - RND can read PPA through `GET /api/fss/purchase-orders/{purchase_order}/ppa`; FSS receives 403.
  - Files: `ProgramProjectActivity.php`, `PurchaseOrderLifecycleService.php`, `PurchaseOrderController.php`, routes, migration `2026_06_26_000003_*`.

- [x] Step 6: Menu Cycle List View
  - Existing menu-cycle page extended; no new page created.
  - API returns active cycle first, then chronological cycles.
  - API exposes `plan_days` flags for Monday-Sunday.
  - UI shows active cycle with left border and inline text, not a badge.
  - UI shows per-day planned/empty text for each weekday.
  - FSS read-only behavior remains enforced by existing route groups and page `readOnly` handling.
  - Files: `MenuCycleController.php`, `MenuCycleResource.php`, `frontend/services/menuCycleService.ts`, `frontend/app/(rnd)/food-service/menu-cycle/page.tsx`.

- [x] Step 7: Tests + Verification
  - Added/updated tests for population cascade, state machine, FSS restrictions, supplies, one PO with vendor groups, vendor operational edits, PPA access, Phase 3 completion, event hooks, event API payloads, and menu-cycle list plan flags.
  - Verification results:
    - Focused lifecycle/API tests: 7 passed, 39 assertions.
    - Food-service suite: 82 passed, 264 assertions.
    - Full backend suite: 656 passed, 2162 assertions.
    - Frontend typecheck: `npx tsc --noEmit` clean.

## Alignment Review

- No manual PO create endpoint was added.
- No standalone vendor PO creation path remains in the conversion flow.
- `finalized` shopping-list state was removed from requests/factories/UI paths touched by C+B.
- `served_population` stays on meal-prep logs and diet-list counts; it does not write to `estimate_population`.
- `estimate_population` stays on menu-cycle days and shopping lists; it does not write to `served_population`.
- D+E budget ledger was not implemented in C+B. Only events and locked PO/PPA values were added for D+E to consume.

## Claude Code Continuation Notes For Prompt D+E

Start D+E from these C+B integration points:

- Use `PurchaseOrderCompleted` as the budget-ledger trigger.
- Use `purchase_orders.lifecycle_status = completed` as the source of finalization.
- Use `purchase_orders.total_amount` as the final locked PO total after completion.
- Use `purchase_orders.actual_budget_per_head_per_day` for actual per-head/day.
- Use `program_project_activities.execution_frozen_at` to detect frozen PPA execution data.
- For spend by supplier, use `purchase_order_vendor_groups` joined to completed POs.
- Do not deduct budget on `PurchaseOrderConverted`; that is Phase 2 only.
- If fiscal-year allocation is missing, D+E should block or hold finalization before creating ledger deduction. C+B currently completes PO without ledger because ledger does not exist yet.

Recommended first D+E files to inspect:

- `backend/app/Events/PurchaseOrderCompleted.php`
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`
- `backend/app/Models/PurchaseOrder.php`
- `backend/app/Models/PurchaseOrderVendorGroup.php`
- `backend/app/Models/ProgramProjectActivity.php`
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`
- `frontend/services/procurementService.ts`
- `frontend/app/(rnd)/food-service/procurement/page.tsx`
