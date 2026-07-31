# Phase 2 platform and backup handoff

Phase 1 makes NutriScope portable. Phase 2 chooses providers, enters client-owned credentials, migrates the system, and proves recovery before retiring the old server.

## Decisions the client must own

- Hosting provider and billing plan
- Managed MySQL and Redis, or equivalent persistent services
- Private S3-compatible backup storage in a different failure domain from hosting
- Persistent private storage for clinical documents
- Public upload storage for purchase-order attachments and branding assets
- Domain registrar, DNS, email delivery, and alert mailbox access

Use client-owned accounts wherever possible. Enable MFA, store recovery codes with the client, name at least two authorized administrators, and confirm who receives billing and outage notices. A listed monthly plan is normally recurring; usage-based bandwidth, database, and object-storage charges may be additional. Confirm current pricing before purchase.

## Credentials to configure

Use the platform's encrypted secret manager and `backend/.env.production.example`. Backup variable names include `BACKUP_DISK`, `BACKUP_ACCESS_KEY_ID`, `BACKUP_SECRET_ACCESS_KEY`, `BACKUP_BUCKET`, `BACKUP_ENDPOINT`, `BACKUP_ARCHIVE_PASSWORD`, and `BACKUP_ALERT_EMAIL`. Never put values in Git.

Set `UPLOADS_DISK` only after testing the selected storage. A database backup does not replace an upload-storage policy.

## Existing-domain facts to re-check at migration time

These values came from the prior server inspection and can change:

- DNS provider/registrar: Name.com
- Nameservers: `ns1kwy.name.com`, `ns4dfh.name.com`, `ns3gmt.name.com`, `ns2hkt.name.com`
- `nutriscope.live` previously resolved to `168.144.115.27`
- `www.nutriscope.live` previously resolved to the same address; no CNAME was returned
- DMARC previously used `v=DMARC1; p=none;`

Re-query all DNS records immediately before cutover. Lower TTL in advance if available. Configure the new target and HTTPS, then update apex and `www`. Keep the old service available until propagation and acceptance finish.

The saved production environment, Nginx configuration, and Let's Encrypt logs are reference material. Do not upload them to Git. Do not move the old certificate private key to managed hosting; let the destination issue a new certificate.

## Migration acceptance

1. Record the Phase 1 revision with `git rev-parse HEAD` and confirm it exists on `main`.
2. Configure web, worker, scheduler, release, MySQL, Redis, uploads, email, and backup storage.
3. Run the release process and verify `/up`.
4. Verify web and mobile sign-in plus critical role workflows.
5. Create a backup in the admin page and confirm it becomes verified.
6. Restore that archive into a temporary MySQL database and complete recovery checks.
7. Change DNS only after the destination and recovery test pass.
8. Keep the old droplet powered on until client sign-off.

## Prompt for the Phase 2 session

> Continue NutriScope Phase 2 from the latest pushed `main`. Read `docs/operations/platform-requirements.md`, `docs/operations/backup-recovery.md`, and `docs/operations/phase-2-platform-handoff.md`; do not read the outdated `deployment.md`. First inspect current code and verify `git rev-parse HEAD`. Help me compare current low-cost managed hosting, MySQL/Redis, and separate S3-compatible backup options using official sources. Then guide me through client-owned account setup, secrets, deployment, DNS/HTTPS cutover, one verified backup, and a temporary-database recovery drill. Do not expose secrets or delete the old droplet until acceptance checks pass.
