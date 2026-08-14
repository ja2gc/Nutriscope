# Food Service Operations Implementation Plan

> **For maintainers:** Execute this plan test-first, one task at a time. Keep `docs/superpowers/specs/2026-08-14-food-service-operations-design.md` authoritative for behavior and scope.

**Goal:** Make the existing food-service path accurate from catalog and recipe setup through menu planning, shopping-list review, PO release, receiving, budget reconciliation, and final reports without adding inventory or a generic workflow system.

**Architecture:** Extend the current catalog, menu-cycle, shopping-list, PO lifecycle, receiving, report, and UI paths. Add only the persisted distinctions required to preserve generated calculations, manual purchase decisions, and planned-versus-actual receiving values. Backend services own readiness and completion rules; web and mobile clients render their results.

**Tech Stack:** Laravel 13 / PHP 8.4 / MySQL / PHPUnit; Next.js / React / TypeScript / Vitest; Expo React Native where existing FSS PO views consume the same API.

---

## Task 1: Add the minimal persisted fields and casts

**Files:**
- Create: `backend/database/migrations/*_add_generation_control_to_fs_items_table.php`
- Create: `backend/database/migrations/*_add_review_fields_to_shopping_list_items_table.php`
- Create: `backend/database/migrations/*_add_actual_values_to_purchase_order_items_table.php`
- Modify: `backend/app/Models/FsItem.php`
- Modify: `backend/app/Models/ShoppingListItem.php`
- Modify: `backend/app/Models/PurchaseOrderItem.php`
- Test: `backend/tests/Feature/FoodShoppingListGenerationTest.php`
- Test: `backend/tests/Feature/PurchaseOrderExecutionLockTest.php`

- [ ] Write tests proving ingredients default to generated-list inclusion, shopping rows preserve `source`, `included_in_po`, and exclusion note, and actual receiving quantities accept three decimals.
- [ ] Run the focused tests and confirm schema/attribute failures.
- [ ] Generate three Laravel migrations with `php artisan make:migration --no-interaction`; add `include_in_generated_lists`, shopping-row review fields, actual quantity/unit-price fields, and only required decimal precision changes.
- [ ] Add fillable/cast definitions. Do not modify old migrations or add stock fields.
- [ ] Run focused tests and `vendor/bin/pint --dirty`.
- [ ] Commit: `feat: add procurement review fields`

## Task 2: Correct catalog generation and recipe/profile presentation

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/FsItemController.php`
- Modify: `backend/app/Http/Resources/InventoryResource.php`
- Modify: `backend/app/Services/FSS/ShoppingListPopulationService.php`
- Modify: `backend/app/Services/MenuCycleCostService.php`
- Modify: `backend/app/Http/Controllers/FSS/MenuCycleController.php`
- Modify: `frontend/services/fsCatalogService.ts`
- Modify: `frontend/app/(rnd)/food-service/inventory/page.tsx`
- Modify: `frontend/app/(rnd)/food-service/recipes/new/page.tsx`
- Modify: `frontend/app/(rnd)/food-service/recipes/[id]/page.tsx`
- Modify: `frontend/components/foodservice/MenuSlotRecipePage.tsx`
- Test: `backend/tests/Feature/FsItemCatalogTest.php`
- Test: `backend/tests/Feature/FoodShoppingListGenerationTest.php`
- Test: existing menu-cost/profile unit and frontend tests

- [ ] Add failing API/service tests: unchecked ingredients remain valid recipe ingredients but are omitted from generated shopping rows; pre-estimate profiles return baseline values and an explicit unset purchase estimate; set estimates return scaled values using `estimate / recipe yield`.
- [ ] Add catalog checkbox `Include in generated shopping lists`, default checked, with helper text `Turn off for items purchased only when needed.` and compact badges in recipe/profile views.
- [ ] Filter only at shopping-list generation; never remove exact recipe measurements or cost/scaling data.
- [ ] Replace the silent one-person purchasing fallback with explicit unset state while preserving existing baseline recipe display.
- [ ] Run focused backend/frontend tests, TypeScript, and Pint.

## Task 3: Make menu naming, templates, and uniform estimates predictable

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/MenuCycleController.php`
- Modify: `backend/app/Http/Controllers/FSS/MenuCycleTemplateController.php`
- Modify: `backend/app/Http/Requests/FSS/StoreMenuCycleRequest.php`
- Modify: `backend/app/Http/Requests/FSS/UpdateMenuCycleRequest.php`
- Modify: `backend/app/Models/MenuCycle.php`
- Modify: `frontend/services/menuCycleService.ts`
- Modify: `frontend/app/(rnd)/food-service/menu-cycle/page.tsx`
- Test: `backend/tests/Feature/MenuCycleWorkflowGuardTest.php`
- Test: `backend/tests/Feature/MenuCyclePoSnapshotTest.php`
- Test: menu-cycle frontend tests

