# Food-service mobile app

Verified against committed source: `d873ea2f3fec63a089bc70c6948f1611ae553d7b` plus the receipt-total change in this working tree (2026-10-23).

Purpose: maintain the Expo food-service API consumer and distribute updates.

Menu-query reuse verified against `fce342df9036dd6fd01814ef75f215968b044a79` plus this working tree (2026-10-29): `lib/menuQueries.ts` owns the shared `['fs-cycle', cycleId]` detail query used by Menu and Meal Prep. It forwards React Query's cancellation signal through `getMenuCycle` to Axios. Identical active requests share the existing in-memory QueryClient; default freshness and authentication-change cache clearing remain in place. This does not persist menus or pending writes across restarts. Its focused regression command from `mobile/` is `node ../frontend/node_modules/vitest/vitest.mjs run lib/menuQueries.test.mts`; it uses the installed frontend test runner without adding a mobile dependency.

`menuDateQuery` in the same owner keeps a known menu visible while another date within its Monday–Sunday window is checked. The date lookup still runs; its response replaces the temporary data. Dates outside that window wait for their own result, including an unplanned week. Keeping the cycle-detail component mounted also avoids downloading the same cycle again solely because the selected day changed. Verified against `48dcc0f9e8b9b3aa97be5ab5d5bb2293df29ba3f` plus this change (2026-10-30). Dashboard summary cards remain accessible buttons without redundant View labels; Purchase pagination uses the existing 16-pixel content inset.

Low-bandwidth consumers additionally verified against that revision plus this working tree (2026-10-30): Procurement notification targets request the existing authorized purchase-order detail endpoint directly instead of walking list pages. The detail key stays under `['fss-purchase-orders']` so existing mutation invalidations include it. Dashboard/Menu/Meal Prep/Procurement retain available in-memory data after failed background reads and offer retry; initial required failures still block the affected task. Announcement feeds use [compact protected images](../shared/README.md#meaning-and-safe-changes) with legacy inline support. Header polling and query freshness policies are unchanged. Source version 1.2.25/code 29 prepares the next APK; export/type tests do not establish publication or device acceptance. Follow the signing/release guide before distributing it.

- [app/_layout.tsx](../../../mobile/app/_layout.tsx) owns root navigation, auth subscription and React Query.
- [app/(tabs)](<../../../mobile/app/(tabs)>) owns menu, procurement, meal preparation and accomplishment screens.
- [lib/auth.ts](../../../mobile/lib/auth.ts) stores tokens with SecureStore; [lib/api.ts](../../../mobile/lib/api.ts) injects bearer auth and clears it on 401.
- [foodService](../../../mobile/lib/foodService.ts) and [mobileContracts](../../../mobile/lib/mobileContracts.ts) describe consumers, not authoritative business rules.
- [appUpdate](../../../mobile/lib/appUpdate.ts) checks published APK metadata. It is separate from Expo EAS Update.
- [app.json](../../../mobile/app.json) owns package/version/project identity; [eas.json](../../../mobile/eas.json) owns build profiles and API URLs.

To change an endpoint, trace mobile and web consumers against the backend. EXPO_PUBLIC_API_URL is baked into builds: preserve the /mobile-api prefix for the current proxy, without a trailing /api (callers already add /api). Moving the VPS under the same hostname can preserve installed clients; changing the hostname or incompatible contracts may require a new APK.

Procurement requests are local to `app/(tabs)/procurement.tsx`, not `lib/foodService.ts`. Its receipt-total checkbox preserves automatic/manual mode via the existing vendor-group PATCH; see [receipt-total semantics](data.md#vendor-receipt-total). Older installed clients omit the additive mode fields and preserve an existing override. Shipping the native checkbox requires a new APK; source/type checks do not publish an app update.

Committed package.json declares Expo ~54.0.37, while [mobile instructions](../../../mobile/AGENTS.md) link v56 docs. Resolve this mismatch using the actual lockfile/installed SDK before mobile code work; no upgrade is implied.

Build, signing, EAS Cloud ownership and update publication: [Android releases](../../mobile-apk-release.md). Development commands and isolation: [local setup](../platform/local-setup.md), [testing](../platform/testing.md).
