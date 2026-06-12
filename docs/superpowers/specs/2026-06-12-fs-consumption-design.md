# Spec 2 — Food-Service Consumption (stock-OUT)

- **Date:** 2026-06-12
- **Status:** Brainstormed — key decisions locked (§9); ready for an implementation plan
- **Domain rule (from user):** procurement is built *from* the menu plan, and the kitchen uses **all** the planned ingredients for each day. So consumption deducts the **planned** day quantity (deterministic). A shortfall is **not** a normal scenario — it means an upstream problem (PO not yet received, headcount changed, or rounding residue) — and is a hard guardrail (§4.2/§9-B).
- ⚠️ **Premise correction (review finding #3):** "stock nets to zero" only holds **once Spec 6 lands** (procurement net-of-on-hand-stock + whole-pack purchase-unit rounding). Until then, procurement over-buys (ignores stock on hand) and whole-pack rounding leaves overage, so **positive leftover drift is the normal state, not zero.** Block-on-shortfall is still correct; the "nets to zero" intuition is not — it requires Spec 6. This spec must not assume zero-netting for its math (it deducts the planned amount regardless of leftover).
- ⚠️ **Supplies gap (review finding #10):** `fs_items.kind = supply` (gloves, detergent…) are stocked but never consumed by recipes/menus, so even after this spec their stock only grows. Out of scope here; either a manual supply-usage log or a separate depletion model is needed — flagged for Spec 6 / a later cycle.
- **Depends on:** Spec 1 (needs correct `inventory.unit_price` per base unit before deduction cost means anything)
- **Roadmap:** Spec 2 of 5 (see [Spec 1](2026-06-12-fs-costing-immutability-design.md))

---

## 1. Background

Inventory is currently **one-way**: PO receipt and manual restock add stock; nothing ever subtracts. `MealPrepLog` exists as a model with **no controller, no route, no logic** — it's orphaned. As a result, on-hand stock reflects everything ever bought and nothing ever used; low-stock alerts can only fire from never-restocking, never from real usage. This spec closes the loop: **completing a menu-cycle day deducts the ingredients it used from inventory.**

## 2. Goals / Non-goals

**Goals**
1. A "complete day" action that deducts each meal's scaled ingredients from inventory.
2. Deductions valued at **stored last-cost** (`inventory.unit_price`) and **snapshotted** on the prep log, so consumption value is immutable and reversal is exact.
3. **Block-on-shortfall** with a specific alert (pre-flight cover check; see §9-B) — a shortfall is an upstream error, never silently absorbed.
4. **Idempotent** (a day can't be deducted twice) and **reversible** (un-completing restores the exact deducted quantities).
5. A consumption/usage record that Spec 3 can chart (plan vs actual).

**Non-goals**
- Spoilage/waste tracking beyond recording shortfalls (later).
- Per-tray / per-patient consumption (we deduct at the day×meal×servings level).
- Expiry-driven FEFO depletion (we hold last-cost, not lots).

## 3. The core modeling problem (read this first)

`MenuCycleDay` is **abstract**: it has `day_of_week` (Mon–Sun) and `meal_type`, **not a calendar date**. A cycle repeats week over week. But consumption is a **real, dated event** ("we served Monday's lunch on 2026-06-15"). So consumption cannot attach to a `MenuCycleDay` alone — it must bind to a **service date**.

**Proposed model:** a prep/service event is keyed by **(`menu_cycle_day_id`, `service_date`)**. Completing "Monday lunch" requires choosing the actual date it was served. The unique pair gives us idempotency for free.

This is the central design question — see §9 Decision A for alternatives.

## 3b. Procurement accuracy fix (rides on the service calendar)

**Current flaw:** the "smart" shopping list ([ShoppingListController::generate](../../../backend/app/Http/Controllers/FSS/ShoppingListController.php#L61) → [ProcurementService::suggestedItems](../../../backend/app/Services/ProcurementService.php#L15)) takes the **whole cycle's** total ingredient usage and scales it by a **proportion** `days_span / cycle_days`. So "3 days of a 7-day cycle" = 3/7 of *everything*, an average — it does **not** sum the actual days' menus, and there's no way to pick a real range like **Tue–Thu**. For a varied menu this is inaccurate.

**Fix:** drive the suggested list off **actual selected days**, not a proportional count. With the service calendar (Decision A), a list for a date range = the **sum of those dated days' planned ingredients** (each day → its recipes → `fs_item` ingredients, scaled to servings, unit-converted), exact and un-smeared. Spans longer than the cycle simply walk more calendar days (the cycle repeats by date). The UI lets the user **pick the menu cycle + a day/date range** and produce a **suggested** list (editable) or commit it as a **normal** list. This supersedes the proportional `days_span` factor.

> Sequencing note: this fix needs day-level granularity. It can ship with Spec 2 (cleanest, uses the calendar) or as a smaller standalone procurement enhancement that sums cycle days by `day_of_week` for the selected range without the full calendar. Recommend bundling with Spec 2.

## 4. Design

### 4.1 Data model

- **`meal_prep_logs`** (extend the orphaned table): add
  `service_date` DATE, `status` ENUM(`completed`,`reversed`) default `completed`,
  `completed_by` FK→users, `completed_at` datetime, `total_value` decimal,
  `has_shortfall` bool. Unique index on (`menu_cycle_day_id`, `service_date`).
- **`meal_prep_log_lines`** (new child — the immutable deduction snapshot): `meal_prep_log_id`, `fs_item_id`, `qty_base` (deducted, in base unit), `unit`, `unit_cost` (₱/base at time of consumption), `line_value`, `shortfall_qty` (how much we wanted but didn't have). **Snapshot, never recomputed** — this is what makes valuation immutable and reversal exact.

### 4.2 `ConsumptionService::completeDay(MenuCycleDay $day, string $serviceDate, ?int $populationOverride)`

In one DB transaction:
1. Guard idempotency: if a non-reversed log exists for (day, date) → 422 "already completed."
2. Resolve servings: `servings_override ?? populationOverride ?? cycle.population`.
3. Build the required base-unit usage:
   - **Recipe day:** scale each ingredient by `RecipeScaler::factor(recipe.servings, target)`, convert to base unit (reuse the exact logic in `MenuCycleCostService::aggregate`/`recalculateCost` — factor this into a shared helper so consumption and costing can't diverge).
   - **Ready item day (`fs_item_id`, no recipe):** `quantity × target` of the catalog item in its base unit.
4. **Pre-flight cover check (block-on-shortfall, §9-B):** compute every required `(fs_item_id, qtyBase)` and compare to `quantity_in_stock` **before** deducting anything. If any item is short, **abort with a 422** listing each short item, the gap, and a likely-cause hint ("receive PO #X" / "headcount changed"). Nothing is deducted. This matches the domain rule: a shortfall is an upstream error to fix, not something to absorb.
5. If everything is covered, deduct each `(fs_item_id, qtyBase)`: lock the inventory row, `quantity_in_stock -= qtyBase`, and write a `meal_prep_log_line` snapshot `{ qty_base, unit, unit_cost = inventory.unit_price (fallback catalog unit_cost), line_value = qty_base × unit_cost }`. (`shortfall_qty` retained on the line schema only for the override path, §9-B; normally 0.)
6. Sum `total_value`; persist the log. Return `{ log }`.

### 4.3 Reversal `ConsumptionService::reverseDay(MealPrepLog $log)`

In one transaction: for each line, `inventory.quantity_in_stock += line.qty_base` (add back exactly what was deducted — **not** a recompute, since the recipe may have changed since). Mark log `reversed`. Reversing frees the (day, date) pair for a fresh completion.

### 4.4 API / UI

- `POST /fss/menu-cycle-days/{day}/complete` `{ service_date, population? }`
- `POST /fss/meal-prep-logs/{log}/reverse`
- `GET /fss/meal-prep-logs?from=&to=` (usage history; feeds Spec 3)
- UI: on the activated menu cycle, a per-day "Mark served" with a date picker; a shortfall warning toast listing short items; a usage log view with reverse.

## 5. Error handling

- Whole complete/reverse runs in a transaction; partial deductions never persist.
- Row-level lock on inventory during deduction to avoid races (two simultaneous completions).
- Days with no recipe **and** no fs_item → skip (nothing to deduct).
- Ingredient whose `fs_item` was deleted → skip its line, add to warnings.
- Unit conversion uses the same degrade-never-throw policy as Spec 1.

## 6. Testing

- Unit: required-usage builder (recipe scaling + base conversion) matches `MenuCycleCostService` for the same inputs (shared helper → identical numbers).
- Feature: complete a day → stock drops by exact base qty; line snapshot written; value = qty×last-cost. Re-complete → 422. Reverse → stock restored exactly. Short stock → clamps, records shortfall, warns, still completes.

## 7. Interaction with other specs

- **Spec 1:** consumption values at `inventory.unit_price` (per base). Without Spec 1 that field is empty → falls back to live catalog (rough). This is *why* Spec 1 goes first.
- **Spec 3:** `meal_prep_log_lines` is the "actual consumption" source for plan-vs-actual and waste charts.
- **Spec 5:** complete/reverse are audited actions (who served what, when).

## 8. Flaws / risks I want to flag

1. **Abstract day vs dated event (§3)** — the whole feature hinges on getting the service-date binding right. If we get it wrong, idempotency and reversal break.
2. **Double-deduction vs re-plan:** if someone edits the recipe *after* serving, the snapshot lines (not the recipe) drive reversal — correct, but means a reversed+recompleted day can differ from the original. Acceptable, but must be documented so it doesn't look like a bug.
3. **Clamp-at-zero hides reality:** clamping shortfalls at zero keeps stock non-negative but means "stock says 0" can mask that you actually used more than recorded. The shortfall column preserves the truth, but only if reports surface it.
4. **No FEFO/lots:** we deduct from a single pooled quantity at last-cost. Fine for a kitchen, wrong for strict lot accounting. Out of scope by decision.

## 9. Decisions (locked)

- **Decision A — service-date binding: SERVICE CALENDAR.** Instantiate menu-cycle days into real **dated** meal entries; consumption completes against a specific dated meal, not an abstract "Monday." Removes the recurring-week ambiguity and gives Spec 3 a real plan-vs-actual calendar. (The prep log references the dated calendar entry; idempotency is per dated entry.)
- **Decision B — shortfall behavior: BLOCK + SPECIFIC ALERT.** Pre-flight cover check; if short, refuse and name the short items + likely cause. A shortfall means an upstream problem, never a silent absorb. (`shortfall_qty` stays on the line schema to support a possible future "override-to-proceed" escape hatch, but the default is block.)
- **Decision C — completion granularity: WHOLE SERVICE DAY.** Completing a calendar day deducts all of that day's meal slots together; the idempotency key is the dated calendar day. (A per-meal variant can come later if needed.)

**Still open (minor, non-blocking):** whether un-completing is allowed only same-day vs anytime; how the service calendar is generated (auto on cycle activation vs on demand).
