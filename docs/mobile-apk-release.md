# EAS Cloud and Android app releases

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: transfer build ownership and publish updates to the existing sideloaded Android app. Commands below are operator instructions for build and release work.

## Ownership and prerequisites

Create the receiving institution's Expo organization/account. Prefer transferring the existing EAS project through its settings over creating an unrelated project. Confirm access to the project linked by [app.json](../mobile/app.json), preserve the Android package identity and original production signing keystore, and store credentials in an approved secret manager.

Use [Expo project transfer](https://docs.expo.dev/accounts/account-types/#transfer-projects-between-accounts) and [signing credentials](https://docs.expo.dev/app-signing/app-credentials/). Do not generate a different signing key for installed users: they need the same package/signature to install an update. Existing cloud credits/quota, signing access and device-installed versions are Unverified.

The repository currently has a dated restriction on EAS Cloud builds through 2026-10-31 in [.agents deployment rules](../.agents/rules/deployment-and-release.md). Check the current restriction, quota and human authorization before building; this guide does not override it.

## Configure and build

Work in mobile/. Install from its lockfile, then authenticate and check the intended project before any upload:

```sh
npm ci
npx eas-cli login
npx eas-cli whoami
npx eas-cli project:info
# Inspect/manage credentials privately; never export them into docs or chat:
npx eas-cli credentials --platform android
```

Changing credentials/project settings or building uploads source to an external service. Review [.easignore](../.easignore) and ensure secrets/private data are excluded.

1. Update expo.version and android.versionCode in [mobile/app.json](../mobile/app.json). Use an increasing human version **and** versionCode: [appUpdate.ts](../mobile/lib/appUpdate.ts) compares the version string, not just versionCode.
2. Review [eas.json](../mobile/eas.json). preview and production both currently produce APKs; development is a development client and uses a different API endpoint. For the current proxy, EXPO_PUBLIC_API_URL includes /mobile-api, not /api. Never bake secret keys into EXPO_PUBLIC variables.
3. Run the appropriate [safe checks](developers/platform/testing.md). Preserve backend/web/mobile API compatibility and verify the actual SDK from the lockfile.
4. Once allowed, request the intended cloud build:

```sh
npx eas-cli build --platform android --profile production
```

[Official build setup](https://docs.expo.dev/build/setup/) and [APK configuration](https://docs.expo.dev/build-reference/apk/) explain current prerequisites. Use --local only on a separately prepared supported Android toolchain; that setup is not verified here. A cloud job succeeding is not device acceptance.

## Publish the verified APK

Download the intended signed build into a controlled local directory; verify version, versionCode, signing certificate and checksum. On Linux/macOS use sha256sum FILE.apk (macOS can use shasum -a 256); on PowerShell use Get-FileHash -Algorithm SHA256 -LiteralPath FILE.apk. These commands read the file; compare the result without publishing signing secrets.

Install the build on a test device **over the previous production app**, then verify login, food-service flows, attachments and update behavior. Retain the previous signed APK in approved release storage.

[Publish FSS Android release](../.github/workflows/release-mobile.yml) accepts workflow_dispatch inputs artifact_url, version, version_code and sha256, or a main push changing [mobile/release.json](../mobile/release.json). The artifact URL must be HTTPS and reachable by GitHub's runner without interactive authentication; treat signed/time-limited URLs as sensitive access links.

Release-request shape (placeholders are not runnable values):

```json
{
  "artifact_url": "REPLACE_WITH_ACCESSIBLE_SIGNED_APK_HTTPS_URL",
  "version": "REPLACE_WITH_NEW_VERSION",
  "version_code": 0,
  "sha256": "REPLACE_WITH_64_CHARACTER_LOWERCASE_SHA256"
}
```

Replace version_code with a positive increasing integer. Prefer manual dispatch if the artifact URL is private/signed; do not commit secret-bearing URLs. The workflow checks downloaded bytes, copies through the configured SSH account and replaces the APK then JSON on the host. These are two individual file replacements, **not one atomic pair**. The host publishing account needs controlled write access to /var/www/nutriscope-downloads; it must not be world-writable.

The existing workflow uses DROPLET_HOST, DROPLET_USER and DROPLET_SSH_KEY even on a different VPS provider. The [Nginx download locations](../nginx/mobile-api.locations.conf) must be installed on the new host. Publishing instructions are not permission to push or trigger the workflow.

## Verify public distribution

The current contract uses the site's /mobile-app page, /downloads/nutriscope-fss.apk and /downloads/nutriscope-fss.json. Verify metadata version/versionCode/checksum, APK MIME/header/download, desktop QR, phone install and in-app update check.

If changing the domain, update and test all hardcoded consumers: [appUpdate.ts](../mobile/lib/appUpdate.ts), [FssAppAccess](../frontend/components/mobile-app/FssAppAccess.tsx), [release workflow](../.github/workflows/release-mobile.yml), [EAS profiles](../mobile/eas.json), proxy/domain/env settings and any distributed links. Existing APKs still request the old API/update host; keep compatibility/redirects where applicable. Keeping the domain while moving VPS is usually simpler.

## EAS Update is a different mechanism

This app's checked app.json/eas.json do not establish an EAS Update runtime/channel deployment. Do not assume eas update publishes to installed devices. The current documented path is build a signed APK → publish it → users install it. Adding [EAS Update](https://docs.expo.dev/eas-update/introduction/) requires separately configuring compatible runtimes/channels and building a binary that supports it; it cannot replace arbitrary native/signing changes.

Related: [mobile maintenance](developers/food-service/mobile.md), [turnover](operations/turnover.md), [VPS setup](operations/vps-setup.md).
