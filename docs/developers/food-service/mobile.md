# Food-service mobile app

Purpose: maintain the Expo food-service API consumer and distribute updates.

Demo video: [Emphive_MobileDemo.mp4](../../../media/Emphive_MobileDemo.mp4) replaces the phone screen in the “Your daily overview” scene (4.10–9.70 seconds) with an Android emulator recording of the current dashboard. Recorded from source revision `97cb53a1` through Expo Go on 9 November 2026; this is source-preview verification, not a new APK release. The original audio, titles, scene timing and other scenes are retained.

Dashboard presentation (`app/(tabs)/index.tsx`): three tappable rows show Meals to record, Logs due and Pending POs with their existing counts and destinations. Today's meals use one full-width row per menu line; the separate weekly-menu dashboard card is removed. Menu remains a primary tab. All primary tabs reuse `AppHeader` with the existing 32-pixel logo and green/orange NutriScope wordmark on one line, without a page name or background tile. Header actions use unboxed 48-pixel touch targets.

Accomplishment navigation: `ReportsScreen` keeps the existing `saved_days/expected_days` progress on period cards before opening them. Its future-day rows are disabled and labeled Upcoming, using Manila today; `app/(tabs)/accomplishments.tsx` also guards report callbacks, date-picker results and submission against future dates. Current and past days remain reachable. The rendered regression is `lib/accomplishmentNavigation.test.mts` using the frontend Vitest runner.

Account and announcement avatars share `components/AnnouncementMedia.tsx`'s `AnnouncementAuthorAvatar`. Account consumers map `UserProfile.display_name` and the existing optional `profile_photo` API field. Protected images wait for SecureStore's bearer token; missing photos, storage failures and image failures show name initials. Inline legacy photos do not require a token. The focused render regression is `node ../frontend/node_modules/vitest/vitest.mjs run lib/announcementAvatar.test.mts` from `mobile/`. Source reviewed at `6ad8a880` plus these changes, 2026-11-06; no backend photo contract is changed.

Daily Menu presentation: `app/(tabs)/menu.tsx` renders all selected-day lines in the existing meal/line order, without the daily Previous/Next pagination footer. Date changes and menu-item navigation remain unchanged. The separate weekly-menu browser still uses server pagination so older weeks remain reachable. The stable Android download is published separately through `mobile/release.json` and the existing release workflow.

Startup readiness: `app/_layout.tsx` shows `AnimatedSplash` only while the existing SecureStore token lookup is pending. Resolving or failing that lookup releases navigation without waiting for an animation timer; the auth subscription, route guard and query-cache clearing are unchanged. The focused root-render regression uses the existing frontend runner: from `mobile/`, run `node ../frontend/node_modules/vitest/vitest.mjs run lib/appStartup.test.mts`. Its platform mocks share one React copy with the DOM renderer; it does not replace Android acceptance. Installed users receive source changes only after a separately verified APK is published.

Menu-query reuse: `lib/menuQueries.ts` owns the shared `['fs-cycle', cycleId]` detail query used by Menu and Meal Prep. It forwards React Query's cancellation signal through `getMenuCycle` to Axios. Identical active requests share the existing in-memory QueryClient; default freshness and authentication-change cache clearing remain in place. This does not persist menus or pending writes across restarts. Its focused regression command from `mobile/` is `node ../frontend/node_modules/vitest/vitest.mjs run lib/menuQueries.test.mts`; it uses the installed frontend test runner without adding a mobile dependency.

`menuDateQuery` in the same owner keeps a known menu visible while another date within its Monday–Sunday window is checked. The date lookup still runs; its response replaces the temporary data. Dates outside that window wait for their own result, including an unplanned week. Keeping the cycle-detail component mounted also avoids downloading the same cycle again solely because the selected day changed. Dashboard summary cards remain accessible buttons without redundant View labels; Purchase pagination uses the existing 16-pixel content inset.

Low-bandwidth consumers: Procurement notification targets request the existing authorized purchase-order detail endpoint directly instead of walking list pages. The detail key stays under `['fss-purchase-orders']` so existing mutation invalidations include it. Dashboard/Menu/Meal Prep/Procurement retain available in-memory data after failed background reads and offer retry; initial required failures still block the affected task. Announcement feeds use [compact protected images](../shared/README.md#meaning-and-safe-changes) with legacy inline support. Header polling and query freshness policies are unchanged. Export/type tests do not establish publication or device acceptance. Follow the signing/release guide before distributing it.

- [app/_layout.tsx](../../../mobile/app/_layout.tsx) owns root navigation, auth subscription and React Query.
- [app/(tabs)](<../../../mobile/app/(tabs)>) owns menu, procurement, meal preparation and accomplishment screens.
- [lib/auth.ts](../../../mobile/lib/auth.ts) stores tokens with SecureStore; [lib/api.ts](../../../mobile/lib/api.ts) injects bearer auth and clears it on 401.
- [foodService](../../../mobile/lib/foodService.ts) and [mobileContracts](../../../mobile/lib/mobileContracts.ts) describe consumers, not authoritative business rules.
- [appUpdate](../../../mobile/lib/appUpdate.ts) checks published APK metadata. It is separate from Expo EAS Update.
- [app.json](../../../mobile/app.json) owns package/version/project identity; [eas.json](../../../mobile/eas.json) owns build profiles and API URLs.

To change an endpoint, trace mobile and web consumers against the backend. EXPO_PUBLIC_API_URL is baked into builds: preserve the /mobile-api prefix for the current proxy, without a trailing /api (callers already add /api). Moving the VPS under the same hostname can preserve installed clients; changing the hostname or incompatible contracts may require a new APK.

Procurement requests are local to `app/(tabs)/procurement.tsx`, not `lib/foodService.ts`. Its receipt-total checkbox preserves automatic/manual mode via the existing vendor-group PATCH; see [receipt-total semantics](data.md#vendor-receipt-total). Older installed clients omit the additive mode fields and preserve an existing override. Shipping the native checkbox requires a new APK; source/type checks do not publish an app update.

Committed package.json declares Expo ~54.0.37, while [mobile instructions](../../../mobile/AGENTS.md) link v56 docs. Resolve this mismatch using the actual lockfile/installed SDK before mobile code work; no upgrade is implied.

Build, signing, EAS Cloud ownership and update publication: [Android releases](../../operations/mobile-release.md). Development commands and isolation: [local setup](../platform/local-setup.md), [testing](../platform/testing.md).
