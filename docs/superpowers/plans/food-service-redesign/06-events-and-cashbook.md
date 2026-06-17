# 06 — Events & Dietary Cash Book

## Events / exception days (NEW concept)

### The problem

There is **no concept of an event/occasion/special meal anywhere in FS** (verified: code search
for event/occasion/special-meal hits only calendar events and Laravel framework events).

[BudgetActualService](../../../../backend/app/Services/BudgetActualService.php) applies **one
uniform daily cap** to every day:

```php
// BudgetActualService.php:73
$cap = ($budget->budget_per_head_day && $budget->population)
    ? (float) $budget->budget_per_head_day * (int) $budget->population
    : ((float) ($budget->allocated_amount ?? 0) / max(1, $start->diffInDays($end) + 1));
```

So an event day (fiesta, Christmas meal) — which legitimately costs far more per head and is
usually funded separately — shows as **false negative variance / over-budget**, even though it
was funded apart from the daily subsistence allowance.

### Recommended design — lightweight exception day

- Add `menu_cycle_days.is_event` (bool, default false) and `menu_cycle_days.event_allocation`
  (decimal, nullable).
- In `BudgetActualService`: for an event day, use `event_allocation` as that day's **cap**
  instead of `per_head × population`. Flag the day as `event` in the returned series so the
  dashboard and printed report show it separately, and it **never counts as standard-cap
  variance**.
- `budgets.scope` already supports `'custom'` if an event needs its own budget envelope rather
  than a per-day allocation.
- The population log still records actual/served for event days (reporting only — no rescaling).

### Open

Are events always single-day, or can one span multiple days/meals? Per-day `is_event` covers
both (a multi-day event = several flagged days). Revisit only if events need a named header or
grouping across days. Tracked in [08-open-questions.md](08-open-questions.md).

## Dietary Cash Book — audit (KEEP, do not remove)

The user asked whether disbursements are entered anywhere and, if not, whether to remove the
report. Answer: **keep it.** It is not orphaned —
[DietaryCashBookGenerator](../../../../backend/app/Services/Reports/Generators/DietaryCashBookGenerator.php)
auto-derives its ledger from existing records:

- **Disbursements** = received purchase orders (payee = supplier, amount = PO total).
- **Replenishments** = budget allocations overlapping the period.

No manual disbursement screen is required — it's computed. But these real gaps should be fixed
in a later phase:

| Gap | Detail | Location |
|-----|--------|----------|
| Non-PO spend invisible | Only `status='received'` POs become disbursements; petty cash / direct buys without a PO never appear (compounded by procurement's missing manual-add, see [04](04-ui-ux.md)) | generator `data()` |
| Lump replenishment | The whole budget allocation is booked as one replenishment on a single date — not how periodic cash advances actually flow | [DietaryCashBookGenerator.php:119](../../../../backend/app/Services/Reports/Generators/DietaryCashBookGenerator.php) |
| Date-basis mismatch | Cashbook filters POs by `order_date`; `BudgetActualService` uses `COALESCE(received_date, order_date)`. The same PO can fall in different periods across the two reports | [generator:66](../../../../backend/app/Services/Reports/Generators/DietaryCashBookGenerator.php) vs [BudgetActualService:67](../../../../backend/app/Services/BudgetActualService.php) |

**Fix direction:** reconcile both reports to one PO date basis (recommend `received_date`,
falling back to `order_date`, since cash is disbursed on receipt); optionally add a manual
disbursement/replenishment entry path so non-PO cash flow is captured.
