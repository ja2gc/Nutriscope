# Food-service data meaning

Verified against committed source: `d873ea2f3fec63a089bc70c6948f1611ae553d7b` plus the receipt-total change in this working tree (2026-10-23).

Purpose: distinguish operational planning, purchasing, receiving and completed history. It covers both web and FSS-mobile consumers; see [workflow owners](README.md) and [mobile](mobile.md).

| Fields / family | Meaning and authority |
|---|---|
| MenuCycleDay.recipe_id / fs_item_id | Recipe versus single catalog item; [MenuCycleDay](../../../backend/app/Models/MenuCycleDay.php) links FoodServiceRecipe, not the NCP Recipe model |
| line_order, servings_override, recipe_override | Ordered menu lines and per-line overrides; trace [MenuCycleController](../../../backend/app/Http/Controllers/FSS/MenuCycleController.php), [MenuCycleCostService](../../../backend/app/Services/MenuCycleCostService.php) and client contracts before changing order/scaling |
| estimate_population | Planned population context on menu/list records; [ShoppingList](../../../backend/app/Models/ShoppingList.php), MenuCycleDay and [ShoppingListPopulationService](../../../backend/app/Services/FSS/ShoppingListPopulationService.php). It is not evidence of people actually served |
| ShoppingListItem.source, included_in_po | Generated/manual provenance versus PO inclusion; [ShoppingListItem](../../../backend/app/Models/ShoppingListItem.php), population service and [ShoppingListController](../../../backend/app/Http/Controllers/FSS/ShoppingListController.php). Excluding a generated row from purchase is different from deleting a recipe ingredient |
| baseline_servings, baseline_quantity, scaled_quantity, generation_baseline | Generation/scaling context retained on shopping rows. Trace syncItems and generationBaseline; preserve manual purchase changes and the selected unit conversion path |
| qty/unit/unit_price versus purchase_qty/purchase_unit/purchase_price | Different quantity/price representations on shopping and PO models. Inspect the owning conversion/calculation path and Resource; do not rename or combine them on the assumption their numbers match |
| PurchaseOrderItem.actual_qty / actual_unit_price | Receiving values, distinct from planned snapshots; [PurchaseOrderItem](../../../backend/app/Models/PurchaseOrderItem.php), [ReceivingService](../../../backend/app/Services/FSS/ReceivingService.php), [PurchaseOrderLifecycleService](../../../backend/app/Services/FSS/PurchaseOrderLifecycleService.php). Actual quantity casts to decimal:3, unit price to decimal:6; totals use their own precision |
| PO/vendor-group status, receipts/proof and completion | Evidence presence and confirmed receiving are separate steps. Trace lifecycle service and [PurchaseOrderController](../../../backend/app/Http/Controllers/FSS/PurchaseOrderController.php); uploading a file alone must not be treated as completion |
| po_snapshot, po_snapshot_at, po_snapshot_locked | Stored menu-day PO context; inspect snapshot writer and report consumers before replacing it with current catalog values |
| DietListCount.population, collected_ward_diet_lists, apportioned_distributed_meals, off_duty | Separate persisted attendance/accomplishment fields; [DietListCount](../../../backend/app/Models/DietListCount.php) defines casts. Their UI/workflow meaning must be traced through [DietListCountController](../../../backend/app/Http/Controllers/FSS/DietListCountController.php) and [ConsumptionService](../../../backend/app/Services/FSS/ConsumptionService.php); similar labels are not aliases |
| Budget.fiscal_year, allocated_amount, per_head_day_limit and ledger entries | [Budget](../../../backend/app/Models/Budget.php) links ledger by fiscal_year and computes remainingBalance from allocation + manual additions − PO/manual deductions, rounded to two decimals. [BudgetLedgerListener](../../../backend/app/Listeners/BudgetLedgerListener.php) owns completion deductions; per-head limits are separate from balance |

Money/quantity casts differ by model; preserve precision through API types, edits, conversion and reports. The presence of decimal:6 storage does not guarantee every consumer preserves all digits. Read the complete round trip before changing it.

## Vendor receipt total

`PurchaseOrderVendorGroup.receipt_total_override` is nullable money with two decimal places: null means automatic, including existing records; a non-null value means a fixed actual receipt amount, even when it equals the calculated amount or zero. `recalculateTotal()` owns the effective `total_amount`: the override when present, otherwise the existing sum of actual quantity × price (or planned line total when actuals are missing), rounded once to cents. The nullable override is necessary to persist the checkbox mode; it is not another unit price.

The existing vendor-group PATCH accepts `use_calculated_total` and, when false, `total_amount` (non-negative, up to 99999999.99, at most two decimals, matching the existing total column). True clears the override. Omitted mode fields preserve it for older clients. The Resource adds an optional-compatible boolean `use_calculated_total` and retains the existing `total_amount` shape. No unit-price response precision changes.

Web Procurement and mobile `app/(tabs)/procurement.tsx` show **Actual receipt total** with **Use calculated total** checked by default. Editing the receipt amount unchecks it and freezes the amount; checking it resumes live item-total calculation. It never changes item quantities or prices. Per-item **Actual Total** retains quantity derivation. Receipt/OR-only saves omit item values, avoiding resubmission of rounded price displays. Mobile `lib/foodService.ts` does not own procurement requests.

Received groups and completed/archived POs reject receipt-total changes. Existing RND/FSS permissions and evidence rules remain. Whole-group vendor moves carry the effective receipt total into the destination (adding the destination total on merge if either is manual); a partial move preserves the source's fixed amount while rows remain. Completion, per-head calculations and the budget ledger use effective group totals. Procurement packs show a receipt-adjustment row when overridden totals differ from item totals. Stored report bytes and existing records are not backfilled.

Audit revisions capture the mode and effective amount. Older revision groups lacking the mode remain readable and show it as unrecorded. Synthetic regressions live in `PurchaseOrderExecutionLockTest`, web `procurement/page.test.tsx`, and mobile `procurementReceiving.test.cjs`. Establish [test isolation](../platform/testing.md) before execution.

Use [general data tracing](../platform/data-semantics.md) for migrations, Resources, historical snapshots, audit serializers and consumer checks. Inspect applicable writers, installed constraints and balance rules before changing financial data. This selected dictionary is not a claim to cover every operational field.
