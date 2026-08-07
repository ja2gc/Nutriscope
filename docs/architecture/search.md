# Search Architecture

This document describes NutriScope search behavior as of August 8, 2026. The design intentionally uses the existing database and small shared helpers. It does not require Laravel Scout, Elasticsearch, Meilisearch, or a new cache service.

## User-facing rules

- Full list pages show their normal first page when the search field is blank. This gives users useful recent or alphabetical records without a separate request.
- Search requests are delayed by 250 ms while the user is typing. The USDA dialog keeps its existing 420 ms delay because it calls an external service.
- Full pages remain paginated at 10 records.
- Compact ingredient, catalog, and procurement pickers show at most the five best matches.
- Empty compact pickers do not make a request before the user types. This is the fastest blank state and avoids presenting arbitrary clinical or food choices.
- Search controls use `frontend/components/ui/SearchInput.tsx`, including the search icon, clear action, loading state, accessible label, 44 px minimum height, and the existing warm/emerald visual language.
- Mobile Help and FSS reports use `mobile/components/SearchInput.tsx`, which keeps the same search, clear, loading, label, and 48 px touch-target behavior in React Native.

## Database search algorithm

The shared implementations are `backend/app/Support/Search/RankedSearch.php` for Eloquent queries and `backend/app/Support/Search/FuzzyText.php` for the pure word matcher used by both database and report-array search.

For a non-empty term it:

1. Trims whitespace, collapses repeated spaces, and converts the term to lowercase.
2. Escapes SQL wildcard characters so `%` and `_` typed by a user are treated as text.
3. Searches the configured columns using bound `LIKE` expressions.
4. Orders normal matches by relevance: exact field match, then field prefix, then substring. The configured column order breaks ties; normal page ordering is the final tie-breaker.
5. Only when normal search returns no rows, checks at most 500 records from the already-authorized and already-filtered query.
6. Compares words with edit distance, treating an adjacent letter swap as one edit. Words up to seven characters allow one edit; longer words allow two. Every typed word must have a close word in a candidate.
7. Returns close candidates in smallest-distance order, then stable ID order.

Examples:

- `Rice` ranks `Rice`, `Rice Cake`, then `Brown Rice`.
- `Chiken` can find `Chicken Breast`.
- `Procuremnt` can find `Monthly Procurement Summary`.

The typo fallback is deliberately conservative. It does not run if a normal match exists, does not scan more than 500 scoped rows, and does not guess beyond one or two edits. This keeps ordinary searches database-only and prevents surprising distant results.

## Report search

Saved report filters are applied in `backend/app/Http/Controllers/ReportController.php` before pagination. Supported query parameters are `search`, `status`, `type`, and `year`. This fixes the old archive behavior where the web page fetched an arbitrary page and removed non-archived rows afterward.

The Archived tab in `frontend/components/reports/ReportsBrowser.tsx` now sends `status=archived` and the search term through `frontend/services/reportService.ts`. A blank field shows the newest archived reports; a term searches report title and type.

Renderable report instances are arrays produced by the existing report sources, so they cannot use the Eloquent helper directly. `ReportController::filterReportInstances()` applies the same substring-first, bounded-edit-distance behavior to instance labels before pagination. The report browser exposes this search for entity-based reports such as patients, purchase orders, menu plans, and NCP records. Period reports retain their year control because a text box would add little value.

## Search coverage

| Area | Search fields | Blank ordering | Implementation |
|---|---|---|---|
| Food library | food or recipe name | alphabetical, 10/page | `RND/FoodItemController.php`, `RND/RecipeController.php` |
| Patients | display/name parts, physician, ward, hospital number | newest, 10/page | `RND/PatientController.php` |
| Users | display/name parts, email | name, 10/page | `Admin/UserController.php` |
| FSS catalog and pickers | item name | alphabetical; pickers top 5 | `FSS/FsItemController.php`, `frontend/services/fsCatalogService.ts` |
| FSS foods/recipes | recipe name and category filter | alphabetical, 10/page | `FSS/FoodServiceRecipeController.php` |
| Suppliers | supplier name | alphabetical, 10/page | `FSS/SupplierController.php` |
| Saved reports | title, type, plus status/type/year filters | newest, 10/page | `ReportController::index()` |
| Report records | generated instance label | source order, 10/page | `ReportController::instances()` |
| Audit actor picker | name fields | name, 10/page with load-more | `Admin/AuditActorController.php`, `AuditActorFilter.tsx` |
| Help | title, description, keywords, steps | all role-visible topics | client-side normalized substring matching |
| USDA | external USDA query | no request until two characters | `RND/UsdaController.php`, 420 ms debounce |

The FSS mobile Reports screen sends the same server-side archived status and search filters through `mobile/lib/reports.ts`; it no longer filters a paginated response on the device.

`FSS/InventoryController::rows()` is a legacy compatibility endpoint with cached SQL substring matching. Current inventory and catalog screens use `/api/fss/fs-items/catalog`, which uses the shared ranked search. New callers should use the catalog endpoint.

## Adding search to another page

1. Add `search` to the request. `PaginatedRequest` already validates it as an optional string up to 100 characters.
2. Apply authorization and structured filters first.
3. Call `RankedSearch::apply($query, $term, ['most_important_column', ...])` before default ordering and pagination.
4. Use `SearchInput` on web pages and `useDebouncedValue` when the page does not already debounce requests.
5. Use 10 results for full pages and five for compact pickers.
6. Add feature tests for exact/prefix/substring ordering, one realistic typo, authorization scope, and filters-before-pagination.

Do not add a separate search server until measured data volume or query timing shows this bounded database approach is insufficient. If growth requires a change, keep the API and `SearchInput` contract stable and replace only the backend implementation.

## Tests and key references

- `backend/tests/Feature/FoodItemControllerTest.php`: relevance order and typo fallback.
- `backend/tests/Feature/ReportControllerTest.php`: report filtering before pagination and report-title typo fallback.
- `frontend/components/ui/SearchInput.test.tsx`: shared field accessibility, loading, and clear states.
- `frontend/app/(rnd)/food-service/stock-retirement-contract.test.ts`: compact catalog default remains five.
