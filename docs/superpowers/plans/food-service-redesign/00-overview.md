# Food Service Redesign — Overview

> Status: **design / documentation** (2026-06-17). No code changes landed in this pass.
> Source: user audit of the running app + code, plus an independent code review.

## Problem

The Food Service (FS) subsystem has accumulated logical-flow defects across seed data,
schema ownership, and UI. Symptoms the user observed in the running app:

- Seed data is unrealistic (wrong units, absurd recipe quantities, inflated/irreconcilable
  budgets, menus that fail their own budget constraint, "untracked" inventory items).
- Schema ownership is wrong: the per-head budget cap lives on the menu cycle, not the budget.
- Population is modeled at the cycle level when it logically belongs to the individual day.
- Several UI surfaces are redundant, missing CRUD, or slow.
- No concept of an **event/exception day** exists, so special meals break the budget math.

## The central change: population per day

The single highest-impact decision: **population is a property of the individual menu cycle
day, not the cycle.** Each `menu_cycle_day` carries its own `estimate_population`, entered when
that day is planned. The scaling multiplier for a day = `day.estimate_population ÷ recipe.servings`,
applied live to every ingredient quantity and resulting cost for that day. There is no
cycle-level population, not even as a fallback or seeding default.

Detail in [01-population-redesign.md](01-population-redesign.md).

## Calendar anchoring (decided 2026-06-17)

A menu **cycle** is a reusable **Mon–Sun day-of-week pattern** (`menu_cycle_days.day_of_week`),
not bound to a fixed calendar. Each **activation** anchors to a real
`menu_cycles.week_start_date` = a **Monday**, mapping the pattern onto an actual calendar week.
Anything that produces dated output — generated shopping lists (weekday→weekday picker), the
population log, future/current-week plans — resolves to **real dates** within that anchored week.
`meal_prep_logs.service_date` already stores real dates. `week_start_date` must be validated to a
Monday at save time.

## Confirmed-intentional facts (do not "fix")

1. **`fs_items` (food service) and `food_items` (clinical NCP) are two separate catalogs by
   design.** FS and clinical nutrition never share ingredient data. Not an artifact of a partial
   rename. Keep them separate.
2. **`ShoppingListController::generate()` already links a list to its source cycle** via
   `menu_cycle_id` and factors population via `usageForDays()`. The connection is NOT missing.
   What it reads — the cycle's single flat population — is the part being replaced by the per-day
   model.

## Scope & phase order

This pass is **documentation only**. Implementation is sequenced into phases (each a future PR):

- **Phase A** — population per day (data model + costing engine). Highest risk, do first.
- **Phase B** — schema/business-logic decisions (budget ownership, low-stock removal, recipe
  baseline, budget monthly default).
- **Phase C** — seeders rebuild.
- **Phase D** — UI/UX (Foods section, procurement CRUD, weekday picker, inline editors).
- Cross-cutting — events/exception days and Dietary Cash Book reconciliation.

## Document map

| File | Covers |
|------|--------|
| [01-population-redesign.md](01-population-redesign.md) | Cycle→day population, formula, costing engine, population log |
| [02-seed-data-fixes.md](02-seed-data-fixes.md) | Seeder defects + target values |
| [03-schema-business-logic.md](03-schema-business-logic.md) | Budget ownership, low-stock, recipe baseline, budget view, migrations |
| [04-ui-ux.md](04-ui-ux.md) | Foods section, procurement, weekday picker, inline editors |
| [05-missing-functionality.md](05-missing-functionality.md) | Inline recipe editor, population log, single-ingredient recipes |
| [06-events-and-cashbook.md](06-events-and-cashbook.md) | Event/exception days, Dietary Cash Book audit |
| [07-workflow-review.md](07-workflow-review.md) | Independent FS logical-flaw review (F1–F8) |
| [08-open-questions.md](08-open-questions.md) | Remaining minor opens |

## Migration stance

Fresh rebuild is acceptable: prior migrations already declare FS data disposable. The assumed
path is `php artisan migrate:fresh --seed`; no back-fill of existing FS data is required.
