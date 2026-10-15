# Deployment, Production, Backup, and Release

## Production operations

- Label commands read-only or state-changing before giving them to the user.
- Copy-paste commands must be plain valid shell: no HTML entities, doubled escapes, or broken continuations.
- Diagnose CPU, memory, swap, disk, network, Docker, service health, and logs before credential/code changes.
- Inspect current Compose, Dockerfiles, workflows, and running image IDs. Do not revive stale-doc-only services.
- Build, deploy start, health, and live behavior are separate checks.

## Environment and secrets

- Compare config by redacted names/status only; never read or print production values.
- Preserve production `APP_KEY`; do not replace live config with an example.
- Require only integrations actually used by the current runtime.

## Backup and recovery

- Backup job or R2 object alone is not recovery proof.
- Recovery readiness needs harmless upload inclusion, valid archive, retention/cleanup behavior, and restore verification in a temporary target.
- Recovery testing is not production cutover. Keep safety snapshot and provider switching explicit.
- Never expose archives, keys, checksums, credentials, or private object locations.

## Mobile and live release

- Shared mobile-contract/release change → update metadata, run tests/type checks, build/publish through existing workflow, then verify version/versionCode, APK bytes/MIME/header/checksum, and QR target.
- EAS/build completion alone is insufficient.
- Live acceptance uses deployed URL, fictional data, exact revision, and recorded pass/fail scenarios. Unit/local/CI/deploy/health checks are not live acceptance.

## Temporary Android build path (2026-10-15 through 2026-10-31)

- Expo's Android Free-plan build quota is exhausted until 2026-11-01. Do not start another EAS cloud build before that date.
- Until 2026-11-01, build Android APKs locally from the checked-out `mobile/` source. Match the intended EAS profile's public build settings explicitly, especially `EXPO_PUBLIC_API_URL`; the local build itself does not imply local or production data.
- Publish a verified local APK through the existing release workflow. The workflow's HTTPS artifact URL must be reachable by its GitHub runner; do not point the public download page or QR at a workstation path. A public update must use the existing production signing certificate; a debug-signed local APK is only for emulator testing. Update release metadata only after the APK version, versionCode, signing certificate, and SHA-256 are verified.
- From 2026-11-01, EAS cloud builds may resume. Confirm quota reset before switching back.
