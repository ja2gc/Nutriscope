# Food Service Overhaul — Brainstorm + Plan

> Scope: Menu Cycle, Budget, Procurement, **Supplies** (new inventory tab), Suppliers tab, Reports (3 tabs).
> Branch: `feat/nutri-engine-overhaul`. Date: 2026-06-11.
> Confirmed decisions (from user): (1) **decouple** food-service inventory/recipes from the `food_items` library into a standalone FS catalog; (2) supplies live in the **same FS catalog** with a `kind` flag; (3) current FS data is **seed/test — rebuild fresh**; (4) build **foundation-first**.

---

## 1. Brainstorm

### Goal
Make the four food-service pages (Menu Cycle, Budget, Procurement, Inventory) and Reports actually work as an end-to-end loop: plan a weekly menu → it auto-computes ingredient usage + cost/head vs budget → procurement turns that into vendor shopping lists + purchase orders with receipts → actuals feed the budget → everything is printable/exportable as the government forms RND already submits.

### Constraints
- **Stack (do not fight it):** Next.js 16 + React 19 + Tailwind 4 + `lucide-react` + `recharts` 3.8 (already installed). Laravel + MySQL backend, DomPDF for PDFs, generators + `report_templates` already scaffolded. No calendar lib installed → menu grid is a **custom grid** (good, because it must render cleanly HTML→PDF).
- **Reuse the established design language** from [inventory/page.tsx](../frontend/app/(rnd)/food-service/inventory/page.tsx): emerald primary / zinc neutrals, `rounded-2xl` cards, lucide icons (no emoji icons), tabbed tables with inline expand-rows, status badges (red=`no_stock`, amber=`low`, emerald=`ok`), `₱` currency, breadcrumb header pattern. New pages must look like they belong.
- **Hard rule:** food-service catalog has **zero relation to `food_items`** (the USDA/NCP library) — not even a FK. They are different domains. FS items carry **no nutrition** (user-confirmed: "items in inventory in food service doesn't have nutrients data to begin with"). FS recipes are **cost-only**.
- Schema is already ~80% scaffolded (`inventory`, `suppliers`, `shopping_lists`, `purchase_orders`, `menu_cycles`, `budgets`, `food_service_recipes`, `inspection_reports`, `marketing_statements`, `marketing_summaries`, `report_templates`). This is mostly **wiring + UX + a decouple migration**, not greenfield.
- PH government report fidelity: AIR (Acceptance & Inspection Report), Statement of Marketing, Summary of Marketing, Dietary Cash Book — all fields must survive into the generated PDFs.

### Known context (current state)
- `inventory` page is **fully built**; `menu-cycle`, `budget`, `procurement` are **empty scaffolds** (~45 lines each).
- **Coupling to remove:** inventory "Ingredients" tab lists `food_items`; FS recipe builder ([recipes/new/page.tsx](../frontend/app/(rnd)/food-service/recipes/new/page.tsx)) searches inventory filtered to `item_type==="food_item"` → `inventory.food_item_id → food_items`. FS recipe ingredients reference `inventory.id`.
- Existing cost formula is a hack: `(qty / 100) × unit_price` (assumes price is per-100g). We replace it with an explicit unit model.
- `food_service_recipes` has unused `total_calories/protein/carbs/fat` columns (artifact of the old coupling) — drop them.
- Reports: `reports.type` enum already lists `inventory`, `budget`, `procurement`, `menu_cycle`, `patient_menu_plan`, `inspection_report`, `marketing_statement`, `marketing_summary`. Generators + Blade views exist per [folder-structure.md](architecture/folder-structure.md).

### Risks
- **Decouple migration** touches inventory + FS recipes. Mitigated: user approved a **fresh rebuild** (drop & reseed FS data), so no risky backfill — but NCP recipes (`recipes` table) and the food library MUST stay untouched.
- **Unit/cost correctness** — purchase unit (kg) vs recipe unit (g) conversions. A wrong factor silently corrupts every cost. → unit-test the conversion + scaling math (TDD).
- **Menu-cycle aggregation** (population × recipe scaling → ingredient usage) is the engine everything else trusts. → TDD it.
- **Report field fidelity** — the government forms have exact signatory blocks; missing a field = rejected submission. → map each form field-by-field before coding the generator.
- **Ownership/role ambiguity** — `menu_cycles.fss_user_id` says FSS owns it, but the spec says **RND** plans menus from the FS recipe page. Needs a one-line decision (see Open Questions).
- **Multiple receipts per PO** — current `purchase_orders.receipt_image` is a single text column; spec needs many files + a receipt/proof distinction → new attachments table.

