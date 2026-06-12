# Spec 3 — Food-Service Insights / Analytics

- **Date:** 2026-06-12
- **Status:** Draft design, pending review
- **Depends on:** Spec 1 (real spend + price trend), Spec 2 (real consumption) for the richest charts; several charts ship without them
- **Roadmap:** Spec 3 of 5 (see [Spec 1](2026-06-12-fs-costing-immutability-design.md))

---

## 1. Background & intent

The official compliance PDFs (AIR, Cash Book, Program/Project/Activity, etc.) must stay **pixel-faithful and graph-free**. This spec adds the *other* family the user asked for: an in-app **Insights** layer "specialized for our strengths," with interactive graphs. Charting already exists in the codebase — **Recharts** is used on [food-service/budget/page.tsx](../../../frontend/app/(rnd)/food-service/budget/page.tsx) and in NCP monitoring — so this is an extension, not a new foundation.

**Hard rule:** analytics live in their own section/endpoints. We do **not** inject graphs into compliance reports.

## 2. Goals / Non-goals

**Goals** — a Food-Service "Insights" dashboard with:
1. **Budget trend & variance** (planned vs actual) — data already exists via `BudgetController::summary`.
2. **Purchase-price trend** per item — the Spec 1 PO-derived endpoint.
3. **Spend by supplier** over a range (received POs grouped by supplier).
4. **Cost-per-head trend** across menu cycles / time.
5. **Plan vs actual consumption** and **shortfall/waste** — from Spec 2's `meal_prep_log_lines`.
6. **Inventory value over time** — point-in-time valuation snapshots.

**Non-goals**
- Predictive/forecasting models (later, if ever).
- Exporting graphs into the compliance PDFs.
- Cross-module clinical analytics (this is food-service scoped).

## 3. Design

### 3.1 Backend — read-only aggregation endpoints
A thin `InsightsController` (or per-topic methods) returning **chart-ready series** (`{ points:[{x,y,...}], summary:{...} }`). Each is a pure aggregation over existing tables; no new writable state. Examples:
- `GET /fss/insights/spend-by-supplier?start=&end=` → received POs grouped by `supplier_id`, bucketed by `COALESCE(received_date, order_date)`.
- `GET /fss/insights/cost-per-head?...` → from `MenuCycleCostService` over selected cycles.
- `GET /fss/insights/consumption?from=&to=` → `meal_prep_log_lines` rolled up (planned from menu cost vs actual from logs).
- Budget trend & price trend reuse the existing/Spec-1 endpoints.

Cache aggregates briefly (the inventory rows() pattern already uses `Cache::remember`); invalidate on the relevant writes.

### 3.2 Frontend — Insights page
A new `food-service/insights/page.tsx` with a date-range control and a grid of Recharts cards (line/bar/stacked). Each card: loading, empty, and error states. Reuse the budget page's chart styling for consistency.

### 3.3 Immutability
Every series is derived from **frozen snapshots** (PO lines, prep-log lines) — so analytics never retroactively change when catalog prices are edited, same guarantee as the compliance reports.

## 4. Phasing (ship value before Specs 2 is done)
- **Wave 1 (after Spec 1):** budget trend, price trend, spend-by-supplier, cost-per-head. No dependency on consumption.
- **Wave 2 (after Spec 2):** plan-vs-actual consumption, shortfall/waste, inventory value over time.

## 5. Error handling
- Empty ranges → explicit empty states, not broken charts.
- Guard divide-by-zero in per-head / averages.
- Large ranges → cap buckets (e.g. daily→weekly auto-rollup like `BudgetService::summarize`).

## 6. Testing
- Unit-test each aggregator as a pure function over fixture rows (mirrors how `MenuCycleCostService`/`BudgetService` are tested).
- Snapshot the series shape the frontend expects.

## 7. Flaws / risks
1. **Double-count consistency:** spend charts must use the same "received PO + manual log, minus overlap" rule as the budget (Spec 1 §5.8) or numbers won't reconcile across screens. Single shared aggregation helper.
2. **Consumption charts are only as honest as Spec 2's shortfall handling** — if clamping hides over-use, "actual consumption" understates reality. Surface shortfall explicitly.
3. **Performance:** naive per-day aggregation over long ranges can be slow; needs the rollup + cache discipline from day one.

## 8. Open decisions
- Which 3–4 charts are the MVP for Wave 1? (Recommend: budget trend, price trend, spend-by-supplier.)
- One combined Insights page vs charts embedded on each existing page (budget chart already lives on the budget page).
