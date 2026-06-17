# 01 — Population Redesign (cycle → day)

> Phase A. Highest-risk change. Do first.

## Decision (final form)

Population is a property of the **individual day**, not the cycle.

- `menu_cycles.population` is **removed entirely** — no cycle-level number, not even as a
  fallback or seeding default.
- Each `menu_cycle_day` carries its own `estimate_population`, entered when that specific day is
  planned.
- The scaling formula divides that day's estimate by the recipe's baseline servings to get a
  multiplier, applied **live** to every ingredient quantity and resulting cost for that day:

  ```
  multiplier(day) = day.estimate_population / recipe.servings
  ```

  No separate scaler UI, no save step. The recipe-level master scaler UI is removed (it didn't
  persist correctly and conflated a recipe's reference batch size with a specific day's headcount).

## Schema changes

- ADD `menu_cycle_days.estimate_population` (unsigned int, nullable).
- DROP `menu_cycles.population`.
- DROP `menu_cycles.budget_per_head_per_day` (ownership → `budgets`, see
  [03-schema-business-logic.md](03-schema-business-logic.md)).
- KEEP `meal_prep_logs.population`, `served_population`, `population_variance` — already present,
  reused for the population log below.

`menu_cycle_days.servings_override` already exists; `estimate_population` is the day's headcount
target. Decide whether `servings_override` is retired in favor of `estimate_population` or kept
as a distinct per-meal override during implementation (lean: `estimate_population` is the day
headcount; `servings_override` becomes redundant and should be removed).

## Costing engine

File: [backend/app/Services/MenuCycleCostService.php](../../../../backend/app/Services/MenuCycleCostService.php)

Today `aggregate(array $entries, int $population)` takes one shared population and computes a
cycle-wide `cost_per_head = total / population`. Changes:

- `aggregate()` reads **each entry's day population** (carried in the entry array) instead of a
  single passed `$population`. Per-day multiplier uses `RecipeScaler::factor(recipe.servings,
  day.estimate_population)` (existing util in `App\Support\RecipeScaler`).
- `entriesForDays($days)` — include `estimate_population` from each `MenuCycleDay` in the entry array.
- `usageForDays($days)` — **drop the `$target` parameter**; read population per day inside the
  loop. (Quoting the decision: "MenuCycleCostService::usageForDays() needs to change from taking
  one population value for a whole batch of days to reading population per day inside its loop,
  since there's no longer a single shared number to pass in.")
- `forCycle()` — remove the `?? $cycle->population` fallback (column gone).
- Cycle-level `cost_per_head` summary becomes an aggregate across days (e.g. Σ day cost ÷ Σ day
  population), not `total / single_population`. The `'population'` key in the return shape becomes
  a sum/avg of day populations, or is dropped in favor of per-day figures.

## Callers to update

- `App\Services\FSS\ConsumptionService` — currently `$populationOverride ?? $cycle->population`
  ([backend/app/Services/FSS/ConsumptionService.php](../../../../backend/app/Services/FSS/ConsumptionService.php)).
  Switch to per-day estimate.
- `ShoppingListController::generate()` — calls `usageForDays()`; update to the new signature. Link
  to cycle stays.
- `InsightsController`, `BudgetActualService` (see [07-workflow-review.md](07-workflow-review.md)
  F2), `MenuCycleController` compute endpoints.
- Tests: `MenuCycleCostServiceTest`, `FoodServiceOpsTest`, `MealPrepShortfallTest`,
  `InsightsControllerTest` — rewrite for per-day population.

## Models

- `MenuCycle` — remove `population` and `budget_per_head_per_day` from `$fillable` / `$casts`.
- `MenuCycleDay` — add `estimate_population` to `$fillable` / `$casts` (integer).

## Frontend

- Move the population input from the cycle form to the per-day planning UI
  ([frontend/app/(rnd)/food-service/menu-cycle/page.tsx](../../../../frontend/app/(rnd)/food-service/menu-cycle/page.tsx),
  [ServiceLogPanel.tsx](../../../../frontend/app/(rnd)/food-service/menu-cycle/_components/ServiceLogPanel.tsx)).
- Remove the recipe-level master scaler UI entirely.
- `menuCycleService.ts` — drop cycle `population` from interfaces; add per-day
  `estimate_population`. `computeCycle()` no longer passes a population override.

## Population log (reporting only)

A separate population log, distinct from the menu plan, captures **actual population** and
**served** per day. Behavior:

- Auto-filled with that day's estimate as a **read-only live reference** (NOT a snapshot) once
  the day is saved.
- Tracked **per day**, not per meal.
- Backed by the existing `meal_prep_logs` columns (`population`, `served_population`,
  `population_variance`) — partially built already, just not yet connected to the per-day scaling.
- **Actual population and served are reporting-only and never retroactively rescale ingredient
  math.** This follows from timing: actuals are only known after shopping has happened, so they
  logically cannot be what drove the purchase.

## Calendar anchoring

See [00-overview.md](00-overview.md). The cycle is a Mon–Sun pattern; activation anchors to a
real Monday `week_start_date`; the population log and generated lists resolve to real dates.