### Options considered (data model for the decouple)
- **A — Standalone unified FS catalog (`fs_items`) with `kind: ingredient|supply`.** One catalog, one CRUD, supplies are just `kind=supply`. Inventory + FS recipes reference `fs_items`. **← chosen.** Cleanest, matches "no relation to food_items," and folds Supplies in for free.
- B — Keep shared `food_items`. Rejected: violates the hard rule.
- C — Two tables (`fs_ingredients` + `supplies`). Rejected: duplicates identical stock/price/CRUD logic for no benefit.

### Recommendation
Option **A**. Everything below assumes it.

### Acceptance criteria (end state)
- [ ] No FK or query path from food-service inventory/recipes to `food_items`. Grep proves it.
- [ ] Inventory has **3 tabs**: Ingredients · Supplies · Recipes — all reusing the existing table/inline-edit pattern.
- [ ] FS recipe builder: pick FS-catalog items, live line + grand totals, a **master serving scaler** that scales quantities **and** cost from a base yield, cost pulled live from catalog price (never hardcoded).
- [ ] Menu Cycle: weekly grid populated from FS recipes; set **population** + **budget/head/day**; auto-computes per-day ingredient usage, per-day cost, weekly total, cost/head, with red/amber/green vs budget; **save as a named template** + **create a new cycle from a template**.
- [ ] **Every entity has full CRUD** (list/create/read/update/delete) — no create-only screens (see §2.8).
- [ ] Procurement: **suggested shopping list** auto-built from a menu over a chosen day-span (qty + cost/day); per-item **vendor dropdown that remembers the last choice as default**; one shopping list → **multiple POs split by vendor**, each with **OR number** + **multiple receipt + proof photos**; records view groups POs by vendor under their list; marking a PO received **restocks inventory**.
- [ ] Budget: set **yearly budget** + **budget/head per day/month/year**; planned-vs-actual; **Recharts** trends over **any timespan**; a **Records tab** of monthly/yearly snapshots.
- [ ] Suppliers tab: CRUD vendors (name, description free-text, contact, OR-relevant fields); used as the procurement dropdown source.
- [ ] Reports: **3 tabs** (Food Service · NCP · Template Edit), a **Generate-All** button, **menu calendar PDF (Mon→Sun)**, patient menu-plan PDF, and a **Template Edit** tab for logo / hospital name / signatories; creator name auto-fills from the logged-in user.

---

## 2. Module designs

### 2.0 Data model (the decouple)

**New table `fs_items`** (food-service catalog, owned by food service, **no link to `food_items`**):

| col | type | notes |
|---|---|---|
| id | bigint | |
| name | varchar | "Carrots", "Chicken thigh", "Dishwashing liquid" |
| kind | enum(`ingredient`,`supply`) | drives which inventory tab + recipe eligibility |
| category | varchar | Vegetable / Meat / Grocery / Dairy / Disposable / Cleaning … |
| base_unit | varchar | the unit recipes & stock use (g, mL, pc) |
| purchase_unit | varchar | how you buy it (kg, L, pack, bundle) |
| purchase_price | decimal(10,2) | ₱ per `purchase_unit` (the editable "current price") |
| base_per_purchase | decimal(10,2) | base_units per purchase_unit (e.g. 1000 g per kg). For pc=1. |
| default_supplier_id | FK suppliers (nullable) | the "remembered" vendor for procurement |
| is_active | bool | |

> **Derived unit cost** = `purchase_price / base_per_purchase` (₱ per base_unit). This kills the `/100` hack and makes kg-bought / g-used recipes exact.

**`inventory`** changes: replace `food_item_id` with `fs_item_id`; `item_type` enum → (`ingredient`,`supply`,`recipe`). Keep `recipe_id` for prepared-recipe stock (→ `food_service_recipes`). Stock fields (`quantity_in_stock`, `unit`, `minimum_stock_threshold`, `usage_rate`, `notes`) unchanged.

**`food_service_recipe_ingredients`**: reference `fs_item_id` (not `inventory_id`) + `quantity` in the item's `base_unit`. **`food_service_recipes`**: keep `name, category, prep_notes, servings, cost`; **drop** `total_calories/protein/carbs/fat`.

