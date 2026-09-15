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
