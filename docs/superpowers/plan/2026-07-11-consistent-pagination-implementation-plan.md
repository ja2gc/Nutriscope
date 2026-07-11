# Consistent Pagination Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply server-side pagination to every growing NutriScope collection while giving all web tables one footer design and all mobile lists one incremental-loading experience.

**Architecture:** Laravel 13 length-aware paginators provide one `data` / `links` / `meta` contract. Next.js services and proxy routes preserve that contract for numbered web pagination; React Native consumes the same page API through TanStack Query infinite queries. Bounded detail collections remain unpaginated.

**Tech Stack:** Laravel 13.11, PHP 8.4, PHPUnit 12, Next.js 16, React 19, TypeScript 5, Vitest 4, Tailwind CSS, Expo 54, React Native 0.81, TanStack Query 5.

**Design spec:** `docs/superpowers/specs/2026-07-11-consistent-pagination-design.md`

---

## Complete File Map

Every file below is referenced because implementation changes it, tests behavior it owns, or verifies a pagination-sensitive contract.

### Shared Backend Infrastructure

- Create `backend/app/Http/Requests/Concerns/HasPaginationRules.php`: central `page`/`per_page` rules and validated page-size helper; prevents controller-specific casts and silent clamping.
- Create `backend/tests/Unit/Http/Requests/HasPaginationRulesTest.php`: proves default and maximum page-size behavior independently of domain endpoints.
- Verify `backend/routes/api.php`: no new collection routes needed; confirms shared notification and role-scoped RND/Admin/FSS routes still point to changed controllers.

### Backend Growing Collections

- Create `backend/app/Http/Requests/RND/IndexNotificationsRequest.php`: validates notification pagination and optional `read`/`type` filters.
- Modify `backend/app/Http/Controllers/RND/NotificationController.php:index`: replace unbounded `get()`, attach source UUIDs to paginator collection, retain separate unread aggregate.
- Verify `backend/app/Http/Resources/NotificationResource.php`: paginated rows must preserve public UUID and deep-link source UUID fields.
- Create `backend/tests/Feature/NotificationPaginationTest.php`: contract, boundaries, filters, ordering, authorization, and UUID coverage.
- Extend `backend/tests/Feature/NotificationAccessTest.php`: shared Admin/RND/FSS route access must survive request type change.
- Extend `backend/tests/Feature/AdminSystemTest.php`: existing notification list assertions must target paginated `data` and metadata.

- Create `backend/app/Http/Requests/FSS/IndexPurchaseOrdersRequest.php`: validates `page`, `per_page`, `shopping_list_id`, status, and lifecycle filters.
- Modify `backend/app/Http/Controllers/FSS/PurchaseOrderController.php:index`: paginate filtered purchase orders and add deterministic ID tie-breaker.
- Verify `backend/app/Http/Resources/PurchaseOrderResource.php`: preserve nested PO summary representation inside resource pagination.
- Create `backend/tests/Feature/PurchaseOrderPaginationTest.php`: page contract, filtered totals, deterministic non-overlap, UUIDs, and FSS/RND access.
- Extend `backend/tests/Feature/FoodServiceOpsTest.php`: existing purchase-order index workflow assertions must consume paginated responses.

- Create `backend/app/Http/Requests/FSS/IndexMenuCyclesRequest.php`: validates pagination plus status/search/active lookup filters.
- Modify `backend/app/Http/Controllers/FSS/MenuCycleController.php:index`: paginate cycles, support active lookup without fetching all pages, and stabilize ordering.
- Verify `backend/app/Http/Resources/MenuCycleResource.php`: preserve cycle days/counts used by web and mobile.
- Create `backend/tests/Feature/MenuCyclePaginationTest.php`: page contract, active/status filters, order, UUIDs, and access.
- Extend `backend/tests/Feature/FssPermissionTest.php`: role access remains correct for paginated index.
- Extend `backend/tests/Feature/FoodServiceOpsTest.php`: existing menu-cycle list assertions consume `meta` without changing workflows.

- Create `backend/app/Http/Requests/FSS/IndexSuppliersRequest.php`: validates pagination and bounded remote search.
- Modify `backend/app/Http/Controllers/FSS/SupplierController.php:index`: replace `Supplier::all()` with searchable stable pagination.
- Verify `backend/app/Http/Resources/SupplierResource.php`: supplier UUID contract remains unchanged.
- Create `backend/tests/Feature/SupplierPaginationTest.php`: contract, search totals, order, UUIDs, validation, and permissions.

- Create `backend/app/Http/Requests/FSS/IndexFsItemCatalogRequest.php`: validates pagination, `kind`, and `search`.
- Create `backend/app/Http/Resources/FsItemCatalogResource.php`: moves catalog mapping out of controller so Resource collections can paginate it.
- Modify `backend/app/Http/Controllers/FSS/FsItemController.php:catalog`: paginate catalog query before transformation; preserve active/kind/search/default-supplier behavior.
- Create `backend/tests/Feature/FsItemCatalogPaginationTest.php`: contract, filters, deterministic order, UUIDs, and default supplier fields.
- Extend `backend/tests/Feature/FsItemCatalogTest.php`: creation/update behavior remains compatible with paginated listing.

- Create `backend/app/Http/Requests/FSS/IndexBudgetLedgerRequest.php`: validates pagination, fiscal year, and source filter.
- Create `backend/app/Http/Resources/BudgetLedgerResource.php`: owns ledger row mapping formerly performed after `get()`.
- Modify `backend/app/Http/Controllers/FSS/BudgetController.php:ledger`: return paginated Resource collection for both FSS and Admin routes.
- Create `backend/tests/Feature/BudgetLedgerPaginationTest.php`: contract, fiscal/source totals, signed amounts, relation labels, order, and role parity.
- Extend `backend/tests/Feature/BudgetLedgerTest.php`: manual adjustments still appear through new response shape.
- Extend `backend/tests/Feature/AdminBudgetReadOnlyTest.php`: Admin read-only ledger remains authorized and paginated.

