# Foodservice Analysis And Restructure Plan

> For execution agent: this is a research and planning handoff only. Do not treat this file as already implemented. Use `superpowers:subagent-driven-development` or `superpowers:executing-plans` before implementation. Use Laravel Boost and `backend/.agents/skills/laravel-best-practices` for Laravel changes.

**Goal:** Restructure Food Service workflow so inventory is a backend reference catalog, recipes/menu cycles drive food procurement, supplies procurement is independent, POs freeze structural data, budgets are ledger-first, and retired reports/insights are removed.

**Architecture:** Keep `fs_items` as the shared catalog for ingredients and supplies. Split procurement behavior with a new `procurement_track` value on shopping lists and POs instead of duplicating all procurement tables. Store immutable PO snapshots on menu-cycle day cells and audit only allowed open-execution corrections.

**Tech Stack:** Laravel 13.11, PHP 8.4, MySQL, Sanctum API, Next.js frontend, Expo/React Native mobile.

**Review Basis:** Rechecked Laravel schema through Boost on 2026-06-27. Current DB still has stock-centered inventory fields, old menu-cycle statuses, budget ledger `procurement_span`, and retired report types.

---

## UI

### Files to touch
- `frontend/app/(rnd)/food-service/procurement/page.tsx`
- `frontend/app/(rnd)/food-service/menu-cycle/page.tsx`
- `frontend/app/(rnd)/food-service/recipes/page.tsx`
- `frontend/components/reports/ReportsBrowser.tsx`
- `frontend/app/(rnd)/food-library/page.tsx`
- `frontend/components/foodservice/SuppliersPanel.tsx`
- `frontend/app/admin/users/page.tsx`

### Files to delete
- None for general UI rules.

### Existing variables, column names, and model names to reuse
- `deleteRowKey` in `frontend/app/(rnd)/food-service/inventory/page.tsx:301` - existing delete confirmation state. Reuse only if page is not deleted during transition.
- `handleDelete` in `frontend/app/(rnd)/food-service/inventory/page.tsx:339` - existing delete action pattern. Prompt source: `UI > All tables must have an action column with Edit and Delete buttons`. Plan use: reference for action-cell behavior before inventory page removal.
- `CycleList` in `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:198` - current menu-cycle table component. Prompt source: `UI`, `Menu Cycle > list should still be accessible`. Plan use: move name click behavior into action column.
- `remove` in `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:213` - existing menu-cycle delete action. Prompt source: `UI`. Plan use: keep delete inside action cell only.
- `RecipeProfilePanel` in `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:59` - profile/edit-like food-item display panel. Prompt source: `Menu Cycle > cell with food should display UI similar to edit recipe`. Plan use: adapt as read-only cell detail panel.
- `FULL_CATALOG` in `frontend/components/reports/ReportsBrowser.tsx:38` - report catalog list. Prompt source: `Reports > remove Dietary Cashbook, Budget, Inventory`. Plan use: remove retired entries.
- `ADMIN_CATALOG` in `frontend/components/reports/ReportsBrowser.tsx:52` - admin report catalog list. Prompt source: `Reports > remove Budget`. Plan use: remove admin budget report.

### New variables, columns, or model names to create
- `ActionCell` component, TypeScript React component, purpose: shared edit/delete action cell for food-service tables.
- `isNameClickable` should not be created. Prompt source: `UI > Delete or open actions should not be triggered by clicking the row name`. Plan use: prevent row-name open/delete regressions.

### Migrations to add, modify, or drop
- None. UI-only concern.

### File references
- `frontend/app/(rnd)/food-service/procurement/page.tsx:665` - list name opens detail. Why referenced: violates row-name open rule. Prompt source: `UI > Delete or open actions should not be triggered by clicking the row name`. Plan use: move open/edit into action column.
- `frontend/app/(rnd)/food-service/procurement/page.tsx:666` - edit icon beside name. Why referenced: direct violation. Prompt source: `UI > Remove any edit action beside the name`. Plan use: remove icon from name cell.
- `frontend/app/(rnd)/food-service/procurement/page.tsx:684` - delete action exists without paired edit. Why referenced: action column incomplete. Prompt source: `UI > action column with Edit and Delete buttons`. Plan use: add action column with both commands.
- `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:264` - cycle name opens cycle. Why referenced: row-name open conflict. Prompt source: `UI`. Plan use: make name text plain.
- `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:281` - action cell currently has Open/Delete. Why referenced: action column exists but labels/actions need standardization. Prompt source: `UI`. Plan use: use Edit/Delete for RND and View/Delete only if read-only role requires view wording.
- `frontend/app/(rnd)/food-service/recipes/page.tsx:157` - recipe name link. Why referenced: edit/open beside name conflict. Prompt source: `UI`. Plan use: make recipe name plain and keep edit/delete in action cell.
- `frontend/components/reports/ReportsBrowser.tsx:413` - archived report row click opens preview. Why referenced: row click open conflict. Prompt source: `UI`. Plan use: remove row click and add explicit View/Open action button if report remains.
- `frontend/components/foodservice/SuppliersPanel.tsx:207` - existing `Actions` header. Why referenced: nearby compliant table. Prompt source: `UI`. Plan use: pattern reference.
- `frontend/app/(rnd)/food-library/page.tsx:500` and `frontend/app/(rnd)/food-library/page.tsx:615` - food and recipe tables already have Actions columns. Why referenced: local UI pattern. Prompt source: `UI`. Plan use: use as consistency reference.
- `frontend/app/admin/users/page.tsx:388` - admin Users table Actions header. Why referenced: existing admin action-column pattern. Prompt source: `UI`. Plan use: consistency reference.

---

## Nutritional Care

### Files to touch
- None.

### Files to delete
- None.

### Existing variables, column names, and model names to reuse
- None.

### New variables, columns, or model names to create
- None.

### Migrations to add, modify, or drop
- None.

### File references
- None. Prompt source: `Nutritional Care - (none)`. Plan use: no work.

---

## Food Service Workflow - Inventory

### Files to touch
- `backend/routes/api.php`
- `backend/app/Http/Controllers/FSS/InventoryController.php`
- `backend/app/Http/Requests/FSS/StoreInventoryRequest.php`
- `backend/app/Http/Requests/FSS/UpdateInventoryRequest.php`
- `backend/app/Http/Resources/InventoryResource.php`
- `backend/app/Models/FsItem.php`
- `backend/app/Models/Inventory.php`
- `backend/app/Services/FSS/ReceivingService.php`
- `backend/app/Services/FSS/ShoppingListPopulationService.php`
- `frontend/components/layout/Sidebar.tsx`
- `frontend/services/inventoryService.ts`
- `frontend/app/api/fss/inventory/route.ts`
- `frontend/app/api/fss/inventory/[id]/route.ts`
- `frontend/app/api/fss/inventory/[id]/restock/route.ts`
- `frontend/app/api/fss/inventory/rows/route.ts`
- `mobile/app/(tabs)/_layout.tsx`

### Files to delete
- `frontend/app/(rnd)/food-service/inventory/page.tsx`
- `mobile/app/(tabs)/inventory.tsx`

### Existing variables, column names, and model names to reuse
- `FsItem` in `backend/app/Models/FsItem.php:19` - catalog item model. Prompt source: `Inventory > backend reference list of items available for procurements`. Plan use: keep as reference catalog for ingredients and supplies.
- `fs_items` in `backend/app/Models/FsItem.php:24` - current catalog table. Prompt source: `Inventory`. Plan use: do not create duplicate catalog table.
- `kind` in live schema `fs_items.kind varchar(20)` and migration `backend/database/migrations/2026_06_11_000100_create_fs_items_table.php:18` - item category (`ingredient`, `supply`, currently also `ready_to_eat`). Prompt source: `Ingredients or Supplies in Inventory`. Plan use: reuse to split ingredients and supplies.
- `base_unit` in `backend/app/Models/FsItem.php:28` - recipe/base unit. Prompt source: `Recipes and Food Items > unit conversion based on inventory unit`. Plan use: source unit for conversion.
- `purchase_unit` in `backend/app/Models/FsItem.php:28` - procurement package unit. Prompt source: `Inventory > only cost per unit`. Plan use: reference purchase display if needed.
- `purchase_price` in `backend/app/Models/FsItem.php:28` and cast at `backend/app/Models/FsItem.php:33` - catalog cost. Prompt source: `Inventory > only cost per unit`. Plan use: keep cost per unit, round/display to 2 decimals.
- `units_per_purchase` in `backend/app/Models/FsItem.php:28` - purchase conversion count. Prompt source: `unit/cost`. Plan use: keep for package-to-base cost conversion.
- `default_supplier_id` in `backend/app/Models/FsItem.php:29` - current vendor default. Prompt source: `vendor auto-updates from latest procurement unless manually locked`. Plan use: reuse as vendor value; add lock columns.
- `Inventory` in `backend/app/Models/Inventory.php:10` - current stock model. Prompt source: `Inventory backend reference only`. Plan use: keep backend stock ledger if needed, remove user-facing stocking.
- `quantity_in_stock` in `backend/app/Models/Inventory.php:18` and schema `inventory.quantity_in_stock decimal(10,2)` - current stock qty. Prompt source: `Supplies in inventory have no qty field when creating/editing`. Plan use: keep backend-only; remove direct create/edit forms and FSS nav.
- `unit_price` in `backend/app/Models/Inventory.php:19` - backend stock unit price. Prompt source: `Inventory backend reference`. Plan use: backend-only cost reference, not FSS edit field.
- `listInventoryRows` in `frontend/services/inventoryService.ts:96` - existing lookup call. Prompt source: `Inventory > backend reference list`. Plan use: keep as read-only catalog lookup for recipes/procurement.

