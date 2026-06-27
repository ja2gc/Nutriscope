# Food Service Data Variables

Source of truth: `docs/superpowers/plan/foodservice redesign plan.md`, with `docs/superpowers/plan/foodservice-analysis-and-restructure.md` used only to find existing names and conflicts.

Rule: Food Service UI/API inputs may show or accept only the variables listed in this file. Existing storage columns not listed as user-facing may remain backend-only when needed for historical data, receiving, snapshots, or audit.

---

## Review Passes

### Review 1 - Original Redesign Plan
- Inventory is a backend reference catalog.
- Food shopping list and supplies list are separate procurement tracks.
- Food shopping list scaling comes from list-level estimated population, not served population and not stock.
- Supplies list is manual, no date span, no menu cycle, no estimated population.
- Purchase orders freeze structural data at conversion.
- Budget is ledger-first.
- Retired reports and insights are removed.

### Review 2 - Mermaid Workflow
- Inventory creation data:
  - Ingredients: `name`, `category`, `vendor`, `unit`, `unit/cost`.
  - Supplies: `name`, `category`, `vendor`, `cost/unit`, no quantity.
- Recipe/single-item data:
  - ingredients, quantities, units, baseline serving.
- Food list generation:
  - date range, required ingredients, vendor suggestion, estimated population, live quantity/cost scaling, estimated budget per head/day.
- Supplies list:
  - manual item search, qty to procure, cost per unit, total cost.
- PO finalization:
  - OR number, receipts, proof, served population for food completion only.

### Review 3 - Existing Code Reuse
- Reuse `fs_items` as the catalog table.
- Reuse `shopping_lists.procurement_track` and `purchase_orders.procurement_track`.
- Reuse `shopping_list_items` for food and supplies list lines.
- Reuse `purchase_order_vendor_groups` for vendor drill-down.
- Reuse `budget_ledger` for shared deductions/additions.
- Reuse `menu_cycle_days.po_snapshot` for frozen food PO snapshots.
- Do not create duplicate tables for catalog, lists, POs, or ledger.

### Review 4 - Exclusion Check
- `ready_to_eat` is not in the original Food Service plan. Do not expose it in Food Service UI, filters, badges, or create/edit inputs.
- `quantity_in_stock`, low-stock thresholds, expiry dates, usage rates, stock status cards, and restock fields are not Food Service user inputs.
- Food shopping list ingredient quantity is not editable by the user; it is calculated from `estimate_population`.
- Shopping-list unit is not editable.
- Supplies list must not show food date span, menu cycle, estimated population, or estimated per-head/day.
- Food Service dashboards must not show inventory/stock cards or budget-per-head KPI cards.

---

## Canonical User-Facing Variables

### 1. Inventory Catalog

Existing model/table:
- `FsItem` model: `backend/app/Models/FsItem.php:19`
- `fs_items` table: `backend/app/Models/FsItem.php:24`

Allowed Food Service catalog variables:
- `name` - existing fillable in `backend/app/Models/FsItem.php:27`
- `kind` - existing fillable in `backend/app/Models/FsItem.php:27`; allowed values in Food Service are `ingredient` and `supply` only
- `category` - existing fillable in `backend/app/Models/FsItem.php:27`
- `base_unit` - existing fillable in `backend/app/Models/FsItem.php:28`; displayed as `unit` for supplies
- `purchase_unit` - existing fillable in `backend/app/Models/FsItem.php:28`; ingredient only
- `purchase_price` - existing fillable/cast in `backend/app/Models/FsItem.php:28` and `backend/app/Models/FsItem.php:34`; displayed as cost per unit/package
- `units_per_purchase` - existing fillable/cast in `backend/app/Models/FsItem.php:28` and `backend/app/Models/FsItem.php:35`; ingredient only
- `default_supplier_id` - existing fillable/relation in `backend/app/Models/FsItem.php:29` and `backend/app/Models/FsItem.php:84`; displayed as vendor
- `default_supplier_locked_at` - existing fillable/cast in `backend/app/Models/FsItem.php:29` and `backend/app/Models/FsItem.php:37`
- `default_supplier_locked_by` - existing fillable/relation in `backend/app/Models/FsItem.php:29` and `backend/app/Models/FsItem.php:46`
- `unit_cost` - existing computed accessor in `backend/app/Models/FsItem.php:78`; calculated, not stored input

