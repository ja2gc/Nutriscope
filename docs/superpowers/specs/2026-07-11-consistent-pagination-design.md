# Consistent Pagination Design

## Context

NutriScope currently mixes server-paginated collections, client-paginated collections, and unbounded API responses. Web tables use several metadata shapes, while mobile list screens usually fetch one full array or only the first fixed-size page. This creates inconsistent controls, stale unread counts, incomplete mobile lists, and growing response sizes.

This design standardizes pagination without redesigning each workflow. It keeps NutriScope's existing dense dashboard styling and uses one Laravel length-aware pagination contract for both web and mobile.

## Goals

- Paginate every user-facing collection expected to grow without a small natural bound.
- Use Laravel `paginate()` and one response contract across affected endpoints.
- Use numbered previous/next pagination on web and incremental loading on mobile.
- Make pagination placement, labels, loading, empty, error, and disabled states consistent.
- Preserve active search and filters when changing pages.
- Reset to page 1 when search, filters, sort, or page size changes.
- Keep query ordering deterministic so records do not move or repeat between pages.
- Add focused API, web, and mobile tests before changing behavior.

## In Scope

High-growth collections:

- Notifications: web RND, web Admin, and mobile.
- Purchase orders: web procurement and mobile procurement.
- Menu cycles: web menu-cycle and mobile menu.
- Suppliers: web supplier management and supplier selectors that need searchable remote results.
- Inventory reference catalog: web inventory.
- Budget ledger: web RND/Admin budget views.
- Reports archive/list: web RND/Admin and mobile reports.

Existing paginated collections are included in the consistency pass, not backend rewrites unless their contract differs:

- Patients, food library items, recipes, announcements, audit logs, inventory rows, Admin users, and existing report instance/archive pagination.

## Out of Scope

- Purchase-order vendor groups and line items.
- Shopping-list line items inside one list.
- Weekly menu grids, meal rows, recipe ingredients, and other naturally bounded detail collections.
- Diagnoses attached to one NCP record. This is a bounded clinical detail list, not a diagnosis reference catalog.
- Dashboard preview lists intentionally capped to a small number.
- Pagination of dropdown option sets that are demonstrably small and loaded once; growing supplier/catalog selectors should use server search instead.
- Cursor pagination. Numbered web navigation and total counts require length-aware offset pagination.
- Broad visual redesign of table columns, workflows, colors, or information architecture.

## API Contract

All paginated endpoints return Laravel's standard resource pagination shape:

```json
{
  "data": [],
  "links": {
    "first": null,
    "last": null,
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": null,
    "last_page": 1,
    "path": "",
    "per_page": 15,
    "to": null,
    "total": 0
  }
}
```

Rules:

- Default `per_page` is 15 for full screens.
- Allowed `per_page` values are 15, 25, 50, and 100 on web where a page-size selector is useful.
- Mobile uses 20 per request to reduce loading frequency while keeping payloads bounded.
- `page` must be an integer of at least 1; `per_page` must be an integer from 1 through 100.
- Invalid values return `422`; controllers use validated values only.
- Search/filter/sort query parameters are server-side and affect `meta.total`.
- Paginators preserve query parameters with `withQueryString()`.
- Every ordering includes a stable ID tie-breaker.
- Existing endpoint-specific top-level aggregates, such as inventory `stats`, remain documented extensions.

Unread notification count remains a separate aggregate endpoint. Headers must use that endpoint instead of loading all notifications and counting locally.

## Web UX

All full table/list screens use the shared `Pagination` component in one location: directly below the table/list content, inside the same bordered surface. The footer remains visible when one or more records exist, including a single page, so count and page-size behavior stay predictable.

Footer content:

- Left: `Showing {from}-{to} of {total}`.
- Optional middle control on large datasets: page-size select using 15, 25, 50, 100.
- Right: previous and next icon buttons plus `Page {current} of {last}`.
- Icon buttons use Lucide chevrons, accessible labels, visible focus states, and at least 44 px targets.
- Small screens collapse wording but keep count, current page, and previous/next controls.

Interaction behavior:

- Page change keeps active filters and search.
- Filter/search/sort/page-size change resets page to 1.
- Search requests are debounced where remote search is added.
- Existing rows remain visible during background page loading; table body gets a subtle loading state and controls disable to prevent duplicate requests.
- Failed page requests keep the current page visible and show an inline retry message.
- Empty results show the existing `EmptyState`, not a pagination footer.
- URL query parameters should hold page and filters on primary index screens where current routing patterns permit, enabling refresh/back navigation without losing context.

Table visual consistency is limited to pagination-adjacent structure in this project: shared border, footer spacing, typography, icon controls, loading state, and empty/error placement. Existing domain-specific columns and row layouts remain unchanged.

## Mobile UX

Mobile uses `FlatList` or `FlashList` with TanStack Query infinite queries over the same `page` API.

- Initial request loads 20 records.
- `onEndReached` fetches the next page when `meta.current_page < meta.last_page`.
- A footer spinner appears only while fetching another page.
- End-of-list text is omitted; absence of a spinner keeps the list quiet.
- Pull-to-refresh clears accumulated pages and reloads page 1.
- Initial load, refresh, load-more, empty, and retry states are distinct.
- Duplicate IDs are removed when pages merge as defense against records changing during offset pagination.
- Mark-read/update mutations patch matching records across all cached pages, then invalidate aggregate counts.
- Mobile does not show numbered page controls or a page-size selector.
- Touch targets remain at least 48 dp and safe-area bottom padding remains intact.