### New variables, columns, or model names to create
- `fs_items.default_supplier_locked_at`, nullable timestamp, purpose: when vendor suggestion was manually locked.
- `fs_items.default_supplier_locked_by`, nullable foreign id to `users.id`, purpose: who locked vendor suggestion.
- `vendor_locked`, frontend boolean derived from `default_supplier_locked_at !== null`, purpose: show locked/unlocked vendor state.
- `toggleDefaultSupplierLock`, backend controller/service method name, purpose: lock/unlock catalog vendor suggestion.
- `latestProcurementVendorService`, service class `App\Services\FSS\LatestProcurementVendorService`, purpose: resolve latest received vendor for an `fs_item_id` and update `default_supplier_id` unless locked.

### Migrations to add, modify, or drop
- Add `2026_06_27_000004_add_supplier_lock_to_fs_items_table.php`: add `default_supplier_locked_at timestamp nullable`, `default_supplier_locked_by foreignId nullable constrained users nullOnDelete`, and index `default_supplier_id`.
- Add `2026_06_27_000005_make_inventory_direct_stock_fields_backend_only.php`: do not drop `inventory.quantity_in_stock`; instead document/remove direct UI/API mutation. If schema must enforce supplies no qty on create/edit, leave columns for backend and remove request validation.
- Do not modify `2026_06_02_210751_create_inventory_table.php`; Laravel best practice says deployed migrations are immutable.

### File references
- `backend/routes/api.php:192` - `inventory/rows`. Why referenced: read-only catalog endpoint to keep. Prompt source: `Inventory > backend reference list`. Plan use: preserve.
- `backend/routes/api.php:193` - `apiResource('inventory')`. Why referenced: exposes write endpoints to FSS/RND. Prompt source: `Inventory > Remove inventory page for FSS; no stocking`. Plan use: limit to read-only or role-guard writes.
- `backend/routes/api.php:194` - `inventory/{inventory}/restock`. Why referenced: direct stocking endpoint. Prompt source: `FSS no stocking`. Plan use: remove route.
- `frontend/components/layout/Sidebar.tsx:379` - Inventory nav link. Why referenced: FSS navigable inventory page. Prompt source: `Inventory > should not be navigable page for FSS`. Plan use: remove nav item.
- `frontend/app/(rnd)/food-service/inventory/page.tsx:287` - `InventoryPage`. Why referenced: full inventory page. Prompt source: `Inventory > remove page`. Plan use: delete file.
- `frontend/app/(rnd)/food-service/inventory/page.tsx:86` - `qty`, `stockUnit`. Why referenced: UI edits qty/unit. Prompt source: `Supplies in inventory have no qty field`. Plan use: remove if page not deleted first.
- `frontend/services/inventoryService.ts:134` - `upsertInventory`. Why referenced: create/edit stock API helper. Prompt source: `Inventory no qty create/edit`. Plan use: remove direct stock mutation usage.
- `frontend/services/inventoryService.ts:159` - `deleteInventory`. Why referenced: direct inventory delete helper. Prompt source: `Inventory backend reference`. Plan use: remove from FSS UI path.
- `frontend/services/inventoryService.ts:211` - `restockInventory`. Why referenced: stocking helper. Prompt source: `FSS no stocking`. Plan use: delete or leave unused only for backend admin if route remains admin-only.
- `mobile/app/(tabs)/_layout.tsx:47` - mobile Inventory tab. Why referenced: FSS mobile inventory nav. Prompt source: `Inventory > no navigable page for FSS`. Plan use: remove tab.
- `mobile/app/(tabs)/inventory.tsx:336` - `InventoryScreen`. Why referenced: mobile stock page. Prompt source: `Inventory`. Plan use: delete file.
- `backend/app/Http/Requests/FSS/StoreInventoryRequest.php:17` - `quantity_in_stock` required. Why referenced: violates no qty create. Prompt source: `Supplies have no qty field when creating`. Plan use: remove from validation.
- `backend/app/Http/Requests/FSS/UpdateInventoryRequest.php:14` - `quantity_in_stock` accepted. Why referenced: violates no qty edit. Prompt source: `Supplies have no qty field when editing`. Plan use: remove from validation.
- `backend/app/Http/Controllers/FSS/InventoryController.php:255` - `restock()`. Why referenced: direct stocking. Prompt source: `FSS no stocking`. Plan use: delete/guard.
- `backend/app/Services/FSS/ReceivingService.php:88` - `Inventory::firstOrNew`. Why referenced: PO receipt stock-in path. Prompt source: `Inventory backend reference`. Plan use: keep as backend path only if stock ledger remains.
- `backend/app/Services/FSS/ReceivingService.php:98` - updates `$fs->purchase_price` only. Why referenced: vendor latest sync incomplete. Prompt source: `vendor auto-updates from latest procurement unless locked`. Plan use: update `default_supplier_id` when not locked.

---

## Food Service Workflow - Recipes And Food Items

### Files to touch
- `backend/app/Http/Controllers/FSS/FoodServiceRecipeController.php`
- `backend/app/Models/FoodServiceRecipe.php`
- `backend/app/Models/FoodServiceRecipeIngredient.php`
- `backend/app/Models/FsItem.php`
- `backend/app/Services/MenuCycleCostService.php`
- `backend/app/Support/UnitConverter.php`
- `frontend/app/(rnd)/food-service/recipes/page.tsx`
- `frontend/app/(rnd)/food-service/recipes/new/page.tsx`
- `frontend/services/menuCycleService.ts`
- `frontend/services/inventoryService.ts`

### Files to delete
- None.

### Existing variables, column names, and model names to reuse
- `FoodServiceRecipe` in `backend/app/Models/FoodServiceRecipe.php:12` - recipe model. Prompt source: `Create either a single item or a recipe`. Plan use: keep for recipe path.
- `food_service_recipes.servings` in `backend/app/Models/FoodServiceRecipe.php:18` and schema `servings int` - baseline serving count. Prompt source: `set a baseline serving for those ingredients`. Plan use: keep as baseline serving.
- `FoodServiceRecipeIngredient` in `backend/app/Models/FoodServiceRecipeIngredient.php:8` - ingredient line model. Prompt source: `set ingredients and their quantity and unit`. Plan use: keep.
- `food_service_recipe_ingredients.quantity` in schema - ingredient quantity. Prompt source: `ingredients and their quantity`. Plan use: keep.
- `food_service_recipe_ingredients.unit` in schema - ingredient unit. Prompt source: `unit in recipes auto convert`. Plan use: keep as user-selected recipe unit.
- `FsItem.base_unit` in `backend/app/Models/FsItem.php:28` - inventory/base unit. Prompt source: `auto convert based on the unit in inventory`. Plan use: conversion target/source.
- `FoodServiceRecipe::recalculateCost()` in `backend/app/Models/FoodServiceRecipe.php:41` - recipe cost recalculation. Prompt source: `unit auto convert rate of price`. Plan use: central cost update.
- `UnitConverter` in `backend/app/Support/UnitConverter.php:16` - conversion utility. Prompt source: `unit cannot be converted warn`. Plan use: reuse for supported mass/volume conversion.
- `UnitConverter::convert()` in `backend/app/Support/UnitConverter.php:59` - conversion method. Prompt source: `auto convert`. Plan use: call before save/display.
- `UnitConverter::scalingFactor()` in `backend/app/Support/UnitConverter.php:99` - factor method. Prompt source: `rate of price based on inventory unit`. Plan use: convert unit cost between recipe and inventory unit.

### New variables, columns, or model names to create
- `conversion_warning`, string nullable in API response only, purpose: warn when a recipe line cannot convert to `FsItem.base_unit`.
- `is_convertible`, boolean in recipe ingredient response, purpose: allow UI to block bundle-like units when inventory unit is convertible.
- `inventory_unit`, string in recipe ingredient response, purpose: show `FsItem.base_unit`.
- `converted_quantity`, decimal(12,2) in response/snapshot, purpose: display converted ingredient quantity max two decimals.
- `converted_unit_cost`, decimal(12,2) in response/snapshot, purpose: display converted rate max two decimals.
- `baseline_servings`, frontend variable mapped to existing `servings`, purpose: clearer UI label without DB rename.

### Migrations to add, modify, or drop
- No migration required if `food_service_recipes.servings`, `food_service_recipe_ingredients.quantity`, and `unit` are reused.
- Add `2026_06_27_000006_add_recipe_conversion_snapshot_fields_if_persisted.php` only if persistent recipe-line conversion snapshots are required. Proposed columns: `food_service_recipe_ingredients.conversion_warning text nullable`, `converted_quantity decimal(12,2) nullable`, `converted_unit_cost decimal(12,2) nullable`. Prefer response-only computation unless audit requires persistence.

