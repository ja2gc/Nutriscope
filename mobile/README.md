# NutriScope food-service app

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Expo provides the native food-service API consumer; RND/Admin use the web console.

- [Mobile maintenance and API contracts](../docs/developers/food-service/mobile.md)
- [Local setup](../docs/developers/platform/local-setup.md)
- [EAS Cloud, signing, ownership transfer and APK updates](../docs/mobile-apk-release.md)
- [Safe checks](../docs/developers/platform/testing.md)

The committed manifest and lockfile define the SDK. Phone API URLs must be reachable from the phone. Never put provider secrets in EXPO_PUBLIC variables.
