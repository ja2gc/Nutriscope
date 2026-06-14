# Report Data-Source Audit (A1) — 2026-06-15

Answers handoff A1: *"Can all report data be generated from actual inputs collected during food-service operations, or are some values currently hardcoded/seeded and not reproducible during normal use?"*

Every generator in `backend/app/Services/Reports/Generators/` was classified field-by-field:

- **(a) live** — queried from a real persisted model; reproducible by normal use.
- **(b) config** — branding / signatories / template defaults; editable, intentionally not operational data → **OK**.
- **(c) param-only** — passed in `report.parameters` with **no backing record** → **the problem case** (not reproducible; whoever generated it typed it).

> Date ranges (`start`/`end`), `granularity`, and id selectors (`budget_id`, `menu_cycle_id`, `purchase_order_id`, `patient_id`, …) are *filters/selectors*, not report content — excluded from the (c) problem set.

---

## Per-report tables

### 1. Procurement Pack — `ProcurementPackGenerator`
| Field | Source | Class |
|---|---|---|
| AIR/Statement items (qty, unit, description, price, total) | `PurchaseOrder->items` (+ `fsItem`) | live |
| supplier | `PurchaseOrder->supplier` | live |
| grand_total | Σ `items.total_value` | live |
| order_date / period_label | `PurchaseOrder->order_date` | live |
| attachments (receipt photos) | `PurchaseOrder->attachments` | live |
| signatories (AIR/Statement/Summary) | `ReportTemplate.signatories` | config |
| `prepared_by_name` override | param (defaults to requesting user) | config |

**Verdict: fully reproducible.** ✅

### 2. Inventory Report — `InventoryReportGenerator`
| Field | Source | Class |
|---|---|---|
| name, type, category, qty, unit, threshold | `Inventory` (+ `fsItem`/`recipe`) | live |
| unit cost | `Inventory.unit_price` (last received), fallback `fsItem.unit_cost` | live |
| value / totals / low/no-stock counts | computed from above | live |

**Verdict: fully reproducible.** ✅

### 3. Budget Report — `BudgetReportGenerator` / `BudgetActualService`
| Field | Source | Class |
|---|---|---|
| allocated | `Budget.allocated_amount` (CRUD) | live |
| planned (daily cap) | `Budget.budget_per_head_day × population`, else `allocated / days` | live |
| actual | completed `MealPrepLog.total_value` + manual `budget_daily_logs.spent` | live |
| cash_flow / remaining | received `PurchaseOrder.total_amount` | live |
| variance / variance_pct | computed | live |

**Verdict: fully reproducible.** ✅ (see A6 note re: variance *sign/label*, not source.)

### 4. PPA — `ProgramProjectActivityGenerator` & 5. Menu Calendar — `MenuCalendarGenerator`
| Field | Source | Class |
|---|---|---|
| menu days / meals | `MenuCycle->days` (+ recipe / fsItem) | live |
| total_cost / per-day cost | `MenuCycleCostService::forReport` (frozen on activation) | live |
| population / output_label | `MenuCycle.population` | live |
| inclusive dates | `MenuCycle.week_start_date` / params | live |

**Verdict: fully reproducible.** ✅ (population is a single cycle-level value — see A8.)

### 6. Demographic Census — `DemographicCensusGenerator`
| Field | Source | Class |
|---|---|---|
| age, sex, ward, diagnosis, risk | `Patient` | live |
| nutritional_status | latest `Assessment.nutritional_status` | live |
| aggregates (age×sex, by ward/dx/status/risk) | computed | live |

**Verdict: fully reproducible.** ✅

### 7. Patient Menu Plan — `PatientMenuPlanGenerator`
| Field | Source | Class |
|---|---|---|
| plan grid (name, qty, unit) | `MealPlan->days->items` (+ foodItem/recipe) | live |
| patient | `MealPlan->patient` | live |

**Verdict: fully reproducible.** ✅

### 8. NCP Summary — `NcpSummaryGenerator`
| Field | Source | Class |
|---|---|---|
| patient demographics | `Patient` (+ `Assessment.religion`) | live |
| assessment / biochem | `Assessment` (+ `biochemicalData`) | live |
| diagnoses (PES) | `Diagnosis` | live |
| intervention / monitorings | `Intervention` / `Monitoring` | live |
| risk_score / risk_band | `NcpRecord.risk_score` | live |

**Verdict: fully reproducible.** ✅

### 9. Dietary Cash Book — `DietaryCashBookGenerator` ⚠️
| Field | Source | Class |
|---|---|---|
| disbursements (date, ref/OR, payee, nature, amount) | received `PurchaseOrder` (+ supplier) | live |
| period_label | params | filter |
| **beginning_balance** | param | **(c) param-only** |
| **replenishment** (amount) | param | **(c) param-only** |
| **replenishment_ref** | param | **(c) param-only** |
| **accountable_officer** (payee on replenishment row) | param | **(c) param-only** |

**Verdict: the disbursement side is fully live; the cash-advance / replenishment / beginning-balance side is NOT backed by any record.** ❌

---

## Summary

| Report | Reproducible? |
|---|---|
| Procurement Pack | ✅ |
| Inventory | ✅ |
| Budget | ✅ |
| PPA | ✅ |
| Menu Calendar | ✅ |
| Demographic Census | ✅ |
| Patient Menu Plan | ✅ |
| NCP Summary | ✅ |
| **Dietary Cash Book** | ⚠️ disbursements live; **replenishment/beginning-balance param-only** |

**Only one gap (c):** the Dietary Cash Book's cash-advance/replenishment + beginning balance. This is the same gap called out in handoff **A5** (needs a cash-ledger workflow decision) and ties to **A7** (surface the year's budget as replenishment context). Everything else is fully driven by stored operational records and reproducible through normal use — the seeded demo values all live in real tables (`Budget`, `MealPrepLog`, `PurchaseOrder`, `MenuCycle`, `Patient`, …), satisfying the "saved across food service" caveat.

**Recommendation:** treat the cashbook replenishment side per handoff A5 in a later session (either a small `cash_ledger` model, or derive replenishment from `Budget` allocations). No other generator needs remediation.
