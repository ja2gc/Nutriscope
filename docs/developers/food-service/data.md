# Food-service data meaning

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

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

Use [general data tracing](../platform/data-semantics.md) for migrations, Resources, historical snapshots, audit serializers and consumer checks. Inspect applicable writers, installed constraints and balance rules before changing financial data. This selected dictionary is not a claim to cover every operational field.