### File references
- `backend/app/Http/Controllers/FSS/FoodServiceRecipeController.php:45` - `unitCompatible()`. Why referenced: current compatibility gate. Prompt source: `If unit cannot be converted, warn user`. Plan use: extend to warning/block behavior.
- `backend/app/Http/Controllers/FSS/FoodServiceRecipeController.php:56` - `assertIngredientUnits()`. Why referenced: currently enforces units. Prompt source: `do not allow bundle units when inventory unit can be converted`. Plan use: update validation logic.
- `backend/app/Http/Controllers/FSS/FoodServiceRecipeController.php:66` - `store()` validation includes `servings` and `ingredients.*`. Why referenced: baseline serving and ingredients entry point. Prompt source: `Create recipe, set ingredients/quantity/unit/baseline serving`. Plan use: keep.
- `backend/app/Http/Controllers/FSS/FoodServiceRecipeController.php:127` - `update()` validation. Why referenced: same behavior on edit. Prompt source: `unit changes auto convert`. Plan use: update.
- `backend/app/Http/Controllers/FSS/FoodServiceRecipeController.php:179` - `formatRecipe()`. Why referenced: response formatter. Prompt source: `warn user`, `max 2 decimals`. Plan use: include conversion fields.
- `backend/app/Models/FoodServiceRecipe.php:91` - `scaleFactor()`. Why referenced: baseline serving scaling. Prompt source: `baseline serving`. Plan use: keep.
- `backend/app/Models/FoodServiceRecipe.php:97` - `scaledCost()`. Why referenced: cost scaling. Prompt source: `cost scale live`. Plan use: reuse for menu/procurement estimates.
- `backend/app/Services/MenuCycleCostService.php:214` - `recipeProfile()`. Why referenced: edit-recipe-like values for menu cycle cell. Prompt source: `cell display UI similar to edit recipe showing actual values`. Plan use: extend with PO snapshot display.
- `frontend/app/(rnd)/food-service/recipes/new/page.tsx:30` - `searchInventory()`. Why referenced: recipe ingredient search. Prompt source: `set ingredients`. Plan use: keep against read-only inventory rows.
- `frontend/app/(rnd)/food-service/recipes/page.tsx:150` - Actions column. Why referenced: recipe table UI rule. Prompt source: `UI`. Plan use: keep edit/delete only in action cell.

---

## Food Service Workflow - Menu Cycle

### Files to touch
- `backend/app/Http/Controllers/FSS/MenuCycleController.php`
- `backend/app/Http/Controllers/FSS/MealPrepLogController.php`
- `backend/app/Http/Controllers/FSS/DietListCountController.php`
- `backend/app/Models/MenuCycle.php`
- `backend/app/Models/MenuCycleDay.php`
- `backend/app/Models/MealPrepLog.php`
- `backend/app/Services/MenuCycleCostService.php`
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`
- `backend/app/Services/FSS/ShoppingListPopulationService.php`
- `backend/app/Services/FSS/ConsumptionService.php`
- `backend/app/Http/Resources/MenuCycleResource.php`
- `frontend/app/(rnd)/food-service/menu-cycle/page.tsx`
- `frontend/app/(rnd)/food-service/menu-cycle/_components/ServiceLogPanel.tsx`
- `frontend/services/menuCycleService.ts`
- `mobile/lib/foodService.ts`
- `mobile/app/(tabs)/menu.tsx`
- `mobile/app/(tabs)/prep.tsx`

### Files to delete
- None.

### Existing variables, column names, and model names to reuse
- `MenuCycle` in `backend/app/Models/MenuCycle.php:10` - menu cycle model. Prompt source: `Menu cycles have three states`. Plan use: keep.
- `menu_cycles.status` in `backend/app/Models/MenuCycle.php:15` and migration `backend/database/migrations/2026_06_02_210757_create_menu_cycles_table.php:19` - current enum `draft|active|archived`. Prompt source: `completed, active, upcoming`. Plan use: migrate values.
- `menu_cycles.is_active` in `backend/app/Models/MenuCycle.php:15` - active marker. Prompt source: `current active menu cycle default`. Plan use: keep for fast active lookup until status logic replaces it.
- `MenuCycle::coveringDate()` in `backend/app/Models/MenuCycle.php:48` - resolves date to covering cycle. Prompt source: `System resolves each date to covering menu cycle`. Plan use: reuse for shopping list span validation.
- `MenuCycleDay` in `backend/app/Models/MenuCycleDay.php:9` - day/cell model. Prompt source: `RND slots food items on each cell`. Plan use: add PO snapshot fields here.
- `menu_cycle_days.recipe_id` in schema - recipe slot. Prompt source: `RND slots food items`. Plan use: keep.
- `menu_cycle_days.fs_item_id` in schema - single item slot. Prompt source: `single item or recipe`. Plan use: keep.
- `menu_cycle_days.estimate_population` in `backend/app/Models/MenuCycleDay.php:13` - planning headcount. Prompt source: `served population is not same as estimated population and must never affect procurement planning`. Plan use: keep separate from served.
- `MealPrepLog` in `backend/app/Models/MealPrepLog.php:10` - service log model. Prompt source: `served population logged by FSS`. Plan use: keep as served source.
- `meal_prep_logs.served_population` in `backend/app/Models/MealPrepLog.php:15` and migration `backend/database/migrations/2026_06_15_021000_meal_prep_population_variance.php:22` - actual served. Prompt source: `value entered on top... served population`. Plan use: use for actual budget and PO completion.
- `MealPrepLog.population` in `backend/app/Models/MealPrepLog.php:15` - prep/planned population. Prompt source: `served not estimated`. Plan use: keep distinct.
- `DietListCountController::syncServedPopulation()` in `backend/app/Http/Controllers/FSS/DietListCountController.php:62` - aggregates diet-list counts into served. Prompt source: `actual headcount FSS logs`. Plan use: keep if diet list remains source.

### New variables, columns, or model names to create
- `menu_cycle_days.snapshot_purchase_order_id`, nullable foreign id to `purchase_orders.id`, purpose: link cell snapshot to food PO.
- `menu_cycle_days.po_snapshot`, nullable JSON, purpose: immutable snapshot of scaled food item, ingredients, quantities, units, costs, vendor grouping after food PO conversion.
- `menu_cycle_days.po_snapshot_at`, nullable timestamp, purpose: when cell structural values froze.
- `menu_cycle_days.po_snapshot_locked`, boolean default false, purpose: prevent changes after related PO completion/conversion.
- `MenuCycleStatus`, PHP enum or constants, values `completed`, `active`, `upcoming`, purpose: replace scattered string literals.
- `activeCycleId`, frontend state number nullable, purpose: default landing to active cycle while keeping list accessible.
- `menuCycleView`, frontend state union `"active" | "list" | "prep_day"`, purpose: support full-cycle and day-by-day FSS views.

### Migrations to add, modify, or drop
- Add `2026_06_27_000007_update_menu_cycle_statuses.php`: convert `menu_cycles.status` to `completed|active|upcoming`; map `archived` to `completed`, `draft` to `upcoming`; keep `active`.
- Add `2026_06_27_000008_add_po_snapshot_to_menu_cycle_days.php`: add `snapshot_purchase_order_id`, `po_snapshot`, `po_snapshot_at`, `po_snapshot_locked`; indexes on `snapshot_purchase_order_id` and `menu_cycle_id`.
- Add `2026_06_27_000009_add_served_population_edit_lock_fields.php`: optional `meal_prep_logs.served_locked_at timestamp nullable`, `served_locked_by foreignId nullable`; purpose: lock backfill after related food PO completion.

### File references
- `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:684` - default `view` is list. Why referenced: wrong landing. Prompt source: `landing page of Menu Cycle should be current active menu cycle`. Plan use: default to active.
- `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:198` - `CycleList`. Why referenced: list must remain accessible. Prompt source: `list should still be accessible`. Plan use: keep behind tab/button.
- `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:334` - `CycleEditor`. Why referenced: full menu cycle grid. Prompt source: `FSS has two views: full menu cycle view and day by day prep view`. Plan use: reuse for full-cycle view.
- `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:540` - day population input. Why referenced: label currently estimate-oriented. Prompt source: `served population label clearly as served`. Plan use: split estimate/planning input from served input.
- `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:550` - title `estimated population`. Why referenced: wording conflict. Prompt source: `served is not estimated population`. Plan use: relabel or move.
- `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:579` - cell profile click. Why referenced: entry point for detail panel. Prompt source: `select a cell with food should display UI similar to edit recipe`. Plan use: show actual values or ingredients only before PO conversion.
- `frontend/services/menuCycleService.ts:24` - `estimate_population` comment says drives scaling. Why referenced: planning headcount. Prompt source: `must never affect procurement planning` applies to served, not estimate. Plan use: clarify name/usage.
- `backend/app/Http/Controllers/FSS/MenuCycleController.php:24` - `orderByDesc('is_active')`. Why referenced: backend already prioritizes active. Prompt source: `active landing`. Plan use: reuse to find default.
- `backend/app/Http/Controllers/FSS/MenuCycleController.php:94` - activate archives old active. Why referenced: status mapping conflict. Prompt source: `completed, active, upcoming`. Plan use: change old active to completed and future/draft to upcoming.
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php:202` - `menuSnapshot()` text only. Why referenced: no structured cell snapshot. Prompt source: `scaled values snapshot saved to menu cycle day cells permanently`. Plan use: add write to `menu_cycle_days.po_snapshot`.
- `mobile/app/(tabs)/menu.tsx:245` - mobile default `openId` null. Why referenced: mobile active cycle not default. Prompt source: `FSS full menu cycle view`. Plan use: auto-open active cycle.
- `mobile/app/(tabs)/menu.tsx:209` - label `Actual served population per day`. Why referenced: good existing served label. Prompt source: `label clearly as served`. Plan use: keep.
- `mobile/app/(tabs)/prep.tsx:63` - `fetchActiveCycle`. Why referenced: day-by-day prep uses active cycle. Prompt source: `FSS day by day prep view`. Plan use: expand into prep workflow.

---

## Food Service Workflow - Dashboard

### Files to touch
- `backend/app/Services/FSS/FssDashboardService.php`
- `backend/app/Http/Controllers/FSS/DashboardController.php`
- `backend/app/Http/Resources/PurchaseOrderResource.php`
- `frontend/app/(rnd)/dashboard/page.tsx`
- `frontend/services/dashboardService.ts`
- `mobile/app/(tabs)/index.tsx`
- `mobile/lib/foodService.ts`

### Files to delete
- None.

