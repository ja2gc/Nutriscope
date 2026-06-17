# 02 — Seed Data Fixes

> Phase C. Rebuild FS seeders so demo data is realistic and self-consistent.

Files:
- [backend/database/seeders/FsCatalogSeeder.php](../../../../backend/database/seeders/FsCatalogSeeder.php)
- [backend/database/seeders/FoodServiceRecipeSeeder.php](../../../../backend/database/seeders/FoodServiceRecipeSeeder.php)
- [backend/database/seeders/FoodServiceDemoSeeder.php](../../../../backend/database/seeders/FoodServiceDemoSeeder.php)

## Defects and targets

| # | Defect | Target |
|---|--------|--------|
| S1 | All inventory items default to **grams** regardless of what's purchased | Default **kg** for hospital-scale bulk; grams only for genuinely small-quantity ingredients |
| S2 | Packed/discrete items seeded with weight units | Use **pack / bundle / piece** — how they're actually procured and counted |
| S3 | Recipe seed data has absurd ingredients and quantities (across the board, not isolated) | Realistic ingredients + quantities for every recipe |
| S4 | Budget/cost numbers don't reconcile — only 3 procurements + 1 menu plan, but budget figures imply far more; costs inflated | Budget figures must **reconcile** with seeded procurement + menu plan |
| S5 | Seeded menus produce **negative variance** against budget (already failing their own constraint) | Nothing seeded in a failing state — see S8 |
| S6 | Some inventory items have **no values or units** ("untracked" state) | Every seeded item gets a real value + unit; **untracked must not exist as a state** |
| S7 | Patient population not in the real range | Seed patient population in **150–200** |
| S8 | Menus seeded above the per-head cap | Seed menus at **₱120–150 per head**, always **under** the 150 cap (cap now owned by Budget — see [03](03-schema-business-logic.md)) |
| S9 | Recipe baseline servings hardcoded as a system number | **50 is a per-recipe seeder default**, not a fixed system constant; user-editable per recipe |
| S10 | One shared population across a cycle | Generate a **distinct `estimate_population` per individual `menu_cycle_day`** (follows from [01](01-population-redesign.md)), each within the 150–200 range |

## Consistency requirements

- The seeded budget's `budget_per_head_day` = **150** (was 130, see [03](03-schema-business-logic.md)).
- Seeded daily menu per-head cost ≤ 150 for every day → no negative variance anywhere.
- Total seeded cost = Σ(day cost) must be plausibly covered by the seeded budget allocation;
  don't allocate a budget that implies more procurement than is seeded.
- Units on `fs_items.base_unit` / `purchase_unit` must be internally consistent with
  `units_per_purchase` and `purchase_price` so derived `unit_price` is sane.

## Verification (when implemented)

`php artisan migrate:fresh --seed`, then via laravel-boost `database-query`:
- every `inventory` row has non-null `unit` and a value;
- each `menu_cycle_day` has a distinct `estimate_population` in 150–200;
- per-day per-head cost ≤ 150;
- budget actual vs planned shows no negative variance for seeded data.
