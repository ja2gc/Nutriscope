# Deployment, Production, Backup, and Release

## Production operations

- Before user runs command: label read-only vs state-changing.
- Copy-paste commands: no HTML entities, doubled escapes, broken shell continuations.
- Diagnose CPU/memory/swap/disk/network/Docker/service health/logs before credential/code changes.
- Inspect current Compose. Never revive stale-doc-only services.
- GitHub Actions change only when scope + proven root cause require.
- Build ≠ deploy success. Verify release/migration exit, containers, health, running image IDs, revision, public behavior.

## Environment and secrets

- Config compare: never read/print prod `.env` values. Use redacted skeleton; names/status only.
- Preserve prod `APP_KEY`. Never replace live config with example.
- Classify keys by runtime need; do not demand unused optional integrations.

## Backup and recovery

- Backup job/R2 object ≠ recovery proof.
- Readiness proof: harmless upload included, archive valid, retention/cleanup valid, temporary-MySQL restore passes.
- Recovery test ≠ prod cutover. Keep safety snapshot + provider switching explicit.
- Never expose archives/keys/checksums/credentials/private object locations.

## Mobile release

- Mobile/shared-contract change + delivery scope → bump release metadata, run tests + TypeScript, build/publish APK through existing workflow.
- Verify QR points to latest stable APK/download page. Verify version/versionCode, bytes, Android MIME/header, checksum. EAS completion alone insufficient.

## Live acceptance

- Test deployed URL, not localhost; fictional data only.
- Record URL, revision, passed/failed scenarios.
- Unit/local/CI/deploy-start/health ≠ live acceptance.
