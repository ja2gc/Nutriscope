# UI/UX Overhaul + Bug Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 3 active bugs (delete plan, auto-generate runtime error, food picker CRUD), unify the button design system, make the entire app responsive with no horizontal scroll, clean up the intervention page layout, remove the fluid_restriction standalone goal, and add water tracking to the food pipeline.

**Architecture:** All UI changes are frontend-only (Tailwind + React). Backend changes are limited to: (1) a migration for `water_g` on `food_items`, (2) UsdaService nutrient extraction update, (3) removing `fluid_restriction` from `InterventionController::mapGoalTypeToConditions()`, and (4) wiring `fluid_ml` autofill into renal/cardiac goals in `nutritionCalculations.ts`. No new tables or routes needed. Responsive layout is handled entirely via Tailwind breakpoints — sidebar gets a mobile drawer, no overflow-x anywhere.

**Tech Stack:** Laravel 13.8, Next.js 16, TypeScript, Tailwind CSS v4, shadcn/ui (Radix), Lucide React, PHPUnit

---

## File Map

**Backend — modified:**
- `backend/app/Services/UsdaService.php` — add water_g extraction (nutrient ID 1051)
- `backend/database/migrations/xxxx_add_water_g_to_food_items.php` — new column
- `backend/app/Models/FoodItem.php` — add water_g to fillable
- `backend/app/Http/Controllers/RND/InterventionController.php` — remove fluid_restriction from mapGoalTypeToConditions
- `backend/tests/Feature/RND/MealPlanTest.php` — add delete test, generate test

**Frontend — modified:**
- `frontend/components/ui/Button.tsx` — add `ghost` + `icon` variants
- `frontend/components/layout/Sidebar.tsx` — mobile drawer
- `frontend/components/layout/TopBar.tsx` — hamburger toggle on mobile
- `frontend/lib/nutritionCalculations.ts` — remove fluid_restriction, wire fluid_ml for renal/cardiac
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx` — sticky tabs
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/GoalSelectorModal.tsx` — remove fluid_restriction goal
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx` — fix delete/generate/picker bugs, move micros button, redesign macro bar, fix manual plan button placement
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MacroTrackerBar.tsx` — white bg, inline micros toggle

---

## Task 1: Fix Delete Plan Bug

**Problem:** `handleDeletePlan` has no `catch` — errors are silently swallowed by `finally`. User sees modal close but plan stays.

**Files:**
- Modify: `backend/tests/Feature/RND/MealPlanTest.php`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx`

- [ ] **Step 1: Write failing PHPUnit test** — Add a test in `MealPlanTest.php` that verifies `DELETE /api/rnd/ncp-records/{ncp}/meal-plans/{plan}` returns 204 and the plan no longer exists in the DB. Confirm it passes (route and controller already exist).

- [ ] **Step 2: Run tests**
  ```
  cd backend && php artisan test --filter=MealPlanTest
  ```
  Expected: all pass.

- [ ] **Step 3: Add error state to MealPlanSection** — In `MealPlanSection.tsx`, add a `deleteError` state (`string | null`). In `handleDeletePlan`, wrap in try/catch; on catch set `deleteError` to the error message. Render a small red error note below the plan pills when `deleteError` is set.

- [ ] **Step 4: Verify manually** — Open a plan, click delete, confirm dialog, verify plan disappears from list and no console errors.

- [ ] **Step 5: Commit**
  ```
  git commit -m "fix: surface delete plan errors + add PHPUnit coverage"
  ```

---

## Task 2: Fix Auto-Generate Runtime Error

**Problem:** After generate, `handleGenerate` calls `setActivePlan(result)` then `loadItems` runs. The exact runtime error needs investigation — most likely: (a) `result.days` is undefined/null causing crash in `dayLookup`, or (b) `generateMealPlan` proxy uses `NEXT_PUBLIC_API_URL` while other proxies use `LARAVEL_API_URL`, causing a mismatch in Docker.

**Files:**
- Modify: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/meal-plans/generate/route.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx`
- Modify: `backend/tests/Feature/RND/MealPlanTest.php`

- [ ] **Step 1: Add backend generate test** — In `MealPlanTest.php` add a test: POST generate with valid data returns 201 and `data.days` is an array of 35 items. Run to confirm passes.

