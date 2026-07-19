# Notifications, FSS Reports, and Mobile UX Implementation Plan

> Execute with Superpowers test-driven development, systematic debugging, and verification-before-completion. Work directly in the current workspace, without subagents. Preserve unrelated staged files.

**Goal:** Deliver actionable cross-platform notifications, lifecycle cleanup, FSS-owned reports, separated mobile workflows, a modern Nutriscope mobile UI, and a downloadable Android APK.

**Architecture:** Laravel remains authoritative for notification ownership, source resolution, task completion, retention, and report scope. Next.js and Expo clients share equivalent destination mapping but use platform-native routes. Mobile presentation is refactored around shared tokens/components and five task-focused tabs.

**Stack:** Laravel 13/PHP 8.4, Next.js 16/React 19, Expo 54/React Native 0.81, Sanctum, PHPUnit, Vitest, TypeScript, EAS Build.

---

## Task 1: Notification lifecycle migration and model contract

**Files:**

- Create: `backend/database/migrations/2026_07_19_000003_add_lifecycle_to_notifications_table.php`
- Modify: `backend/app/Models/Notification.php`
- Modify: `backend/app/Http/Resources/NotificationResource.php`
- Modify: `backend/database/factories/NotificationFactory.php`
- Test: `backend/tests/Feature/NotificationAccessTest.php`

1. Add failing response/model tests for `read_at`, `opened_at`, and `resolved_at`.
2. Add nullable indexed timestamps and model casts/fillable fields.
3. Expose public lifecycle fields while keeping the UUID `id` and existing `read` boolean.
4. Run the focused test.

## Task 2: Owner-scoped notification opening and correct deep-link metadata

**Files:**

- Modify: `backend/routes/api.php`
- Modify: `backend/app/Http/Controllers/RND/NotificationController.php`
- Modify: `backend/tests/Feature/NotificationAccessTest.php`
- Modify: `backend/tests/Feature/NotificationPaginationTest.php`

1. Add failing tests for owner open, foreign-user denial, mark-all timestamp behavior, and source UUID exposure.
2. Add `PATCH /api/notifications/{notification}/open`.
3. Make read/read-all timestamp-aware and idempotent; open sets both read state and `opened_at` atomically.
4. Verify pagination and authorization regressions.

## Task 3: Action resolution and three-day pruning

**Files:**