- Create `backend/app/Http/Requests/IndexReportsRequest.php`: validates archive pagination and report type/date filters.
- Create `backend/app/Http/Requests/IndexReportInstancesRequest.php`: validates instance pagination and period/entity filters.
- Create `backend/app/Http/Resources/ReportInstanceResource.php`: standard row shape for paginated report instances.
- Modify `backend/app/Http/Controllers/ReportController.php:index`: paginate archived reports after role/owner filters.
- Modify `backend/app/Http/Controllers/ReportController.php:instances`: pass validated pagination to report browser and return standard contract.
- Verify `backend/app/Http/Resources/ReportResource.php`: archive rows retain download/view metadata and UUID identifiers.
- Modify `backend/app/Services/Reports/Contracts/InstanceSource.php`: replace array-only contract with a paginator-aware return contract.
- Modify `backend/app/Services/Reports/Instances/EntityInstanceSource.php`: apply filters before DB pagination and add stable ID ordering.
- Modify `backend/app/Services/Reports/Instances/PeriodInstanceSource.php`: slice generated period buckets into a `LengthAwarePaginator`.
- Modify `backend/app/Services/Reports/Instances/SingletonInstanceSource.php`: wrap natural singleton output in same response contract.
- Modify `backend/app/Services/Reports/ReportBrowser.php`: forward page size/page/filter values to selected instance source.
- Create `backend/tests/Feature/ReportPaginationTest.php`: archive and instance contracts, boundaries, filters, role scope, and deterministic order.
- Extend `backend/tests/Feature/ReportControllerTest.php`: CRUD/archive expectations use paginated index.
- Extend `backend/tests/Feature/ReportsBrowseTest.php`: each instance-source type honors paging and filters.
- Extend `backend/tests/Feature/AdminReportsTest.php`: Admin subset and PHI restrictions remain intact.
- Extend `backend/tests/Feature/FssReportScopeTest.php`: mobile FSS accomplishment archive remains owner-scoped.

### Backend Existing-Pagination Consistency

- Create `backend/app/Http/Requests/RND/IndexPatientsRequest.php`: shared pagination rules plus patient search/status validation.
- Modify `backend/app/Http/Controllers/RND/PatientController.php:index`: validated input, `withQueryString()`, and `created_at/id` order.
- Create `backend/app/Http/Requests/RND/IndexFoodItemsRequest.php`: page/search/category/allergen validation.
- Modify `backend/app/Http/Controllers/RND/FoodItemController.php:index`: validated input, preserved links, stable name/ID order.
- Create `backend/app/Http/Requests/RND/IndexRecipesRequest.php`: page/search/category validation.
- Modify `backend/app/Http/Controllers/RND/RecipeController.php:index`: validated input, preserved links, stable name/ID order.
- Create `backend/app/Http/Requests/Announcement/IndexAnnouncementsRequest.php`: shared role-safe announcement filters and pagination rules.
- Modify `backend/app/Http/Controllers/RND/AnnouncementController.php:index`: validated input, stable pinned/date/ID order, preserved links.
- Modify `backend/app/Http/Controllers/Admin/AnnouncementController.php:index`: use same request contract and ordering as RND board.
- Create `backend/app/Http/Requests/Admin/IndexAuditLogsRequest.php`: moves existing inline filter rules and adds validated `page`.
- Modify `backend/app/Http/Controllers/Admin/AuditLogController.php:index`: standard 15 default, preserved filters, stable date/ID order.
- Create `backend/app/Http/Requests/Admin/IndexUsersRequest.php`: validates page/search/role/status.
- Modify `backend/app/Http/Controllers/Admin/UserController.php:index`: replace full user array with server-filtered pagination.
- Create `backend/app/Http/Requests/FSS/IndexInventoryRowsRequest.php`: validates page/search/type/status filters.
- Modify `backend/app/Http/Controllers/FSS/InventoryController.php:rows`: use `LengthAwarePaginator`, complete links/meta, deterministic union ordering, preserve top-level `stats`.
- Create `backend/app/Http/Requests/FSS/IndexFoodServiceRecipesRequest.php`: validates page/search/category.
- Modify `backend/app/Http/Controllers/FSS/FoodServiceRecipeController.php:index`: replace custom metadata with Resource pagination and stable order.
- Create `backend/tests/Feature/ExistingPaginationConsistencyTest.php`: common contract, invalid input, stable ordering, filter links, and defaults for all endpoints above.
- Extend `backend/tests/Feature/PatientFeatureTest.php`: patient workflow expectations remain valid.
- Extend `backend/tests/Feature/FoodItemControllerTest.php`: food list boundaries and resource contract.
- Extend `backend/tests/Feature/RecipeControllerTest.php`: recipe list boundaries and resource contract.
- Extend `backend/tests/Feature/AnnouncementFeatureTest.php`: RND/Admin boards preserve pinned order and visibility.
- Extend `backend/tests/Feature/AdminAuditLogTest.php`: filtered links and 15-row default.

### Shared Web Contract And UI

- Create `frontend/types/pagination.ts`: sole `PaginationLinks`, `PaginationMeta`, `PaginatedResponse<T>`, and empty metadata definitions.
- Modify `frontend/components/ui/Pagination.tsx`: shared count range, page-size select, page label, 44 px icon controls, loading/error/retry, and one-page footer.
- Create `frontend/components/ui/Pagination.test.tsx`: server-rendered structure, range text, disabled states, labels, one-page visibility, and retry state.
- Modify `frontend/services/patientService.ts`: import shared response type and remove duplicate metadata.
- Modify `frontend/services/foodLibraryService.ts`: import shared generic response and remove duplicate definition.
- Modify `frontend/services/foodDatabaseService.ts`: use shared response contract for legacy selectors.
- Modify `frontend/services/inventoryService.ts`: import/re-export shared metadata while preserving `stats` extension.
- Modify `frontend/services/announcementService.ts`: use complete shared metadata and fallback.
- Modify `frontend/services/auditLogService.ts`: stop importing metadata from inventory service.

### Web Growing Collections

