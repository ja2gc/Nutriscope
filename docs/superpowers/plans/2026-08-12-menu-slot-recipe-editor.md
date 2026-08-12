# Menu Slot Recipe Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a dedicated, responsive Menu Item Details page where RND customizes one Menu Cycle slot and FSS sees the same slot read-only, with one scaling pipeline through procurement and PO locking.

**Architecture:** Add one nullable `recipe_override` JSON document to `menu_cycle_days`; keep the master recipe relation as fallback. Extend `MenuCycleCostService` to normalize either source into the same entry shape, expose composite day/meal endpoints, and render one shared Next.js page through RND and FSS route wrappers.

**Tech Stack:** Laravel 13.23, PHP 8.4, MySQL, PHPUnit 12, Next.js 16 App Router, React 19, TypeScript, Tailwind, Vitest, Lucide.

## Global Constraints

- User-facing title is `Menu Item Details`; never use `Edit Recipe` in Menu Cycle flow.
- RND writes only slot override; master Food Service recipe remains unchanged.
- FSS and locked PO slots are read-only; backend enforces both.
- All ingredients scale proportionally: `baseline quantity × target servings ÷ recipe makes`.
- Existing `MenuCycleCostService` and PO snapshot remain scaling and freezing authorities.
- Reuse NutriScope components/tokens; no new UI/dependency framework.
- One JSON column; no duplicate normalized recipe/ingredient tables, versioning, fixed-quantity mode, or autosave system.
- Preserve unrelated `.codex/config.toml`; no AI attribution in Git metadata.

---

### Task 1: Persist and Scale Slot Overrides

**Files:**
- Create: `backend/database/migrations/2026_08_12_000000_add_recipe_override_to_menu_cycle_days.php`
- Modify: `backend/app/Models/MenuCycleDay.php`
- Modify: `backend/app/Services/MenuCycleCostService.php`
- Modify: `backend/app/Http/Controllers/FSS/MenuCycleController.php`
- Test: `backend/tests/Unit/MenuCycleCostServiceTest.php`
- Test: `backend/tests/Feature/MenuCycleWorkflowGuardTest.php`

**Interfaces:**
- Produces: `MenuCycleDay::recipe_override: ?array`.
- Produces: `MenuCycleCostService::entryForDay(MenuCycleDay $day): ?array` used by cost, shopping-list, and snapshot flows.
- Preserves override across `syncDays()` only when composite slot and source recipe/item match.

- [ ] **Step 1: Write failing scaling tests**

Add a unit case with a 20-serving override containing 2 kg ingredient and target 100; assert quantity 10 kg and cost. Add feature case saving a cycle twice and assert unchanged slot retains `recipe_override`, while replacing its recipe clears it.

- [ ] **Step 2: Run tests and verify RED**

Run: `php artisan test tests/Unit/MenuCycleCostServiceTest.php tests/Feature/MenuCycleWorkflowGuardTest.php`

Expected: failure because `recipe_override` and override selection do not exist.

- [ ] **Step 3: Add minimal migration/model support**

Migration adds nullable JSON after `servings_override`. Model fillable/casts adds:

```php
'recipe_override',
// casts
'recipe_override' => 'array',
```

- [ ] **Step 4: Centralize entry selection**

Extract one public helper from `entriesForDays()`:

```php
public static function entryForDay(MenuCycleDay $day): ?array
```

When `recipe_override` exists, map its `reference_servings` and ingredients, resolving authoritative `FsItem` unit costs. Otherwise map current recipe/item exactly as today. `entriesForDays()` filters null and calls this helper.

- [ ] **Step 5: Preserve override during grid sync**

Before deleting days, key current rows by `day_of_week|meal_type`. Copy `recipe_override` only if old/new `recipe_id` and `fs_item_id` match; otherwise insert null.

- [ ] **Step 6: Run focused tests and commit**

Run focused PHP tests, then commit:

```text
feat(menu): add slot recipe overrides
```

---

### Task 2: Add Authorized Slot API and PO Regression Coverage

**Files:**
- Create: `backend/app/Http/Requests/FSS/UpdateMenuSlotRecipeRequest.php`
- Modify: `backend/app/Http/Controllers/FSS/MenuCycleController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/MenuSlotRecipeTest.php`
- Modify/Test: `backend/tests/Feature/MenuCyclePoSnapshotTest.php`

**Interfaces:**
- Produces GET `/api/fss/menu-cycles/{menu_cycle}/slots/{day}/{meal}`.
- Produces PATCH and DELETE/reset at same path inside existing `role:RND` middleware.
- GET response: `{ data: { cycle_id, day, meal, source, locked, name, reference_servings, planned_servings, prep_notes, ingredients, total_cost, cost_per_head } }`.

- [ ] **Step 1: Write failing feature tests**

Cover RND GET/PATCH/DELETE, FSS GET and PATCH 403, another cycle/day rejection, duplicate/missing ingredients, locked slot rejection, master recipe unchanged, and PO snapshot using override scaled to shopping-list population.

- [ ] **Step 2: Run tests and verify RED**

Run: `php artisan test tests/Feature/MenuSlotRecipeTest.php tests/Feature/MenuCyclePoSnapshotTest.php`

Expected: 404/missing request class.

- [ ] **Step 3: Implement validation**

Validate exact nested shape:

```php
'name' => ['required', 'string', 'max:255'],
'reference_servings' => ['required', 'integer', 'min:1'],
'planned_servings' => ['required', 'integer', 'min:1'],
'prep_notes' => ['nullable', 'string', 'max:5000'],
'ingredients' => ['required', 'array', 'min:1'],
'ingredients.*.fs_item_id' => ['required', 'string', 'distinct', 'exists:fs_items,uuid'],
'ingredients.*.quantity' => ['required', 'numeric', 'gt:0'],
'ingredients.*.unit' => ['required', 'string', 'max:30'],
```

