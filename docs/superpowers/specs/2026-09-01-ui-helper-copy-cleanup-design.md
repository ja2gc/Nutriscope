# UI Helper Copy Cleanup Design

## Goal

Reduce persistent visual clutter across NutriScope web and Food Service Staff mobile UI. Remove copy that repeats a page title, button, field label, role, or obvious action. Keep copy only when it answers a likely question about behavior, constraints, safety, permissions, or data interpretation.

## Decisions

- Remove high-confidence page subtitles and inline summaries identified in the source audit.
- Remove obvious role/purpose labels, including generic Food Service Staff, RND, and Admin descriptions, when navigation or the page heading already provides that context.
- Remove mobile `Read-only weekly plan`, `Actual served population is recorded in Meal Prep`, login subtitles/status copy, counseling-field hints, and intervention summaries.
- Preserve permission-sensitive role labels where they explain why an action is unavailable, plus security, destructive-action, validation, upload, procurement-proof, chart-legend, and generated-draft guidance.
- Convert only genuinely non-obvious persistent guidance to compact help affordances. Web uses the existing Radix Popover dependency with a keyboard-accessible info button, Escape/outside dismissal, and an accessible relationship to its content. Mobile uses a pressable info button/card only where retained guidance needs discovery.
- Admin AI token cap guidance becomes one help affordance explaining daily/monthly caps and estimated cost calculation: input tokens multiplied by input rate plus output tokens multiplied by output rate, then converted to PHP using the displayed exchange rate.
- Do not add a new service, route, state store, or broad UI refactor.

## Scope

Web copy cleanup covers dashboard, calendar, food-service catalog/recipes/inventory/menu-cycle, food library/database, notifications, reports, help, settings/profile, admin pages, NCP pages, login, and announcement surfaces.

Mobile copy cleanup covers login, help, procurement, meal preparation, menu detail, food detail, and shared APK handoff copy. Mobile source changes require a new Android build/release metadata and end-to-end public APK/QR verification.

## Testing

- Add source-contract tests before implementation for removed strings, retained role-sensitive/security guidance, and the AI cost-help content.
- Run focused frontend tests, mobile contract/type checks available in the repository, frontend lint/build, and release verification.
- Confirm no unrelated worktree files are staged.

## Delivery

Create one task-only commit after verification, preserve unrelated local changes, push `main`, and report the exact commit and APK/QR verification evidence.