- Modify `frontend/services/notificationService.ts`: add paginated fetch parameters/result and separate unread-count request.
- Extend `frontend/services/notificationService.test.ts`: query construction, response parsing, preferences, and unread aggregate.
- Modify `frontend/app/(rnd)/notifications/page.tsx`: server page state, shared footer, preserved rows while loading, and mutations that refetch current page/count.
- Modify `frontend/app/admin/notifications/page.tsx`: same pagination behavior and visual structure as RND.
- Verify `frontend/components/layout/TopBar.tsx`: header uses unread aggregate rather than current page length.
- Modify `frontend/app/api/notifications/route.ts`: forward complete request search params.
- Create `frontend/app/api/notifications/unread-count/route.ts`: proxy existing Laravel aggregate endpoint.
- Create `frontend/app/api/notifications/notification-routes.test.ts`: query forwarding and unread-count target.

- Modify `frontend/services/procurementService.ts`: `listPurchaseOrders` accepts page/filter values and returns `PaginatedResponse<PurchaseOrder>`.
- Modify `frontend/app/(rnd)/food-service/procurement/page.tsx`: paginate only PO event table; retain bounded shopping-list/PO detail tables; remote supplier selector uses explicit page/search contract.
- Verify `frontend/app/api/fss/purchase-orders/route.ts`: existing search forwarding remains intact.
- Create `frontend/app/(rnd)/food-service/procurement/pagination.test.ts`: source-contract test for shared footer, page reset, and unchanged bounded detail tables.

- Modify `frontend/services/menuCycleService.ts`: paginated cycle list and explicit active lookup filter.
- Modify `frontend/app/(rnd)/food-service/menu-cycle/page.tsx`: cycle table uses server page and shared footer; active lookup does not scan one returned page.
- Verify `frontend/app/api/fss/menu-cycles/route.ts`: complete query forwarding remains intact.
- Extend `frontend/app/(rnd)/food-service/menu-cycle/served-population-ui.test.ts`: list pagination does not break served-population controls.
- Create `frontend/app/(rnd)/food-service/menu-cycle/pagination.test.ts`: cycle page/filter contract and shared footer use.

- Modify `frontend/services/supplierService.ts`: server page/search response.
- Modify `frontend/components/foodservice/SuppliersPanel.tsx`: debounced remote search, page reset, shared footer, and consistent loading/error states.
- Modify `frontend/app/api/fss/suppliers/route.ts`: forward search and pagination parameters on GET.
- Create `frontend/components/foodservice/SuppliersPanel.test.tsx`: search/page reset, footer metadata, and empty/error behavior.

- Modify `frontend/services/fsCatalogService.ts`: server page/kind/search response.
- Modify `frontend/app/(rnd)/food-service/inventory/page.tsx`: remove client filtering of full catalog and use shared footer.
- Verify `frontend/app/api/fss/fs-items/catalog/route.ts`: existing query forwarding remains complete.
- Create `frontend/app/(rnd)/food-service/inventory/catalog-pagination.test.ts`: request params, page reset, shared footer, and supplier selector behavior.

- Modify `frontend/services/budgetService.ts`: ledger returns paginated response and accepts page/per-page.
- Modify `frontend/components/budget/BudgetPageShell.tsx`: shared footer and page reset on year/source changes for both roles.
- Verify `frontend/app/(rnd)/food-service/budget/page.tsx`: RND entry point still configures shared shell.
- Verify `frontend/app/admin/budget/page.tsx`: Admin entry point remains read-only while sharing pagination.
- Verify `frontend/app/api/fss/budgets/ledger/route.ts`: FSS proxy already forwards full query.
- Verify `frontend/app/api/admin/budgets/ledger/route.ts`: Admin proxy already forwards full query.
- Extend `frontend/app/api/fss/budgets/budget-routes.test.ts`: add page/per-page forwarding assertion.
- Extend `frontend/app/api/admin/budgets/budget-routes.test.ts`: add page/per-page forwarding assertion.
- Extend `frontend/app/(rnd)/food-service/budget/placement.test.ts`: shared footer remains inside ledger surface.
- Extend `frontend/app/admin/budget/page.test.ts`: Admin uses same paginated shell.

- Modify `frontend/services/reportService.ts`: archive and instance methods return shared paginated response and send page/filter params.
- Modify `frontend/components/reports/ReportsBrowser.tsx`: remove client `slice()`, add independent server page state for instances/archive, shared footer, and filter resets.
- Verify `frontend/app/(rnd)/reports/page.tsx`: RND wrapper remains unchanged except propagated contract if types require it.
- Verify `frontend/app/admin/reports/page.tsx`: Admin wrapper remains unchanged except propagated contract if types require it.
- Modify `frontend/app/api/rnd/reports/route.ts`: forward archive query parameters.
- Modify `frontend/app/api/admin/reports/route.ts`: forward archive query parameters.
- Verify `frontend/app/api/rnd/reports/[id]/instances/route.ts`: already forwards filters and pagination.
- Verify `frontend/app/api/admin/reports/[id]/instances/route.ts`: already forwards filters and pagination.
- Create `frontend/components/reports/ReportsBrowser.pagination.test.ts`: no client slicing, independent pages, filter reset, and shared footer.
- Extend `frontend/app/admin/reports/page.test.ts`: Admin contract still delegates to shared browser.

### Web Existing-Pagination Consistency

- Modify `frontend/app/(rnd)/ncp/patients/page.tsx`: shared footer loading/page-size/reset behavior.
- Modify `frontend/app/api/patients/route.ts`: forward `per_page` with existing filters/page.
- Modify `frontend/app/(rnd)/food-library/page.tsx`: foods and recipes use complete shared metadata/footer behavior.
- Verify `frontend/app/api/rnd/food-items/route.ts`: full query forwarding already correct.
- Verify `frontend/app/api/rnd/recipes/route.ts`: full query forwarding already correct.
- Modify `frontend/app/(rnd)/food-service/recipes/page.tsx`: remove local metadata fallback and use shared contract/footer.
- Modify `frontend/app/api/fss/food-service-recipes/route.ts`: forward page/search/category.
- Modify `frontend/components/announcements/AnnouncementsBoard.tsx`: preserve rows while loading, show one-page footer, and keep deep-link behavior.
- Modify `frontend/app/api/announcements/route.ts`: forward RND page/per-page.
- Verify `frontend/app/api/admin/announcements/route.ts`: Admin forwarding already complete.
- Modify `frontend/app/admin/audit-logs/page.tsx`: import shared metadata, use shared loading/error/page-size behavior, and replace the full user preload with debounced paginated actor search that retains the selected UUID.
- Verify `frontend/app/api/admin/audit-logs/route.ts`: full filter/paging forwarding already correct.
- Modify `frontend/services/adminUserService.ts`: return server pagination instead of stripping metadata.
- Modify `frontend/app/admin/users/page.tsx`: remove client slicing and send server search/role/status/page.
- Verify `frontend/app/api/admin/users/route.ts`: query forwarding already complete.
- Create `frontend/app/pagination-consistency.test.ts`: source-contract coverage that every full table imports shared footer and no migrated screen slices full arrays.