Backend-only catalog support:
- `is_active` - existing fillable/cast in `backend/app/Models/FsItem.php:30` and `backend/app/Models/FsItem.php:36`; not a normal user form field

Explicitly excluded from Food Service catalog UI/API inputs:
- `ready_to_eat`
- `quantity_in_stock`
- `expiry_date`
- `received_date`
- `usage_rate`
- `minimum_stock_threshold`
- stock status / low-stock state
- restock quantity

Single-item rule:
- A single item is a food catalog item with `kind = ingredient`.
- Do not use `kind = ready_to_eat` in Food Service.

### 2. Recipe And Single Item Creation

Existing model/table:
- `FoodServiceRecipe` model: `backend/app/Models/FoodServiceRecipe.php`
- `FoodServiceRecipeIngredient` model: `backend/app/Models/FoodServiceRecipeIngredient.php`
- `food_service_recipe_ingredients.quantity` and `food_service_recipe_ingredients.unit` already exist in schema.

Allowed recipe variables:
- `name`
- `category`
- `prep_notes`
- `servings` - baseline serving count; existing plan name is baseline serving, existing DB name is `servings`
- `ingredients[].fs_item_id`
- `ingredients[].quantity`
- `ingredients[].unit`

Allowed calculated recipe display values:
- `converted_quantity` - calculated from ingredient unit to `fs_items.base_unit`
- `converted_unit_cost` - calculated from `fs_items.purchase_price`, `purchase_unit`, and `units_per_purchase`
- `cost` - existing recipe cost, calculated from ingredients
- `conversion_warning` - response/UI warning only
- `is_convertible` - response/UI boolean only

Excluded recipe variables:
- nutrition values
- stock quantities
- low-stock indicators
- purchase-order fields

### 3. Menu Cycle

Existing model/table:
- `MenuCycle` model: `backend/app/Models/MenuCycle.php`
- `MenuCycleDay` model: `backend/app/Models/MenuCycleDay.php`

Allowed menu-cycle variables:
- `name` - existing fillable in `backend/app/Models/MenuCycle.php:15`
- `cycle_days` - existing fillable in `backend/app/Models/MenuCycle.php:15`
- `week_start_date` - existing fillable in `backend/app/Models/MenuCycle.php:15`
- `status` - existing fillable in `backend/app/Models/MenuCycle.php:15`; allowed states are `completed`, `active`, `upcoming`
- `is_active` - existing fillable/cast in `backend/app/Models/MenuCycle.php:15` and `backend/app/Models/MenuCycle.php:22`

Allowed menu-cycle day variables:
- `day_of_week` - existing fillable in `backend/app/Models/MenuCycleDay.php:14`
- `meal_type` - existing fillable in `backend/app/Models/MenuCycleDay.php:14`
- `recipe_id` - existing fillable in `backend/app/Models/MenuCycleDay.php:15`
- `fs_item_id` - existing fillable in `backend/app/Models/MenuCycleDay.php:15`
- `quantity` - existing fillable/cast in `backend/app/Models/MenuCycleDay.php:15` and `backend/app/Models/MenuCycleDay.php:22`; for single item per-head quantity
- `servings_override` - existing fillable/cast in `backend/app/Models/MenuCycleDay.php:15` and `backend/app/Models/MenuCycleDay.php:23`; menu-cell scale override
- `snapshot_purchase_order_id` - existing fillable/relation in `backend/app/Models/MenuCycleDay.php:18` and `backend/app/Models/MenuCycleDay.php:33`
- `po_snapshot` - existing fillable/cast in `backend/app/Models/MenuCycleDay.php:18` and `backend/app/Models/MenuCycleDay.php:28`
- `po_snapshot_at` - existing fillable/cast in `backend/app/Models/MenuCycleDay.php:18` and `backend/app/Models/MenuCycleDay.php:29`
- `po_snapshot_locked` - existing fillable/cast in `backend/app/Models/MenuCycleDay.php:18` and `backend/app/Models/MenuCycleDay.php:30`

Served population:
- Source is `meal_prep_logs.served_population`.
- It is actual daily headcount logged/backfilled by FSS.
- It must not affect procurement planning quantities.

Compatibility-only field:
- `menu_cycle_days.estimate_population` exists in `backend/app/Models/MenuCycleDay.php:16`, but Food Shopping List planning must use `shopping_lists.estimate_population`.

