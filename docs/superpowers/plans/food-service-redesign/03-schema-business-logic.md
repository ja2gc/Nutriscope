# 03 — Schema / Business-Logic Decisions

> Phase B.

## Budget ownership (per-head cap)

- The per-head/day cap is a **Budget** concept, not a Menu Cycle concept. This is a
  data-ownership fix, not just a wrong number.
- `menu_cycles.budget_per_head_per_day` is **removed** (in Phase A migration).
- The cap lives in `budgets.budget_per_head_day` (column already exists). Seed value **150**
  (currently seeded 130).
- Once correctly homed in Budget, menus are seeded in the **₱120–150** range, under the cap,
  never above it (see [02-seed-data-fixes.md](02-seed-data-fixes.md)).

## Low stock removal

Remove the low-stock concept entirely:

- No editable minimum-threshold field. Drop `inventory.minimum_stock_threshold`.
- No numeric low-stock state; remove `usage_rate`-based low-stock logic.
- Replace with a **binary green/red indicator** (a dot): red = out of stock (qty 0), green = in
  stock. UI detail in [04-ui-ux.md](04-ui-ux.md).

## Recipe baseline servings

- Each recipe stores its own baseline servings (`food_service_recipes.servings`, already exists),
  set and editable by the user when authoring the recipe.
- Seeder defaults this to **50**; it is **not** a fixed system number.
- This baseline is the denominator in the per-day scaling multiplier (see
  [01-population-redesign.md](01-population-redesign.md)).

## Population: cycle → day

Covered fully in [01-population-redesign.md](01-population-redesign.md). Schema impact here:
ADD `menu_cycle_days.estimate_population`; DROP `menu_cycles.population`.

## Budget view default

- Budget should **default to the monthly view**; the user can switch to weekly.
- Files: [BudgetController.php](../../../../backend/app/Http/Controllers/FSS/BudgetController.php),
  [budget/page.tsx](../../../../frontend/app/(rnd)/food-service/budget/page.tsx).
- `budgets.scope` enum already supports `monthly` / `quarterly` / `yearly` / `custom`.

## Events

Event/exception-day handling adds `menu_cycle_days.is_event` + `event_allocation`. Designed in
[06-events-and-cashbook.md](06-events-and-cashbook.md).

## Migration stance

Fresh rebuild is acceptable — prior migrations already declare FS data disposable. Path:
`php artisan migrate:fresh --seed`. No back-fill needed.

Net new columns across the redesign:
- `menu_cycle_days.estimate_population` (unsigned int, nullable)
- `menu_cycle_days.is_event` (bool, default false)
- `menu_cycle_days.event_allocation` (decimal, nullable)

Dropped columns:
- `menu_cycles.population`
- `menu_cycles.budget_per_head_per_day`
- `inventory.minimum_stock_threshold` (and unused `usage_rate` low-stock logic)
- `menu_cycle_days.servings_override` (likely redundant under per-day population — confirm)
