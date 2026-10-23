# Food-service mobile app

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: maintain the Expo food-service API consumer and distribute updates.

- [app/_layout.tsx](../../../mobile/app/_layout.tsx) owns root navigation, auth subscription and React Query.
- [app/(tabs)](<../../../mobile/app/(tabs)>) owns menu, procurement, meal preparation and accomplishment screens.
- [lib/auth.ts](../../../mobile/lib/auth.ts) stores tokens with SecureStore; [lib/api.ts](../../../mobile/lib/api.ts) injects bearer auth and clears it on 401.
- [foodService](../../../mobile/lib/foodService.ts) and [mobileContracts](../../../mobile/lib/mobileContracts.ts) describe consumers, not authoritative business rules.
- [appUpdate](../../../mobile/lib/appUpdate.ts) checks published APK metadata. It is separate from Expo EAS Update.
- [app.json](../../../mobile/app.json) owns package/version/project identity; [eas.json](../../../mobile/eas.json) owns build profiles and API URLs.

To change an endpoint, trace mobile and web consumers against the backend. EXPO_PUBLIC_API_URL is baked into builds: preserve the /mobile-api prefix for the current proxy, without a trailing /api (callers already add /api). Moving the VPS under the same hostname can preserve installed clients; changing the hostname or incompatible contracts may require a new APK.

Committed package.json declares Expo ~54.0.37, while [mobile instructions](../../../mobile/AGENTS.md) link v56 docs. Resolve this mismatch using the actual lockfile/installed SDK before mobile code work; no upgrade is implied.

Build, signing, EAS Cloud ownership and update publication: [Android releases](../../mobile-apk-release.md). Development commands and isolation: [local setup](../platform/local-setup.md), [testing](../platform/testing.md).
