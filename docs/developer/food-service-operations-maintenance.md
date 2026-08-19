# Food Service Operations — Developer Maintenance Guide

Last verified: **2026-08-19**.

## Behavioral Invariants

1. Recipe measurements remain exact baseline data.
2. `fs_items.include_in_generated_lists = false` only suppresses auto-generation; it never removes a recipe ingredient.
3. Suggested-list generation requires one positive `estimate_population` for the span. Do not reintroduce per-slot overrides.
4. Generated rows are recalculated and cannot be deleted. Users change purchase fields or set `included_in_po = false`. Manual rows survive recalculation and may be deleted.
5. PO approval copies included rows only and calls centralized readiness under the budget lock.
6. Evidence upload never changes vendor status. Explicit receiving requires actual values, receipt, proof, and supplier. OR remains optional.
7. Final reports use actual values; incomplete records remain visibly draft.
8. Open vendor reassignment reuses the vendor-group PATCH endpoint. It may move a whole group or one line, must merge an existing destination group, and is blocked by source/destination evidence, received status, or PO completion.
9. Procurement reports consume every PO line. Do not filter by shopping-list row source; manual additions and fully manual lists are valid report inputs.

## Main Files

| Concern | Backend | Web/mobile |
|---|---|---|
| Catalog generation flag | `FsItemController.php`, `FsItem.php`, `FsItemAuditValues.php` | `fsCatalogService.ts`, Inventory page |
| Menu naming/templates/profile | `MenuCycle.php`, `MenuCycleController.php`, `MenuCycleTemplateController.php` | `menuCycleService.ts`, Menu Cycle page, `MenuSlotRecipePage.tsx` |
| Scaling/list sync | `ShoppingListPopulationService.php` | Procurement page |
| Shopping-list API | `ShoppingListController.php`, `ShoppingListResource.php` | `procurementService.ts` |
| PO lifecycle/vendor reassignment | `PurchaseOrderLifecycleService.php`, `PurchaseOrderController.php` | Procurement page, `VendorChangeControls.tsx`, mobile `procurement.tsx` |
| Receiving/catalog price/comparison | `ReceivingService.php`, `PurchaseOrderResource.php` | `PurchaseValueComparison.tsx`, `FssPurchaseOrders.tsx`, mobile `procurement.tsx` |
| Reports | `ProcurementPackGenerator.php`, procurement-pack Blade, `ProgramProjectActivityGenerator.php` | Reports browser |
| Auditing | Shopping-list and PO revision serializers, `config/audit.php` | `AuditTrail.tsx` |
| Demo | `FsCatalogSeeder.php`, `FoodServiceDemoSeeder.php` | — |

## Data Flow

`MenuCycleDay` → `MenuCycleCostService` → `ShoppingListPopulationService` → included `ShoppingListItem` rows → planned `PurchaseOrderItem` snapshot → confirmed actuals → lifecycle completion → budget ledger/PPA/procurement pack.

Use `ShoppingListPopulationService` for scaling/synchronization and `PurchaseOrderLifecycleService` for readiness, snapshots, completion, and PPA values. Do not duplicate these calculations in controllers or clients.

## Safe Change Checklist

- Use reversible migrations and explicit decimal precision.
- Add mutable fields to fillables/casts, resources, and revision serializers.
- Preserve generated/manual separation during recalculation.
- Keep budget checks inside the fiscal-year budget transaction.
- Store evidence privately and render through authorized URLs or embedded report data.
- For mutations, verify `backend/config/audit.php` and `AuditInventoryContractTest` coverage.
- Run focused tests, Pint, backend full suite, web lint/type-check/tests, and mobile type-check.

## High-Value Tests

- `FoodShoppingListGenerationTest.php`
- `PurchaseOrderReadinessTest.php`
- `PurchaseOrderExecutionLockTest.php`
- `PurchaseOrderCompletionPatternTest.php`
- `PoAttachmentUploadTest.php`
- `FoodServiceOpsTest.php`
- `MealPrepShortfallTest.php`
- shopping-list and purchase-order revision serializer tests
- `AuditInventoryContractTest.php`
- `FoodServiceDemoSeederSourceTest.php`

## Common Failure Modes

- Counting excluded rows in list/PPA/budget totals.
- Recalculation deleting manual additions.
- Treating upload as receiving completion.
- Moving items into a received/evidence-bearing vendor group or leaving an empty source group behind.
- Hiding manual PO lines from the procurement pack because they were not menu-generated.
- Showing planned values as confirmed actuals.
- Requiring OR despite receipt/proof.
- Scaling before the span estimate exists.
- Updating a template when editing its loaded copy.