### Shared Mobile Contract And UI

- Create `mobile/lib/pagination.ts`: shared response types, `MOBILE_PAGE_SIZE = 20`, next-page resolver, ID dedupe, page mapping, and page-1 reset helpers.
- Create `mobile/lib/pagination.test.cjs`: transpiles TypeScript like existing route-guard tests; covers next-page stop, dedupe, reset, and all-page mutation mapping.
- Create `mobile/components/PaginatedListFooter.tsx`: consistent load-more spinner/retry footer; renders nothing when complete.
- Modify `mobile/package.json`: add `test` script running `node --test "lib/*.test.cjs"` without new dependencies.

### Mobile Growing Collections

- Create `mobile/lib/notifications.ts`: central notification type, page/count APIs, mutations, and distinct query keys.
- Modify `mobile/app/notifications.tsx`: infinite query, deduped pages, pull-to-refresh reset, footer retry, all-page optimistic updates.
- Modify `mobile/components/AppHeader.tsx`: use `/api/notifications/unread-count`; never fetch full notification list.
- Modify `mobile/app/settings.tsx`: read-all invalidates paginated list and unread-count keys.

- Modify `mobile/app/(tabs)/procurement.tsx`: paginate PO collection through `FlatList`; retain bounded vendor-group/item detail sections and mutation invalidations.
- Modify `mobile/lib/foodService.ts`: paginated menu-cycle API and explicit active filter.
- Modify `mobile/app/(tabs)/menu.tsx`: infinite cycle list, pull-to-refresh reset, and shared footer.
- Modify `mobile/app/(tabs)/prep.tsx`: active-cycle lookup requests filtered first page rather than assuming full array.

- Modify `mobile/lib/reports.ts`: paginated FSS report archive with server-side accomplishment type filter.
- Modify `mobile/app/reports.tsx`: infinite report list with distinct initial/load-more/retry states.
- Modify `mobile/app/announcements.tsx`: replace hard stop at 30 with infinite loading and deep-link-safe lookup.
- Modify `mobile/app/(tabs)/index.tsx`: use shared response type for capped 10-item announcement preview; remain intentionally non-infinite.

### Verified Exclusions

- Verify `frontend/services/diagnosisService.ts`, `frontend/app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx`, `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`, `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx`, and `backend/app/Http/Controllers/RND/DiagnosisController.php`: diagnoses are bounded children of one NCP record; pagination would fragment the clinical workflow.
- Verify `mobile/app/_layout.tsx`: existing `QueryClientProvider` already supports infinite queries.
- Verify `mobile/tsconfig.json`: new TypeScript helpers/components are already included.
- Verify `mobile/package-lock.json`: no dependency change required.
- Verify PO vendor groups, PO line items, shopping-list detail rows, weekly menu grids, recipe ingredients, and meal-prep logs in their existing screens: naturally bounded detail data stays unpaginated.
- Verify dashboard previews in `frontend/app/(rnd)/dashboard/page.tsx` and `mobile/app/(tabs)/index.tsx`: intentional small caps remain previews, not pageable full feeds.

---

## Task 1: Shared Laravel Pagination Rules

**Files:**
- Create `backend/app/Http/Requests/Concerns/HasPaginationRules.php` - reusable validated pagination contract.
- Create `backend/tests/Unit/Http/Requests/HasPaginationRulesTest.php` - isolated rule/helper coverage.

- [ ] **Step 1: Write failing unit tests**

Test that rules accept `page=1`, `per_page=1..100`, reject zero/non-integers/101, and `perPage(15)` returns 15 when absent.

- [ ] **Step 2: Run test and confirm failure**

Run: `cd backend && php artisan test tests/Unit/Http/Requests/HasPaginationRulesTest.php`

Expected: FAIL because trait does not exist.

- [ ] **Step 3: Add reusable trait**

```php
trait HasPaginationRules
{
    protected function paginationRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(int $default = 15): int
    {
        return (int) ($this->validated('per_page') ?? $default);
    }
}
```

- [ ] **Step 4: Run focused test**

Run: `cd backend && php artisan test tests/Unit/Http/Requests/HasPaginationRulesTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Requests/Concerns/HasPaginationRules.php backend/tests/Unit/Http/Requests/HasPaginationRulesTest.php
git commit -m "feat(api): standardize pagination input"
```

## Task 2: Shared Web Pagination Contract And Footer

**Files:**
- Create `frontend/types/pagination.ts` - canonical generic response contract.
- Modify `frontend/components/ui/Pagination.tsx` - canonical footer UI.
- Create `frontend/components/ui/Pagination.test.tsx` - visual contract tests.
- Modify six existing services listed in Shared Web Contract And UI - remove duplicate types only.

- [ ] **Step 1: Write failing component/type tests**

Assert `Showing 1-15 of 31`, `Page 1 of 3`, previous disabled, next enabled, one-page footer visible, accessible labels, 15/25/50/100 options, loading-disabled controls, and retry action.

- [ ] **Step 2: Run test and confirm failure**

Run: `cd frontend && npm test -- components/ui/Pagination.test.tsx`

Expected: FAIL because footer lacks range/page-size/error contract.

- [ ] **Step 3: Add canonical types**

```ts
export interface PaginationLinks { first: string | null; last: string | null; prev: string | null; next: string | null }
export interface PaginationMeta { current_page: number; from: number | null; last_page: number; path: string; per_page: number; to: number | null; total: number }
export interface PaginatedResponse<T> { data: T[]; links: PaginationLinks; meta: PaginationMeta }
```