Convert UUIDs to internal IDs in `passedValidation()` following existing Menu Cycle request style. Never accept price/cost input.

- [ ] **Step 4: Implement composite slot resolution and actions**

Allow only declared `DAYS`/`MEALS`, scope query through `$menuCycle->days()`, load recipe ingredients/items, reject non-recipe single items for ingredient-list editing, reject locked snapshots, save `recipe_override` + `servings_override`, and reset override to null.

- [ ] **Step 5: Make PO snapshot consume shared entry authority**

Replace direct `recipeProfile($cell->recipe, $pop)` selection with a profile generated from `entryForDay($cell)` so override/master/item all use identical calculation.

- [ ] **Step 6: Run focused tests, Pint, and commit**

```text
feat(menu): add slot details API
```

---

### Task 3: Build Shared Menu Item Details Page

**Files:**
- Modify: `frontend/services/menuCycleService.ts`
- Create: `frontend/components/foodservice/MenuSlotRecipePage.tsx`
- Create: `frontend/app/(rnd)/food-service/menu-cycle/[cycleId]/slots/[day]/[meal]/page.tsx`
- Create: `frontend/app/fss/menu/[cycleId]/slots/[day]/[meal]/page.tsx`
- Create: matching `loading.tsx` wrappers or one shared loading component
- Create: `frontend/components/foodservice/menu-slot-recipe-contract.test.ts`

**Interfaces:**
- Produces `MenuSlotRecipe` and `MenuSlotIngredient` types.
- Produces `getMenuSlotRecipe`, `updateMenuSlotRecipe`, `restoreMenuSlotRecipe` service calls.
- Shared component prop: `{ readOnly: boolean; backHref: string }`, with route params read by wrappers.

- [ ] **Step 1: Write failing frontend contract and pure scaling tests**

Assert page title/copy, route wrappers, RND/FSS modes, 44px targets, no `Edit Recipe`, and pure local formula for 2 kg/20→10 kg/100.

- [ ] **Step 2: Run tests and verify RED**

Run: `npm test -- --run components/foodservice/menu-slot-recipe-contract.test.ts services/menuCycleService.test.ts`

- [ ] **Step 3: Add service methods and local calculator**

Use existing `apiFetch`. Calculator accepts baseline quantity, reference servings, target servings and returns rounded display quantity without network calls.

- [ ] **Step 4: Build responsive shared page**

Reuse `Button`, existing input classes/tokens, `SearchInput`/catalog service, Lucide icons. Single column at 375px; summary grid on wider screens. Ingredient rows become stacked cards on mobile. Inputs remain 16px/min-h-11. Save keeps form visible; success uses `aria-live`; errors appear near form.

- [ ] **Step 5: Add thin route wrappers and loading skeleton**

RND wrapper passes editable; FSS wrapper passes read-only. Both use same component and exact `Menu Item Details` title.

- [ ] **Step 6: Run focused tests, lint/typecheck, and commit**

```text
feat(menu): add menu item details page
```

---

### Task 4: Replace Menu Cycle Modal With Predictable Navigation

**Files:**
- Modify: `frontend/app/(rnd)/food-service/menu-cycle/page.tsx`
- Modify: `frontend/app/(rnd)/food-service/menu-cycle/served-population-ui.test.ts`
- Create: `frontend/app/(rnd)/food-service/menu-cycle/menu-slot-navigation.test.ts`

**Interfaces:**
- Consumes dedicated RND/FSS slot routes from Task 3.
- Produces save-before-navigation behavior for new/dirty cycles and preserves cycle selection through `?cycle={id}`.

- [ ] **Step 1: Write failing navigation contract**

Assert modal/profile fetch/Edit master link removed; populated card navigates to role-specific slot URL; copy uses `Open menu item` or `View menu item`; page restores `cycle` query selection.

- [ ] **Step 2: Run test and verify RED**

Run focused Vitest files.

- [ ] **Step 3: Implement minimal navigation**

Remove `RecipeProfilePanel` and profile state. Make populated card a 44px target. For persisted clean cycle, use `router.push`. For unsaved/dirty cycle, call existing `handleSave`, then navigate only on success. Back link includes `?cycle={cycleId}`; initial root reads it and opens that cycle. Browser handles scroll restoration—no `location.reload` or `router.refresh`.

- [ ] **Step 4: Run focused/full frontend tests and commit**

```text
fix(menu): replace profile modal with slot page
```

---

### Task 5: Blast-Radius Verification and Delivery

**Files:**
- Modify only if a verification failure proves a task-scoped defect.

- [ ] **Step 1: Backend verification**

Run relevant feature/unit tests, then full `php artisan test`; run `vendor/bin/pint --test` on changed PHP files.

- [ ] **Step 2: Frontend verification**

Run full Vitest alone (Windows spawn stability), ESLint, `npx tsc --noEmit`, and `npm run build`.

- [ ] **Step 3: Responsive/role browser verification**

Verify 375, 768, 1024, 1440 widths: no horizontal scroll; Back works; RND edits locally and saves; FSS has no inputs/actions; locked state read-only; no content blanking during number edits.

- [ ] **Step 4: Database and Git safety checks**

Run migration status/schema inspection, `git diff --check`, task-only staged-file audit, and case-insensitive scan for `codex|claude|openai|co-authored|generated-by`. Confirm only configured user name/email on commits. Leave `.codex/config.toml` uncommitted.

- [ ] **Step 5: Push and prove remote**

Push `main`, then assert local `git rev-parse HEAD` equals `git ls-remote origin refs/heads/main`.
