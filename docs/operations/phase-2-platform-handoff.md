# Phase 2 platform and backup handoff

Phase 1.5 provides provider-neutral private storage, file-aware verified restore points, and staged recovery orchestration. Phase 2 is limited to provider selection, client-owned account setup, production deployment, secrets, DNS/HTTPS cutover, one verified backup, a recovery drill, acceptance, and old-droplet retirement.

## Starting revision

Start from the pushed `main` commit referenced by the annotated tag `phase-1.5-complete`. Verify it with `git rev-parse phase-1.5-complete^{commit}` and confirm that commit is reachable from `origin/main`. The tag avoids placing a commit's own hash inside itself.

## Client-owned decisions and accounts

The selected first deployment keeps the existing DigitalOcean Droplet, Docker MySQL and Redis, and primary private uploads on the persistent `nutriscope_private_uploads` volume. Encrypted backup archives and protected upload copies use a private Cloudflare R2 bucket outside DigitalOcean. The client still owns hosting/billing, domain/DNS, mail delivery, and alert mailbox access. If primary uploads later move to object storage, use a separate bucket and credentials that cannot delete backups.

Enable MFA, store recovery codes with the client, name at least two authorized account administrators, and confirm billing/outage contacts. Check current official provider pricing and behavior before purchase. Provider snapshots, object versioning, lifecycle rules, and bucket locks are additional safeguards, not replacements for NutriScope restore points.

## Secrets and deployment

Use `backend/.env.production.example` and keep the live file root-owned and unreadable to other system users. Preserve `APP_KEY`. Set `PRIVATE_UPLOADS_DRIVER=local`; the unused private-upload object-storage credential fields remain blank. Store Cloudflare R2 credentials only in `BACKUP_*`. Never expose `.env`, archive passwords, SMTP credentials, database administration credentials, or object-storage keys in Git or the Admin UI.

Configure web, worker, scheduler, release, MySQL, Redis, the persistent private-upload volume, Cloudflare R2 backup storage, email, and public `/up`. Keep `REDIS_QUEUE_RETRY_AFTER=1260`, which exceeds the 1200-second backup-worker job timeout. Use Redis-backed maintenance mode across the web and worker containers. The DigitalOcean single-Droplet `EnvironmentSwitcher` remains disabled through `BACKUP_RESTORE_ENABLED=false` until the temporary-database recovery test and all storage checks pass; do not bypass the temporary-database-first workflow.

Production Compose requires `DB_ROOT_PASSWORD` and removes MySQL's empty-password initialization flag. Keep the matching application database password in the protected production environment. Deployment accepts fast-forward Git updates only, retains one prior backend and frontend image as rollback candidates, and must pass the internal Laravel `/up` check before reporting success.

## Existing domain facts to re-check

Prior inspection found Name.com nameservers and `nutriscope.live`/`www` resolving to `168.144.115.27`, with DMARC `p=none`. These facts can change. Re-query all DNS records immediately before cutover, lower TTL in advance, issue a new certificate at the destination, and do not copy the old certificate private key.

## Acceptance sequence

1. Verify the Phase 1.5 tag and pushed `main` revision.
2. Copy any existing container-local private uploads into `nutriscope_private_uploads`, deploy, and run the release process once.
3. Verify `/up`, workers, scheduler heartbeat, Redis locks, private uploads, mail, and role workflows.
4. Migrate legacy sensitive files with `php artisan storage:migrate-private-objects` and confirm no required public bytes remain.
5. Create one manual restore point and confirm archive, manifest, uploaded-file copies, and verification.
6. Complete a disposable temporary-MySQL recovery drill with non-mutating checks.
7. Set `BACKUP_RESTORE_ENABLED=true`, recreate the backend containers so cached configuration changes, then exercise one staged production switch and automatic rollback drill with an approved disposable marker.
8. Cut DNS/HTTPS only after deployment, backup, uploaded-file recovery, and recovery checks pass.
9. Obtain client acceptance for application health and temporary-database recovery.
10. Retire the old DigitalOcean droplet only after all checks and DNS propagation pass.

## Prompt for the Phase 2 session

> Continue NutriScope Phase 2 from `phase-1.5-complete`. Read the current platform requirements, backup/recovery guide, and this handoff; do not read the outdated `deployment.md`. Verify the tag and `origin/main`, compare current provider options using official sources, and guide client-owned setup, secrets, deployment, private storage, DNS/HTTPS, one verified restore point, provider switching, rollback, and temporary-database recovery acceptance. Do not expose secrets or delete the old droplet before every acceptance check passes.