### Existing variables, column names, and model names to reuse
- `PurchaseOrder.lifecycle_status` in schema and `backend/app/Models/PurchaseOrder.php:13` - current PO lifecycle. Prompt source: `Pending PO card shows POs currently in open execution`. Plan use: filter `open_execution`.
- `PurchaseOrderVendorGroup.status` in `backend/app/Models/PurchaseOrderVendorGroup.php:14` - vendor group receipt status. Prompt source: `what they are waiting on`. Plan use: derive waiting reason.
- `pos_awaiting_receipt` in `backend/app/Services/FSS/FssDashboardService.php:30` - current receipt missing count. Prompt source: `Pending PO card`. Plan use: replace or supplement with open execution pending PO payload.
- `inventory_no_stock` in `backend/app/Services/FSS/FssDashboardService.php:31` - stock KPI. Prompt source: `Remove inventory or stock related cards`. Plan use: remove.
- `costPerHeadKpi` in `frontend/app/(rnd)/dashboard/page.tsx:107` - cost/head KPI. Prompt source: `Remove budget per head per day from KPI cards`. Plan use: remove.

### New variables, columns, or model names to create
- `pending_pos`, array in dashboard JSON, purpose: list open-execution POs and waiting reasons.
- `pending_pos_count`, integer in dashboard JSON, purpose: card count.
- `waiting_on`, string array per pending PO, purpose: values like `receipts`, `served_population`, `budget_allocation`.
- `PendingPoCard`, frontend component, purpose: dashboard card for RND/FSS.

### Migrations to add, modify, or drop
- None. Use existing PO lifecycle and vendor group/served data.

### File references
- `backend/app/Services/FSS/FssDashboardService.php:75` - `posAwaitingReceipt()`. Why referenced: already queries open execution vendor groups. Prompt source: `Pending PO card`. Plan use: replace with richer pending PO service.
- `backend/app/Services/FSS/FssDashboardService.php:93` - `inventoryNoStock()`. Why referenced: stock card backend. Prompt source: `Remove inventory or stock related cards`. Plan use: remove method and response field.
- `frontend/app/(rnd)/dashboard/page.tsx:18` - `getCostToday` import. Why referenced: cost/head source. Prompt source: `Remove budget per head per day from KPI cards`. Plan use: remove.
- `frontend/app/(rnd)/dashboard/page.tsx:648` - Cost / Head Today card. Why referenced: visible KPI to remove. Prompt source: `Dashboard`. Plan use: replace with Pending PO.
- `mobile/app/(tabs)/index.tsx:23` - `DashboardData` includes `inventory_no_stock`. Why referenced: mobile stock KPI source. Prompt source: `Dashboard > remove inventory/stock cards`. Plan use: remove and add pending PO fields.
- `mobile/app/(tabs)/index.tsx:156` - POs awaiting receipt card. Why referenced: narrower than pending open execution. Prompt source: `Pending PO card shows open execution and what waiting on`. Plan use: replace wording/data.
- `mobile/app/(tabs)/index.tsx:163` - Items out of stock card. Why referenced: stock card. Prompt source: `Dashboard`. Plan use: remove.

---

## Food Service Workflow - Budget

### Files to touch
- `backend/routes/api.php`
- `backend/app/Http/Controllers/FSS/BudgetController.php`
- `backend/app/Http/Resources/BudgetResource.php`
- `backend/app/Models/Budget.php`
- `backend/app/Models/BudgetLedger.php`
- `backend/app/Listeners/BudgetLedgerListener.php`
- `backend/app/Http/Requests/FSS/StoreBudgetRequest.php`
- `frontend/app/(rnd)/food-service/budget/page.tsx`
- `frontend/services/budgetService.ts`
- `frontend/app/admin/settings/page.tsx`
- `frontend/app/(rnd)/settings/page.tsx`
- `frontend/app/api/fss/budgets/ledger/route.ts`
- `frontend/app/api/fss/budgets/summary/route.ts`
- `frontend/app/api/fss/budgets/adjust/route.ts`

### Files to delete
- `backend/app/Http/Controllers/FSS/InsightsController.php`
- `frontend/services/insightsService.ts`
- `frontend/app/(rnd)/food-service/insights/page.tsx`
- `frontend/app/api/fss/insights/budget-burn/route.ts`
- `frontend/app/api/fss/insights/per-head-actual-vs-limit/route.ts`
- `frontend/app/api/fss/insights/procurement-deduction-timeline/route.ts`
- `frontend/app/api/fss/insights/spend-by-supplier/route.ts`
- `frontend/app/api/fss/insights/insights-routes.test.ts`
- `frontend/app/(rnd)/food-service/budget/placement.test.ts` if it only asserts insights tabs.

### Existing variables, column names, and model names to reuse
- `Budget` in `backend/app/Models/Budget.php:8` - fiscal budget model. Prompt source: `Budget page`. Plan use: keep for fiscal year allocation.
- `budgets.fiscal_year` in `backend/app/Models/Budget.php:12` - fiscal year. Prompt source: `fiscal year setup`. Plan use: keep.
- `budgets.allocated_amount` in `backend/app/Models/Budget.php:12` - allocated total. Prompt source: `summary cards: Allocated`. Plan use: keep.
- `budgets.per_head_day_limit` in `backend/app/Models/Budget.php:12` - current per-head setting. Prompt source: `Budget per head per day should be set in Settings`. Plan use: migrate or move access to Settings UI.
- `BudgetLedger` in `backend/app/Models/BudgetLedger.php:8` - ledger model. Prompt source: `shared budget ledger`. Plan use: keep as single source of deductions/additions.
- `budget_ledger.type` in `backend/app/Models/BudgetLedger.php:12` and schema enum `po_deduction|manual_addition|manual_deduction` - ledger type. Prompt source: `columns date, type, amount`. Plan use: keep.
- `budget_ledger.amount` in `backend/app/Models/BudgetLedger.php:12` - amount. Prompt source: `ledger amount`. Plan use: keep.
- `budget_ledger.reason` in `backend/app/Models/BudgetLedger.php:12` - reason. Prompt source: `filterable by reason from system or manual`. Plan use: keep; add source classifier.
- `budget_ledger.reference` in `backend/app/Models/BudgetLedger.php:12` - reference. Prompt source: `reference column`. Plan use: keep.
- `budget_ledger.created_by` in `backend/app/Models/BudgetLedger.php:12` - user. Prompt source: `created by column`. Plan use: keep.
- `BudgetLedger::signedAmount()` in `backend/app/Models/BudgetLedger.php:33` - signed amount helper. Prompt source: `deductions and additions log`. Plan use: reuse for Remaining calculation.

### New variables, columns, or model names to create
- `food_service_settings`, new table, purpose: shared Food Service settings for admin/RND.
- `FoodServiceSetting`, new Laravel model, purpose: store `per_head_day_limit`.
- `food_service_settings.per_head_day_limit`, decimal(10,2) nullable, purpose: budget per head per day setting outside Budget page.
- `food_service_settings.updated_by`, nullable foreign id to `users.id`, purpose: audit settings update.
- `budget_ledger.source`, enum `system|manual`, purpose: filter ledger by system/manual reason source.
- `total_deductions`, decimal string in budget summary response, purpose: collapsed total deductions card.
- `remaining`, decimal string in budget summary response, purpose: Remaining summary card.
- `LedgerReasonFilter`, TypeScript union `"all" | "system" | "manual"`, purpose: replace old type filter.

### Migrations to add, modify, or drop
- Add `2026_06_27_000010_create_food_service_settings_table.php`: create table with `per_head_day_limit decimal(10,2) nullable`, `updated_by nullable foreignId constrained users nullOnDelete`, timestamps.
- Add `2026_06_27_000011_add_source_drop_procurement_span_from_budget_ledger.php`: add `source enum('system','manual') default 'manual'`, backfill `po_deduction` as `system`, manual types as `manual`, drop `procurement_span`.
- Optional add `2026_06_27_000012_drop_per_head_day_limit_from_budgets_table.php`: drop `budgets.per_head_day_limit` only after settings migration/backfill. If fiscal-year-specific limit is still needed, keep DB column but remove Budget page editing and expose via Settings.
- Do not modify `2026_06_27_000002_create_budget_ledger_table.php`; add forward migration.

### File references
- `backend/routes/api.php:228` - budgets summary route. Why referenced: Budget page data. Prompt source: `Budget`. Plan use: update response fields.
- `backend/routes/api.php:229` - budget ledger route. Why referenced: ledger columns/filter. Prompt source: `Budget view should show log`. Plan use: update filter contract.
- `backend/routes/api.php:233` through `backend/routes/api.php:236` - insights routes. Why referenced: backend insights to remove. Prompt source: `Remove graphs and insights from budget and their backends`. Plan use: delete routes/controller.
- `backend/app/Http/Controllers/FSS/BudgetController.php:63` - `ledger()`. Why referenced: current ledger API. Prompt source: `ledger columns exact`. Plan use: reshape.
- `backend/app/Http/Controllers/FSS/BudgetController.php:65` - validates `type` filter. Why referenced: current filter is type/manual/po. Prompt source: `filterable by reason from system or manual`. Plan use: replace with `source`.
- `backend/app/Http/Controllers/FSS/BudgetController.php:86` - maps ledger response. Why referenced: includes old fields. Prompt source: `no procurement span column`. Plan use: remove `procurement_span`, emit date/type/amount/reason/reference/created_by.
- `backend/app/Http/Controllers/FSS/BudgetController.php:96` - returns `procurement_span`. Why referenced: explicit conflict. Prompt source: `no procurement span column`. Plan use: remove.
- `backend/app/Http/Resources/BudgetResource.php:22` - returns `per_head_day_limit`. Why referenced: current Budget-page setting source. Prompt source: `Budget per head per day should be set in Settings`. Plan use: move UI/API exposure.
- `frontend/app/(rnd)/food-service/budget/page.tsx:12` - imports `BudgetInsightsPanel`. Why referenced: frontend insights. Prompt source: `Remove graphs and insights`. Plan use: remove import.
- `frontend/app/(rnd)/food-service/budget/page.tsx:34` - `BudgetTab = "budget" | "insights"`. Why referenced: insights tab. Prompt source: `Remove graphs and insights`. Plan use: remove tabs.
- `frontend/app/(rnd)/food-service/budget/page.tsx:81` - `kpis` five cards. Why referenced: too many cards. Prompt source: `summary cards slim down to three`. Plan use: reduce to Allocated, Total Deductions, Remaining.
- `frontend/app/(rnd)/food-service/budget/page.tsx:100` - progress bar. Why referenced: prompt says remove progress bar. Plan use: delete.
- `frontend/app/(rnd)/food-service/budget/page.tsx:109` - per-head limit text. Why referenced: setting belongs in Settings. Prompt source: `Budget per head per day should be set in Settings`. Plan use: remove from Budget page.
- `frontend/app/(rnd)/food-service/budget/page.tsx:223` - ledger table headers include span. Why referenced: exact ledger columns conflict. Prompt source: `no procurement span column`. Plan use: update table.
- `frontend/app/(rnd)/food-service/budget/page.tsx:251` - `NewYearSection` currently below ledger. Why referenced: fiscal year setup position. Prompt source: `fiscal year setup section moves to top`. Plan use: reorder.
- `frontend/app/admin/settings/page.tsx:29` - Admin Settings page. Why referenced: target for per-head setting. Prompt source: `Settings for admin and RND`. Plan use: add control.
- `frontend/app/(rnd)/settings/page.tsx:18` - RND Settings page. Why referenced: target for per-head setting. Prompt source: `Settings for admin and RND`. Plan use: add control.