Excluded menu-cycle UI:
- budget-per-head KPI cards
- inventory/stock cards
- `ready_to_eat` badges/text

### 4. Food Shopping List

Existing model/table:
- `ShoppingList` model: `backend/app/Models/ShoppingList.php`
- `ShoppingListItem` model: `backend/app/Models/ShoppingListItem.php`

Allowed food shopping list header variables:
- `procurement_track = food` - existing fillable in `backend/app/Models/ShoppingList.php:15`
- `name` - existing fillable in `backend/app/Models/ShoppingList.php:14`
- `period_start` - existing fillable/cast in `backend/app/Models/ShoppingList.php:14` and `backend/app/Models/ShoppingList.php:27`
- `period_end` - existing fillable/cast in `backend/app/Models/ShoppingList.php:14` and `backend/app/Models/ShoppingList.php:28`
- `days_span` - existing fillable/cast in `backend/app/Models/ShoppingList.php:15` and `backend/app/Models/ShoppingList.php:29`
- `estimate_population` - existing fillable/cast in `backend/app/Models/ShoppingList.php:16` and `backend/app/Models/ShoppingList.php:31`
- `estimate_population_updated_at` - existing fillable/cast in `backend/app/Models/ShoppingList.php:16` and `backend/app/Models/ShoppingList.php:32`
- `list_type` - existing fillable in `backend/app/Models/ShoppingList.php:15`; for food generation use `suggested`
- `status` - existing fillable in `backend/app/Models/ShoppingList.php:15`; allowed values are `draft`, `converted`

Allowed calculated food shopping list header display:
- `estimated_total` - calculated from `shopping_list_items.total`
- `estimated_budget_per_head_per_day` - calculated as `estimated_total / (days_span * estimate_population)`

Allowed food shopping list item variables:
- `fs_item_id` - existing fillable in `backend/app/Models/ShoppingListItem.php:13`
- `ingredient_name` - existing fillable in `backend/app/Models/ShoppingListItem.php:13`
- `baseline_servings` - existing fillable/cast in `backend/app/Models/ShoppingListItem.php:17` and `backend/app/Models/ShoppingListItem.php:27`
- `baseline_quantity` - existing fillable/cast in `backend/app/Models/ShoppingListItem.php:17` and `backend/app/Models/ShoppingListItem.php:28`
- `scaled_quantity` - existing fillable/cast in `backend/app/Models/ShoppingListItem.php:17` and `backend/app/Models/ShoppingListItem.php:29`
- `scaled_unit` - existing fillable in `backend/app/Models/ShoppingListItem.php:17`
- `qty` - existing fillable/cast in `backend/app/Models/ShoppingListItem.php:14` and `backend/app/Models/ShoppingListItem.php:21`; calculated, not user-editable for food list
- `unit` - existing fillable in `backend/app/Models/ShoppingListItem.php:14`; follows recipe/single item, not user-editable
- `supplier_id` - existing fillable/relation in `backend/app/Models/ShoppingListItem.php:14` and `backend/app/Models/ShoppingListItem.php:47`
- `unit_price` - existing fillable/cast in `backend/app/Models/ShoppingListItem.php:14` and `backend/app/Models/ShoppingListItem.php:22`
- `total` - existing fillable/cast in `backend/app/Models/ShoppingListItem.php:14` and `backend/app/Models/ShoppingListItem.php:23`
- `purchase_qty` - existing fillable/cast in `backend/app/Models/ShoppingListItem.php:15` and `backend/app/Models/ShoppingListItem.php:24`
- `purchase_unit` - existing fillable in `backend/app/Models/ShoppingListItem.php:15`
- `purchase_price` - existing fillable/cast in `backend/app/Models/ShoppingListItem.php:15` and `backend/app/Models/ShoppingListItem.php:25`
- `vendor_locked_at` - existing fillable/cast in `backend/app/Models/ShoppingListItem.php:16` and `backend/app/Models/ShoppingListItem.php:26`
- `vendor_locked_by` - existing fillable in `backend/app/Models/ShoppingListItem.php:16`

Food shopping list behavior:
- Generation is all-or-nothing for missing menu cycle/menu items.
- No partial list is created.
- User enters `estimate_population` once.
- All item quantities and costs recalculate from `estimate_population`.
- Ingredient quantity and unit are not editable.
- Estimated budget per head/day must not show blank/dash after `estimate_population` exists.

