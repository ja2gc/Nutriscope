# Spec 1 — Food-Service Costing & Immutability (stock-IN)

- **Date:** 2026-06-12
- **Status:** Approved design, pending implementation plan
- **Branch context:** `feat/nutri-engine-overhaul`
- **Part of:** the food-service loop overhaul (**Spec 1 of 6**). As of 2026-06-13 the 6-spec roadmap is **complete** — Specs 1–5 implemented, and Spec 6's flow-level fixes landed (#1 report snapshots closed via Spec 4's archive mechanism; #2/#4 procurement accuracy; #7 budget-from-consumption).
  - **Spec 1 (this doc)** — Costing & immutability: receiving a PO updates stock cost correctly; reports stay frozen.
  - **Spec 2 (future)** — Consumption: completing a menu-cycle day deducts ingredients from inventory (warn-but-allow on short stock; idempotent; reversible).
  - **Spec 3 (future)** — Insights/analytics: Recharts dashboards distinct from the compliance PDFs.
  - **Spec 4 (done)** — Reports-UX overhaul: reports are **always-present, period/entity-filtered views** (browse by type → period/record), rendered on demand — no manual "generate" step. **Archive** freezes the as-submitted PDF + a branding/signatories/period snapshot for officially filed forms (this also closes Spec 6 #1). See [Spec 4](2026-06-12-reports-ux-overhaul-design.md).
  - **Spec 5 (future, app-wide)** — Audit trail: add subject-centric **change** auditing (created-by / edited-by / field-level diffs with actor) to sensitive models via the already-installed `spatie/laravel-activitylog` `LogsActivity` trait, surfaced per-record in the UI. Complements the existing route-level *access* logging. NOTE: corrects `docs/security/security.md:13`, which currently overclaims model-level auditing that is not yet implemented.
  - **Spec 6 (future)** — Procurement accuracy, report snapshots & budget-from-consumption: fixes the **flow-level** flaws the end-to-end review surfaced — net-of-stock + whole-pack purchase-unit procurement (#2/#4), period snapshots that freeze menu-derived reports (#1), and budget daily "actual" sourced from consumption instead of lumpy PO dates (#7). See [Spec 6](2026-06-12-fs-procurement-accuracy-and-snapshots-design.md).

---

## 1. Background

The food-service module runs a loop: **catalog (`fs_items`) → recipes → menu cycle (the plan) → procurement → inventory → reports**. Today the **stock-in / cost half** of that loop has correctness gaps:

- Receiving a purchase order adds **quantity** to inventory but never records **what was paid** — inventory cost drifts from reality.
- `inventory.unit_price` is read as a per-**purchase**-unit price in one place while the natural value from a PO line is per-**base**-unit — a latent unit-mixing bug.
- `food_service_recipes.cost` is a stored snapshot recomputed **only** on recipe save, so it goes stale when a catalog price changes — while the planner recomputes live, producing two different costs for the same recipe.
- Spend is dated to PO **creation**, not **receipt**, mis-attributing daily budget figures.
- `purchase_orders.total_amount` is not recomputed when line items change, so the Cash Book/Budget (which read `total_amount`) can disagree with the Procurement Pack (which re-sums line items).
- Budget "actual" double-counts when a user both receives a PO and logs the same spend by hand.

### The founding requirement

> Changing an ingredient's price today must **not** alter previous receipts or the cost shown on previously generated reports.

**This is already true for the PO-derived reports** — Procurement Pack, Dietary Cash Book, and the Budget **actual** line all read values frozen on the purchase order (`purchase_order_items.unit_price`, `total_value`, `purchase_orders.total_amount`). The Inventory valuation report leaks (reads live catalog price) and this spec closes that leak.

> ⚠️ **Scope correction (review finding #1):** the invariant is NOT true for **menu-derived reports** — Program/Project/Activity, Menu Calendar, and the Budget **planned** line are recomputed **live** from the current cycle + current prices (e.g. [ProgramProjectActivityGenerator.php:77](../../../backend/app/Services/Reports/Generators/ProgramProjectActivityGenerator.php#L77)). Editing a price or a cycle *does* change those historical reports. Freezing them (period snapshot) is **out of scope here and handled in Spec 6.** This spec's guarantee covers PO-derived reports only.

---

## 2. Goals / Non-goals

**Goals**
1. Receiving a PO (status → `received`) records the price paid into inventory as a **last-cost** value, expressed in **₱ per base unit**.
2. Receiving refreshes the catalog price (`fs_items.purchase_price`) so the planner and next suggested list reflect recent real prices.
3. Keep `food_service_recipes.cost` fresh (recompute affected recipes whenever a catalog price changes).
4. Remove the `inventory.unit_price` unit-mixing bug.
5. Value the Inventory report at stored last-cost.
6. Date spend by receipt, keep `total_amount` consistent, and guard against budget double-counting.
7. Provide a **purchase-price trend** derived from PO history (no new table).
8. Explicit error handling throughout.
9. **Unit integrity (review findings #5/#6/#8)** — validate recipe-ingredient units are dimensionally compatible with the item `base_unit`; force inventory rows to store in `base_unit` (no free-text unit drift); block deletion of recipes/`fs_items` still referenced by cycles. (Small, correctness-critical fixes that ride with this spec since they share the unit/cost machinery.)

**Non-goals (deferred)**
- Inventory **consumption** / meal-prep deduction → Spec 2.
- New analytics dashboards / graph-rich reports → Spec 3.
- A dedicated `price_history` table for **manual** (non-PO) catalog edits — only add later if manual edits become common. PO history covers purchased-price trend already.
- FIFO / specific-lot inventory accounting. We use **last cost**, by decision.

---

## 3. Core principle: snapshot vs. live

| World | Reads from | When it changes |
|---|---|---|
| **Backward-looking (history)** — Procurement Pack, Cash Book, Budget, prep deductions (Spec 2) | Frozen snapshots captured at the event (`purchase_order_items.*`, `purchase_orders.total_amount`, `inventory.unit_price` at receipt) | Never rewritten by a later price edit |
| **Forward-looking (planning)** — menu planner, suggested shopping list, live recipe-cost cache | Current catalog price (`fs_items.purchase_price` / `unit_cost`) | Reflects the latest price immediately |

**Invariant (PO-derived reports only):** a posted PO and any report derived from it are valued at the price captured at the moment of the event. Editing a catalog price only moves forward-looking figures. **Menu-derived reports (PPA, Menu Calendar, Budget planned) are live and NOT covered by this invariant — see Spec 6 for snapshotting them.**

---

## 4. Locked decisions

| # | Decision | Choice |
|---|---|---|
| D1 | Inventory costing method | **Last cost** (most recent received PO overwrites stored cost) |
| D2 | Receiving refreshes catalog price | **Yes**, auto-update `fs_items.purchase_price` |
| D3 | Inventory valuation report basis | **Stored last-cost** (`inventory.unit_price`), fallback to catalog |
| D4 | `inventory.unit_price` unit | **₱ per base_unit** (aligns with stock qty, which is in base units) |
| D5 | Recipe-cost staleness | **Recompute on price change** (treat stored `cost` as a cache) |
| D6 | Purchase-price trend | **Derive from PO history** (no new table) |
| D7 | Short-stock policy (Spec 2 preview) | Warn but allow |

---

## 5. Detailed design

### 5.1 `ReceivingService` (extract from controller)

Move the restock logic out of `PurchaseOrderController::restockFrom()` into a dedicated `App\Services\FSS\ReceivingService` (it now does conversion, cost, catalog refresh, and recipe-cost recompute — too much for a controller private method).

```
ReceivingService::receive(PurchaseOrder $po): void
```

Called inside the existing transaction in `PurchaseOrderController::update()` when status transitions to `received` (the `previousStatus !== 'received'` guard stays — it makes receive idempotent).

For each PO line **with** an `fs_item_id`:

1. **Resolve base-unit qty and per-base cost.** Let `lineUnit = item.unit`, `baseUnit = fsItem.base_unit`, `lineCost = item.unit_price` (₱ per `lineUnit`).
   - If `lineUnit` and `baseUnit` are both known and differ and are convertible:
     `basePerLine = UnitConverter::convert(1, lineUnit, baseUnit)`
     `qtyBase = item.qty * basePerLine`
     `perBaseCost = lineCost / basePerLine` (guard `basePerLine > 0`, else treat as base)
   - Otherwise (same unit / unknown unit): `qtyBase = item.qty`, `perBaseCost = lineCost`.
   - The suggested-list → PO flow already emits base-unit lines, so this is a no-op there; it only matters for manually-entered POs.
2. **Add stock:** `inventory.quantity_in_stock += qtyBase`; set `inventory.unit = baseUnit` (create the row if missing, with `item_type = fsItem.kind`).
3. **Store last cost:** `inventory.unit_price = perBaseCost` (D1, D4).
4. **Refresh catalog (D2):** `fsItem.purchase_price = perBaseCost * fsItem->basePerPurchase()` (see 5.2). Save.

Lines **without** an `fs_item_id` are skipped (free-text PO lines carry no catalog target) — logged at `info`, not silently dropped.

After the loop: collect the distinct `fs_item_id`s touched and recompute the cost of every recipe that uses them (5.4).

### 5.2 `FsItem` unit helpers

Extract the conversion factor used in `getUnitCostAttribute` so the reverse is DRY:

```php
/** Base units contained in ONE purchase unit (e.g. 1000 g per kg, or units_per_purchase for count packs). */
public function basePerPurchase(): float
{
    $from = (string) $this->purchase_unit;
    $to   = (string) $this->base_unit;
    if ($from === '' || $to === '' || UnitConverter::normalize($from) === UnitConverter::normalize($to)) {
        return 1.0;
    }
    if (UnitConverter::isKnown($from) && UnitConverter::isKnown($to)) {
        return UnitConverter::convert(1, $from, $to);
    }
    return (float) ($this->units_per_purchase ?? 0);
}
```

`getUnitCostAttribute` is rewritten to `return basePerPurchase() > 0 ? round(purchase_price / basePerPurchase(), 6) : 0.0;` — behavior-preserving. The receiving reverse is `purchase_price = perBaseCost * basePerPurchase()`.

### 5.3 Inventory rows() unit-mix fix

In `InventoryController::unionFor()`, **remove** `COALESCE(inv.unit_price, f.purchase_price) AS purchase_price`; use `f.purchase_price` directly for the buy-price column. Rationale: `inv.unit_price` is now per-**base**-unit (D4) and must not be fed into the per-**purchase**-unit `purchase_price` slot (which `decorateRow` re-derives `unit_cost` from). Because receiving keeps the catalog in sync, the displayed buy price stays current without the overlay. `inventory.unit_price` is used only for at-cost valuation, not display.

### 5.4 Recipe-cost freshness (D5)

`food_service_recipes.cost` stays a stored cache but is recomputed whenever a catalog price changes:

- **On receive:** `ReceivingService` recomputes recipes using the touched items.
- **On manual catalog edit:** `FsItemController::update()` recomputes recipes using the edited item when `purchase_price` (or unit fields) change.

Add a helper:
```php
FoodServiceRecipe::recalculateForItems(array $fsItemIds): void  // recompute each recipe referencing any of these items
```
which loads affected recipes via `food_service_recipe_ingredients.fs_item_id IN (...)` and calls `recalculateCost()` on each. Wrapped so one bad recipe can't abort the batch.

### 5.5 Inventory valuation report (D3)

In `InventoryReportGenerator`, change the cost basis to stored last-cost first:
```php
$cost = $inv->unit_price !== null ? (float) $inv->unit_price : ($inv->fsItem?->unit_cost ?? 0.0);
```
`inventory.unit_price` is per-base-unit and `quantity_in_stock` is in base units, so `value = qty * cost` is dimensionally correct. Items never received via PO fall back to the live catalog `unit_cost`.

### 5.6 Receipt dating

Add `purchase_orders.received_date` (nullable date). Set to `now()->toDateString()` on the receive transition. Budget summary and Dietary Cash Book bucket **actual spend** by `COALESCE(received_date, order_date)` (fallback keeps legacy rows working). `order_date` keeps its meaning (when the order was placed).

### 5.7 `total_amount` single source of truth

Add `PurchaseOrder::recalcTotal()` = `items()->sum('total_value')`, and call it whenever line items are created/updated/deleted and on receive. Cash Book/Budget (read `total_amount`) and Procurement Pack (re-sum items) can no longer disagree.

### 5.8 Budget double-count guard

When storing a manual daily log (`BudgetController::storeDailyLog`), if one or more **received** POs already fall on that `log_date`, return the created log **with a `warning`** field (e.g. `"N purchase order(s) totalling ₱X are already counted on this date; manual logs are for non-PO cash spends only."`). Soft warning, not a hard block — POs remain the automatic source; manual logs are for off-PO cash. (Frontend surfaces the warning; no schema change.)

### 5.9 Purchase-price trend (D6)

New read-only endpoint, e.g. `GET /fss/fs-items/{fs_item}/price-trend?start=&end=`, deriving the series from frozen PO history:

```
SELECT COALESCE(po.received_date, po.order_date) AS d, poi.unit_price
FROM purchase_order_items poi
JOIN purchase_orders po ON po.id = poi.purchase_order_id
WHERE poi.fs_item_id = ? AND po.status = 'received'
  AND COALESCE(po.received_date, po.order_date) BETWEEN ? AND ?
ORDER BY d
```
(`COALESCE(received_date, order_date)` keeps legacy rows — received before this spec — in the series.)

Returns `{ points: [{date, unit_price}], min, max, latest, avg }`. Immutable by construction (built from frozen lines). Consumed by Spec 3's charts later; ships here as the data source.

---

### 5.10 Unit integrity & inventory cleanup (review findings #5 / #6 / #8 / #11)

- **#5 — recipe ingredient ↔ base_unit compatibility.** [FoodServiceRecipe::recalculateCost](../../../backend/app/Models/FoodServiceRecipe.php#L50) (and `MenuCycleCostService::aggregate`) currently *skip* conversion when a unit is unknown, so a **count** unit ("2 eggs", base `g`) is silently costed as 2 grams, and mass↔volume crossings assume density 1.0. Add validation **at recipe save**: the ingredient unit must be dimensionally compatible with the item's `base_unit` (mass↔mass, volume↔volume, or an explicit density/`units_per_purchase` for crossings); reject or require a density otherwise. Costing then never silently mis-multiplies.
- **#6 — inventory stores in base_unit only.** Receiving already sets `inventory.unit = base_unit` (§5.1). Make the **manual** path do the same: `StoreInventoryRequest`/`UpdateInventoryRequest` convert any entered qty to `base_unit` and ignore free-text units (or restrict the unit to the item's base/known-convertible set). `inventory` already has `UNIQUE(fs_item_id)`, so use an **upsert** on the manual path to avoid the unhandled unique-violation 500 when a received row already exists.
- **#8 — block destructive deletes of referenced catalog/recipes.** `menu_cycle_days.recipe_id`/`fs_item_id` are `nullOnDelete`, so deleting an in-use recipe/`fs_item` silently empties menu slots. Guard `destroy()` on `FoodServiceRecipeController`/`FsItemController`: refuse (409) when referenced by any `menu_cycle_day` (and recipe ingredient), pointing the user at the existing `is_active` soft-deactivate instead.
- **#11 — remove `expiry_date` entirely (user decision).** A single `expiry_date` per pooled stock row gives false confidence (no lots/FEFO, overwritten each receipt). **Drop it** rather than half-support it. Touchpoints: migration to drop the column; remove from `Inventory` (fillable/casts), `InventoryResource`, `Store/UpdateInventoryRequest`, `InventoryController` (rows select + `decorateRow`), the seeders/factory, and the frontend (`inventory/page.tsx`, `inventoryService.ts`). No FEFO replaces it — pooled last-cost stock only.
- **Seeder cleanup (review finding).** `InventorySeeder` and `InventoryDemoSeeder` use the **dropped** `food_item_id` / `item_type='food_item'` shape — dead code that would error if run (not in `DatabaseSeeder`). **Delete both.** The live seeder is `FoodServiceDemoSeeder`; update it to stop setting `expiry_date` (with #11) and decide on `usage_rate` (currently never seeded, likely vestigial — drop the column or seed it).

## 6. Data model changes

| Table | Change |
|---|---|
| `purchase_orders` | add `received_date` DATE NULL |
| `inventory` | `unit_price` meaning fixed to ₱/base-unit (D4); **drop `expiry_date`** (#11) |

`inventory.unit_price` for legacy rows stays NULL until the next receipt; valuation falls back to catalog `unit_cost` meanwhile. Dropping `expiry_date` is the one destructive change — a column drop with no replacement (pooled last-cost stock, no FEFO).

---

## 7. Error handling

- **Transactions:** the receive flow runs inside the existing `update()` DB transaction; stock, cost, catalog, and recipe-cost updates commit or roll back together.
- **Idempotency:** the `previousStatus !== 'received'` guard prevents double-restock; re-saving a received PO is a no-op for stock.
- **Unit safety:** division guarded by `basePerPurchase() > 0` / `basePerLine > 0`; unknown or missing units degrade to "treat line as base unit" rather than throwing (consistent with the existing `unit_cost` degrade-never-throw policy).
- **Missing targets:** PO lines without `fs_item_id` are skipped and logged; they never abort the batch.
- **Recipe recompute isolation:** a single malformed recipe is caught and logged; the rest of the batch still recomputes.
- **No reversal yet:** un-receiving or deleting a received PO does **not** roll back stock/catalog (documented limitation; revisited in Spec 2 alongside consumption reversal).

---

## 8. Testing

- **Unit (PHP, no DB):**
  - `FsItem::basePerPurchase()` and `unit_cost` round-trip: `unit_cost * basePerPurchase == purchase_price` across kg→g, L→mL, pack→pc (count), same-unit, and misconfigured cases.
  - `ReceivingService` qty/cost normalization for base-unit lines (no-op) and purchase-unit lines (converted).
- **Feature (tinker / HTTP):**
  - Receive a PO → inventory qty up, `unit_price` set to per-base cost, catalog `purchase_price` refreshed, dependent recipe `cost` updated.
  - Generate Procurement Pack / Cash Book / Budget **before** and **after** editing a catalog price → figures **identical** (immutability proof).
  - Inventory report values at stored last-cost; falls back to catalog for never-received items.
  - Manual daily log overlapping a received PO returns the warning.
- Follows the project test conventions in memory `[[nutriscope-test-commands]]` (FE `node:test` via tsx; PHP unit only, no sqlite; tinker for DB logic).

---

## 9. Open questions

None blocking. Resolved during brainstorming: costing method (last cost), catalog auto-update (yes), valuation basis (stored last-cost), recipe staleness (recompute-on-change), trend source (PO-derived), short-stock policy (warn+allow, applies in Spec 2).
