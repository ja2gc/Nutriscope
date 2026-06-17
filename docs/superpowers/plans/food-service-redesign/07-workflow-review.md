# 07 — Independent Workflow Review

> An honest re-review of the running FS workflow. The user noted a prior review said "ok" while
> real flaws remained. These are issues found in the actual code, ordered by potential damage.

## Findings

| # | Flaw | Location | Impact | Resolved by |
|---|------|----------|--------|-------------|
| F1 | No event/exception day — one uniform per-head cap applied to every day | [BudgetActualService.php:73](../../../../backend/app/Services/BudgetActualService.php) | Event days show false negative variance / over-budget | Event design ([06](06-events-and-cashbook.md)) |
| F2 | Cap uses static `budget.population` | [BudgetActualService.php:73](../../../../backend/app/Services/BudgetActualService.php) | Contradicts per-day population model | Phase A ([01](01-population-redesign.md)) |
| F3 | Consumption read **facility-wide**, not scoped to the budget's cycle/population | [BudgetActualService.php:26-29](../../../../backend/app/Services/BudgetActualService.php) | Two overlapping budgets double-count the same consumption as "actual" | Scope by `menu_cycle_id`/period (future) |
| F4 | PO date-basis mismatch between cashbook (`order_date`) and budget (`COALESCE(received_date, order_date)`) | [06](06-events-and-cashbook.md) | The two reports disagree on which period a PO falls in | Reconcile date basis (future) |
| F5 | Two silent cap definitions: `per_head × population` vs `allocated ÷ range-days` | [BudgetActualService.php:73-75](../../../../backend/app/Services/BudgetActualService.php) | "Planned" line means different things depending on which branch fires | Pick one definition (future) |
| F6 | `SUM(population)` across all cycles for a date | [BudgetActualService.php:54](../../../../backend/app/Services/BudgetActualService.php) | Multi-cycle service days muddy the per-head math | Scope per cycle (with F3) |
| F7 | Spend without a PO is invisible to the cashbook | [06](06-events-and-cashbook.md) | Untracked real disbursements (petty cash, direct buys) | Manual entry path (future) |
| F8 | `cost_per_head = total ÷ population` ill-defined under per-day model | [MenuCycleCostService.php:96,109](../../../../backend/app/Services/MenuCycleCostService.php) | Cycle-level per-head figure becomes meaningless | Phase A ([01](01-population-redesign.md)) |

## Disposition

- **F2, F8** — resolved by the population-per-day work in Phase A.
- **F1** — resolved by the event/exception-day design in [06](06-events-and-cashbook.md).
- **F3, F6** — need budget→cycle scoping; the current facility-wide read is only correct under a
  strict single-facility, one-active-budget-per-period assumption (the code's own SCOPING NOTE
  admits this). Address when budgets can overlap in time.
- **F4, F7** — cashbook / procurement reconciliation, see [06](06-events-and-cashbook.md).
- **F5** — clarity fix: choose a single definition of the planned cap and label it consistently.

All documented now; the fixes (beyond what Phase A and the event design already cover) are future
phases, not part of this documentation pass.

## Confirmed NOT broken

- `fs_items` vs `food_items` separation is intentional (separate FS and clinical catalogs).
- `ShoppingListController::generate()` correctly links a generated list to its source cycle and
  factors population via `usageForDays()`. The link is not missing — only the single-flat-population
  input is being replaced by the per-day model.