**Reseed:** a `FsCatalogSeeder` with a realistic PH hospital-kitchen item list (ingredients + supplies). Drop/clear old FS inventory + FS recipe rows. **Do not touch** `food_items`, `recipes`, `recipe_ingredients`, NCP tables.

---

### 2.1 Inventory — add **Supplies** tab
- Reuse [inventory/page.tsx](../frontend/app/(rnd)/food-service/inventory/page.tsx) wholesale. Change primary tabs from `Ingredients | Recipes` → **`Ingredients | Supplies | Recipes`**. Supplies tab = same table, `kind=supply` (icon: `Boxes`/`Package`, a distinct accent e.g. sky to differentiate from emerald ingredients / violet recipes).
- Supplies have stock + price but no recipe linkage. Same inline edit/restock rows. Same status badges + thresholds.
- Backend `InventoryController` list query keys off `item_type` (`ingredient`/`supply`/`recipe`) instead of `food_item`/`recipe`.

### 2.2 FS Recipe builder (RND, under Food Service)
Extends the existing [recipes/new/page.tsx](../frontend/app/(rnd)/food-service/recipes/new/page.tsx):
- Ingredient search hits the **FS catalog** (`kind=ingredient`), not inventory/food_items.
- Each row: item, qty (base_unit), unit cost (from catalog, live), **line total = qty × unit_cost**; running **grand total bottom-right** (already present, fix the formula to `qty × unit_cost`).
- **Servings (base yield)** field — this is the **baseline**: "this recipe serves N with these ingredient amounts." Per-serving qty = row qty ÷ servings (shown as a hint).
- **Scaling does NOT live here (user decision Q5).** The recipe is an immutable baseline. The **menu plan** does the scaling (recipe × population). The recipe page keeps only a **read-only "preview at N servings"** calculator for convenience — it never overwrites the saved baseline.
- Cost is **never stored hardcoded** beyond a cached `cost` snapshot recomputed on save and on catalog price change.

### 2.3 Menu Cycle planner — the engine
**Ownership (user decision Q1): RND-owned.** `menu_cycles.fss_user_id` → `rnd_user_id` (RND creates/edits the cycle). **FSS = read-only + meal-prep log** (FSS sees the active cycle and checks off prepared meals via `meal_prep_logs`; cannot edit the plan).

**Schema:** rename `menu_cycles.fss_user_id`→`rnd_user_id`; add `population` (int), `budget_per_head_per_day` (decimal). `menu_cycle_days` swaps `food_item_id`→`fs_item_id` (for ready single items) and keeps `recipe_id` (→ `food_service_recipes`) + `servings_override` (nullable; defaults to population). The **scaling lives here**, not on the recipe (Q5): the recipe baseline (`recipe.servings`) is scaled to `population` at plan time.

**UI (custom weekly grid):**
- Rows = meal slots (Breakfast, AM Snack, Lunch, PM Snack, Dinner); columns = Mon→Sun (respect `cycle_days`). Each cell: assigned recipe(s)/item + a "+ add from recipes" picker (search FS recipes).
- Top controls: **Population** input, **Budget/head/day** input (defaults from the active Budget), cycle length, week start.
- **Live summary panel (right side):** total weekly cost, **cost per head/day**, vs budget → red/amber/green chip (reuse status colors). Per-day cost row under the grid.
- **Auto-calc service** (`MenuCycleCostService`, TDD):
  - factor for a recipe in a slot = `population / recipe.servings` (or `servings_override / recipe.servings`).
  - ingredient usage = Σ over recipes `recipe_ingredient.qty × factor`, grouped by `fs_item` per day and per week.
  - day cost = Σ `recipe.cost × factor`; week cost = Σ days; cost/head/day = day cost ÷ population.
- This per-day ingredient usage is the **single source** that Procurement's suggested list consumes.
- **Templates (first-class):** save any planned cycle as a **named menu-cycle template**, browse a Templates list, and **"Create cycle from template"** (clones days/recipes; population + week_start chosen fresh on instantiate). Plus a quick "Duplicate week" for in-place copy.
  - Schema: `menu_cycle_templates` (`fss_user_id`/owner, `name`, `description`, `cycle_days`) + `menu_cycle_template_days` (`template_id`, `day_of_week`, `meal_type`, `recipe_id`, `fs_item_id`, `quantity`) — mirrors the existing `meal_plan_templates`/`meal_plan_template_days` shape for consistency. Templates carry **no** population/budget (those are set when you instantiate).
  - Templates get **full CRUD** (list, create-from-cycle, rename/edit, delete) like everything else.