## Shared Types And Components

Web should have one generic `PaginatedResponse<T>` and one complete `PaginationMeta` definition instead of service-local duplicates. The existing `Pagination` component becomes the only full-screen pagination footer.

Mobile should have one generic paginated-response type and one reusable list-footer/loading helper. Each screen owns its row renderer and filters, but not pagination metadata parsing or page-merging rules.

## Data Flow

1. Screen sends `page`, `per_page`, search, filters, and sort to its service.
2. Next.js proxy route forwards the query string unchanged where a proxy exists.
3. Laravel Form Request validates pagination and endpoint filters.
4. Controller builds filtered query, applies deterministic ordering, and calls `paginate()`.
5. API Resource collection emits standard `data`, `links`, and `meta`.
6. Web replaces current page rows and renders shared footer metadata.
7. Mobile appends next-page rows and uses metadata to stop loading.
8. Mutations invalidate the affected collection and any separate aggregate count.

## Migration And Compatibility

Changing unpaginated endpoints from `{ data: T[] }` to a paginated resource response preserves the `data` array but adds `links` and `meta`. Consumers still need coordinated updates because they currently return arrays directly from service functions.

Migration order per collection:

1. Add failing backend contract and boundary tests.
2. Add validated server pagination and deterministic ordering.
3. Update shared TypeScript response types and service.
4. Update all web and mobile consumers in the same change set.
5. Verify mutations, filters, deep links, and aggregate counts.

Do not temporarily fetch every page on clients. That hides contract mistakes and retains the performance problem.

## Error Handling

- Validation errors return standard Laravel `422` JSON.
- Out-of-range pages return an empty `data` array with valid metadata.
- Web page-fetch failure preserves existing rows and offers retry.
- Mobile load-more failure preserves loaded rows and exposes a footer retry action.
- Initial-load failure uses each screen's existing full empty/error treatment.
- A mutation failure rolls back optimistic cache changes across all loaded pages.

## Testing

Backend feature tests per endpoint:

- Standard response contract and default page size.
- More than one page, accurate totals, no overlap, and deterministic order.
- First, middle, last, empty, and out-of-range pages.
- `per_page` boundaries and invalid `page`/`per_page` values.
- Combined search/filter behavior and filtered totals.
- Pagination links preserve active query parameters.
- Authorization and resource UUID behavior remain unchanged.

Web tests:

- Shared footer count, page text, disabled controls, accessibility labels, and one-page behavior.
- Filters and page size reset page 1.
- Page changes send correct query parameters.
- Loading and failed-page states preserve current rows.
- Each migrated screen renders the shared component rather than local pagination markup.

Mobile tests or focused query-helper tests:

- Next-page parameter derives from metadata.
- Pages merge without duplicate IDs.
- End-of-list stops requests.
- Pull-to-refresh resets accumulated pages.
- Mutation cache updates cover all pages.
- Load-more failure retains loaded rows and allows retry.

## Rollout Order

1. Shared API types, validation concern, web footer, and mobile pagination helpers.
2. Notifications, including separate unread count usage.
3. Purchase orders and menu cycles.
4. Suppliers and inventory catalog.
5. Budget ledger and reports.
6. Existing-pagination consistency pass and removal of duplicate metadata types/local controls.

Each phase should remain independently testable and shippable.

## Acceptance Criteria

- Every in-scope growing collection is server-paginated.
- Web tables share one footer design and interaction behavior.
- Mobile lists load additional pages without numbered controls.
- All affected endpoints return the same complete Laravel pagination contract.
- Search, filters, sort, and page-size behavior are stable and reset pages correctly.
- No duplicate or missing records occur in deterministic test data across page boundaries.
- Notification headers no longer fetch full notification collections for unread counts.
- Bounded detail tables remain unpaginated.
- Existing authorization, deep links, mutations, and domain workflows still pass.

## Implementation Plan Reference Standard

The implementation plan must be saved under `docs/superpowers/plan/`. Every task must include an exhaustive file reference block grouped as `Create`, `Modify`, `Test`, and `Verify only / no change` where applicable.

Each referenced file must include:

- Exact repository-relative path.
- One-line reason the file is relevant.
- Expected responsibility or contract change.
- Relevant symbol or line location when the current file already exists.

The plan must include indirect consumers, not only visible table screens. This includes Next.js proxy routes that must forward pagination query parameters, mobile cache invalidation consumers, aggregate unread-count consumers, shared response types, resources, Form Requests, report instance-source contracts, and existing tests that inspect changed source files.

Files reviewed and deliberately excluded must appear in a final `Verified exclusions` section with the reason no change is needed. At minimum, this section must record the bounded NCP diagnosis list, purchase-order detail sublists, dashboard previews, mobile query provider/configuration, and proxy routes already forwarding complete query strings.

Do not list speculative files. A file belongs in the plan only when implementation changes it, tests it, or verifies a contract that could regress because of pagination.
