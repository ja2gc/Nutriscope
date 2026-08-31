# Always-Visible Pagination and Quick Actions Design

## Goal

Make pagination behavior consistent across NutriScope web views and simplify the Admin dashboard Quick Actions panel without changing list contents, page sizes, filters, routes, or permissions.

## Pagination behavior

- Every web view that already receives pagination metadata must render the shared pagination footer after its data request completes.
- The footer remains visible for empty results and one-page results.
- Empty results display `Page 1 of 1 · 0 items`.
- One-page results display `Page 1 of 1 · N items`.
- Previous and Next controls remain visible and disabled when navigation is unavailable.
- Loading states do not fabricate pagination metadata or show an inaccurate footer before a response arrives.
- Existing multi-page navigation, page sizes, filters, searching, ordering, and URL state remain unchanged.

## Scope boundaries

- Update shared web pagination behavior and any consumer that conditionally hides its footer based on item count, empty state, or a single page.
- Keep bounded dashboard previews, including Admin Recent Activity, as fixed summaries with their existing View All links.
- Keep mobile infinite-scroll and Load More interactions unchanged because they are not numbered pagination footers.
- Do not convert bounded detail collections into paginated lists as part of this change.

## Admin Quick Actions

- Remove every decorative icon from the three Quick Actions links, including the account, activity, announcement, and arrow icons.
- Preserve the existing destinations, titles, descriptions, keyboard behavior, and hover/focus affordances.
- Reduce the unused vertical space left by the removed icon row so the cards read as compact text actions.
- Keep typography, border radius, spacing, and colors consistent with the surrounding Admin dashboard.

## Implementation approach

Retain `frontend/components/ui/Pagination.tsx` as the single pagination presentation component. Correct only consumer-level conditions that prevent the component from receiving valid metadata for empty or one-page responses. Avoid a new wrapper abstraction or per-page fallback metadata because both would duplicate behavior and widen the change unnecessarily.

## Verification

- Add regression tests proving the shared footer remains visible for zero and one-page metadata with disabled navigation.
- Add source or component contracts for paginated consumers that previously hid the footer behind item-count conditions.
- Add an Admin dashboard contract proving Quick Actions contain no decorative icons while retaining all three links and descriptions.
- Run the affected frontend tests, the complete frontend test suite, targeted lint, TypeScript checking, and a production build.
- Review the staged diff to confirm that unrelated files, especially `.codex/config.toml`, are excluded.