### 2.4 Procurement
**Schema deltas:** `purchase_orders` add `or_number`. New **`purchase_order_attachments`** (`purchase_order_id`, `path`, `type` enum(`receipt`,`proof`), `caption`) replacing the single `receipt_image`. `shopping_list_items.supplier_id` already exists (the per-item vendor); `fs_items.default_supplier_id` provides the remembered default.

**Flows:**
1. **Suggested shopping list:** pick a menu cycle + **day span** → aggregate the menu's per-day ingredient usage across that span (× cycle repeats if span > cycle) → list items with qty, unit, **default vendor** (from `fs_items.default_supplier_id`), unit price, line total; show **cost/day** + total. `list_type=suggested`. (Manual lists also supported, `list_type=manual`.)
   - **Span is fully configurable, NOT hardcoded (user note).** RND currently groceries **Tue & Fri**, so ship presets like "Tue→Thu (3d)" and "Fri→Mon (4d)" — but the span is a free input so any org/cadence works. The Tue/Fri rhythm is a *default preset*, never baked into logic (keeps it multi-tenant-ready — see §2.9).
2. **Per-item vendor dropdown:** options = suppliers. Changing it updates that line **and** writes back `fs_items.default_supplier_id` so it's the default next time (until changed again).
3. **Split into POs by vendor:** from one shopping list, "Generate POs" groups items by selected vendor → one **draft PO per vendor** (veg PO, meat PO, …), each with `po_number`, `or_number`, `order_date`, items, total.
4. **Receipts + proof:** each PO accepts **multiple** photo uploads tagged `receipt` or `proof`.
5. **Records view:** list of shopping lists → click one → see all its POs **grouped by vendor**, each with its attachments + status.
6. **Receive → restock:** marking a PO `received` adds its quantities to `inventory` (creates/updates rows) and feeds **actuals** to Budget.
7. Government docs (AIR / Statement of Marketing / Summary of Marketing) are **generated from PO data** here or in Reports (shared generators).

### 2.5 Suppliers tab
- CRUD on `suppliers`: name, **description** (free text — "vegetables", "meats"), contact, address, payment_terms, notes. (`description` ≈ current `category` column — reuse or rename.)
- Source of all vendor dropdowns in Procurement, and contact block in procurement reports.

### 2.6 Budget
**Schema:** extend `budgets` for a **yearly** scope + per-head rates: add `scope` enum(`daily`,`monthly`,`quarterly`,`yearly`,`custom`), `budget_per_head_day`, `budget_per_head_month`, `budget_per_head_year` (nullable as appropriate). `budget_daily_logs` already has `date/planned/actual/variance`.
- **Planned** = from active menu cycle (cost/head/day × population). **Actual** = from received POs / marketing statements, written into `budget_daily_logs`.
- **Page:** period selector (any range) → **Recharts**: planned-vs-actual trend line, cost/head trend, variance bars. KPI cards (allocated, spent, remaining, variance %, cost/head vs target).
- **Records tab:** snapshots per month + per year (allocated / actual / variance), reusing the tabbed-table pattern; each row → its report.

### 2.8 CRUD coverage (no half-built entities)
Every entity below ships with **full CRUD** — list, create, read/detail, update/edit, delete (soft-delete or archive where history matters, e.g. POs, budgets, cycles). No create-only screens. UI uses the shared inline-edit + confirm-delete pattern from the inventory page.

| Entity | List | Create | Read | Update | Delete | Notes |
|---|---|---|---|---|---|---|
| FS catalog items (ingredient/supply) | ✓ | ✓ | ✓ | ✓ | ✓ | delete = soft if referenced by a recipe |
| Inventory rows | ✓ | ✓ (set stock) | ✓ | ✓ (edit/restock) | ✓ (clear) | already built; extend to supplies |
| FS recipes | ✓ | ✓ | ✓ | ✓ | ✓ | edit re-runs scaler/cost |
| Menu cycles | ✓ | ✓ | ✓ | ✓ | ✓ (archive) | |
| **Menu-cycle templates** | ✓ | ✓ (from cycle) | ✓ | ✓ (rename) | ✓ | |
| Suppliers | ✓ | ✓ | ✓ | ✓ | ✓ (soft if on a PO) | |
| Shopping lists + items | ✓ | ✓ (manual + suggested) | ✓ | ✓ | ✓ | edit items/qty/vendor |
| Purchase orders + items + attachments | ✓ | ✓ | ✓ | ✓ | ✓ | attachments individually deletable |
| Budgets + daily logs | ✓ | ✓ | ✓ | ✓ | ✓ | |
| Report templates / branding | ✓ | ✓ | ✓ | ✓ | ✓ | Template Edit tab |