Excluded food shopping list variables/UI:
- inventory stock deduction
- editable item unit
- editable generated ingredient quantity
- served population
- supplies item add UI
- low-stock indicators

### 5. Supplies List

Existing model/table:
- Reuse `ShoppingList` and `ShoppingListItem`.

Allowed supplies list header variables:
- `procurement_track = supplies` - existing fillable in `backend/app/Models/ShoppingList.php:15`
- `name` - existing fillable in `backend/app/Models/ShoppingList.php:14`
- `list_date` - existing fillable/cast in `backend/app/Models/ShoppingList.php:14` and `backend/app/Models/ShoppingList.php:26`
- `list_type = manual` - existing fillable in `backend/app/Models/ShoppingList.php:15`
- `status` - existing fillable in `backend/app/Models/ShoppingList.php:15`

Allowed supplies list item variables:
- `fs_item_id`
- `ingredient_name`
- `qty`
- `unit`
- `supplier_id`
- `unit_price`
- `total`
- `purchase_qty`
- `purchase_unit`
- `purchase_price`
- `vendor_locked_at`
- `vendor_locked_by`

Supplies behavior:
- RND searches supply catalog items one by one.
- `fs_items.kind` must be `supply`.
- User inputs only quantity to procure and cost per unit; item name/unit/vendor default from catalog.
- Running total recalculates immediately.
- Supplies PO conversion is independent from food PO conversion.

Excluded supplies list variables/UI:
- `period_start`
- `period_end`
- `days_span`
- `estimate_population`
- `estimated_budget_per_head_per_day`
- menu cycle references
- recipe/baseline fields

### 6. Purchase Order

Existing model/table:
- `PurchaseOrder` model: `backend/app/Models/PurchaseOrder.php:8`
- `PurchaseOrderItem` model: `backend/app/Models/PurchaseOrderItem.php:8`
- `PurchaseOrderVendorGroup` model: `backend/app/Models/PurchaseOrderVendorGroup.php:10`
- `PurchaseOrderAttachment` model: `backend/app/Models/PurchaseOrderAttachment.php:8`

Allowed PO variables:
- `shopping_list_id` - existing fillable in `backend/app/Models/PurchaseOrder.php:14`
- `po_number` - existing fillable in `backend/app/Models/PurchaseOrder.php:14`
- `order_date` - existing fillable in `backend/app/Models/PurchaseOrder.php:15`
- `received_date` - existing fillable in `backend/app/Models/PurchaseOrder.php:15`
- `total_amount` - existing fillable/cast in `backend/app/Models/PurchaseOrder.php:15` and `backend/app/Models/PurchaseOrder.php:23`
- `actual_budget_per_head_per_day` - existing fillable/cast in `backend/app/Models/PurchaseOrder.php:15` and `backend/app/Models/PurchaseOrder.php:24`; food PO completion only
- `status` - existing fillable in `backend/app/Models/PurchaseOrder.php:16`
- `lifecycle_status` - existing fillable in `backend/app/Models/PurchaseOrder.php:16`
- `procurement_track` - existing fillable in `backend/app/Models/PurchaseOrder.php:16`
- `converted_at`
- `completed_at`
- `structural_locked_at`
- `final_locked_at`

Allowed vendor group variables:
- `supplier_id` - existing fillable/relation in `backend/app/Models/PurchaseOrderVendorGroup.php:16` and `backend/app/Models/PurchaseOrderVendorGroup.php:35`
- `or_number` - existing fillable in `backend/app/Models/PurchaseOrderVendorGroup.php:17`
- `status` - existing fillable in `backend/app/Models/PurchaseOrderVendorGroup.php:18`
- `total_amount` - existing fillable/cast in `backend/app/Models/PurchaseOrderVendorGroup.php:19` and `backend/app/Models/PurchaseOrderVendorGroup.php:25`
- `received_at` - existing fillable in `backend/app/Models/PurchaseOrderVendorGroup.php:20`