- Create: `backend/app/Console/Commands/PruneNotifications.php`
- Create: `backend/app/Services/NotificationLifecycleService.php`
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/app/Http/Controllers/RND/MonitoringController.php`
- Modify: the PO receipt mutation path identified in `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`
- Create: `backend/tests/Feature/NotificationLifecycleTest.php`
- Modify: `backend/tests/Feature/PoAwaitingReceiptNotificationTest.php`
- Modify: `backend/tests/Feature/NotificationTriggersTest.php`

1. Add failing tests for announcement open + three days, unopened retention, PO receipt completion, NCP monitoring completion, unresolved retention, and exact retention boundary.
2. Implement source-specific resolution in one lifecycle service.
3. Invoke resolution only after the associated domain mutation succeeds.
4. Add the daily prune command to the Laravel scheduler with overlap protection.
5. Run notification, monitoring, PO, and scheduler-focused suites sequentially.

## Task 4: Website notification wiring for all roles

**Files:**

- Modify: `frontend/services/notificationService.ts`
- Modify: `frontend/services/notificationService.test.ts`
- Create: `frontend/app/api/notifications/[id]/open/route.ts`
- Modify: `frontend/app/(rnd)/notifications/page.tsx`
- Modify: `frontend/app/admin/notifications/page.tsx`
- Modify: `frontend/components/layout/TopBar.tsx`
- Add/modify focused tests beside these surfaces.

1. Add failing tests for UUID IDs, destination mapping, open-before-navigation, and FSS bell availability.
2. Add the open proxy/service call.
3. Route announcements, POs, and NCP follow-ups to their actionable pages using `source_uuid`.
4. Show safe unavailable feedback for missing/deleted sources.
5. Ensure unread badge invalidation is consistent across roles.

## Task 5: FSS-owned reports on the website

**Files:**

- Modify: `frontend/app/(rnd)/reports/page.tsx`
- Modify: `frontend/components/reports/ReportsBrowser.tsx`
- Create: `frontend/app/api/fss/reports/route.ts`
- Create: `frontend/app/api/fss/reports/[id]/route.ts`
- Create: `frontend/app/api/fss/reports/[id]/instances/route.ts`
- Create: `frontend/app/api/fss/reports/[id]/view/route.ts`
- Create: `frontend/app/api/fss/reports/[id]/download/route.ts`
- Add/modify report page tests.
- Verify: `backend/tests/Feature/FssReportScopeTest.php`

1. Add failing UI/source tests for role-aware catalog and `/api/fss` requests.
2. Extend the shared browser API prefix type to include FSS.
3. Render only accomplishment reports and hide unauthorized template/admin functions for FSS.
4. Preserve RND/Admin behavior and verify backend ownership enforcement.

## Task 6: Mobile shared design system and shell

**Files:**

- Create: `mobile/lib/theme.ts`
- Create: `mobile/components/ui/Screen.tsx`
- Create: `mobile/components/ui/Card.tsx`
- Create: `mobile/components/ui/StatusChip.tsx`
- Create: `mobile/components/ui/SectionHeader.tsx`
- Create: `mobile/components/ui/ActionButton.tsx`
- Modify: `mobile/components/AppHeader.tsx`
- Modify: `mobile/components/BrandLogo.tsx`
- Modify: `mobile/app/_layout.tsx`
- Modify: `mobile/app/(tabs)/_layout.tsx`

1. Define Nutriscope color, spacing, radius, elevation, and typography tokens.
2. Build accessible reusable primitives with loading/disabled/pressed states.
3. Restyle headers, surfaces, and bottom navigation using original Taskpilot/Loag-inspired hierarchy.
4. Preserve Expo Router guards and safe-area behavior.

## Task 7: Split Meal Prep and Accomplishments

**Files:**

- Refactor: `mobile/app/(tabs)/prep.tsx`
- Create: `mobile/app/(tabs)/accomplishments.tsx`
- Create supporting components under `mobile/components/food-service/` as needed.
- Modify: `mobile/app/(tabs)/_layout.tsx`
- Modify: `mobile/app/reports.tsx`
- Add: focused `.test.cjs` contract/navigation tests under `mobile/lib/`.

1. Add a failing navigation/source contract test requiring separate five-tab routes.
2. Keep meal-prep queries and mutations on the Prep screen only.
3. Move accomplishment form/state to its own tab and add an obvious Own Reports action.
4. Confirm cache keys, active-cycle handling, error states, and keyboard/scroll behavior remain correct.

## Task 8: Taskpilot-inspired menu day picker

**Files:**

- Modify: `mobile/app/(tabs)/menu.tsx`
- Create: `mobile/components/menu/DayPicker.tsx`
- Create: `mobile/components/menu/PopulationSummary.tsx`
- Add: focused day-selection contract test under `mobile/lib/`.

1. Add a failing test for a controlled selected date, horizontal day cells, and selected-day filtering.
2. Implement the accessible horizontal picker and active-day auto-scroll.
3. Keep planned population and total served visible above the selected day's meal cards.
4. Reuse existing cycle and service-log data; do not duplicate backend calculations.

## Task 9: Mobile notification/report contracts and deep links

**Files:**

- Modify: `mobile/app/notifications.tsx`
- Modify: `mobile/lib/reports.ts`
- Modify: `mobile/app/reports.tsx`
- Modify: `mobile/app/announcements.tsx`
- Modify: `mobile/app/(tabs)/procurement.tsx`
- Modify: `mobile/components/AppHeader.tsx`
- Modify/add: `mobile/lib/stringRouteIds.test.cjs` and routing tests.

1. Add failing tests that require UUID string IDs and all destination mappings.
2. Change notification/report public IDs and service parameters from number to string.
3. Call the open endpoint before deep-linking.
4. Verify PO and announcement query parameters open the exact target; add NCP follow-up routing support where applicable.
5. Confirm FSS report lists remain backend-scoped to the authenticated user.

## Task 10: Verify population-scaled food and recipe profiles

**Files:**

- Verify/modify: `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`
- Verify/modify: `backend/app/Services/MenuCycleCostService.php`
- Verify/modify: the menu-cycle and food-profile resources/controllers serving web and mobile.
- Modify: `frontend/app/(rnd)/food-service/menu-cycle/page.tsx`
- Modify: `frontend/app/(rnd)/food-service/menu-cycle/_components/ServiceLogPanel.tsx` if needed.
- Modify: `mobile/app/(tabs)/menu.tsx`
- Modify: `mobile/lib/foodService.ts`
- Add/modify: backend food-service snapshot tests, website served-population tests, and mobile profile contract tests.

1. Add failing regression tests proving that the RND estimate is frozen and scaled when the shopping list becomes a PO.
2. Verify recipe ingredients and direct food quantities use the same authoritative snapshot.
3. Ensure tapping foods/recipes exposes the profile and snapshot quantities on mobile and the RND website.
4. Prevent client-side recalculation after conversion and preserve locked historical values.

## Task 11: Redesign remaining mobile surfaces

**Files:**

- Modify: `mobile/app/(tabs)/index.tsx`
- Modify: `mobile/app/(tabs)/procurement.tsx`
- Modify: `mobile/app/login.tsx`
- Modify: `mobile/app/forgot-password.tsx`
- Modify: `mobile/app/account-setup.tsx`
- Modify: `mobile/app/announcements.tsx`
- Modify: `mobile/app/notifications.tsx`
- Modify: `mobile/app/reports.tsx`
- Modify: `mobile/app/profile.tsx`
- Modify: `mobile/app/settings.tsx`

1. Apply the shared components and information hierarchy without changing domain behavior.
2. Standardize loading, empty, retry, form, validation, confirmation, and destructive states.
3. Keep important operational actions visible without excessive scroll.
4. Validate touch targets, contrast, dynamic text resilience, and Android safe areas.

## Task 12: Integrated verification and blast-radius review

**Files:** All changed files and adjacent tests.

1. Run Pint on changed backend files.
2. Run focused Laravel suites sequentially, then the broader backend suite if the environment permits.
3. Run frontend lint, Vitest, TypeScript/build.
4. Run mobile Node contract tests, `npx tsc --noEmit`, and Expo diagnostics.
5. Inspect Laravel routes, Next.js proxy routes, Expo routes, git diff, and git status.
6. Fix every in-scope regression found; do not alter unrelated staged changes.

## Task 13: Android release artifact, commit, and push

**Files:**

- Modify: `mobile/app.json`
- Modify if required: `mobile/eas.json`
- Add only release metadata/artifact link documentation if the repository convention requires it.

1. Increment application version/versionCode.
2. Build an installable Android APK with the configured EAS profile.
3. Confirm the artifact URL and installability metadata from the completed build.
4. Stage only task files, create neutral conventional commits with no AI attribution, and push the current branch.
5. Report the commit, pushed branch, verification evidence, migration requirement, and APK download URL.
