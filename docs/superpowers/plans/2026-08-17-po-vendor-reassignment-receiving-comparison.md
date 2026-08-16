# PO Vendor Reassignment and Receiving Comparison Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let RND and FSS correct pending PO vendors at group or item scope and understand calculated, planned, and actual values without overloading the receiving screen.

**Architecture:** Reuse the existing vendor-group PATCH route, purchase-order lifecycle service, audit revision path, supplier index, PO resource, and receiving components. Add no migration or dependency. A single transaction moves or merges vendor groups under row locks; existing report generators remain unchanged unless new regression tests expose an omission.

**Tech Stack:** PHP 8.4, Laravel 13, MySQL, PHPUnit 12, Next.js 16/React 19/Tailwind, Expo/React Native, TypeScript.

---

## File Map

- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php` — validate and dispatch vendor reassignment through the existing group update.
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php` — atomic move/merge, totals, audit revision.
- `backend/app/Http/Resources/PurchaseOrderResource.php` — expose accurate calculated/planned/actual values and reassignment capability.
- `backend/routes/api.php` — make the existing supplier index shared read-only; keep writes RND-only.
- `backend/tests/Feature/PurchaseOrderExecutionLockTest.php` — vendor reassignment and value-stage API behavior.
- `backend/tests/Feature/FoodServiceOpsTest.php` — role access and report inclusion regression.
- `frontend/services/procurementService.ts` — extend the existing vendor-group request and value types.
- `frontend/app/(rnd)/food-service/procurement/page.tsx` — RND group/row actions and compact comparison.
- `frontend/components/fss/FssPurchaseOrders.tsx` — FSS web group/row actions and compact comparison.
- `frontend/app/(rnd)/food-service/procurement/receiving-contract.test.ts` — web terminology/scope contract.
- `mobile/app/(tabs)/procurement.tsx` — FSS mobile group/row actions and collapsed details.
- `mobile/lib/procurementReceiving.test.cjs` — mobile terminology/scope contract.
- Maintained Food Service docs/help/storyboard files — corrected lifecycle and UI instructions.

### Task 1: Backend Vendor Reassignment Contract

**Files:**
- Modify: `backend/tests/Feature/PurchaseOrderExecutionLockTest.php`
- Modify: `backend/tests/Feature/FoodServiceOpsTest.php`

- [ ] **Step 1: Write failing group-wide tests**

Create two suppliers and a converted PO. PATCH the existing vendor-group route with `supplier_id` and assert every item belongs to the replacement group, the old group is removed or merged, totals remain correct, and the response supplier is updated.

```php
$this->actingAs($this->rnd)->patchJson(
    "/api/fss/purchase-order-vendor-groups/{$group->uuid}",
    ['supplier_id' => $replacement->uuid],
)->assertOk()->assertJsonPath('data.vendor_groups.0.supplier.name', $replacement->name);
```

- [ ] **Step 2: Write failing row-level tests**

Send `supplier_id` plus `item_id`; assert only that line moves, a matching destination group is reused or created, the source remains when non-empty, and an empty source is deleted.

```php
['supplier_id' => $replacement->uuid, 'item_id' => $line->id]
```

- [ ] **Step 3: Write failing safety tests**

Cover same-vendor no-op; preserved purchase/actual values; received/completed groups; receipt or proof on source; evidence on an existing destination; and an item outside the routed group. Expect direct `422` recovery messages and no partial database changes.

- [ ] **Step 4: Write failing role and audit tests**

Prove RND and FSS may reassign, FSS can list suppliers but cannot author them, and a successful move produces one sanitized purchase-order revision.

- [ ] **Step 5: Run RED tests**

Run:

```powershell
php artisan test --compact tests/Feature/PurchaseOrderExecutionLockTest.php tests/Feature/FoodServiceOpsTest.php
```

Expected: failures because `supplier_id`/`item_id` are not accepted and FSS cannot list suppliers.