---

## Food Service Workflow - Reports

### Files to touch
- `backend/app/Http/Controllers/ReportController.php`
- `backend/app/Services/Reports/ReportService.php`
- `backend/app/Services/Reports/ReportBrowser.php`
- `backend/database/seeders/ReportTemplateSeeder.php`
- `frontend/services/reportService.ts`
- `frontend/components/reports/ReportsBrowser.tsx`
- `frontend/app/admin/reports/page.tsx`
- `frontend/app/(rnd)/reports/page.tsx`

### Files to delete
- `backend/app/Services/Reports/Generators/DietaryCashBookGenerator.php`
- `backend/app/Services/Reports/Generators/BudgetReportGenerator.php`
- `backend/app/Services/Reports/Generators/InventoryReportGenerator.php`
- `backend/resources/views/reports/dietary-cash-book.blade.php`
- `backend/resources/views/reports/budget.blade.php`
- `backend/resources/views/reports/inventory.blade.php`
- `backend/tests/Unit/Reports/DietaryCashBookTest.php`

### Existing variables, column names, and model names to reuse
- `Report` model and `reports.type` from live schema `reports.type varchar(255)` - report archive model. Prompt source: `Reports`. Plan use: keep for remaining reports.
- `ReportService::$generators` in `backend/app/Services/Reports/ReportService.php:29` - generator registry. Prompt source: `Remove reports entirely including backends`. Plan use: remove retired generator keys.
- `ReportBrowser` in `backend/app/Services/Reports/ReportBrowser.php:24` - instance source registry. Prompt source: `remaining reports reproducible`. Plan use: remove retired sources and require explicit params for remaining reports.
- `FULL_CATALOG` in `frontend/components/reports/ReportsBrowser.tsx:38` - frontend catalog. Prompt source: `Remove reports frontends`. Plan use: remove retired cards.
- `ADMIN_CATALOG` in `frontend/components/reports/ReportsBrowser.tsx:52` - admin catalog. Prompt source: `Remove Budget`. Plan use: remove budget report.

### New variables, columns, or model names to create
- `retired_report_types`, local constant array in cleanup migration or seeder, values `dietary_cash_book`, `budget_report`, `inventory_report`, `budget`, `inventory`, purpose: delete/hide retired templates and prevent generation.
- `ReportInputRequiredException`, optional exception class, purpose: force remaining report generators to require actual input instead of current-date fallback.

### Migrations to add, modify, or drop
- Add `2026_06_27_000013_remove_retired_report_templates.php`: delete `report_templates` rows where `type` in `dietary_cash_book`, `budget_report`, `inventory_report`, `budget`, `inventory`; decide archive policy for existing `reports` rows. Recommended: keep archived PDFs inaccessible by catalog but do not generate new ones.
- No enum migration needed for `reports.type` because `backend/database/migrations/2026_06_13_000100_reports_spec4_archive.php:27` changed report type to string.
- Do not modify `backend/database/migrations/2026_06_02_210807_create_reports_table.php` or `backend/database/migrations/2026_06_12_000200_reports_phase5_branding_and_types.php`.

### File references
- `backend/app/Http/Controllers/ReportController.php:25` - `dietary_cash_book` allowlist. Why referenced: retired report still exposed. Prompt source: `Reports > Remove Dietary Cashbook`. Plan use: remove.
- `backend/app/Http/Controllers/ReportController.php:27` - `budget_report` allowlist. Why referenced: retired report still exposed. Prompt source: `Reports > Remove Budget`. Plan use: remove.
- `backend/app/Http/Controllers/ReportController.php:28` - `inventory_report` allowlist. Why referenced: retired report still exposed. Prompt source: `Reports > Remove Inventory`. Plan use: remove.
- `backend/app/Http/Controllers/ReportController.php:45` - admin `budget_report` allowlist. Why referenced: admin can browse budget report. Prompt source: `Remove Budget report entirely`. Plan use: remove.
- `backend/app/Services/Reports/ReportService.php:35` - `dietary_cash_book` generator key. Why referenced: backend generator registry. Prompt source: `including backends`. Plan use: delete key/import/generator.
- `backend/app/Services/Reports/ReportService.php:40` - `budget_report` generator key. Why referenced: backend generator registry. Prompt source: `Budget report itself remove`. Plan use: delete.
- `backend/app/Services/Reports/ReportService.php:42` - `inventory_report` generator key. Why referenced: backend generator registry. Prompt source: `Inventory report remove`. Plan use: delete.
- `backend/app/Services/Reports/ReportBrowser.php:33` - dietary cashbook instance source. Why referenced: browse backend. Prompt source: `frontends/backends`. Plan use: delete.
- `backend/app/Services/Reports/ReportBrowser.php:51` - budget report instance source. Why referenced: browse backend. Prompt source: `Budget report remove`. Plan use: delete.
- `backend/app/Services/Reports/ReportBrowser.php:85` - inventory report source. Why referenced: browse backend. Prompt source: `Inventory report remove`. Plan use: delete.
- `backend/database/seeders/ReportTemplateSeeder.php:49` - dietary template. Why referenced: related seed data. Prompt source: `related migrations`. Plan use: remove from seeder and cleanup existing row.
- `backend/database/seeders/ReportTemplateSeeder.php:121` - budget template. Why referenced: related seed data. Prompt source: `related migrations`. Plan use: remove.
- `backend/database/seeders/ReportTemplateSeeder.php:130` - inventory template. Why referenced: related seed data. Prompt source: `related migrations`. Plan use: remove.
- `frontend/services/reportService.ts:6` - `dietary_cash_book` type. Why referenced: frontend type still allows retired report. Prompt source: `frontends`. Plan use: remove.
- `frontend/services/reportService.ts:10` - `budget_report` type. Why referenced: frontend type. Prompt source: `Budget report remove`. Plan use: remove.
- `frontend/services/reportService.ts:11` - `inventory_report` type. Why referenced: frontend type. Prompt source: `Inventory report remove`. Plan use: remove.
- `frontend/components/reports/ReportsBrowser.tsx:41` - Dietary Cash Book card. Why referenced: frontend catalog. Prompt source: `Reports`. Plan use: remove.
- `frontend/components/reports/ReportsBrowser.tsx:43` - Budget Report card. Why referenced: frontend catalog. Prompt source: `Reports`. Plan use: remove.
- `frontend/components/reports/ReportsBrowser.tsx:44` - Inventory Report card. Why referenced: frontend catalog. Prompt source: `Reports`. Plan use: remove.
- `backend/app/Services/Reports/Generators/ProcurementPackGenerator.php:85` - current-month fallback. Why referenced: stale/non-reproducible risk. Prompt source: `remaining reports reproducible from actual input`. Plan use: require explicit period/input.
- `backend/app/Services/Reports/Generators/AccomplishmentReportGenerator.php:78` - current-month fallback. Why referenced: reproducibility risk. Prompt source: `no stale data`. Plan use: require explicit period.
- `backend/app/Services/Reports/Generators/DemographicCensusGenerator.php:42` - current year/day fallback. Why referenced: reproducibility risk. Prompt source: `actual input processed by system`. Plan use: require explicit period.
- `backend/app/Services/Reports/Generators/PatientMenuPlanGenerator.php:50` - latest meal plan fallback. Why referenced: stale/dead-end risk. Prompt source: `reproducible`. Plan use: require exact `meal_plan_id`.

---

## Procurement - Food Shopping List

