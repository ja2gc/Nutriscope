# 05 — Missing Functionality (net-new behavior)

## Inline recipe/food editor in Menu Cycle

Menu Cycle needs to let you **view (and ideally edit) a food or recipe inline** using the same
UI as the food service recipe editor, instead of being a disconnected reference. Reuse the
existing FS recipe editor component rather than building a separate viewer.

## Population log

A separate population log (tab or page) capturing **actual population and served per day**.
Detailed in [01-population-redesign.md](01-population-redesign.md):

- Auto-filled with that day's estimate as a read-only live reference (not a snapshot) once the
  day is saved.
- Tracked per day, not per meal.
- Backed by existing `meal_prep_logs.population` / `served_population` / `population_variance`.
- Reporting-only — never rescales ingredient math.

## Single-ingredient recipes

Recipes need to support single-ingredient entries (e.g. a banana, a packaged snack) so the menu
cycle can include them without forcing them through a multi-ingredient structure.

- This is just a recipe with a **baseline of one serving and one ingredient**.
- Note: `MenuCycleCostService::aggregate()` already has an `item` branch for ready-to-serve
  catalog items (one serving per head, no recipe scaling). During implementation, decide whether
  single-ingredient entries reuse that `item` path or the normal recipe path with servings=1.
  Lean: model as a real recipe (servings=1, one ingredient) so it flows through one code path and
  participates in scaling consistently.
