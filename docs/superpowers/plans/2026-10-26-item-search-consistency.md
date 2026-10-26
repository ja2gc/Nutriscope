# Item Search Consistency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Make active NCP and Food Service item pickers use the existing paginated MenuItemPicker interaction and domain filters.

**Architecture:** Extend the shared picker layout and reuse existing Food Library and Food Service catalog APIs. Keep clinical and operational catalogs separate; adapt each caller to load the first page before typing and continue pagination for query and filter changes.

**Tech Stack:** Next.js 16, React 19, TypeScript, Tailwind CSS 4, Vitest.

**Spec:** 2026-10-26-item-search-consistency-design.md

## Global Constraints

- Use the existing picker and controls; do not create a new visual design.
- NCP category options are All, Meals, Snacks, and Carbs, mapped to all, meal, snack, and carb.
- Food Service grocery options are All, Non-auto grocery, and Auto grocery, mapped to the existing catalog grocery filter.
- Keep Food Recall source choice as Ingredients or Menu Items; do not add category filters there.
- Do not add item date filters, report filters, backend schema changes, or catalog merging.
- Preserve existing item IDs, selection payloads, loading/error/empty states, and keyboard focus behavior.
- Keep search and pagination reachable on narrow viewports; scroll long results inside the picker.

## Review Focus

1. Opening a picker with an empty query must load the first page; changing a query, category, or grocery filter must restart at page 1.
2. A page with no matching items must show the existing empty state and keep filters and search usable.
3. A failed search must show a recoverable error without losing the selected menu cell or ingredient row.
4. Menu Calendar combines two paginated sources; uneven result counts must not hide later recipe or inventory results.
5. At 320 CSS px and 200% zoom, search, result scrolling, pagination, focus, and close controls must remain usable.

---

### Task 1: Keep the shared picker usable at narrow heights

**Files:**
- Modify: frontend/components/ncp/MenuItemPicker.tsx
- Test: frontend/components/ncp/MenuItemPicker.test.tsx

**Interfaces:**
- Keep the existing MenuItemPicker props and filters slot.
- Keep Pagination as the picker pagination control.

- [x] **Step 1: Add the failing test** keeps search and pagination outside the scrollable results and assert the dialog is height-constrained, search/filter controls stay outside the results scroller, and pagination stays reachable.
- [x] **Step 2: Run the focused test** with npx vitest run components/ncp/MenuItemPicker.test.tsx and confirm it fails on the missing layout behavior.
- [x] **Step 3: Implement the minimal layout change** so the dialog stays within the viewport, keeps search/filter and pagination reachable, and scrolls results internally.
- [x] **Step 4: Re-run the focused test** and confirm it passes.

### Task 2: Add NCP category filters to plan and template pickers

**Files:**
- Modify: frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx
- Test: frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.test.tsx
- Modify: frontend/app/(rnd)/food-library/_components/MealTemplatesTab.tsx
- Test: frontend/app/(rnd)/food-library/_components/MealTemplatesTab.test.tsx

**Interfaces:**
- Consume fetchRecipes(search, category, page, perPage).
- Render filters through the existing MenuItemPicker.filters slot and ButtonFilterGroup.

- [x] **Step 1: Add failing tests** filters intervention menu items by category and filters template menu items by category; assert options All/Meals/Snacks/Carbs, the matching API category, and page 1 after category/query changes.
- [x] **Step 2: Run the two focused tests** with npx vitest run 'app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.test.tsx' 'app/(rnd)/food-library/_components/MealTemplatesTab.test.tsx' and confirm each fails for the missing category behavior.
- [x] **Step 3: Add category state and filter controls** to both pickers; pass API categories all, meal, snack, or carb and reset to page 1 when filters change.
- [x] **Step 4: Re-run both focused tests** with the same command and confirm they pass.

### Task 3: Paginate Food Library ingredient selection

**Files:**
- Modify: frontend/app/(rnd)/food-library/recipes/new/page.tsx
- Test: frontend/app/(rnd)/food-library/recipes/new/page.test.tsx
- Modify: frontend/app/(rnd)/food-library/recipes/[id]/page.tsx
- Test: frontend/app/(rnd)/food-library/recipes/[id]/page.test.tsx

**Interfaces:**
- Consume fetchFoodItems(search, "all", page) and its pagination metadata.
- Reuse MenuItemPicker with itemLabel="Ingredient"; preserve the selected FoodItem and its serving-unit behavior.

- [x] **Step 1: Add failing tests** loads paginated ingredients when opened, selects a paginated ingredient into the active row, and recovers from an ingredient search error; assert the empty-query page 1 request, next-page request, preserved unit/quantity behavior, and retained row after error.
- [x] **Step 2: Run both focused tests** with npx vitest run 'app/(rnd)/food-library/recipes/new/page.test.tsx' 'app/(rnd)/food-library/recipes/[id]/page.test.tsx' and confirm the new picker behavior is missing.
- [x] **Step 3: Replace each five-result inline dropdown** with the shared picker and page state; keep quantity, units, nutrient preview, and row selection behavior intact.
- [x] **Step 4: Re-run both focused tests** with the same command and confirm they pass.

### Task 4: Paginate Food Service ingredient selection