- [ ] **Step 4: Replace footer API**

Use props `meta`, `page`, `onPageChange`, optional `onPerPageChange`, `isLoading`, `error`, and `onRetry`. Render footer when `meta.total > 0`; keep controls fixed-size and inside table surface.

- [ ] **Step 5: Migrate duplicate service types**

Import `PaginatedResponse`/`PaginationMeta` from `@/types/pagination`; preserve existing service exports only as type re-exports where consumers require compatibility.

- [ ] **Step 6: Run tests and type check**

Run: `cd frontend && npm test -- components/ui/Pagination.test.tsx && npx tsc --noEmit`

Expected: PASS; no duplicate metadata type errors.

- [ ] **Step 7: Commit**

```bash
git add frontend/types/pagination.ts frontend/components/ui/Pagination.tsx frontend/components/ui/Pagination.test.tsx frontend/services/patientService.ts frontend/services/foodLibraryService.ts frontend/services/foodDatabaseService.ts frontend/services/inventoryService.ts frontend/services/announcementService.ts frontend/services/auditLogService.ts
git commit -m "feat(web): unify pagination footer"
```

## Task 3: Shared Mobile Pagination Contract

**Files:**
- Create `mobile/lib/pagination.ts` - infinite-page helpers and types.
- Create `mobile/lib/pagination.test.cjs` - helper tests.
- Create `mobile/components/PaginatedListFooter.tsx` - loading/retry footer.
- Modify `mobile/package.json` - test command.

- [ ] **Step 1: Write failing helper tests**

Cover `getNextPageParam`, `flattenUniquePages`, `mapPageItems`, and `firstPageOnly` using duplicate IDs and last-page metadata.

- [ ] **Step 2: Run test and confirm failure**

Run: `cd mobile && node --test lib/pagination.test.cjs`

Expected: FAIL because helper module does not exist.

- [ ] **Step 3: Implement helper API**

```ts
import type { InfiniteData } from "@tanstack/react-query";

export interface PaginationLinks { first: string | null; last: string | null; prev: string | null; next: string | null }
export interface PaginationMeta { current_page: number; from: number | null; last_page: number; path: string; per_page: number; to: number | null; total: number }
export interface PaginatedResponse<T> { data: T[]; links: PaginationLinks; meta: PaginationMeta }
export const MOBILE_PAGE_SIZE = 20;
export const getNextPageParam = <T>(last: PaginatedResponse<T>) =>
  last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined;
export function flattenUniquePages<T extends { id: string | number }>(pages: PaginatedResponse<T>[]): T[] {
  const seen = new Set<string | number>();
  return pages.flatMap((page) => page.data.filter((item) => {
    if (seen.has(item.id)) return false;
    seen.add(item.id);
    return true;
  }));
}
export function mapPageItems<T>(data: InfiniteData<PaginatedResponse<T>>, map: (item: T) => T): InfiniteData<PaginatedResponse<T>> {
  return { ...data, pages: data.pages.map((page) => ({ ...page, data: page.data.map(map) })) };
}
export function firstPageOnly<T>(data: InfiniteData<PaginatedResponse<T>>): InfiniteData<PaginatedResponse<T>> {
  return { pages: data.pages.slice(0, 1), pageParams: data.pageParams.slice(0, 1) };
}
```

- [ ] **Step 4: Add footer and test script**

Footer accepts `loading`, `error`, and `onRetry`; spinner during load, 48 dp retry target on error, `null` otherwise. Add `"test": "node --test \"lib/*.test.cjs\""`.

- [ ] **Step 5: Verify**

Run: `cd mobile && npm test && npx tsc --noEmit`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add mobile/lib/pagination.ts mobile/lib/pagination.test.cjs mobile/components/PaginatedListFooter.tsx mobile/package.json
git commit -m "feat(mobile): add infinite paging helpers"
```

## Task 4: Notifications End To End

**Files:** all notification backend, web, proxy, mobile, and tests listed in Complete File Map.

- [ ] **Step 1: Write failing backend pagination tests**

Create 32 notifications across two users. Assert default 15, `meta.total` scoped to authenticated user, page non-overlap, date/ID ordering, `read` filter, invalid input `422`, source UUIDs, and all-role access.

- [ ] **Step 2: Run backend tests and confirm failure**

Run: `cd backend && php artisan test tests/Feature/NotificationPaginationTest.php tests/Feature/NotificationAccessTest.php tests/Feature/AdminSystemTest.php`

Expected: FAIL because index returns all rows without metadata.

- [ ] **Step 3: Implement backend pagination**

Use `IndexNotificationsRequest`, stable `created_at DESC, id DESC`, `paginate($request->perPage())`, `withQueryString()`, and `$paginator->getCollection()` for existing bulk source-UUID attachment.

- [ ] **Step 4: Write failing web service/proxy tests**

Assert `/api/notifications?page=2&per_page=15&read=0` forwarding, standard response parsing, and `/api/notifications/unread-count` proxy target.

- [ ] **Step 5: Implement web pagination**

Both notification pages use identical state/markup: server page, shared footer, page reset after preference filter changes, preserved rows during fetch, and unread aggregate independent from current page.

- [ ] **Step 6: Implement mobile pagination**

Move APIs/query keys to `mobile/lib/notifications.ts`; use `useInfiniteQuery`, shared flatten/next/footer helpers, page-1 refresh, and all-page optimistic mark-read rollback. Header and settings use distinct unread-count key.

- [ ] **Step 7: Verify notification slice**

Run: `cd backend && php artisan test --filter=Notification`

Run: `cd frontend && npm test -- services/notificationService.test.ts app/api/notifications/notification-routes.test.ts && npx tsc --noEmit`

Run: `cd mobile && npm test && npx tsc --noEmit`

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add backend/app/Http/Requests/RND/IndexNotificationsRequest.php backend/app/Http/Controllers/RND/NotificationController.php backend/tests/Feature/NotificationPaginationTest.php backend/tests/Feature/NotificationAccessTest.php backend/tests/Feature/AdminSystemTest.php frontend/services/notificationService.ts frontend/services/notificationService.test.ts frontend/app/\(rnd\)/notifications/page.tsx frontend/app/admin/notifications/page.tsx frontend/app/api/notifications/route.ts frontend/app/api/notifications/unread-count/route.ts frontend/app/api/notifications/notification-routes.test.ts mobile/lib/notifications.ts mobile/app/notifications.tsx mobile/components/AppHeader.tsx mobile/app/settings.tsx
git commit -m "feat: paginate notifications"
```