### Task 2: Minimal Atomic Backend Implementation

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`
- Modify: `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`
- Modify: `backend/app/Http/Resources/PurchaseOrderResource.php`
- Modify: `backend/routes/api.php`

- [ ] **Step 1: Extend the existing request branch**

Add these rules to `updateVendorGroup` and resolve the public supplier UUID using the established `Supplier::idFromUuid` pattern:

```php
'supplier_id' => ['nullable', 'string', 'exists:suppliers,uuid'],
'item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
```

When `supplier_id` is present, call the lifecycle reassignment method and return the refreshed resource before entering receiving-value handling. Reject `item_id` without `supplier_id`.

- [ ] **Step 2: Implement one lifecycle method**

Add one method with an explicit return shape:

```php
public function reassignVendor(
    PurchaseOrderVendorGroup $sourceGroup,
    Supplier $supplier,
    ?int $itemId = null,
): PurchaseOrder
```

Inside `DB::transaction`, lock the PO and source group, load attachments/items, resolve and lock any destination group, validate pending/evidence ownership, return unchanged for the same supplier, create or reuse the destination, move one/all rows, delete an empty source, recalculate both group totals and PO total, record one procurement update, and write the before/after revision.

- [ ] **Step 3: Keep the total calculation DRY**

Use one private group-total helper matching receiving semantics:

```php
$group->total_amount = round((float) $group->items->sum(
    fn ($item) => $item->actual_qty !== null && $item->actual_unit_price !== null
        ? (float) $item->actual_qty * (float) $item->actual_unit_price
        : (float) $item->total_value,
), 2);
```

- [ ] **Step 4: Share only supplier reads**

Move `GET suppliers` into the shared `role:FSS,RND` group and leave POST/PATCH/DELETE routes inside `role:RND`.

- [ ] **Step 5: Expose capability and accurate stages**

For each vendor group expose `can_change_vendor`, based on pending lifecycle and absence of evidence. Keep `qty/unit/unit_price` as calculated, `purchase_*` as planned, and `actual_*` with `actual_values_confirmed` as actual.

- [ ] **Step 6: Run GREEN tests and format**

```powershell
php artisan test --compact tests/Feature/PurchaseOrderExecutionLockTest.php tests/Feature/FoodServiceOpsTest.php
vendor\bin\pint --dirty --format agent
```

Expected: all targeted tests pass and Pint reports success.

### Task 3: Report Inclusion Regression

**Files:**
- Modify: `backend/tests/Feature/FoodServiceOpsTest.php`
- Modify only on demonstrated failure: `backend/app/Services/Reports/Generators/ProcurementPackGenerator.php`

- [ ] **Step 1: Add generated-list manual-row report test**

Create a generated shopping list with one generated and one manual included row, convert it, build a procurement pack, and assert both descriptions occur under their actual vendor groups.

- [ ] **Step 2: Add fully manual-list report test**

Create a manual list with multiple included rows, convert it, and assert every row appears in the procurement pack. Move one row to another vendor and assert the pack changes supplier grouping without dropping either row.

- [ ] **Step 3: Verify the existing generator**

```powershell
php artisan test --compact tests/Feature/FoodServiceOpsTest.php --filter=procurement_pack
```

Expected: tests pass with no production report change because the pack maps all frozen PO items. If a test fails for an actual omission, make only the smallest filter/mapping correction and rerun RED/GREEN.

### Task 4: Web Receiving Contract and UX

**Files:**
- Create: `frontend/app/(rnd)/food-service/procurement/receiving-contract.test.ts`
- Modify: `frontend/services/procurementService.ts`
- Modify: `frontend/app/(rnd)/food-service/procurement/page.tsx`
- Modify: `frontend/components/fss/FssPurchaseOrders.tsx`

- [ ] **Step 1: Write the failing source contract**

Assert both receiving surfaces contain exact labels **Change vendor for all**, **Change vendor**, **Actual purchased quantity**, **Planned:**, **View calculation details**, **Not reviewed**, and **Reviewed**; reject the misleading `Calculated qty` header.

- [ ] **Step 2: Run the web RED test**

```powershell
npm test -- --run 'app/(rnd)/food-service/procurement/receiving-contract.test.ts'
```

Expected: fail on missing new labels and disclosure.

- [ ] **Step 3: Extend existing service types/functions**

Add `can_change_vendor`, preserve all three value stages, and extend `updateVendorGroup`:

```ts
supplier_id?: string;
item_id?: number;
```

Reuse `listSuppliers()` rather than adding another supplier API.

- [ ] **Step 4: Implement the low-density RND web layout**

Place one labeled selector/button card above the table for **Change vendor for all**. Put **Change vendor** in the row action cell. Use `window.confirm` with explicit scope. Keep actual inputs primary, render one muted planned reference, and use native `<details><summary>View calculation details</summary>` for calculated/planned/actual/delta.

- [ ] **Step 5: Apply the same hierarchy to FSS web**

Load the shared supplier list once, reuse the same endpoint, and keep controls disabled with clear recovery text when evidence or status locks the group.

- [ ] **Step 6: Run web tests, types, and lint**

```powershell
npm test -- --run 'app/(rnd)/food-service/procurement/receiving-contract.test.ts'
npx tsc --noEmit
npm run lint
```

Expected: all pass with no lint warnings.

### Task 5: Mobile Receiving Contract and UX

**Files:**
- Create: `mobile/lib/procurementReceiving.test.cjs`
- Modify: `mobile/app/(tabs)/procurement.tsx`

- [ ] **Step 1: Write the failing mobile source contract**

Read the procurement screen source and require the exact two vendor-action labels, actual/planned terminology, calculation disclosure, review status, and an accessibility expanded state.

- [ ] **Step 2: Run the mobile RED test**

```powershell
node --test lib/procurementReceiving.test.cjs
```

Expected: fail because the new actions and disclosure do not exist.

- [ ] **Step 3: Implement the compact mobile cards**

Fetch the shared supplier index. Put **Change vendor for all** in the group header card and **Change vendor** inside each item card. Reuse native `Alert.alert` for confirmation. Keep actual inputs visible, planned reference compact, and calculation detail behind a labeled `Pressable` with `accessibilityState={{ expanded }}` and at least a 44-point hit target.

- [ ] **Step 4: Run mobile checks**

```powershell
node --test lib/*.test.cjs
npx tsc --noEmit
```

Expected: all Node contracts and TypeScript pass.

### Task 6: Maintained Documentation

**Files:**
- Modify: `docs/modules/Flowcharts/Food Service Operations.md`
- Modify: `docs/modules/Flowcharts/FSS Mobile Execution Flow.md`
- Modify: `docs/modules/fss.md`
- Modify: `docs/modules/rnd.md`
- Modify: `docs/ROLE-HOW-TO.md`
- Modify: `docs/FAQ.md`
- Modify: `docs/developer/food-service-operations-maintenance.md`
- Modify: `frontend/lib/helpContent.ts`
- Modify: `mobile/lib/helpContent.ts`
- Modify outside repository: `C:\Users\jared\Documents\Food Service Operations Video Storyboard.md`

- [ ] **Step 1: Correct vendor-lock language**

Document that planned quantity/unit structure freezes at release, while pending vendor assignments can be corrected until receiving status or evidence locks the affected groups.

- [ ] **Step 2: Document exact action scope and value stages**

Use the labels **Change vendor for all**, **Change vendor**, and **View calculation details**. Explain calculated need, planned purchase, prefilled-but-unreviewed actuals, and confirmed actuals.

- [ ] **Step 3: Keep report claims accurate**

State that procurement packs include all frozen PO rows, including manual additions and fully manual lists, and that PPA summaries do not invent menu/population data for lists without a menu span.

- [ ] **Step 4: Run documentation/source checks**

Search maintained docs/help for stale claims such as permanent vendor lock or `Calculated qty` used for planned quantities. Confirm historical plans remain unchanged.

### Task 7: Final Verification and Delivery

- [ ] **Step 1: Run focused backend suites**

```powershell
php artisan test --compact tests/Feature/PurchaseOrderExecutionLockTest.php tests/Feature/FoodServiceOpsTest.php tests/Feature/Audit/PurchaseOrderTrailTest.php
```

- [ ] **Step 2: Run complete backend and formatting gates**

```powershell
php artisan test --compact
vendor\bin\pint --format agent
```

- [ ] **Step 3: Run complete web gates**

```powershell
npx tsc --noEmit
npm run lint
npm test -- --run
npm run build
```

- [ ] **Step 4: Run complete mobile gates**

```powershell
node --test lib/*.test.cjs
npx tsc --noEmit
```

- [ ] **Step 5: Verify repository hygiene**

Run `git diff --check`, inspect the exact task diff, exclude `.codex/config.toml`, and confirm no agent attribution or unrelated files are staged.

- [ ] **Step 6: Commit, push, and verify**

Commit task-only changes with a neutral Conventional Commit subject, push `main`, and confirm local HEAD, `origin/main`, and `git ls-remote origin refs/heads/main` are identical.