- [ ] Write failing tests for the date-span default name, editable custom names, copy-only template instantiation, service-date resolution, and uniform span estimate cascading without per-slot override.
- [ ] Add one shared default-name formatter on the backend and mirror only display preview client-side.
- [ ] Ensure templates copy menu structure into a new independent draft and never copy actual population or a default weekly estimate.
- [ ] Keep existing day estimate storage internally, but expose one estimate field only in generated-list flow; clear/ignore slot override for new purchase scaling.
- [ ] Fix `coveringDate`/active resolution so a future published cycle cannot displace the cycle covering the requested date.
- [ ] Run focused tests, TypeScript, and Pint.

## Task 4: Preserve generated requirements while allowing simple purchase review

**Files:**
- Modify: `backend/app/Services/FSS/ShoppingListPopulationService.php`
- Modify: `backend/app/Http/Controllers/FSS/ShoppingListController.php`
- Modify: `backend/app/Http/Requests/FSS/StoreShoppingListRequest.php`
- Modify: `backend/app/Http/Requests/FSS/UpdateShoppingListRequest.php`
- Modify: `backend/app/Http/Resources/ShoppingListResource.php`
- Modify: `backend/app/Services/Audit/Revisions/Serializers/ShoppingListRevisionSerializer.php`
- Modify: `frontend/services/procurementService.ts`
- Modify: `frontend/app/(rnd)/food-service/procurement/page.tsx`
- Test: `backend/tests/Feature/FoodShoppingListGenerationTest.php`
- Test: `backend/tests/Feature/SuppliesProcurementTest.php`
- Test: `backend/tests/Unit/ShoppingListRevisionSerializerTest.php`
- Test: procurement frontend tests

- [ ] Write failing tests that recalculation updates generated requirements but preserves manual food rows, review values, exclusions, and notes.
- [ ] Mark service-created rows `generated` and user-created rows `manual`; scope synchronization/deletion to generated rows only.
- [ ] Keep calculated baseline/scaled requirement read-only. Allow editing planned purchase quantity, purchase unit, expected price, supplier, inclusion, and optional exclusion note.
- [ ] Allow manual ingredient rows in food lists and supply catalog rows in supply lists. Keep mixed events as two independently named lists.
- [ ] Add a compact `Calculated / Manual` source badge, an `Include in PO` control, an inline exclusion note, and manual-add action to food lists using existing controls.
- [ ] Ensure excluded rows remain visible and are omitted from planned totals/readiness/PO conversion.
- [ ] Run focused tests, TypeScript, and Pint.

## Task 5: Centralize PO release readiness and budget checks

**Files:**
- Modify: `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`
- Modify: `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`
- Modify: `backend/app/Http/Resources/ShoppingListResource.php`
- Modify: `backend/app/Http/Resources/PurchaseOrderResource.php`
- Modify: `backend/app/Services/Audit/Revisions/Serializers/PurchaseOrderRevisionSerializer.php`
- Modify: `frontend/app/(rnd)/food-service/procurement/page.tsx`
- Modify: `frontend/services/procurementService.ts`
- Test: `backend/tests/Feature/FoodShoppingListGenerationTest.php`
- Test: `backend/tests/Feature/BudgetLedgerTest.php`
- Test: `backend/tests/Feature/PurchaseOrderExecutionLockTest.php`
- Test: serializer and frontend tests

