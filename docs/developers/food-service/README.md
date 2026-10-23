# Food service

Verified against committed source: `d873ea2f3fec63a089bc70c6948f1611ae553d7b` plus the receipt-total change in this working tree (2026-10-23).

Purpose: maintain operational menus, shopping, purchasing and budgets separately from clinical meal planning.

This group covers planning through execution across RND web and FSS mobile. Follow [data meaning](data.md) for quantities/prices/statuses/snapshots, [mobile maintenance](mobile.md) for native clients, and the [detailed reference](reference.md) for invariants. EAS/signing/publication is linked from the mobile guide. Shared help, announcements and accounts live in [shared features](../shared/README.md).

| Responsibility | Source |
|---|---|
| Menu costs and generated shopping quantities | [MenuCycleCostService](../../../backend/app/Services/MenuCycleCostService.php), [ShoppingListPopulationService](../../../backend/app/Services/FSS/ShoppingListPopulationService.php) |
| PO readiness, vendor changes and completion | [PurchaseOrderLifecycleService](../../../backend/app/Services/FSS/PurchaseOrderLifecycleService.php) |
| Confirmed receiving | [ReceivingService](../../../backend/app/Services/FSS/ReceivingService.php) |
| Automatic/manual receipt totals | [PurchaseOrderVendorGroup](../../../backend/app/Models/PurchaseOrderVendorGroup.php), vendor-group update in [PurchaseOrderController](../../../backend/app/Http/Controllers/FSS/PurchaseOrderController.php); [data meaning](data.md#vendor-receipt-total) |
| Fiscal-year balance and completion ledger | [Budget](../../../backend/app/Models/Budget.php), [BudgetLedgerListener](../../../backend/app/Listeners/BudgetLedgerListener.php) |
| Attendance/service completion | [ConsumptionService](../../../backend/app/Services/FSS/ConsumptionService.php) |
| Clients | [procurementService](../../../frontend/services/procurementService.ts), [menuCycleService](../../../frontend/services/menuCycleService.ts), [mobile foodService](../../../mobile/lib/foodService.ts) |

Follow menu → generated/manual shopping row → PO snapshot → actual receiving → completion/ledger/report. Preserve generated/manual distinctions, planned versus actual values, fiscal-year meaning and completed-record locks. Uploading evidence does not itself confirm receiving. ConsumptionService records/reverses attendance/status; it does not decrement catalog stock.

When changing fields, check Resources, audit revision serializers, generated reports and both clients. Extend matching existing components/services; avoid duplicating calculations in clients.

[Detailed maintenance reference](reference.md) preserves domain invariants and deeper file relationships; its older verification date is explicit. [RND](../../modules/rnd.md) and [FSS](../../modules/fss.md) explain user workflows.

Tests: [FoodShoppingListGenerationTest](../../../backend/tests/Feature/FoodShoppingListGenerationTest.php), [PurchaseOrderReadinessTest](../../../backend/tests/Feature/PurchaseOrderReadinessTest.php), [PurchaseOrderExecutionLockTest](../../../backend/tests/Feature/PurchaseOrderExecutionLockTest.php), [BudgetLedgerTest](../../../backend/tests/Feature/BudgetLedgerTest.php). Commands: [testing](../platform/testing.md).
