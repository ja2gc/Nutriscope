# Backup List Hierarchy Design

## Goal

Make Backup & Recovery navigation stable, non-scrollable, and easy to understand without changing backup or recovery behavior.

## Approved interface

- Replace the four equal-looking status tabs with three primary views: **Restore points**, **Failed**, and **Recently deleted**.
- Remove the permanent **In progress** view. Show a compact **Backup activity** panel only while a backup is queued, being created, or being verified. Keep automatic polling and duplicate-work protection while activity exists.
- Show active restoration separately from backup creation. Restoration remains attached to the selected restore point and uses clear recovery activity/history text.
- Render Daily, Weekly, Monthly, Manual, and **Pre-restore** as wrapping filter chips under a visible **Filter by backup type** label. Keep all five filters visible at zero so navigation does not appear unexpectedly.
- Rename Safety to **Pre-restore** in administrator-facing copy. Explain that one protected copy is created before every restoration attempt, including an attempt that later fails.
- Use the existing NutriScope font, colors, spacing tokens, `Tabs`, `Button`, `Card`, `Badge`, `EmptyState`, and `Pagination` components where their semantics fit. Extend an existing component only when needed; do not add a parallel design system or font.
- Remove nested horizontal or vertical scrolling from the filter/navigation area. Controls wrap within the viewport and retain keyboard access, visible focus, and 44-pixel minimum targets.

## Restore-point rows

- Do not show a **Completed** badge inside the Restore points view; presence in that view already communicates successful creation.
- Keep failure emphasis for failed work and live stage labels for queued, creating, and verifying work.
- Replace `Recovery: completed` with history text such as `Used for system restore · Completed Sep 5, 2026`.
- Show active or failed restoration states with explicit restoration wording, not generic backup-state labels.
- Hide Delete whenever the backend would reject deletion, including the latest verified backup, an active recovery reference, or an unexpired pre-restore backup.
- Pre-restore backups show their protection deadline. After protection expires, existing retention behavior moves them to Recently deleted.

## Data flow

- Existing paginated restore-point requests continue using status and category query parameters.
- Backup activity uses the existing `in_progress` API section with the `all` category; this is presentation restructuring, not a new service or endpoint.
- Restore-point pagination remains ten items per page and resets when the primary view or type filter changes.
- Existing five-second polling continues while backup creation or restoration is active.

## Help and documentation

Update existing Admin Help/FAQ entries instead of creating duplicate guidance. Define:

- Restore points, Failed, and Recently deleted views.
- Queued, Creating, and Verifying activity stages.
- Daily, Weekly, Monthly, Manual, and Pre-restore types.
- Completed, failed, cancelled, and rolled-back restoration history wording.
- Why multiple pre-restore backups can exist and why protected ones have no Delete action.

## Tests

- Start with failing frontend contracts for hierarchy, labels, absence of redundant badges, stable filters, conditional activity, pagination, and Help copy.
- Add a failing backend resource test proving protected pre-restore backups do not advertise Delete.
- Run focused tests, relevant backend backup/recovery suite, full frontend tests, type checking, lint, production build, and `git diff --check`.
- After deployment, verify desktop and narrow viewport layouts, keyboard semantics, activity behavior, protected actions, Help search, public health, and Git parity.
