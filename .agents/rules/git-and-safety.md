# Git, Safety, Privacy, and Secrets

## Working tree

- Check `git status` before edit. Existing changes = user-owned unless proven otherwise.
- Preserve unrelated work: no overwrite/reformat/stage/discard/move/commit.
- Use `apply_patch` for deliberate text edits. Prefer `rg` and `rg --files` for discovery.
- No destructive Git (`git reset --hard`, `git checkout --`) without exact request.
- Forward-only migrations; never rewrite applied migration.
- `migrate:fresh` only confirmed disposable local/test/demo DB. Never shared/prod.
- Stage task files only. Neutral Conventional Commit; no AI/assistant/co-author/contributor/generated-by/branch attribution.
- Commit/push/deploy/publish/private-source upload/external mutation only when requested/approved.

## Secrets and privacy

- Never expose/request/print/log/commit secrets/tokens/credentials/prod `.env` values/object keys/checksums/backups.
- Env drift: variable names + `SET`/`BLANK`/`MISSING` only; classify required/optional/defaulted.
- Never copy env example over live config. Preserve `APP_KEY` for encrypted data.
- Tests/screens/demos/live checks: fictional accounts + synthetic patient/receipt/financial/clinical data.

## Destructive operations

- Resolve exact targets before delete/reset/migration/cleanup/broad storage action.
- Material destruction needs action-time confirmation unless exact action just authorized.
- DB deletion ≠ object deletion. Verify references + bytes; avoid orphans/shared-file loss.
- After material delete: report target + recoverability.