### Files to touch
- `backend/app/Http/Controllers/FSS/ShoppingListController.php`
- `backend/app/Http/Requests/FSS/StoreShoppingListRequest.php`
- `backend/app/Http/Requests/FSS/UpdateShoppingListRequest.php`
- `backend/app/Http/Resources/ShoppingListResource.php`
- `backend/app/Models/ShoppingList.php`
- `backend/app/Models/ShoppingListItem.php`
- `backend/app/Services/FSS/ShoppingListPopulationService.php`
- `backend/app/Services/MenuCycleCostService.php`
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`
- `frontend/app/(rnd)/food-service/procurement/page.tsx`
- `frontend/services/procurementService.ts`
- `frontend/app/api/fss/shopping-lists/generate/route.ts`
- `frontend/app/api/fss/shopping-list-items/[id]/route.ts`
- `mobile/app/(tabs)/procurement.tsx`

### Files to delete
- None.

### Existing variables, column names, and model names to reuse
- `ShoppingList` in `backend/app/Models/ShoppingList.php:8` - procurement list model. Prompt source: `Food Shopping List`. Plan use: keep for both tracks with `procurement_track`.
- `shopping_lists.period_start` and `period_end` in `backend/app/Models/ShoppingList.php:13` - food procurement span. Prompt source: `date range for food shopping list`. Plan use: food track only.
- `shopping_lists.days_span` in `backend/app/Models/ShoppingList.php:13` - span days. Prompt source: `span of days multiplied by estimated population`. Plan use: estimate per head/day calculation.
- `shopping_lists.estimate_population` in `backend/app/Models/ShoppingList.php:13` and migration `backend/database/migrations/2026_06_26_000001_add_estimate_population_tracking_to_procurement.php:12` - uniform estimated population. Prompt source: `Estimated population set once at shopping list level`. Plan use: keep.
- `shopping_lists.coverage_status` and `uncovered_dates` in `backend/app/Models/ShoppingList.php:13` - coverage data. Prompt source: `block creation entirely with exact missing dates`. Plan use: reuse but change partial creation to blocked response.
- `ShoppingListItem.qty` in `backend/app/Models/ShoppingListItem.php:12` - scaled qty. Prompt source: `ingredients and quantities scale live`. Plan use: keep scaled quantity.
- `ShoppingListItem.unit` in `backend/app/Models/ShoppingListItem.php:12` - recipe unit. Prompt source: `Unit is not editable`. Plan use: keep immutable from recipe/item creation.
- `ShoppingListItem.unit_price` in `backend/app/Models/ShoppingListItem.php:12` - cost per unit. Prompt source: `prices and estimated population calculate`. Plan use: editable before conversion.
- `ShoppingListItem.total` in `backend/app/Models/ShoppingListItem.php:12` - line total. Prompt source: `running total procurement cost`. Plan use: keep.
- `ShoppingListPopulationService::planRange()` in `backend/app/Services/FSS/ShoppingListPopulationService.php:17` - span planner. Prompt source: `System resolves each date to covering menu cycle`. Plan use: enforce all-or-nothing span validation.
- `ShoppingListPopulationService::cascadePopulation()` in `backend/app/Services/FSS/ShoppingListPopulationService.php:72` - estimate population cascade. Prompt source: `once estimated population is set, quantities scale live`. Plan use: keep but food-track only.

### New variables, columns, or model names to create
- `shopping_lists.procurement_track`, enum `food|supplies`, purpose: split food and supplies procurement tracks.
- `shopping_list_items.vendor_locked_at`, nullable timestamp, purpose: manual vendor lock per ingredient.
- `shopping_list_items.vendor_locked_by`, nullable foreign id, purpose: who locked vendor.
- `shopping_list_items.baseline_servings`, unsigned integer nullable, purpose: freeze recipe/food baseline at list generation.
- `shopping_list_items.baseline_quantity`, decimal(12,2) nullable, purpose: freeze per-baseline ingredient amount.
- `shopping_list_items.scaled_quantity`, decimal(12,2) nullable, purpose: live/frozen scaled qty from estimate.
- `shopping_list_items.scaled_unit`, string nullable, purpose: immutable recipe/item unit.
- `estimated_budget_per_head_per_day`, response field decimal(12,2), purpose: never blank once estimate set.
- `missing_dates`, response array, purpose: exact missing dates.
- `missing_items_by_date`, response object, purpose: exact missing cycle/menu items per date.
- `FoodShoppingCart`, frontend component, purpose: cart-style food shopping list UI.

### Migrations to add, modify, or drop
- Add `2026_06_27_000014_add_procurement_track_to_shopping_lists.php`: `procurement_track enum('food','supplies') default 'food'`, index with `status`.
- Add `2026_06_27_000015_add_vendor_lock_to_shopping_list_items.php`: `vendor_locked_at`, `vendor_locked_by`.
- Add `2026_06_27_000016_add_scaled_snapshot_fields_to_shopping_list_items.php`: `baseline_servings`, `baseline_quantity`, `scaled_quantity`, `scaled_unit`.
- Do not drop `shopping_lists.total_served_population` until all code no longer reads it; it is stale relative to `meal_prep_logs.served_population`.

### File references
- `backend/app/Http/Controllers/FSS/ShoppingListController.php:117` - `generate()`. Why referenced: food list generator. Prompt source: `generate food shopping list includes all required ingredients`. Plan use: enforce all-or-nothing validation.
- `backend/app/Http/Controllers/FSS/ShoppingListController.php:129` - calls `planRange()`. Why referenced: date coverage engine. Prompt source: `missing dates and what missing per date`. Plan use: extend response.
- `backend/app/Http/Controllers/FSS/ShoppingListController.php:150` - creates list even with coverage status. Why referenced: current partial list risk. Prompt source: `do not create partial list`. Plan use: block creation if uncovered/missing.
- `backend/app/Http/Controllers/FSS/ShoppingListController.php:178` - `updateItem()`. Why referenced: line edit. Prompt source: `vendor auto-suggested`, `unit not editable`. Plan use: allow supplier/price only before conversion, respect lock.
- `backend/app/Http/Controllers/FSS/ShoppingListController.php:194` - updates `FsItem.default_supplier_id` on draft edit. Why referenced: wrong vendor update timing. Prompt source: `latest procurement unless locked`. Plan use: move vendor update to completed/received procurement.
- `backend/app/Services/FSS/ShoppingListPopulationService.php:169` - subtracts inventory `quantity_in_stock`. Why referenced: conflicts with prompt. Prompt source: `include all required ingredients for span`. Plan use: stop subtracting on-hand stock for food list.
- `backend/app/Services/FSS/ShoppingListPopulationService.php:192` - uses `purchase_price`. Why referenced: current cost source. Prompt source: `costs scale live`. Plan use: keep if line price missing.
- `frontend/app/(rnd)/food-service/procurement/page.tsx:64` - `populationDraft`. Why referenced: estimate input. Prompt source: `Estimated population set once at shopping list level`. Plan use: keep and make live.
- `frontend/app/(rnd)/food-service/procurement/page.tsx:158` - `total`. Why referenced: running total source. Prompt source: `running total procurement cost`. Plan use: use in cart layout.
- `frontend/app/(rnd)/food-service/procurement/page.tsx:159` - `perDay`. Why referenced: estimated per head/day. Prompt source: `should never display blank/dash once estimate set`. Plan use: calculate live.
- `frontend/app/(rnd)/food-service/procurement/page.tsx:260` - add picker. Why referenced: current table-like add flow. Prompt source: `ecommerce cart layout`. Plan use: replace with cart layout.
- `frontend/services/procurementService.ts:143` - `generateByDates()`. Why referenced: date-span generation API. Prompt source: `RND selects procurement span`. Plan use: return blocked missing date payload.
- `frontend/services/procurementService.ts:171` - `updateListItem()`. Why referenced: item edit API. Prompt source: `vendor lock`, `unit not editable`. Plan use: remove unit patch from UI/API if present.

---

## Procurement - Supplies List

### Files to touch
- `backend/app/Http/Controllers/FSS/ShoppingListController.php`
- `backend/app/Http/Requests/FSS/StoreShoppingListRequest.php`
- `backend/app/Http/Resources/ShoppingListResource.php`
- `backend/app/Models/FsItem.php`
- `backend/app/Models/ShoppingList.php`
- `backend/app/Models/ShoppingListItem.php`
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`
- `frontend/app/(rnd)/food-service/procurement/page.tsx`
- `frontend/services/procurementService.ts`
- `frontend/services/inventoryService.ts`
- `mobile/app/(tabs)/procurement.tsx`

### Files to delete
- None.

### Existing variables, column names, and model names to reuse
- `FsItem.kind` in `backend/app/Models/FsItem.php:17` and schema `fs_items.kind` - supply vs ingredient. Prompt source: `Supplies in inventory`. Plan use: use `kind='supply'`.
- `FsItem.purchase_price` in `backend/app/Models/FsItem.php:28` - supply cost per unit. Prompt source: `supplies only cost per unit`. Plan use: keep as default cost.
- `ShoppingList.list_type` in schema enum `manual|suggested` - current manual marker. Prompt source: `Supplies list fully manual`. Plan use: keep `manual`, but add `procurement_track='supplies'`.
- `ShoppingListItem.qty` in `backend/app/Models/ShoppingListItem.php:12` - qty to procure. Prompt source: `qty to procure`. Plan use: keep.
- `ShoppingListItem.unit_price` in `backend/app/Models/ShoppingListItem.php:12` - cost per qty. Prompt source: `cost per unit`. Plan use: keep.
- `ShoppingListItem.total` in `backend/app/Models/ShoppingListItem.php:12` - total item cost. Prompt source: `total cost per item`. Plan use: keep.

### New variables, columns, or model names to create
- `procurement_track = 'supplies'` on `shopping_lists`, purpose: independent supplies flow.
- `SuppliesShoppingCart`, frontend component, purpose: cart-style manual supplies UI.
- `supply_qty_to_procure`, frontend field mapped to `ShoppingListItem.qty`, purpose: clear label without DB rename.
- `vendor_locked_at` and `vendor_locked_by` reuse from shopping list items, purpose: lock vendor suggestion per supply.

### Migrations to add, modify, or drop
- Use `2026_06_27_000014_add_procurement_track_to_shopping_lists.php`.
- Use `2026_06_27_000015_add_vendor_lock_to_shopping_list_items.php`.
- No separate supplies tables recommended; use `fs_items.kind='supply'` and `shopping_lists.procurement_track='supplies'`.