**Files:**
- Modify: frontend/app/(rnd)/food-service/recipes/new/page.tsx
- Test: frontend/app/(rnd)/food-service/recipes/new/page.test.tsx (create)
- Modify: frontend/app/(rnd)/food-service/recipes/[id]/page.tsx
- Test: frontend/app/(rnd)/food-service/recipes/[id]/page.test.tsx (create)
- Modify: frontend/components/foodservice/MenuSlotRecipePage.tsx
- Test: frontend/components/foodservice/MenuSlotRecipePage.test.tsx

**Interfaces:**
- Consume listCatalog("ingredient", page, search, grocery); default page size remains 10.
- Reuse MenuItemPicker and ButtonFilterGroup; preserve each form's catalog item, unit, and cost behavior.

- [x] **Step 1: Add failing tests** loads inventory ingredients before typing and filters inventory ingredients by grocery behavior; assert initial results, the selected page/filter request, and selection into the correct row.
- [x] **Step 2: Run the focused tests** with npx vitest run 'app/(rnd)/food-service/recipes/new/page.test.tsx' 'app/(rnd)/food-service/recipes/[id]/page.test.tsx' components/foodservice/MenuSlotRecipePage.test.tsx and confirm the existing five-result or unfiltered picker behavior fails the new assertions.
- [x] **Step 3: Replace the inline dropdowns** in create/edit screens with the shared picker; add grocery filters to these forms and the existing MenuSlot picker.
- [x] **Step 4: Re-run the focused tests** with the same command and confirm they pass.

### Task 5: Paginate Menu Calendar item search

**Files:**
- Modify: frontend/services/menuCycleService.ts
- Test: frontend/services/menuCyclePickerPagination.test.ts
- Modify: frontend/services/fsCatalogService.ts
- Test: frontend/services/fsCatalogService.test.ts (create)
- Modify: frontend/app/(rnd)/food-service/menu-cycle/page.tsx
- Test: frontend/app/(rnd)/food-service/menu-cycle/page.test.tsx

**Interfaces:**
- Change listRecipeOptions(search, page, perPage) to return { data, meta } from the existing Food Service recipe endpoint and throw on request failure.
- Extend listCatalog(kind, page, search, grocery, perPage = 10) so Menu Calendar can request 5 items per source; keep other callers at 10.
- Replace listFsItemOptions with listCatalog in Menu Calendar, then remove the unused helper and FsItemOption type.
- Keep the picker result IDs namespaced as recipe:<id> and item:<id>.

- [x] **Step 1: Add failing service tests** requests the requested recipe page and forwards catalog page size and grocery filter; assert request query parameters and returned metadata.
- [x] **Step 2: Run the service tests** with npx vitest run services/menuCyclePickerPagination.test.ts services/fsCatalogService.test.ts and confirm current APIs return only first-page arrays or omit the requested page size.
- [x] **Step 3: Update the services** to return existing pagination metadata and support the required page size without changing other callers' defaults.
- [x] **Step 4: Add failing page tests** loads both menu sources before typing and keeps later results reachable when source totals differ; assert empty-query calls, filter behavior, page resets, and the maximum source page count.
- [x] **Step 5: Run the page test** with npx vitest run 'app/(rnd)/food-service/menu-cycle/page.test.tsx' and confirm the current query-only five-result picker fails those assertions.
- [x] **Step 6: Load both sources when the picker opens**, merge the current pages, keep recipes independent of grocery filters, and set combined total/page metadata so every source page remains reachable.
- [x] **Step 7: Re-run service and page tests** with npx vitest run services/menuCyclePickerPagination.test.ts services/fsCatalogService.test.ts 'app/(rnd)/food-service/menu-cycle/page.test.tsx' and confirm they pass.

### Task 6: Paginate Procurement item selection

**Files:**
- Modify: frontend/app/(rnd)/food-service/procurement/page.tsx
- Test: frontend/app/(rnd)/food-service/procurement/page.test.tsx
- Modify: frontend/services/fsCatalogService.ts

**Interfaces:**
- Consume listCatalog(kind, page, search, grocery) and metadata.
- Reuse MenuItemPicker; keep Non-auto grocery as the initial Food list filter and omit grocery filters for supply lists.

- [x] **Step 1: Add failing tests** loads initial food suggestions with grocery filters and loads supply items without grocery filters; assert page 1, filter choices, next page, and selected-item cost/vendor defaults.
- [x] **Step 2: Run the Procurement page test** with npx vitest run 'app/(rnd)/food-service/procurement/page.test.tsx' and confirm its five-result inline search fails the pagination/filter assertions.
- [x] **Step 3: Replace the inline search dropdown** with MenuItemPicker, keeping add-item quantity, price, vendor, and list-save behavior unchanged. Remove searchCatalog if no caller remains after this task.
- [x] **Step 4: Re-run the Procurement page test** with the same command and confirm it passes (33 tests).

### Task 7: Verify the full web search flow

**Files:**
- No additional source files.

- [x] Run affected tests and the full frontend suite (`npx vitest run --maxWorkers=2`).
- [x] Run `npx tsc --noEmit`, `npm run lint`, and `npm run build` from frontend/.
- [x] Use the built-in browser on the local shared-picker fixture at 320×640, 375×812, 1280×800, and the shortest available 240×320 viewport; check search, long-list scrolling, pagination, filters, keyboard focus, empty state, and the constrained-height layout. The Playwright CLI could not bootstrap because npm registry access was denied, and the browser viewport tool clamps below 240px, so actual 200% browser zoom could not be set; 240×320 was used as the closest short-height check.
- [x] Re-read this plan and the design spec; report the unverified actual 200% browser-zoom scenario.