Allowed PO item variables:
- `fs_item_id` - existing fillable in `backend/app/Models/PurchaseOrderItem.php:13`
- `description` - existing fillable in `backend/app/Models/PurchaseOrderItem.php:13`
- `qty` - existing fillable/cast in `backend/app/Models/PurchaseOrderItem.php:14` and `backend/app/Models/PurchaseOrderItem.php:19`
- `unit` - existing fillable in `backend/app/Models/PurchaseOrderItem.php:14`
- `unit_price` - existing fillable/cast in `backend/app/Models/PurchaseOrderItem.php:14` and `backend/app/Models/PurchaseOrderItem.php:20`
- `total_value` - existing fillable/cast in `backend/app/Models/PurchaseOrderItem.php:14` and `backend/app/Models/PurchaseOrderItem.php:21`
- `purchase_qty` - existing fillable/cast in `backend/app/Models/PurchaseOrderItem.php:15` and `backend/app/Models/PurchaseOrderItem.php:22`
- `purchase_unit` - existing fillable in `backend/app/Models/PurchaseOrderItem.php:15`
- `purchase_price` - existing fillable/cast in `backend/app/Models/PurchaseOrderItem.php:15` and `backend/app/Models/PurchaseOrderItem.php:23`

Allowed attachment variables:
- `type` - existing fillable in `backend/app/Models/PurchaseOrderAttachment.php:10`; values `receipt`, `proof`
- `path` - existing fillable in `backend/app/Models/PurchaseOrderAttachment.php:10`
- `caption` - existing fillable in `backend/app/Models/PurchaseOrderAttachment.php:10`

Allowed correction variables:
- `purchase_order_item_id`
- `old_unit_price`
- `new_unit_price`
- `old_purchase_price`
- `new_purchase_price`
- `corrected_by`
- `corrected_at`
- `reason`

Open execution behavior:
- RND/FSS can input OR numbers.
- RND/FSS can upload receipts/proof on vendor groups.
- RND can correct unit cost/price only.
- No structural fields change after conversion: item, quantity, unit, and vendor grouping stay locked.

Completion behavior:
- Food PO completes after all vendor groups have receipts and every span date has served population.
- Supplies PO completes after all vendor groups have receipts.
- Completed POs are permanently locked.

### 7. Budget

Existing model/table:
- `BudgetLedger` model: `backend/app/Models/BudgetLedger.php`
- `FoodServiceSetting` model: `backend/app/Models/FoodServiceSetting.php`

Allowed budget setup variables:
- `fiscal_year`
- `allocated_amount`
- `per_head_day_limit` - existing fillable/cast in `backend/app/Models/FoodServiceSetting.php:10` and `backend/app/Models/FoodServiceSetting.php:13`

Allowed budget ledger variables:
- `fiscal_year` - existing fillable in `backend/app/Models/BudgetLedger.php:13`
- `type` - existing fillable in `backend/app/Models/BudgetLedger.php:13`; values `po_deduction`, `manual_addition`, `manual_deduction`
- `source` - existing fillable in `backend/app/Models/BudgetLedger.php:13`; values `system`, `manual`
- `amount` - existing fillable/cast in `backend/app/Models/BudgetLedger.php:13` and `backend/app/Models/BudgetLedger.php:19`
- `reason` - existing fillable in `backend/app/Models/BudgetLedger.php:13`
- `reference` - existing fillable in `backend/app/Models/BudgetLedger.php:13`
- `purchase_order_id` - existing fillable/relation in `backend/app/Models/BudgetLedger.php:14` and `backend/app/Models/BudgetLedger.php:22`
- `created_by` - existing fillable/relation in `backend/app/Models/BudgetLedger.php:14` and `backend/app/Models/BudgetLedger.php:29`
- `created_at` - standard timestamp for date column

Allowed budget UI:
- fiscal year setup at top
- summary cards: Allocated, Total Deductions, Remaining
- ledger log columns: date, type, amount, reason, reference, created by
- filter by `source`

Excluded budget UI/API:
- graphs
- insights
- progress bar
- PO Deductions card
- Manual Additions card
- Manual Deductions card
- Over Allocation card
- procurement span column
- budget report

### 8. Reports

Allowed reports:
- Program Project Activity
- Menu Calendar
- Procurement Pack
- Accomplishment/Diet List reports
- Clinical reports already outside this Food Service variable map

Removed reports:
- Dietary Cashbook
- Budget Report
- Inventory Report

Report rule:
- Any remaining report must require concrete input filters or IDs and must not fall back to current month/year/latest records when the plan requires reproducible data.

---

## Implementation Gate

Before any Food Service change is marked complete:
- Check all visible form fields against this file.
- Check all table columns/cards/badges against this file.
- Check all API accepted payload fields against this file.
- Remove or hide unlisted Food Service fields.
- Keep backend-only legacy columns only when required for migrations, historical records, receiving, or audit.

