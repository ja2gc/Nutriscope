# Spec 6 — Procurement Accuracy, Report Snapshots & Budget-from-Consumption

- **Date:** 2026-06-12
- **Status:** Draft design (born from the end-to-end flow review) — pending review
- **Why it exists:** the costing/consumption/reports specs each assumed a clean flow that the review proved false in four structural places. This spec fixes the **flow-level logic**, not just per-field correctness.
- **Roadmap:** Spec 6 of 6 (see [Spec 1](2026-06-12-fs-costing-immutability-design.md))

---

## 1. The four flaws this spec owns

| # | Flaw | Today | Consequence |
|---|---|---|---|
| **#2** | Procurement ignores stock on hand | [generate()](../../../backend/app/Http/Controllers/FSS/ShoppingListController.php#L61) computes **gross** menu need | systematic over-buying; inventory grows unbounded |
| **#4** | Procurement runs in **base units** | `ingredient_usage.unit = base_unit`; PO lines in grams/mL | "order 12345.67 g"; can't buy whole packs; AIR/Statement print grams |
| **#1** | Menu-derived reports are **live** | PPA/Menu Calendar/Budget-planned recompute from current cycle+prices | a past report changes when prices/cycle change; unsafe to re-render |
| **#7** | Budget daily "actual" = lumpy POs | [summary()](../../../backend/app/Http/Controllers/FSS/BudgetController.php#L52) buckets PO totals by date | a weekly buy spikes one day vs a flat daily cap; daily variance meaningless |

These interlock: #2 + #4 are *why* Spec 2's "stock nets to zero" premise fails, and #7 is the missing link that should connect Spec 2's consumption to the budget.

## 2. Goals / Non-goals

**Goals**
1. **Net-requirement procurement** — buy only what's needed beyond stock on hand and not-yet-received POs.
2. **Purchase-unit procurement** — present and round suggested/PO quantities in `purchase_unit` (whole packs), keep base units internal for stock/cost.
3. **Report period snapshots** — freeze menu-derived reports (PPA, Menu Calendar, Budget-planned) at generation/period-close so history is reproducible.
4. **Budget actual from consumption** — per-day "actual" comes from Spec 2 consumption value; POs are cash-flow only (Cash Book).

**Non-goals**
- Lot/FEFO expiry tracking (still out; documented limitation).
- Supplies depletion model (#10) — acknowledged; may attach here or later.

## 3. Design

### 3.1 Net-requirement procurement (#2)
In `ShoppingListController::generate` / `ProcurementService`, after computing planned need per `fs_item`:
```
net_need = max(0, planned_need − on_hand_base − in_transit_base)
```
- `on_hand_base` = `inventory.quantity_in_stock` (base unit).
- `in_transit_base` = sum of qty on PO lines for that item with status in {draft? sent?, not received} — **decide which statuses count as "committed"** (§5).
- The suggested list shows planned / on-hand / net columns so the user sees *why* a quantity dropped. A line whose net is 0 is omitted (or shown as "covered by stock").

### 3.2 Purchase-unit procurement (#4)
- Convert each net base-unit need into `purchase_unit` via `FsItem::basePerPurchase()` (the Spec 1 helper), then **round up to whole purchase units** (you can't buy 1.3 sacks): `packs = ceil(net_base / basePerPurchase)`.
- Suggested list & PO lines carry **both**: `purchase_qty`+`purchase_unit`+`purchase_price` (what the vendor sees, what AIR/Statement print) and the derived `base_qty` (what restock adds). Receiving (Spec 1 §5.1) already normalizes to base — it now reads `purchase_qty × basePerPurchase` directly instead of re-deriving.
- **Whole-pack rounding produces overage** → this is the leftover that makes Spec 2's "nets to zero" false; net-requirement (#3.1) then consumes it next cycle instead of re-buying. The two fixes together make inventory cyclical, not monotonic.

### 3.3 Report period snapshots (#1)
- When a menu-derived report is **archived** (Spec 4) — or at an explicit "close period" — persist a **frozen snapshot** of its computed data (menu listing + per-day costs + population + prices used) onto the `Report` row (`parameters`/a `snapshot` JSON column), not just the PDF.
- Menu-derived report rendering: if a snapshot exists, render from it; only live-compute when no snapshot (i.e. a current/draft view). This is what makes Spec 4 on-demand rendering safe for these types.
- Narrows the gap Spec 1 §1 flagged: after this, *all* report families are reproducible.

### 3.4 Budget actual from consumption (#7)
- Budget `summary()` per-day **actual** = Σ `meal_prep_log_lines.line_value` for that **service_date** (Spec 2), not PO totals.
- POs remain the **Dietary Cash Book** disbursements (cash out), and an optional cash-flow view — but they stop driving the daily budget-vs-plan variance.
- Keeps the double-count guard (Spec 1 §5.8) meaningful: consumption (food used) and cash logs (money out) are now clearly different axes.
- **Dependency:** requires Spec 2 consumption data to exist; until then budget actual falls back to received-PO totals (current behavior) with a clear "estimated from purchases" label.
- **Schema cleanup (review finding — split-brain `budget_daily_logs`).** The table carries **two parallel column sets** for the same concept — `date` + `planned/actual/variance` **and** `log_date` + `spent` — used inconsistently (seeder writes `date`/`actual`; `storeDailyLog` + `summary` use `log_date`/`spent`). Effect: **seeded daily logs are invisible** to the dashboard (summary filters on `log_date`, which is null in seeded rows). Consolidate to **one** set (`log_date` + `spent`), migrate existing rows, and fix the seeder. Do this as part of the budget rework so there's a single source of truth.

## 4. Data model
- `purchase_order_items` / `shopping_list_items`: add `purchase_qty`, `purchase_unit`, `purchase_price` (keep existing base fields for stock math).
- `reports`: add a `snapshot` JSON column (or reuse `parameters`) for frozen menu-report data.
- No change to `inventory` (base-unit qty stays the source of truth).

## 5. Open decisions
- **A — in-transit definition:** which PO statuses count as "already committed" stock for net-requirement (draft? only sent? exclude drafts?).
- **B — overage handling:** carry whole-pack overage purely as leftover stock [recommended], vs surface it as an explicit "buffer" line.
- **C — snapshot trigger:** snapshot menu reports on **archive only**, vs an explicit **period-close** action that snapshots the whole report set at once.
- **D — budget fallback labeling:** how to present budget actual before Spec 2 exists (purchase-estimated) vs after (consumption-actual).

## 6. Flaws / risks
1. **Sequencing:** #7 needs Spec 2; #1-snapshots need Spec 4's archive; #2/#4 need Spec 1's unit helpers. So Spec 6 is genuinely *last* and partly interleaves — call out the cross-dependencies in the plan.
2. **Migration of in-flight data:** existing shopping lists / POs are base-unit only; the purchase-unit columns are nullable and back-filled lazily.
3. **Rounding up always over-buys slightly** — correct for whole packs, but for expensive low-use items it can tie up budget; net-requirement mitigates over cycles.