### File references
- `backend/app/Http/Requests/FSS/StoreShoppingListRequest.php:16` - `list_type manual|suggested`, nullable span fields. Why referenced: supplies manual can reuse manual list but must forbid span. Prompt source: `Supplies list fully manual process with no date span`. Plan use: validate `period_start`, `period_end`, `days_span`, `estimate_population` null for supplies.
- `backend/app/Http/Controllers/FSS/ShoppingListController.php:208` - `storeItem()`. Why referenced: manual add item. Prompt source: `RND searches and adds each supply item one by one`. Plan use: supplies track adds only `kind='supply'`.
- `backend/app/Http/Controllers/FSS/ShoppingListController.php:214` - validates manual item fields. Why referenced: supply line fields. Prompt source: `qty to procure, cost per unit, total cost`. Plan use: restrict fields.
- `frontend/app/(rnd)/food-service/procurement/page.tsx:55` - `itemTab`. Why referenced: UI currently switches item types. Prompt source: `Food shopping list and supplies list independent`. Plan use: separate tabs/routes into independent tracks.
- `frontend/app/(rnd)/food-service/procurement/page.tsx:94` - `selectManualItem()`. Why referenced: manual item add. Prompt source: `RND searches and adds supply item one by one`. Plan use: adapt for supplies only.
- `frontend/app/(rnd)/food-service/procurement/page.tsx:102` - `addManualItem()`. Why referenced: current manual add. Prompt source: `Supplies List`. Plan use: make supplies track no date span/menu involvement.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php:123` - groups PO by supplier. Why referenced: supplies PO vendor drilldown. Prompt source: `PO shows vendors, drilling into vendor shows qty, cost per qty, total cost`. Plan use: reuse vendor groups.

---

## Purchase Order And Finalization

### Files to touch
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`
- `backend/app/Http/Resources/PurchaseOrderResource.php`
- `backend/app/Models/PurchaseOrder.php`
- `backend/app/Models/PurchaseOrderItem.php`
- `backend/app/Models/PurchaseOrderVendorGroup.php`
- `backend/app/Models/PurchaseOrderAttachment.php`
- `backend/app/Models/ProgramProjectActivity.php`
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`
- `backend/app/Services/FSS/ReceivingService.php`
- `backend/app/Services/FSS/ShoppingListPopulationService.php`
- `backend/app/Services/NotificationService.php`
- `backend/app/Listeners/BudgetLedgerListener.php`
- `frontend/app/(rnd)/food-service/procurement/page.tsx`
- `frontend/services/procurementService.ts`
- `mobile/app/(tabs)/procurement.tsx`

### Files to delete
- None.

### Existing variables, column names, and model names to reuse
- `PurchaseOrder` in `backend/app/Models/PurchaseOrder.php:8` - PO model. Prompt source: `Purchase Order and Finalization`. Plan use: keep.
- `purchase_orders.lifecycle_status` in `backend/app/Models/PurchaseOrder.php:13` and migration `backend/database/migrations/2026_06_26_000003_create_po_vendor_groups_and_ppa_snapshots.php:12` - `open_execution|completed|archived`. Prompt source: `open execution phase`, `completed`. Plan use: keep.
- `purchase_orders.converted_at` in `backend/app/Models/PurchaseOrder.php:13` - conversion timestamp. Prompt source: `at moment of conversion`. Plan use: keep.
- `purchase_orders.completed_at` in `backend/app/Models/PurchaseOrder.php:13` - completion timestamp. Prompt source: `After completed nothing can change`. Plan use: keep.
- `purchase_orders.actual_budget_per_head_per_day` in `backend/app/Models/PurchaseOrder.php:13` - actual cost/head/day. Prompt source: `Food PO actual budget per head per day calculated at completion`. Plan use: keep for final calculation, not KPI card.
- `PurchaseOrderItem.purchase_qty`, `purchase_unit`, `purchase_price`, `unit_price`, `total_value` in `backend/app/Models/PurchaseOrderItem.php:12` - structural line fields. Prompt source: `all structural data freezes`. Plan use: freeze after conversion; only allow unit cost correction.
- `PurchaseOrderVendorGroup` in `backend/app/Models/PurchaseOrderVendorGroup.php:10` - vendor group model. Prompt source: `vendor groups receipts/proof/OR`. Plan use: keep.
- `purchase_order_vendor_groups.or_number` in `backend/app/Models/PurchaseOrderVendorGroup.php:14` - OR input. Prompt source: `input OR numbers`. Plan use: editable during open execution.
- `PurchaseOrderAttachment.type` in schema enum `receipt|proof` - attachment type. Prompt source: `upload receipts and proof of purchase`. Plan use: keep.
- `ProgramProjectActivity` in `backend/app/Models/ProgramProjectActivity.php:8` - PPA snapshot. Prompt source: `own PPA`. Plan use: add track-specific support.
- `BudgetLedgerListener` in `backend/app/Listeners/BudgetLedgerListener.php:31` - PO completion ledger hook. Prompt source: `auto-deducts from shared budget ledger`. Plan use: keep/idempotent.

### New variables, columns, or model names to create
- `purchase_orders.procurement_track`, enum `food|supplies`, purpose: distinguish food PO and supplies PO.
- `purchase_orders.structural_locked_at`, timestamp nullable, purpose: mark conversion freeze time.
- `purchase_orders.final_locked_at`, timestamp nullable, purpose: mark completion freeze time.
- `purchase_order_item_corrections`, new table/model `PurchaseOrderItemCorrection`, purpose: audit open-execution unit cost corrections.
- `purchase_order_item_corrections.purchase_order_item_id`, foreign id, purpose: corrected line.
- `purchase_order_item_corrections.old_unit_price`, decimal(10,2), purpose: before value.
- `purchase_order_item_corrections.new_unit_price`, decimal(10,2), purpose: after value.
- `purchase_order_item_corrections.old_purchase_price`, decimal(10,2) nullable, purpose: before purchase-unit cost if corrected.
- `purchase_order_item_corrections.new_purchase_price`, decimal(10,2) nullable, purpose: after purchase-unit cost if corrected.
- `purchase_order_item_corrections.corrected_by`, foreign id to `users.id`, purpose: user audit.
- `purchase_order_item_corrections.corrected_at`, timestamp, purpose: timestamp audit.
- `purchase_order_item_corrections.reason`, string nullable, purpose: correction reason.
- `waiting_on_served_population`, boolean response field, purpose: pending PO card waiting reason.
- `waiting_on_receipts`, boolean response field, purpose: pending PO card waiting reason.

### Migrations to add, modify, or drop
- Add `2026_06_27_000017_add_procurement_track_to_purchase_orders.php`: `procurement_track enum('food','supplies') default 'food'`, index `procurement_track,lifecycle_status`.
- Add `2026_06_27_000018_add_lock_fields_to_purchase_orders.php`: `structural_locked_at`, `final_locked_at`.
- Add `2026_06_27_000019_create_purchase_order_item_corrections_table.php`: correction audit table as above.
- Use `2026_06_27_000008_add_po_snapshot_to_menu_cycle_days.php` for food PO cell snapshots.

### File references
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php:100` - `approve()`. Why referenced: shopping list to PO conversion. Prompt source: `Converting a food shopping list to a PO means procurement confirmed`. Plan use: set `structural_locked_at`, `procurement_track`, cell snapshots.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php:119` - sets `lifecycle_status=open_execution`. Why referenced: open execution entry. Prompt source: `both POs enter open execution phase`. Plan use: keep.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php:123` - vendor grouping. Why referenced: vendor drilldown. Prompt source: `PO shows vendors`. Plan use: keep.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php:148` - shopping list status `converted`. Why referenced: conversion lock. Prompt source: `structural data freezes`. Plan use: also lock list and snapshot cells.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php:168` - `updateVendorGroup()` validation. Why referenced: currently allows structural line edits. Prompt source: `only editable field during open execution is unit cost/price correction`. Plan use: restrict to unit cost/price and audit.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php:193` - patches `purchase_qty`, `purchase_unit`, `purchase_price`, `unit_price`. Why referenced: structural mutation conflict. Prompt source: `nothing structural changes`. Plan use: block `purchase_qty` and `purchase_unit`; audit price corrections.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php:220` - attachment type validation. Why referenced: receipt/proof support. Prompt source: `upload receipts and proof`. Plan use: keep.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php:288` - receipt upload marks group received. Why referenced: receipt-driven completion. Prompt source: `not complete until every vendor group has receipts`. Plan use: keep but ensure no stock-in without receipt.
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php:41` - all vendor groups receipt check. Why referenced: completion rule. Prompt source: `food/supplies PO completion`. Plan use: keep and split food/supplies served requirement.
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php:45` - served population progress. Why referenced: food-only completion rule. Prompt source: `Food PO not complete until every day served logged`. Plan use: apply only `procurement_track='food'`.
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php:61` - sets completed. Why referenced: final lock point. Prompt source: `After completed nothing can change`. Plan use: set `final_locked_at`.
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php:63` - actual budget per head/day. Why referenced: completion calculation. Prompt source: `Food PO actual budget per head per day calculated at completion`. Plan use: food only.
- `mobile/app/(tabs)/procurement.tsx:444` - `VendorDetail`. Why referenced: mobile PO detail. Prompt source: `mobile app FSS only uploading photos/proof/receipts and entering OR number`. Plan use: remove line edit controls.
- `mobile/app/(tabs)/procurement.tsx:457` - PATCH sends `items`. Why referenced: mobile can edit line details. Prompt source: `mobile limited to photos and OR`. Plan use: remove `items` from mobile payload.
- `mobile/app/(tabs)/procurement.tsx:553` - vendor line detail inputs. Why referenced: mobile structural edit conflict. Prompt source: `mobile limited`. Plan use: make read-only.
- `mobile/app/(tabs)/procurement.tsx:612` - receipt/proof lists. Why referenced: desired mobile behavior. Prompt source: `upload photos for proof/receipts`. Plan use: keep.

---

## Tests To Update Or Add

### Backend tests
- `backend/tests/Feature/FoodServiceOpsTest.php` - update procurement, budget, insights, menu cycle, and PO lifecycle assertions.
- `backend/tests/Feature/ReportsBrowseTest.php` - remove/update retired report tests.
- `backend/tests/Feature/AdminReportsTest.php` - remove admin `budget_report` allowlist tests.
- `backend/tests/Feature/FssReportScopeTest.php` - update removed report behavior to 404/not found for all roles or remove redundant tests.
- `backend/tests/Feature/InsightsTest.php` - delete because insights backend is removed.
- Add `backend/tests/Feature/FoodShoppingListGenerationTest.php` - assert missing dates block creation and no partial list is created.
- Add `backend/tests/Feature/SuppliesProcurementTest.php` - assert supplies list has no date span/menu cycle and converts to supplies PO.
- Add `backend/tests/Feature/PurchaseOrderExecutionLockTest.php` - assert converted PO blocks structural changes, allows audited unit cost correction, completion locks all fields.
- Add `backend/tests/Feature/MenuCyclePoSnapshotTest.php` - assert food PO conversion writes `menu_cycle_days.po_snapshot`.
- Add `backend/tests/Feature/BudgetLedgerRestructureTest.php` - assert ledger columns/source filters and no `procurement_span`.

### Frontend/mobile tests
- `frontend/app/api/fss/budgets/budget-routes.test.ts` - update filter from `type=po` to `source=system|manual`.
- `frontend/app/api/fss/insights/insights-routes.test.ts` - delete.
- `frontend/app/(rnd)/food-service/budget/placement.test.ts` - rewrite for no Insights tab and fiscal setup at top.
- Add component tests for action columns in procurement/menu-cycle/recipes tables.
- Add mobile procurement tests if test harness exists: OR/photo allowed, line edit disallowed.

---

## Errors and Conflicts Found

- `backend/routes/api.php:193` - `apiResource('inventory')` exposes inventory write endpoints to FSS/RND. Conflict with prompt: inventory should be backend reference only and not FSS stocking.
- `backend/routes/api.php:194` - `inventory/{inventory}/restock` route exists. Conflict with prompt: FSS no longer needs stocking.
- `backend/app/Http/Requests/FSS/StoreInventoryRequest.php:17` - `quantity_in_stock` is required on create. Conflict with prompt: supplies in inventory have no qty field when creating.
- `backend/app/Http/Requests/FSS/UpdateInventoryRequest.php:14` - `quantity_in_stock` is accepted on edit. Conflict with prompt: no qty field when editing.
- `frontend/components/layout/Sidebar.tsx:379` - Inventory nav visible. Conflict with prompt: inventory should not be navigable page for FSS.
- `frontend/app/(rnd)/food-service/inventory/page.tsx:287` - FSS/RND inventory page exists. Conflict with prompt: remove inventory page for food service staff entirely.
- `mobile/app/(tabs)/_layout.tsx:47` - mobile Inventory tab exists. Conflict with prompt: FSS inventory page removed.
- `mobile/app/(tabs)/inventory.tsx:336` - mobile inventory screen exists. Conflict with prompt: no FSS stocking page.
- `backend/app/Services/FSS/ReceivingService.php:98` - latest procurement updates `purchase_price` only, not vendor, and no lock exists. Conflict with prompt: vendor auto-updates from latest procurement unless manually locked.
- `backend/app/Http/Controllers/FSS/ShoppingListController.php:194` - draft shopping-list vendor edit updates `fs_items.default_supplier_id`. Conflict with prompt: vendor should update from latest procurement, not draft entry.
- `backend/database/migrations/2026_06_02_210757_create_menu_cycles_table.php:19` - menu status enum is `draft|active|archived`. Conflict with prompt: states must be `completed|active|upcoming`.
- `frontend/app/(rnd)/food-service/menu-cycle/page.tsx:684` - Menu Cycle default view is list. Conflict with prompt: landing page should be current active menu cycle.
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php:202` - PPA/menu snapshot is text only, no menu-cycle-day cell snapshot. Conflict with prompt: scaled values snapshot saved permanently to each cell.
- `backend/app/Services/FSS/ShoppingListPopulationService.php:169` - food shopping list subtracts on-hand inventory. Conflict with prompt: list should include all required ingredients for span.
- `backend/app/Http/Controllers/FSS/ShoppingListController.php:150` - generator can create list with partial coverage status. Conflict with prompt: block creation entirely if any date missing cycle/menu items.
- `backend/app/Http/Controllers/FSS/ShoppingListController.php:45` - converted shopping-list header can still be edited except population path. Conflict with prompt: all structural data freezes at conversion.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php:193` - open-execution vendor update can patch `purchase_qty` and `purchase_unit`. Conflict with prompt: only unit cost/price correction editable during open execution.
- `backend/app/Models/PurchaseOrderItem.php:8` - no correction audit model/table. Conflict with prompt: every correction logged with user and timestamp.
- `mobile/app/(tabs)/procurement.tsx:457` - mobile PATCH sends `items`. Conflict with prompt: mobile FSS limited to OR number, proof, receipts.
- `mobile/app/(tabs)/procurement.tsx:553` - mobile displays editable vendor line details. Conflict with prompt: mobile cannot edit structural PO lines.
- `backend/app/Services/FSS/FssDashboardService.php:31` - `inventory_no_stock` returned. Conflict with prompt: remove inventory/stock dashboard cards.
- `frontend/app/(rnd)/dashboard/page.tsx:107` - `costPerHeadKpi` exists. Conflict with prompt: remove budget per head per day from KPI cards.
- `frontend/app/(rnd)/dashboard/page.tsx:648` - Cost / Head Today card visible. Conflict with prompt: remove budget per head/day KPI and add Pending PO card.
- `mobile/app/(tabs)/index.tsx:163` - Items out of stock KPI visible. Conflict with prompt: remove inventory/stock cards.
- `backend/database/migrations/2026_06_27_000002_create_budget_ledger_table.php:19` - `budget_ledger.procurement_span` exists. Conflict with prompt: no procurement span column.
- `backend/app/Http/Controllers/FSS/BudgetController.php:96` - ledger response returns `procurement_span`. Conflict with prompt: ledger columns exclude procurement span.
- `frontend/app/(rnd)/food-service/budget/page.tsx:34` - Budget page still has `insights` tab. Conflict with prompt: remove graphs and insights.
- `frontend/app/(rnd)/food-service/budget/page.tsx:81` - Budget page has five KPI cards. Conflict with prompt: only Allocated, Total Deductions, Remaining.
- `frontend/app/(rnd)/food-service/budget/page.tsx:100` - budget progress bar exists. Conflict with prompt: remove progress bar.
- `frontend/app/(rnd)/food-service/budget/page.tsx:238` - budget ledger renders `procurement_span`. Conflict with prompt: no procurement span column.
- `backend/routes/api.php:233` through `backend/routes/api.php:236` - budget insights backend routes exist. Conflict with prompt: remove graphs/insights and backends.
- `backend/app/Http/Controllers/FSS/InsightsController.php:14` - Insights backend exists. Conflict with prompt: remove insights backends.
- `frontend/app/(rnd)/food-service/insights/page.tsx:1` - insights frontend exists. Conflict with prompt: remove graphs/insights.
- `backend/app/Http/Controllers/ReportController.php:25` - `dietary_cash_book` still allowed. Conflict with prompt: remove Dietary Cashbook report entirely.
- `backend/app/Http/Controllers/ReportController.php:27` - `budget_report` still allowed. Conflict with prompt: remove Budget report entirely.
- `backend/app/Http/Controllers/ReportController.php:28` - `inventory_report` still allowed. Conflict with prompt: remove Inventory report entirely.
- `backend/app/Services/Reports/ReportService.php:35` - `dietary_cash_book` generator registered. Conflict with prompt: remove backend.
- `backend/app/Services/Reports/ReportService.php:40` - `budget_report` generator registered. Conflict with prompt: remove backend.
- `backend/app/Services/Reports/ReportService.php:42` - `inventory_report` generator registered. Conflict with prompt: remove backend.
- `frontend/components/reports/ReportsBrowser.tsx:41` - Dietary Cash Book catalog entry exists. Conflict with prompt: remove frontend.
- `frontend/components/reports/ReportsBrowser.tsx:43` - Budget Report catalog entry exists. Conflict with prompt: remove frontend.
- `frontend/components/reports/ReportsBrowser.tsx:44` - Inventory Report catalog entry exists. Conflict with prompt: remove frontend.
- `frontend/components/reports/ReportsBrowser.tsx:413` - archived reports open via row click. Conflict with prompt: delete/open actions should not be triggered by row/name click.
- `backend/app/Services/Reports/Generators/ProcurementPackGenerator.php:85` - generator falls back to current month. Conflict with prompt: remaining reports must be reproducible from actual input.
- `backend/app/Services/Reports/Generators/AccomplishmentReportGenerator.php:78` - generator falls back to current month. Conflict with prompt: no stale/dead-end values.
- `backend/app/Services/Reports/Generators/DemographicCensusGenerator.php:42` - generator falls back to current year/day. Conflict with prompt: reproducible from actual input.
- `backend/app/Services/Reports/Generators/PatientMenuPlanGenerator.php:50` - generator can pick latest meal plan when exact id missing. Conflict with prompt: reproducible from actual input.