> Backend: prefer Laravel `apiResource` routes (index/store/show/update/destroy) per controller so nothing is missed.

### 2.7 Reports — 3 tabs
**Tab A · Food Service:**
**Report set + what to combine vs keep standalone** (decided after reading the actual forms):

| Report | What it is (from the real form) | Combine / standalone | Source |
|---|---|---|---|
| **PPA — Program Project Activity** | The weekly **menu + total cost + output (headcount) + inclusive dates + Prepared/Approved signatures** ("Food Subsistence for Patients"). | **COMBINE** the old "Menu Cycle report" into this — they're the same data. One report, two layouts: the **PPA form** (official) and a **Mon→Sun calendar** (printable). | menu cycle + cost service |
| **Menu Calendar (print)** | Mon→Sun grid of the menu to print/post in the kitchen. | Layout variant of PPA (same data, calendar skin). | menu cycle |
| **Dietary Cash Book** (Cash Disbursement Record) | Finance ledger: Date · Ref/OR/Check No · Payee (vendor) · Nature of Payment · Cash Advance/Replenishment · Disbursements · Balance (begin/end). | **Standalone** (accounting doc). | POs + budget logs |
| **Procurement pack** | The three buy-event docs. | **COMBINE into one "Procurement Pack" job** (AIR + Statement of Marketing + Summary of Marketing) per purchase/period — each still its **own page + own signatories**; also reachable standalone. | POs |
| Budget report | Planned vs actual, any range, charts/variance. | Standalone. | budgets + logs |
| Inventory report | Stock levels, usage, low/no-stock. | Standalone. | inventory |
| **Generate-All** | One click → produces the full Food-Service set for a chosen period (PPA + Cash Book + Procurement Pack + Budget + Inventory) as one background job. | — | all of the above |

**Tab B · NCP:** choose **patient** → choose reports (ADIME summary, Patient Menu Plan as calendar PDF). Plus a population-level **Demographic / Research Census** report (not patient-scoped): patient counts broken down by **age group, sex, ward, admission diagnosis, nutritional status, malnutrition severity, risk level**, over **any date range** — for research/census use. (Replaces the old "bi-annual" — that sheet is now just a layout reference, not a fixed format.) (Care-plan/screening generation is **out of scope** here per user.)

**Tab C · Template Edit:** edit branding + signatory blocks per report purpose. The real forms gave us the exact fields to make editable:
- **Header block:** logo, hospital name (`ROMANA PANGAN DISTRICT HOSPITAL`), address (`San Jose, Floridablanca, Pampanga`), accreditation line (`PhilHealth Accredited`), service name (`Nutrition and Dietetics Service`).
- **Signatories per purpose:** PPA → *Prepared by* (`ELAINE JUSTINA L. ABRIOL, RND` / `Nutritionist-Dietitian II`) + *Approved* (`RACHELL P. GUTIERREZ, MD, MHM` / `Chief of Hospital II`); AIR → inspected/certified/verified/approved/conforme/OIC-PGSO; Marketing → buyer/certified/examined/verified; Cash Book → accountable officer + designation + station.
- Store on `report_templates` (per-type defaults) + a `report_branding` singleton for the shared header. The **"prepared by" name auto-fills from the logged-in user** by default; the saved template provides the fallback + the other signatories. All of this is **config, never hardcoded** (multi-tenant — §2.9).

**Form → generator map** (fields now confirmed from the real files):

