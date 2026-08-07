# Phase 2 platform and backup handoff

Phase 1.5 provides provider-neutral private storage, file-aware verified restore points, and staged recovery orchestration. Phase 2 is limited to provider selection, client-owned account setup, production deployment, secrets, DNS/HTTPS cutover, one verified backup, a recovery drill, acceptance, and old-droplet retirement.

## Starting revision

Start from the pushed `main` commit referenced by the annotated tag `phase-1.5-complete`. Verify it with `git rev-parse phase-1.5-complete^{commit}` and confirm that commit is reachable from `origin/main`. The tag avoids placing a commit's own hash inside itself.

## Client-owned decisions and accounts

The client owns hosting/billing, managed MySQL and Redis, private S3-compatible primary-upload storage, separate private backup storage, domain/DNS, mail delivery, and alert mailbox access. Use separate buckets or least-privilege credentials so upload compromise cannot delete backups. Keep backup storage in another failure domain where practical.

Enable MFA, store recovery codes with the client, name at least two authorized account administrators, and confirm billing/outage contacts. Check current official provider pricing and behavior before purchase. Provider snapshots, object versioning, lifecycle rules, and bucket locks are additional safeguards, not replacements for NutriScope restore points.

## Secrets and deployment

Use the platform secret manager and `backend/.env.production.example`. Configure `PRIVATE_UPLOADS_*` and `BACKUP_*` separately. Never expose `.env`, `APP_KEY`, archive passwords, SMTP credentials, database administration credentials, or object-storage keys in Git or the Admin UI.

Configure web, worker, scheduler, release, MySQL, Redis, private uploads, backup storage, email, and `/up`. Implement the Phase 1.5 `EnvironmentSwitcher` contract for the selected provider; do not bypass the temporary-database-first workflow or write provider logic into generic storage services.

## Existing domain facts to re-check

Prior inspection found Name.com nameservers and `nutriscope.live`/`www` resolving to `168.144.115.27`, with DMARC `p=none`. These facts can change. Re-query all DNS records immediately before cutover, lower TTL in advance, issue a new certificate at the destination, and do not copy the old certificate private key.

## Acceptance sequence

1. Verify the Phase 1.5 tag and pushed `main` revision.
2. Deploy to client-owned services and run the release process once.
3. Verify `/up`, workers, scheduler heartbeat, Redis locks, private uploads, mail, and role workflows.
4. Migrate legacy sensitive files with `php artisan storage:migrate-private-objects` and confirm no required public bytes remain.
5. Create one manual restore point and confirm archive, manifest, uploaded-file copies, and verification.
6. Complete a disposable temporary-MySQL recovery drill with non-mutating checks.
7. Configure and exercise staged switching and automatic rollback using the chosen provider.
8. Cut DNS/HTTPS only after deployment, backup, uploaded-file recovery, and recovery checks pass.
9. Obtain client acceptance for application health and temporary-database recovery.
10. Retire the old DigitalOcean droplet only after all checks and DNS propagation pass.

## Prompt for the Phase 2 session

> Continue NutriScope Phase 2 from `phase-1.5-complete`. Read the current platform requirements, backup/recovery guide, and this handoff; do not read the outdated `deployment.md`. Verify the tag and `origin/main`, compare current provider options using official sources, and guide client-owned setup, secrets, deployment, private storage, DNS/HTTPS, one verified restore point, provider switching, rollback, and temporary-database recovery acceptance. Do not expose secrets or delete the old droplet before every acceptance check passes.