## Task 5: Purchase Orders And Menu Cycles

**Files:** all purchase-order/menu-cycle backend, web, proxy, mobile, and tests listed in Complete File Map.

- [ ] **Step 1: Write failing backend tests**

For each collection create more than 15 rows; assert standard contract, filters, stable non-overlap, UUIDs, access, and invalid pagination.

- [ ] **Step 2: Run and confirm failure**

Run: `cd backend && php artisan test tests/Feature/PurchaseOrderPaginationTest.php tests/Feature/MenuCyclePaginationTest.php`

Expected: FAIL because both indexes return unbounded arrays.

- [ ] **Step 3: Implement backend requests/controllers**

PO order: `created_at DESC, id DESC`. Cycle order: active first, date/created date descending, ID descending. Add explicit active/status filters so active lookup never depends on page scanning.

- [ ] **Step 4: Update web services/screens**

Return shared paginated responses. Add shared footer only to PO event and cycle tables. Reset page on PO/cycle filter changes. Keep shopping lists, vendor groups, line items, menu grids, and templates bounded/unpaginated.

- [ ] **Step 5: Update mobile screens**

PO screen uses a virtualized infinite list without nesting same-axis lists. Menu uses infinite `FlatList`. Prep requests `active=1&per_page=1`; deep-linked PO/cycle details fetch by UUID when absent from loaded pages.

- [ ] **Step 6: Verify slice**

Run: `cd backend && php artisan test tests/Feature/PurchaseOrderPaginationTest.php tests/Feature/MenuCyclePaginationTest.php tests/Feature/FssPermissionTest.php --filter='purchase|menu|cycle'`

Run: `cd frontend && npm test -- app/\(rnd\)/food-service/procurement/pagination.test.ts app/\(rnd\)/food-service/menu-cycle/pagination.test.ts app/\(rnd\)/food-service/menu-cycle/served-population-ui.test.ts && npx tsc --noEmit`

Run: `cd mobile && npm test && npx tsc --noEmit`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Requests/FSS/IndexPurchaseOrdersRequest.php backend/app/Http/Requests/FSS/IndexMenuCyclesRequest.php backend/app/Http/Controllers/FSS/PurchaseOrderController.php backend/app/Http/Controllers/FSS/MenuCycleController.php backend/tests/Feature/PurchaseOrderPaginationTest.php backend/tests/Feature/MenuCyclePaginationTest.php backend/tests/Feature/FssPermissionTest.php backend/tests/Feature/FoodServiceOpsTest.php frontend/services/procurementService.ts frontend/services/menuCycleService.ts frontend/app/\(rnd\)/food-service/procurement/page.tsx frontend/app/\(rnd\)/food-service/procurement/pagination.test.ts frontend/app/\(rnd\)/food-service/menu-cycle/page.tsx frontend/app/\(rnd\)/food-service/menu-cycle/pagination.test.ts frontend/app/\(rnd\)/food-service/menu-cycle/served-population-ui.test.ts mobile/app/\(tabs\)/procurement.tsx mobile/lib/foodService.ts mobile/app/\(tabs\)/menu.tsx mobile/app/\(tabs\)/prep.tsx
git commit -m "feat(fss): paginate operational lists"
```

## Task 6: Suppliers And Inventory Catalog

**Files:** all supplier/catalog backend, web, proxy, and tests listed in Complete File Map.

- [ ] **Step 1: Write failing backend tests**

Assert 16+ suppliers/catalog items produce standard pages, search/kind totals, stable name/ID order, UUIDs, supplier relation fields, and validation errors.

- [ ] **Step 2: Run and confirm failure**

Run: `cd backend && php artisan test tests/Feature/SupplierPaginationTest.php tests/Feature/FsItemCatalogPaginationTest.php`

Expected: FAIL because both endpoints return full arrays.

- [ ] **Step 3: Implement backend pagination/resources**

Use stable `name ASC, id ASC`; paginate before Resource transformation. Keep `/fs-items` ready-to-eat picker unchanged unless its existing bounded consumer proves otherwise.

- [ ] **Step 4: Update web management tables/selectors**

Supplier and catalog tables use debounced server search and shared footer. Selector requests use bounded search pages; they do not silently fetch every page. Preserve selected supplier by UUID even when not in current search page.

- [ ] **Step 5: Verify slice**

Run: `cd backend && php artisan test tests/Feature/SupplierPaginationTest.php tests/Feature/FsItemCatalogPaginationTest.php tests/Feature/FsItemCatalogTest.php`

Run: `cd frontend && npm test -- components/foodservice/SuppliersPanel.test.tsx app/\(rnd\)/food-service/inventory/catalog-pagination.test.ts && npx tsc --noEmit`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Requests/FSS/IndexSuppliersRequest.php backend/app/Http/Requests/FSS/IndexFsItemCatalogRequest.php backend/app/Http/Resources/FsItemCatalogResource.php backend/app/Http/Controllers/FSS/SupplierController.php backend/app/Http/Controllers/FSS/FsItemController.php backend/tests/Feature/SupplierPaginationTest.php backend/tests/Feature/FsItemCatalogPaginationTest.php backend/tests/Feature/FsItemCatalogTest.php frontend/services/supplierService.ts frontend/components/foodservice/SuppliersPanel.tsx frontend/components/foodservice/SuppliersPanel.test.tsx frontend/app/api/fss/suppliers/route.ts frontend/services/fsCatalogService.ts frontend/app/\(rnd\)/food-service/inventory/page.tsx frontend/app/\(rnd\)/food-service/inventory/catalog-pagination.test.ts
git commit -m "feat(fss): paginate reference catalogs"
```

## Task 7: Budget Ledger