| Form (`docs/Nutriscope Forms`) | Generator / table | Status |
|---|---|---|
| PPA — Program Project Activity (`.docx`) | **new** `ProgramProjectActivityGenerator` (menu+cost+headcount) | fields mapped |
| Dietary Cash Book (`.xlsx`) | **new** `DietaryCashBookGenerator` (disbursement ledger) | fields mapped |
| Summary of Marketing (`Summary 1.xlsx`) | `MarketingSummaryGenerator` / `marketing_summaries` | columns exist |
| Acceptance & Inspection Report (`.jpg`) | `InspectionReportGenerator` / `inspection_reports(+items)` | columns exist |
| Statement of Marketing Purchased (`.jpg`) | `MarketingStatementGenerator` / `marketing_statements(+items)` | columns exist |
| Demographic / Research Census (bi-annual `.jpg` = layout ref only) | `NcpCensusGenerator` — refocus to demographic breakdowns, any date range | exists, refocus |

### 2.9 Single-tenant — org values as editable config
**Not multi-tenant.** This is built for **one hospital** (government-owned; the government decides whether to distribute it). **No `tenant_id`, no tenancy layer.** We still keep org-specific values as **editable config** — but only because those are real features you asked for, not for tenancy:
- **Branding & signatories** → `report_branding` / `report_templates` (Template Edit tab), so the hospital can fix a logo/name/signatory without a code change.
- **Procurement cadence / list span** → user input + presets (Tue/Fri preset), so the kitchen sets its own rhythm.
- **Budgets, population, cost/head** → data, set per cycle/period.

That's it — no speculative tenancy work.

---

## 3. Phased plan (foundation-first)

> Convention each step: **backend** (migration → model → controller → route, TDD the math) → **frontend** (page reusing the inventory pattern) → **verify**. Commit per step. Verify backend with `php artisan test` (unit only — no sqlite; see [[nutriscope-test-commands]]); FE via the dev server + the page.

### Phase 0 — Decouple + FS catalog + Supplies + Suppliers
0.1 Migration: create `fs_items`; alter `inventory` (`fs_item_id`, `item_type` enum incl. `supply`, drop `food_item_id`); alter `food_service_recipe_ingredients` (`fs_item_id`); drop nutrition cols on `food_service_recipes`. **Verify:** `php artisan migrate:fresh`, schema grep shows no `food_item_id` on FS tables.
0.2 `FsItem` model + `FsCatalogSeeder` (ingredients + supplies). Clear old FS data. **Verify:** seeder runs, rows present, food_items untouched.
0.3 Backend: `InventoryController` keyed on `ingredient|supply|recipe`; unit-cost derivation helper (TDD: kg→g, L→mL, pc). **Verify:** unit test for `unitCost()`.
0.4 Suppliers CRUD controller + routes + page (new tab/section). **Verify:** create/edit/delete a vendor.
0.5 Inventory page: add **Supplies** tab. **Verify:** supplies CRUD + status badges work.
**Rollback:** revert the migration batch; FS data was disposable.

### Phase 1 — FS Recipe builder
1.1 Point recipe ingredient search at FS catalog (`kind=ingredient`). Fix line/grand-total to `qty × unit_cost`.
1.2 Baseline yield + **read-only "preview at N servings"** calculator (no persist; scaling itself lives in Phase 2). TDD the scale-factor math once, reuse in `MenuCycleCostService`.
1.3 Recipe view/edit page parity (the `[id]` page is currently empty). **Verify:** build a recipe with baseline servings, preview at 120 shows scaled qty/cost, saved baseline unchanged.

### Phase 2 — Menu Cycle planner
2.1 Migration: rename `menu_cycles.fss_user_id`→`rnd_user_id`; add `population`, `budget_per_head_per_day`; `menu_cycle_days.fs_item_id` + `servings_override`; `menu_cycle_templates` + `menu_cycle_template_days`. (RND owns; FSS read + prep-log.)
2.2 `MenuCycleCostService` (TDD: usage aggregation, day/week cost, cost/head).
2.3 `MenuCycleController` (`apiResource` — full CRUD) + compute endpoint; `MenuCycleTemplateController` (full CRUD + create-from-cycle + instantiate-cycle-from-template).
2.4 Weekly grid page + recipe picker + live summary panel + budget alert chips + **Templates** (save-as-template, Templates list, create-from-template) + "Duplicate week". **Verify:** populate a week, set population/budget, totals + alerts correct; per-day ingredient usage endpoint returns expected; save→load a template reproduces the cycle.