- [ ] **Step 2: Normalize generate proxy env var** — In `generate/route.ts`, change `NEXT_PUBLIC_API_URL` to `LARAVEL_API_URL ?? "http://127.0.0.1:8000/api"` (drop the `/api` duplication — check the URL construction so it doesn't double-append `/api`). Match exact pattern used in `[mealPlanId]/route.ts`.

- [ ] **Step 3: Guard `plan.days` in loadItems** — In `loadItems`, the line `const dayLookup = new Map((plan.days ?? []).map(...))` already has `?? []` guard. Confirm `result` from `generateMealPlan` returns `data.data` which includes `days`. Add a console.error in the catch block of `loadItems` so errors surface in dev tools.

- [ ] **Step 4: Add generate error display** — In `handleGenerate`, wrap `loadItems` call in try/catch after generate; set `generateError` with the message if it throws. The existing `generateError` display box already renders in JSX.

- [ ] **Step 5: Run tests + manual verify**
  ```
  cd backend && php artisan test --filter=MealPlanTest
  ```
  Then generate a plan in the UI and verify it loads correctly.

- [ ] **Step 6: Commit**
  ```
  git commit -m "fix: normalize generate proxy env var, surface loadItems errors post-generate"
  ```

---

## Task 3: Fix Food Picker CRUD (Add Food to Meal Slot)

**Problem:** `addFromLibrary`, `addFromRecipe`, `addFromUsda` all have no `catch` block — errors silently swallowed. User clicks "Add", spinner shows, nothing happens.

**Files:**
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx`
- Modify: `backend/tests/Feature/RND/MealPlanTest.php`

- [ ] **Step 1: Add backend item store test** — In `MealPlanTest.php`, add a test: POST to `meal-plans/{plan}/days/{day}/items` with `food_item_id` returns 201 and item appears in the day. Run to confirm passes.

- [ ] **Step 2: Add `pickerError` state** — In `MealPlanSection.tsx` add `pickerError: string | null` state. In all three `addFrom*` functions, add a catch block that sets `pickerError`. Reset `pickerError` when picker opens. Display it inside the picker modal as a small red message.

- [ ] **Step 3: Verify fdc_id flow** — `addFromUsda` passes `fdc_id: String(food.fdc_id)` to `addMealPlanItem`. Check that `MealPlanItemController::store()` handles `fdc_id` — it should call `UsdaService::import()` and create a food item on the fly. If this path is broken, add the fix in the controller.

- [ ] **Step 4: Manual test all three tabs** — Add food from Library, Recipes, and USDA. Verify items appear in the meal slot and macro totals update.

- [ ] **Step 5: Commit**
  ```
  git commit -m "fix: surface food picker errors in all three add-from paths"
  ```

---

## Task 4: Remove Fluid Restriction Goal + Wire Fluid to Renal/Cardiac

**Problem:** `fluid_restriction` is a standalone goal but should be a modifier within CKD and Cardiac. Also `fluid_ml` is never autofilled for hemodialysis or severe cardiac despite the spec saying it should.

**Files:**
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/GoalSelectorModal.tsx`
- Modify: `frontend/lib/nutritionCalculations.ts`
- Modify: `backend/app/Http/Controllers/RND/InterventionController.php`
- Modify: `backend/tests/Feature/RND/InterventionTest.php` (if exists, else create)

- [ ] **Step 1: Remove from GoalSelectorModal** — Delete the `fluid_restriction` entry from the `GOALS` array in `GoalSelectorModal.tsx`. Verify the modal shows 9 goals (was 10).

- [ ] **Step 2: Update nutritionCalculations.ts** — Remove the `fluid_restriction` case from `autofillPrescription()` and `GOAL_MICRO_FLAGS`. Add `fluid_ml` to the autofill output for:
  - `renal_diet` + `hemodialysis` → `fluid_ml: 750`
  - `renal_diet` + `peritoneal` → `fluid_ml: 1000` (conservative default; clinician adjusts)
  - `cardiac_diet` + `moderate` → `fluid_ml: 2000`
  - `cardiac_diet` + `severe` → `fluid_ml: 1500`
  All other goal/stage combos → `fluid_ml: 0` (unrestricted; 0 = "not set").

- [ ] **Step 3: Update InterventionController backend** — In `mapGoalTypeToConditions()`, remove the `'fluid_restriction'` case. Run tests.
  ```
  cd backend && php artisan test
  ```
  Expected: all 166+ tests pass.

- [ ] **Step 4: Commit**
  ```
  git commit -m "feat: remove fluid_restriction goal, wire fluid_ml autofill for renal/cardiac"
  ```

---

## Task 5: Water Tracking — Backend

**Goal:** Extract water content (USDA nutrient ID 1051) from USDA imports and store in `food_items.water_g`. This enables fluid tracking in meal plans for renal/cardiac patients.

**Files:**
- Create: `backend/database/migrations/xxxx_add_water_g_to_food_items.php`
- Modify: `backend/app/Models/FoodItem.php`
- Modify: `backend/app/Services/UsdaService.php`
- Modify: `backend/tests/Unit/UsdaServiceTest.php` (if exists)

- [ ] **Step 1: Write migration** — Add nullable `water_g` float column to `food_items`. Run migration.
  ```
  cd backend && php artisan migrate
  ```

- [ ] **Step 2: Add to FoodItem fillable** — Add `'water_g'` to the `$fillable` array in `FoodItem.php`.

- [ ] **Step 3: Update UsdaService** — In `UsdaService::import()`, where nutrients are mapped, add extraction of nutrient ID 1051 → `water_g`. Pattern matches how existing nutrients (protein/fat/carbs) are already extracted. No code needed in plan — follow existing pattern in the service.

- [ ] **Step 4: Write unit test** — Assert that after importing a food item that has USDA nutrient 1051, the resulting FoodItem has a non-null `water_g`. Use a mock/fake response or a known FDC ID.

- [ ] **Step 5: Re-seed** — Run `FoodItemsSeeder` again to backfill water_g for already-imported items (seeder skips existing by name, so no duplicates). Water will be null for items without USDA ID (manual entries).
  ```
  cd backend && php artisan db:seed --class=FoodItemsSeeder
  ```

- [ ] **Step 6: Run all tests**
  ```
  cd backend && php artisan test
  ```

- [ ] **Step 7: Commit**
  ```
  git commit -m "feat: add water_g to food_items, extract USDA nutrient 1051 on import"
  ```

---

## Task 6: Button Design System

**Goal:** Consolidate all interactive buttons to use `Button.tsx` variants. Eliminate ad-hoc `className` button soup. Add `ghost` (text-only, no border) and `icon` (square, icon-only) variants.

**Files:**
- Modify: `frontend/components/ui/Button.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx` (biggest offender)
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`

**Variant rules (document at top of Button.tsx):**
- `primary` — main positive action (Save, Apply, Generate, Add)
- `secondary` — neutral action (New Week, From Template, Save Template, Auto-Generate when not primary)
- `ghost` — cancel / text links / "Change Goal"
- `danger` — destructive (Delete Plan, Delete Template)
- `icon` — icon-only square button (trash icon on plan pills, edit pencil on meal items)

- [ ] **Step 1: Add `ghost` and `icon` variants** — In `Button.tsx`, add to the variants object:
  - `ghost`: no background, no border, zinc text, hover underline or zinc-100 bg
  - `icon`: `p-1.5 w-auto` override, no padding changes from className, same base focus ring

- [ ] **Step 2: Replace inline buttons in MealPlanSection** — Convert:
  - Cancel buttons → `Button variant="ghost"`
  - "Delete Plan" confirm → `Button variant="danger"`
  - "New Week" → `Button variant="secondary"`
  - "Auto-Generate" → `Button variant="secondary"` (visually matches New Week — same row, same weight)
  - "Save Template", "From Template" → `Button variant="secondary"`
  - Trash icons on plan pills → `Button variant="icon"` with red hover class override
  - Edit pencil, remove item → `Button variant="icon"`
  - "Add" inside picker → already uses `Button variant="primary"` ✓

- [ ] **Step 3: Replace inline buttons in intervention page.tsx** — "Set Goal" / "Change Goal" button → `Button variant="ghost"`. Save prescription button → `Button variant="primary"`.

- [ ] **Step 4: Visual check** — Open intervention page, verify all buttons follow the variant system. No raw `<button>` with long `className` chains remain in these two files.

- [ ] **Step 5: Commit**
  ```
  git commit -m "refactor: unify button variants (ghost + icon added), replace inline buttons in intervention page"
  ```

---

## Task 7: MacroTrackerBar + Micros Button Redesign

**Goal:** Remove green background from MacroTrackerBar. Move micros toggle to sit inline with the macro values (right side). Rename to `PlanStatsBar`. Manual "New Week" button sits visually level with "Auto-Generate".

**Files:**
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MacroTrackerBar.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx`

- [ ] **Step 1: Redesign MacroTrackerBar** — Change `bg-emerald-50 border-b border-emerald-100` to `bg-white border border-zinc-200 rounded-xl`. Remove `sticky top-0 z-10` — macro bar should NOT be sticky (plan grid is long; sticky macro bar blocks content). Remove `border-b` (it was a full-width bar style; now it's a card).

- [ ] **Step 2: Add `showMicros` and `onToggleMicros` props to MacroTrackerBar** — Accept `showMicros: boolean`, `onToggleMicros: () => void`, `hasMicros: boolean`. Render the `FlaskConical` micros toggle button inline at the right end of the bar. This is the ONE canonical micros button — remove the duplicate from the MealPlanSection header row.

- [ ] **Step 3: Update MealPlanSection** — Remove the standalone micros button from the header toolbar. Pass `showMicros`, `onToggleMicros`, `hasMicros={displayedMicros.length > 0}` into `MacroTrackerBar`. Verify only one micros button exists.

- [ ] **Step 4: Level "New Week" with "Auto-Generate"** — Both buttons in the header toolbar should be `Button variant="secondary"` with identical sizing. "Auto-Generate" is NOT primary (it's not more important than manual creation). Move them into a consistent button group at the right side of the header.

- [ ] **Step 5: Visual check** — Macro bar is white/zinc, numbers colored by status, micros toggle at far right of the bar. Header toolbar has consistent buttons.

- [ ] **Step 6: Commit**
  ```
  git commit -m "refactor: MacroTrackerBar white bg, inline micros toggle, level New Week vs Auto-Generate"
  ```

---

## Task 8: Intervention Tabs — Sticky + Overflow Fix

**Goal:** Make intervention tabs sticky so they stay visible while scrolling through the Food/Nutrient Delivery tab content. Fix tab bar from causing horizontal overflow on small screens.

**Files:**
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`

- [ ] **Step 1: Make tab bar sticky** — On the tab `<div>`, add `sticky top-0 z-20 bg-white`. The scrollable container is `<main className="flex-1 overflow-y-auto">` in `layout.tsx` — `sticky` works within this scroll context. Add `-mx-6 px-6 lg:-mx-8 lg:px-8` to stretch the bar edge-to-edge within the padded main.

- [ ] **Step 2: Fix tab overflow** — The tab bar currently has `overflow-x-auto`. On smaller screens this causes a horizontal scrollbar. Replace with `flex-wrap gap-0` so tabs wrap to a second line rather than scroll. `whitespace-nowrap` stays on individual tab buttons.

- [ ] **Step 3: Verify** — Scroll down in the Food/Nutrient Delivery tab. Confirm tabs stay visible at top. Resize to mobile — tabs wrap, no horizontal scrollbar appears.

- [ ] **Step 4: Commit**
  ```
  git commit -m "feat: sticky intervention tabs, wrap instead of h-scroll on small screens"
  ```

---

## Task 9: Responsive Layout — Sidebar Mobile Drawer

**Goal:** On mobile (< `md` breakpoint), sidebar becomes a slide-in overlay drawer triggered by a hamburger button in TopBar. On desktop (≥ `md`), behavior unchanged.

**Files:**
- Modify: `frontend/components/layout/Sidebar.tsx`
- Modify: `frontend/components/layout/TopBar.tsx`
- Modify: `frontend/app/(rnd)/layout.tsx`

- [ ] **Step 1: Add sidebar open state to layout** — In `layout.tsx`, add `const [sidebarOpen, setSidebarOpen] = useState(false)`. Pass `open={sidebarOpen}` and `onClose={() => setSidebarOpen(false)}` to `Sidebar`. Pass `onMenuClick={() => setSidebarOpen(true)}` to `TopBar`.

- [ ] **Step 2: Sidebar mobile behavior** — In `Sidebar.tsx`, accept `open` and `onClose` props. On `md+`, render normally (no change). On `< md`: render as `fixed inset-0 z-50` overlay — backdrop + slide-in panel from left. Use `translate-x-0` when open, `-translate-x-full` when closed. Backdrop is semi-transparent black that closes sidebar on click.

- [ ] **Step 3: TopBar hamburger** — In `TopBar.tsx`, accept `onMenuClick` prop. Render a `Menu` icon button (Lucide) that is only visible on `md:hidden`. Clicking fires `onMenuClick`.

- [ ] **Step 4: Verify no overflow** — On mobile viewport: no horizontal scroll on any page. Sidebar drawer slides in and out. On desktop: no change to existing behavior.

- [ ] **Step 5: Commit**
  ```
  git commit -m "feat: sidebar mobile drawer, TopBar hamburger toggle"
  ```

---

## Task 10: Responsive Layout — Content + Tables

**Goal:** No horizontal scroll on any page. Tables scroll within their container, not the full page. All flex/grid layouts reflow correctly on small screens.

**Files:**
- Modify: `frontend/app/(rnd)/layout.tsx`
- Modify: Any page with tables: `food-library/page.tsx`, `food-library/foods/[id]/page.tsx`, `food-library/recipes/[id]/page.tsx`, `ncp/patients/page.tsx` — check for `<table>` elements and wrap them.

- [ ] **Step 1: Add overflow guard to main** — In `layout.tsx`, on the `<main>` element, ensure `overflow-x-hidden` is set (it currently only has `overflow-y-auto`). Add `min-w-0` to the flex child wrapper.

- [ ] **Step 2: Audit tables** — Search for `<table` in the frontend. For each table found, wrap in `<div className="overflow-x-auto">` and add `min-w-[480px]` to the `<table>` itself so it scrolls within the container but doesn't overflow the page.
  ```
  grep -r "<table" frontend/app --include="*.tsx" -l
  ```

- [ ] **Step 3: Audit fixed widths** — Search for `w-[` or hardcoded pixel widths on layout-level elements. Replace any that exceed viewport width on mobile with responsive equivalents (`max-w-full`, `min-w-0`).

- [ ] **Step 4: Test on narrow viewport** — Resize browser to 375px wide. Scroll through: Dashboard, Patients list, Food Library, Intervention page. Confirm no horizontal scrollbar appears anywhere.

- [ ] **Step 5: Commit**
  ```
  git commit -m "fix: no horizontal overflow — table wrappers, overflow-x-hidden on main, min-w-0 guards"
  ```

---

## Task 11: Final Check + Tests

- [ ] **Step 1: Run full test suite**
  ```
  cd backend && php artisan test
  ```
  Expected: all tests pass (166+).

- [ ] **Step 2: Check for TypeScript errors**
  ```
  cd frontend && npx tsc --noEmit
  ```
  Fix any new type errors introduced by Task 6 (button prop changes).

- [ ] **Step 3: Update session handoff** — Update `memory/session-handoff.md` to mark all tasks complete and note water_g seeding needs a re-run of FoodItemsSeeder.

- [ ] **Step 4: Final commit**
  ```
  git commit -m "chore: UI/UX overhaul + bug fixes complete — tests passing"
  ```

---

## Self-Review

**Spec coverage check:**
- ✓ Delete plan bug → Task 1
- ✓ Auto-generate runtime error → Task 2
- ✓ Food picker CRUD → Task 3
- ✓ Remove fluid_restriction goal → Task 4
- ✓ Wire fluid_ml to renal/cardiac → Task 4
- ✓ Water tracking (USDA + food_items) → Task 5
- ✓ Button consistency / universal design → Task 6
- ✓ MacroTrackerBar redesign (no green bg) → Task 7
- ✓ Micros button beside macros → Task 7
- ✓ Two different micros buttons → Task 7 (removes duplicate)
- ✓ Sticky tabs → Task 8
- ✓ No horizontal scroll → Tasks 9 + 10
- ✓ Sidebar mobile → Task 9
- ✓ Tables on small screens → Task 10
- ✓ New Week vs Auto-Generate visual parity → Task 7
- ✓ TDD throughout → PHPUnit tests in Tasks 1, 2, 3, 5

**Not in scope (deferred):**
- Full button system rollout to Food Library / Food Service pages (Task 6 scoped to intervention page only; document the rules so future pages follow)
- Wiring patient age/sex from patient context into prescription autofill (separate task)