**Files:** all budget backend, shared web shell, proxies, entry points, and tests listed in Complete File Map.

- [ ] **Step 1: Write failing backend tests**

Create 31 entries across years/sources; assert standard contract, filtered totals, signed values, PO/creator labels, stable date/ID order, FSS/Admin parity, and validation.

- [ ] **Step 2: Run and confirm failure**

Run: `cd backend && php artisan test tests/Feature/BudgetLedgerPaginationTest.php`

Expected: FAIL because ledger maps an unbounded collection.

- [ ] **Step 3: Implement paginated Resource response**

Move inline mapping to `BudgetLedgerResource`; eager-load existing relations, add ID tie-breaker, paginate validated query, preserve query string.

- [ ] **Step 4: Update shared web shell**

`getLedger` returns `PaginatedResponse<BudgetLedgerEntry>`. Shell owns page/per-page; year/source changes reset page 1. RND and Admin render identical footer, loading, error, and empty states.

- [ ] **Step 5: Verify slice**

Run: `cd backend && php artisan test tests/Feature/BudgetLedgerPaginationTest.php tests/Feature/BudgetLedgerTest.php tests/Feature/AdminBudgetReadOnlyTest.php`

Run: `cd frontend && npm test -- app/api/fss/budgets/budget-routes.test.ts app/api/admin/budgets/budget-routes.test.ts app/\(rnd\)/food-service/budget/placement.test.ts app/admin/budget/page.test.ts && npx tsc --noEmit`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Requests/FSS/IndexBudgetLedgerRequest.php backend/app/Http/Resources/BudgetLedgerResource.php backend/app/Http/Controllers/FSS/BudgetController.php backend/tests/Feature/BudgetLedgerPaginationTest.php backend/tests/Feature/BudgetLedgerTest.php backend/tests/Feature/AdminBudgetReadOnlyTest.php frontend/services/budgetService.ts frontend/components/budget/BudgetPageShell.tsx frontend/app/api/fss/budgets/budget-routes.test.ts frontend/app/api/admin/budgets/budget-routes.test.ts frontend/app/\(rnd\)/food-service/budget/placement.test.ts frontend/app/admin/budget/page.test.ts
git commit -m "feat(budget): paginate ledger entries"
```

## Task 8: Reports Across Roles

**Files:** all report backend controller/request/resource/source files, web service/browser/proxies, mobile files, and tests listed in Complete File Map.

- [ ] **Step 1: Write failing backend archive/instance tests**

Cover DB entity, generated period, singleton, RND/Admin/FSS role scopes, date/type filters, standard metadata, and page boundaries.

- [ ] **Step 2: Run and confirm failure**

Run: `cd backend && php artisan test tests/Feature/ReportPaginationTest.php tests/Feature/ReportsBrowseTest.php`

Expected: FAIL because archive and instance sources return arrays/custom shapes.

- [ ] **Step 3: Implement backend contracts**

Index uses Resource pagination. Instance sources return `LengthAwarePaginator`-compatible results; generated arrays are sliced after all filters, DB sources paginate queries, singleton uses one-item paginator.

- [ ] **Step 4: Update web reports**

Archive and instance tabs own independent server page state. Remove `slice()` and synthetic metadata. Filters reset only their tab's page. Both use shared footer.

- [ ] **Step 5: Update mobile FSS reports**

Send accomplishment type to server, use infinite query/shared footer, preserve report detail fetch by UUID.

- [ ] **Step 6: Verify slice**

Run: `cd backend && php artisan test tests/Feature/ReportPaginationTest.php tests/Feature/ReportControllerTest.php tests/Feature/ReportsBrowseTest.php tests/Feature/AdminReportsTest.php tests/Feature/FssReportScopeTest.php`

Run: `cd frontend && npm test -- components/reports/ReportsBrowser.pagination.test.ts app/admin/reports/page.test.ts && npx tsc --noEmit`

Run: `cd mobile && npm test && npx tsc --noEmit`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Requests/IndexReportsRequest.php backend/app/Http/Requests/IndexReportInstancesRequest.php backend/app/Http/Resources/ReportInstanceResource.php backend/app/Http/Controllers/ReportController.php backend/app/Services/Reports/Contracts/InstanceSource.php backend/app/Services/Reports/Instances/EntityInstanceSource.php backend/app/Services/Reports/Instances/PeriodInstanceSource.php backend/app/Services/Reports/Instances/SingletonInstanceSource.php backend/app/Services/Reports/ReportBrowser.php backend/tests/Feature/ReportPaginationTest.php backend/tests/Feature/ReportControllerTest.php backend/tests/Feature/ReportsBrowseTest.php backend/tests/Feature/AdminReportsTest.php backend/tests/Feature/FssReportScopeTest.php frontend/services/reportService.ts frontend/components/reports/ReportsBrowser.tsx frontend/components/reports/ReportsBrowser.pagination.test.ts frontend/app/api/rnd/reports/route.ts frontend/app/api/admin/reports/route.ts frontend/app/admin/reports/page.test.ts mobile/lib/reports.ts mobile/app/reports.tsx
git commit -m "feat(reports): paginate archives and instances"
```

## Task 9: Mobile Announcements Feed

**Files:**
- Modify `mobile/app/announcements.tsx` - load all server pages incrementally.
- Modify `mobile/app/(tabs)/index.tsx` - shared type for capped preview.

- [ ] **Step 1: Add failing helper/source assertions**

Extend `mobile/lib/pagination.test.cjs` to assert announcement page merge and retained deep-link target after multiple pages.

- [ ] **Step 2: Convert feed and preserve preview**

Full announcements screen uses `useInfiniteQuery` and `per_page=20`; dashboard remains `per_page=10` one-page preview. Deep-linked announcement fetches/searches explicitly when not loaded.

- [ ] **Step 3: Verify**