### Phase 3 — Procurement
3.1 Migration: `purchase_orders.or_number`; `purchase_order_attachments` table; `fs_items.default_supplier_id`.
3.2 Suggested-list builder consuming `MenuCycleCostService` over a day span (TDD aggregation). `ShoppingListController`.
3.3 Per-item vendor dropdown with default write-back. 
3.4 `PurchaseOrderController`: split list → POs by vendor; multi-file receipt/proof upload; OR number; receive→restock.
3.5 Pages: shopping lists, list detail (POs grouped by vendor), PO detail w/ uploads. **Verify:** menu→suggested list→split POs→upload→receive→inventory restocked.

### Phase 4 — Budget
4.1 Migration: budget scope + per-head columns.
4.2 `BudgetController`: planned (from menu cycle) vs actual (from received POs) → `budget_daily_logs`; period rollups (TDD variance).
4.3 Budget page: KPI cards + Recharts trends (any range) + variance table + **Records tab** (monthly/yearly). **Verify:** set yearly budget + per-head; charts render; variance matches.

### Phase 5 — Reports
5.1 Reports page shell with **3 tabs** (Food Service · NCP · Template Edit) + `report_branding` singleton.
5.2 `ProgramProjectActivityGenerator` (PPA = menu+cost+headcount+dates+signatures) with two Blade layouts: official PPA form **and** Mon→Sun calendar; Patient Menu Plan PDF + **Demographic/Research Census** (refocus `NcpCensusGenerator` to demographic breakdowns over any date range) on the NCP tab.
5.3 `DietaryCashBookGenerator` (disbursement ledger from POs + budget logs); `MarketingSummaryGenerator` (Summary 1) confirmed; **Procurement Pack** job (AIR + Statement + Summary together); wire **Generate-All**.
5.4 Template Edit tab: header branding (logo/hospital/address/accreditation/service) + per-type signatory defaults; "prepared by" from auth user. **Verify:** generate PPA (both layouts), Cash Book, Procurement Pack, Budget, Inventory, + a patient menu plan; edited branding/signatories appear in output.

### Risks & mitigations (rollup)
- Wrong unit conversion → TDD `unitCost()` + scaling before any UI.
- Aggregation drift → `MenuCycleCostService` is the single source for menu cost, suggested lists, and planned budget; test once, reuse everywhere (DRY).
- Report rejection → field-map each government form before coding its generator; keep signatory blocks identical, only templatize logo/name/people.
- Role confusion → **resolved:** RND-owned menu cycle, FSS read + prep-log.

### Rollback plan
Each phase is its own migration batch + commits; `migrate:rollback` one batch reverts cleanly. FS data is disposable, so Phase 0 carries no data-loss risk to NCP/library.

---

## 4. Suggestions (user-reviewed)
✅ **Accepted:**
1. **Low-stock → one-click "add to shopping list"** (uses `minimum_stock_threshold`).
2. **Auto-update catalog price from latest received PO** (+ tiny price history) so recipe/menu costs track reality.
3. **Configurable procurement span/cadence** — presets for the Tue/Fri rhythm, but span is free input (multi-tenant-safe). *(replaces the old hardcoded-cadence assumption)*
4. **Population presets** + special-day/holiday menus.
5. **PO status timeline** (draft → ordered → received, timestamped) for the audit trail RND submits.
6. **Menu calendar "print" button** from the planner (same Blade as the report).
7. **Supplies in procurement** — shopping lists/POs can include `kind=supply` items (buy disposables/cleaning/LPG via the same flow — the real Cash Book lists "LPG", "roll bag", "paper meal box", so this is needed, not just nice-to-have).

❌ **Dropped (per user):**
- **Vendor price compare** — not needed.
- **Waste/leftover log** — RND reports everything is consumed within the span, so there's nothing to track.

## 5. Open questions — all resolved
1. **Menu-cycle ownership** → **RND-owned; FSS read + prep-log.** ✅
2. **Unit model** → **confirmed:** buy-unit + buy-price + "recipe-units per buy-unit" (e.g. kg, ₱80, 1000 g) → app derives ₱/g. Replaces the `/100` hack. ✅
3. **PPA / Summary 1 / Dietary Cash Book fields** → **extracted + mapped** (§2.7). PPA = menu+cost report; Cash Book = disbursement ledger; Summary 1 = Summary of Marketing. ✅
4. **Proof vs receipt** → **yes, separate tags** on `purchase_order_attachments`. ✅
5. **Recipe scaling** → **recipe is the immutable baseline; scaling happens in the menu plan.** Recipe page shows a read-only preview only. ✅