- [ ] Write failing tests for one backend readiness result covering non-empty included rows, suggested-food estimate, full menu coverage, suppliers, fiscal-year budget, and planned amount within remaining allocation.
- [ ] Add a small readiness method/value array to the existing lifecycle service and expose it through the shopping-list resource.
- [ ] Make conversion reject the same backend blockers, copy only included rows, freeze planned fields, and return the existing generated PO number.
- [ ] Rename the primary CTA to `Create and release PO`; show a concise readiness checklist and the shopping-list purpose beside the PO number.
- [ ] Keep current PO numbering and lifecycle columns unless a tested defect requires change.
- [ ] Run focused tests, TypeScript, and Pint.

## Task 6: Record actual receiving values and require complete vendor evidence

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`
- Modify: `backend/app/Services/FSS/ReceivingService.php`
- Modify: `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`
- Modify: `backend/app/Models/PurchaseOrderItemCorrection.php` only if existing audit fields can safely capture actual changes
- Modify: `backend/app/Http/Resources/PurchaseOrderResource.php`
- Modify: `backend/app/Services/Audit/Revisions/Serializers/PurchaseOrderRevisionSerializer.php`
- Modify: `frontend/app/(rnd)/food-service/procurement/page.tsx`
- Modify: `frontend/components/fss/FssPurchaseOrders.tsx`
- Modify: `mobile/app/(tabs)/procurement.tsx`
- Test: `backend/tests/Feature/PurchaseOrderExecutionLockTest.php`
- Test: `backend/tests/Feature/PurchaseOrderCompletionPatternTest.php`
- Test: `backend/tests/Unit/ReceivingServiceNormalizeTest.php`
- Test: relevant web/mobile tests

- [ ] Write failing tests proving planned values stay unchanged, actuals prefill from planned in the resource, FSS/RND can submit decimal actual quantity and actual unit price, and receipt total can derive quantity without a persisted calculator mode.
- [ ] Require supplier, reviewed valid actual lines, at least one receipt, at least one proof, and explicit receive action before a vendor group becomes received.
- [ ] Keep OR nullable; return/display `Not provided` when empty. Uploading evidence must never change status by itself.
- [ ] Compute actual totals centrally. Update catalog price/default vendor only on the explicit received transition after both evidence types exist.
- [ ] Reuse current attachment storage/gallery and correction/revision audit path; add no separate invoice or approval model.
- [ ] Run focused tests, TypeScript/mobile checks, and Pint.

## Task 7: Correct completion, population, budget reconciliation, and report data

**Files:**
- Modify: `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`
- Modify: `backend/app/Http/Controllers/FSS/MealPrepLogController.php`
- Modify: `backend/app/Listeners/BudgetLedgerListener.php`
- Modify: `backend/app/Models/Budget.php`
- Modify: `backend/app/Models/BudgetLedger.php`
- Modify: `backend/app/Http/Resources/BudgetResource.php`
- Modify: `backend/app/Services/Reports/Generators/ProcurementPackGenerator.php`
- Modify: `backend/app/Services/Reports/ReportBrowser.php`
- Modify: `backend/app/Http/Controllers/ReportController.php`
- Modify: `backend/app/Http/Resources/ReportResource.php`
- Modify: `frontend/app/(rnd)/food-service/menu-cycle/_components/ServiceLogPanel.tsx`
- Modify: `frontend/components/reports/ReportsBrowser.tsx`
- Modify: `frontend/components/audit/history/types/PurchaseOrderHistory.tsx`
- Test: `backend/tests/Feature/PurchaseOrderCompletionPatternTest.php`
- Test: `backend/tests/Feature/BudgetLedgerTest.php`
- Test: report generator/controller tests
- Test: served-population frontend tests

- [ ] Write failing tests for track-specific completion: suggested Food requires all received vendor groups plus actual population for every service date; manual Food and Supplies require vendor evidence/receiving only.
- [ ] Require positive actual served population before Meal Prep completion for menu-generated service dates; keep FSS accomplishment separate.
- [ ] Reconcile final actual spend against the planned reservation/remaining fiscal-year budget and reject invalid negative availability.
- [ ] Use actual quantities/prices/totals in final procurement output while retaining planned comparison values.
- [ ] Resolve private stored-object receipt/proof files in Procurement Pack; keep legacy-path compatibility. Mark unfinished previews incomplete and final/archive output only after completed lock.
- [ ] Rename `Actual cost/head/day` to `Food purchase cost per served patient-day` in API/report/UI/help text.
- [ ] Run focused tests, TypeScript, and Pint.

## Task 8: Add route audit coverage and end-to-end UX checks

**Files:**
- Modify: `backend/config/audit.php` if any unsafe endpoint is added
- Modify: `backend/tests/Feature/Audit/AuditInventoryContractTest.php` only if an intentional contract fixture changes
- Modify: existing procurement/menu audit tests
- Modify: frontend procurement/menu tests

- [ ] Prefer existing routes. If a dedicated receive endpoint is clearer and smaller than overloading PATCH, add it and immediately add audit classification/revision recording.
- [ ] Test role boundaries: RND review/release, FSS receiving/population, both evidence uploads as currently authorized, final locks immutable.
- [ ] Test the shortest happy paths and blocker messages in backend and UI.
- [ ] Run audit inventory, focused backend suites, frontend tests, TypeScript, and Pint.

## Task 9: Refresh demo seeders and user/developer documentation

**Files:**
- Modify: `backend/database/seeders/FsCatalogSeeder.php`
- Modify: `backend/database/seeders/FoodServiceDemoSeeder.php`
- Modify: `backend/tests/Unit/FoodServiceDemoSeederSourceTest.php`
- Modify: `docs/modules/Flowcharts/Food Service Operations.md`
- Modify: `docs/modules/fss.md`
- Modify: `docs/modules/rnd.md`
- Modify: `docs/modules/admin.md`
- Modify: `docs/ROLE-HOW-TO.md`
- Modify: `docs/FAQ.md`
- Modify: `docs/module-workflow-flowchart.md`
- Modify: `docs/STORYBOARD.md`
- Modify: `docs/modules/STORYBOARD-SCREENSHOT-GUIDE.md`
- Modify: `frontend/lib/helpContent.ts`
- Create: `docs/developer/food-service-operations-maintenance.md`

- [ ] Write/update seeder source tests for current-relative menu/procurement dates, opt-out pantry ingredients, manual/generated rows, released/receiving/completed examples, optional OR, evidence, actual decimals, population, budget, and final outputs.
- [ ] Update seeders through public/business paths where practical so demo data obeys lifecycle requirements.
- [ ] Rewrite the flowchart and operating docs to exactly match tested behavior and explicitly list excluded inventory/event concerns.
- [ ] Add a maintenance guide mapping schema, models, services, controllers, resources, clients, screens, tests, seeders, lifecycle invariants, attachment storage, report behavior, and safe extension points.
- [ ] Update Help text but do not implement the deferred storyboard tab.
- [ ] Run seeder tests, a fresh seeded database smoke test where safe, help-content tests, and documentation searches for stale labels/rules.

## Task 10: Create the external video storyboard script

**Files:**
- Create outside repository: `C:\Users\jared\Documents\Food Service Operations Video Storyboard.md`

- [ ] Write a start-to-finish simulation using newly created records: catalog ingredients/supplies, purchase-only-when-needed item, recipe, template, weekly cycle, uniform estimate, reviewed generated list, manual addition/exclusion, release, vendor evidence, decimal actuals, optional OR, actual population, completion, budget, and reports.
- [ ] For every scene include role, starting page, exact action, expected screen result, narration, and reset/cleanup note where needed.
- [ ] Keep the narration faithful to tested current behavior and explicitly avoid seeded-record dependency.
- [ ] Verify all route/page labels against the final UI.

## Task 11: Full verification, neutral delivery, and push

- [ ] Run `git diff --check` and confirm `.codex/config.toml` remains unrelated and unstaged.
- [ ] Run full backend PHPUnit suite and `vendor/bin/pint --dirty`.
- [ ] Run frontend Vitest, lint, TypeScript, and production build sequentially.
- [ ] Run relevant mobile tests/typecheck if mobile files changed.
- [ ] Run a final search for stale `Actual cost/head/day`, receipt-auto-received behavior, silent estimate fallback, and documentation contradictions.
- [ ] Review the complete diff against the approved design; remove speculative or duplicate code.
- [ ] Commit remaining task files with neutral Conventional Commit messages and no AI attribution.
- [ ] Push `main` to `origin` and verify the remote ref equals local HEAD.