Run: `cd mobile && npm test && npx tsc --noEmit`

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add mobile/lib/pagination.test.cjs mobile/app/announcements.tsx mobile/app/\(tabs\)/index.tsx
git commit -m "feat(mobile): page announcement feed"
```

## Task 10: Existing Pagination Backend Consistency

**Files:** all request/controller/test files under Backend Existing-Pagination Consistency.

- [ ] **Step 1: Write failing consistency tests**

Data-provider test each endpoint for full `data/links/meta`, default 15, invalid page/per-page `422`, filter-preserving links, and stable ID tie-breaker.

- [ ] **Step 2: Run and confirm failures**

Run: `cd backend && php artisan test tests/Feature/ExistingPaginationConsistencyTest.php`

Expected: FAIL for incomplete custom metadata, unvalidated inputs, missing links, Admin users, or unstable ordering.

- [ ] **Step 3: Add endpoint Form Requests and controller fixes**

Use shared trait in every request. Keep endpoint-specific search/filter rules. Inventory retains top-level `stats`; Admin users become server paginated; all other resources retain current row shapes.

- [ ] **Step 4: Run focused and existing tests**

Run: `cd backend && php artisan test tests/Feature/ExistingPaginationConsistencyTest.php tests/Feature/PatientFeatureTest.php tests/Feature/FoodItemControllerTest.php tests/Feature/RecipeControllerTest.php tests/Feature/AnnouncementFeatureTest.php tests/Feature/AdminAuditLogTest.php`

Expected: PASS.

- [ ] **Step 5: Format and commit**

Run: `cd backend && vendor/bin/pint --dirty`

```bash
git add backend/app/Http/Requests/RND/IndexPatientsRequest.php backend/app/Http/Requests/RND/IndexFoodItemsRequest.php backend/app/Http/Requests/RND/IndexRecipesRequest.php backend/app/Http/Requests/Announcement/IndexAnnouncementsRequest.php backend/app/Http/Requests/Admin/IndexAuditLogsRequest.php backend/app/Http/Requests/Admin/IndexUsersRequest.php backend/app/Http/Requests/FSS/IndexInventoryRowsRequest.php backend/app/Http/Requests/FSS/IndexFoodServiceRecipesRequest.php backend/app/Http/Controllers/RND/PatientController.php backend/app/Http/Controllers/RND/FoodItemController.php backend/app/Http/Controllers/RND/RecipeController.php backend/app/Http/Controllers/RND/AnnouncementController.php backend/app/Http/Controllers/Admin/AnnouncementController.php backend/app/Http/Controllers/Admin/AuditLogController.php backend/app/Http/Controllers/Admin/UserController.php backend/app/Http/Controllers/FSS/InventoryController.php backend/app/Http/Controllers/FSS/FoodServiceRecipeController.php backend/tests/Feature/ExistingPaginationConsistencyTest.php backend/tests/Feature/PatientFeatureTest.php backend/tests/Feature/FoodItemControllerTest.php backend/tests/Feature/RecipeControllerTest.php backend/tests/Feature/AnnouncementFeatureTest.php backend/tests/Feature/AdminAuditLogTest.php
git commit -m "refactor(api): align pagination contracts"
```

## Task 11: Existing Web Table Consistency

**Files:** all screens/services/proxies/tests under Web Existing-Pagination Consistency.

- [ ] **Step 1: Write failing source/component tests**

Assert every full table imports shared `Pagination`, no migrated screen uses client `slice()` for paging, proxies forward `per_page`, and filter/page-size changes reset page 1.

- [ ] **Step 2: Run and confirm failures**

Run: `cd frontend && npm test -- app/pagination-consistency.test.ts`

Expected: FAIL for local paging, incomplete proxies, and duplicate metadata.

- [ ] **Step 3: Migrate screens and proxies**

Patients, food/recipes, FSS recipes, announcements, audit logs, and users use identical footer props and state transitions. Audit actor options call paginated `listUsers({ search, per_page: 15 })` rather than preloading all users. Keep domain row styles; standardize only footer, loading/error/empty placement, controls, and metadata text.

- [ ] **Step 4: Verify frontend**

Run: `cd frontend && npm test && npm run lint && npx tsc --noEmit`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend/app/\(rnd\)/ncp/patients/page.tsx frontend/app/api/patients/route.ts frontend/app/\(rnd\)/food-library/page.tsx frontend/app/\(rnd\)/food-service/recipes/page.tsx frontend/app/api/fss/food-service-recipes/route.ts frontend/components/announcements/AnnouncementsBoard.tsx frontend/app/api/announcements/route.ts frontend/app/admin/audit-logs/page.tsx frontend/services/adminUserService.ts frontend/app/admin/users/page.tsx frontend/app/pagination-consistency.test.ts
git commit -m "refactor(web): align table pagination"
```

## Task 12: Full Verification And UX QA

**Files:** verify all files in Complete File Map; no production edits expected unless verification exposes a defect.

- [ ] **Step 1: Backend full suite and formatting**

Run: `cd backend && vendor/bin/pint --test && php artisan test`

Expected: PASS with zero failures.

- [ ] **Step 2: Web full checks**

Run: `cd frontend && npm test && npm run lint && npm run build`

Expected: PASS.

- [ ] **Step 3: Mobile full checks**

Run: `cd mobile && npm test && npx tsc --noEmit`

Expected: PASS.

- [ ] **Step 4: Browser UX verification**

Start backend/frontend, then verify at 375x812, 768x1024, and 1440x900: footer remains inside table surface; no overlap; 44 px web targets; range/page labels; one-page footer; search/filter reset; loading preserves rows; failed page retry; back/refresh query state where supported.

- [ ] **Step 5: Mobile UX verification**

Verify small phone, large phone, and tablet portrait/landscape: 48 dp retry controls; safe-area padding; initial/refresh/load-more states differ; no duplicate rows; no request after last page; pull-to-refresh returns to page 1; deep links open records outside loaded pages.

- [ ] **Step 6: Confirm exclusions**

Inspect NCP diagnosis, PO details, shopping-list details, menu grids, recipe ingredients, dashboards, and meal-prep logs. Confirm no pagination controls were added and no full growing collection is fetched to support a bounded preview.

- [ ] **Step 7: Final diff review**

Run: `git diff --check && git status --short`

Expected: no whitespace errors; only planned files changed; unrelated `.superpowers/` remains untouched.

If QA finds defects, return to the owning task, add a failing regression test, apply the smallest fix, rerun that task's focused checks, then commit the exact files named by that task.
