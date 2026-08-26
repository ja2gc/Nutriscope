# NutriScope FSS APK Release

The permanent staff handoff is `https://nutriscope.live/mobile-app`. Its QR code always points there. Phones download `https://nutriscope.live/downloads/nutriscope-fss.apk`; the app checks `https://nutriscope.live/downloads/nutriscope-fss.json`.

For each release:

1. Increase `mobile/app.json` `expo.version` and `android.versionCode`.
2. Run `eas build --platform android --profile preview` from `mobile` using the existing package and signing credentials.
3. Download the APK and calculate its SHA-256.
4. Add or update `mobile/release.json` with the EAS artifact URL, version, version code, and lowercase SHA-256, then push it to `main`. The **Publish FSS Android release** GitHub Actions workflow verifies the file and atomically replaces `/var/www/nutriscope-downloads/nutriscope-fss.apk` and its JSON metadata through the existing deployment SSH secret. A manual workflow dispatch accepts the same values when GitHub CLI or the Actions UI is available.
5. The workflow publishes `/var/www/nutriscope-downloads/nutriscope-fss.json` in this shape:

```json
{
  "artifact_url": "REPLACE_WITH_EAS_APK_URL",
  "version": "1.2.1",
  "version_code": 5,
  "sha256": "REPLACE_WITH_APK_SHA256"
}
```

The public metadata is:

```json
{
  "version": "1.2.1",
  "version_code": 5,
  "download_url": "https://nutriscope.live/downloads/nutriscope-fss.apk",
  "sha256": "REPLACE_WITH_APK_SHA256",
  "published_at": "REPLACE_WITH_RELEASE_TIMESTAMP"
}
```

6. Verify the JSON version, APK MIME type, checksum, phone download button, desktop QR, and in-app update check.

Keep Android package `live.nutriscope.fss` and the same signing key so Android installs the release as an update. Sideloaded APKs still require Android's normal install-source approval; a stable QR cannot remove that operating-system prompt.
