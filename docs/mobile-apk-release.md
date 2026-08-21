# NutriScope FSS APK Release

The permanent staff handoff is `https://nutriscope.live/mobile-app`. Its QR code always points there. Phones download `https://nutriscope.live/downloads/nutriscope-fss.apk`; the app checks `https://nutriscope.live/downloads/nutriscope-fss.json`.

For each release:

1. Increase `mobile/app.json` `expo.version` and `android.versionCode`.
2. Run `eas build --platform android --profile production` from `mobile` using the existing package and signing credentials.
3. Download the APK and calculate its SHA-256.
4. Upload it as `/var/www/nutriscope-downloads/nutriscope-fss.apk`.
5. Upload `/var/www/nutriscope-downloads/nutriscope-fss.json` with:

```json
{
  "version": "1.2.0",
  "version_code": 4,
  "download_url": "https://nutriscope.live/downloads/nutriscope-fss.apk",
  "sha256": "REPLACE_WITH_APK_SHA256",
  "published_at": "2026-08-21T00:00:00+08:00"
}
```

6. Verify the JSON version, APK MIME type, checksum, phone download button, desktop QR, and in-app update check.

Keep Android package `live.nutriscope.fss` and the same signing key so Android installs the release as an update. Sideloaded APKs still require Android's normal install-source approval; a stable QR cannot remove that operating-system prompt.
