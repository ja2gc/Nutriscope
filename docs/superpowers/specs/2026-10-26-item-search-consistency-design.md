# Item Search Consistency Design

## Intent

Make item selection behave consistently across Nutrition Care and Food Service. Use the Intervention Plan and Food Library Templates pickers as the visual and interaction reference: show results before typing, search all pages, and use the existing `MenuItemPicker` dialog.

## Goals

- Extend the existing picker to active Food Library and Food Service item-selection searches that currently show only a few matches or wait for a query.
- Preserve server pagination and search behavior through existing APIs.
- Add NCP category filters: All, Meals, Snacks, and Carbs.
- Add Food Service grocery filters: All, Non-auto grocery, and Auto grocery wherever users select inventory ingredients or items.
- Keep the Food Recall Calculator source filter for Ingredients and Menu Items.
- Keep search, results, and pagination usable on narrow and wide web viewports. Keep the picker within the viewport and let long result lists scroll.

## Non-goals

- Do not add month/year filters to item search or change the existing DatePicker.
- Do not add report filters in this task.
- Do not merge the clinical Food Library and Food Service inventory catalogs.
- Do not introduce a new picker design or change item persistence, calculations, or backend contracts.

## Approaches

1. **Reuse `MenuItemPicker` and its existing filter slot (chosen by user direction).** Extend current callers and use the existing paginated Food Library and Food Service APIs. This matches both reference screens and keeps catalog ownership separate.
2. Keep each inline search and add pagination locally. This retains current layouts but leaves the search interaction inconsistent.
3. Create a new universal picker and search API. This duplicates existing UI and adds backend surface without a demonstrated need.

## Constraints and decisions

- NCP menu-item category values use existing API values `meal`, `snack`, and `carb`; display labels are Meals, Snacks, and Carbs. All is the default.
- Food Service grocery filters use the existing `include_in_generated_lists` catalog behavior. Grocery filters apply only to Food Service inventory items, not clinical Food Library items. Supplies do not use grocery filters.
- The Procurement item picker keeps its existing initial Non-auto grocery suggestions. Users can select All or Auto grocery.
- Food Recall continues to choose between Ingredients and Menu Items through its existing source filter. Do not add menu-category filters there.
- Use existing pagination metadata and public IDs. Preserve loading, empty, error, keyboard, focus-return, and selection behavior.
- DatePicker already provides month/year selection with a current-year-relative range. It is outside this item-search change.

## Scope map

- Intervention Plan Add Menu Item and Food Library Templates Add item: add NCP category filters while retaining existing paginated search.
- NCP and Food Library ingredient-selection flows: use the shared picker and paginated Food Library ingredient search.
- Food Service recipe ingredient selection, Menu Calendar item selection, and Procurement list item selection: use the shared picker and paginated inventory search with grocery filters.
- Existing Food Library lists, Food Service Inventory lists, Food Recall source selection, and other already-paginated searches remain on their current workflows unless responsive checks find a picker defect.

## Verification

Add focused tests for initial results, query pagination, filter changes, and selection in affected flows. Check the shared picker at narrow and desktop web sizes, including loading, empty, and long-result states; verify keyboard use and scrolling.
