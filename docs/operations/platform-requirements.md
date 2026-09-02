# NutriScope platform requirements

This provider-neutral contract defines the production services required after Phase 1.5. Phase 2 selects and configures client-owned providers.

## Required services

- **Web:** run Laravel and Next.js continuously. Route `/mobile-api/` to Laravel and `/` to Next.js.
- **Worker:** continuously process the Laravel `backups,default` queues.
- **Scheduler:** continuously run `php artisan schedule:work`.
- **Release:** run `/usr/local/bin/docker-release.sh` once per release. Do not run migrations whenever web starts.
- **Database:** MySQL 8 with persistent storage and permission to create/drop recovery-test databases.
- **Cache and queue:** persistent Redis supporting atomic locks. Redis is not a MySQL backup.
- **Health check:** use Laravel `/up`.

`docker-compose.prod.yml` expresses the web, worker, scheduler, and release roles. A managed platform may express the same roles differently.

## Production configuration

Start from `backend/.env.production.example` and store values only in the platform secret manager. Never put `.env`, archive passwords, SMTP credentials, database administration credentials, or object-storage keys in Git, documentation, screenshots, chat, or the Admin page. Preserve `APP_KEY` during relocation.

Important groups are `APP_*`, `DB_*`, `REDIS_*`, `CACHE_*`, `SESSION_*`, `QUEUE_*`, `MAIL_*`, `PRIVATE_UPLOADS_*`, and `BACKUP_*`.

## Storage boundaries

- `private_uploads` holds clinical documents, purchase-order evidence, profile photos, private branding, and other durable sensitive files. APIs use authorized streaming. The selected single-Droplet deployment uses the named `nutriscope_private_uploads` Docker volume outside the public web root; a future object-storage deployment must use a private bucket.
- `report_cache` holds reproducible prepared PDFs for 24 hours. It is excluded from durable manifests and backup retention.
- `BACKUP_DISK` holds encrypted MySQL archives, immutable manifests, and checksum-addressed protected uploaded-file copies.
- Backup storage must remain outside the Droplet that holds primary uploads. Backup credentials access only the private backup bucket. If primary uploads later move to object storage, use a separate bucket and credentials that cannot delete backups.
- Only intentionally public assets use public storage. No binary-file framework stores all uploads in MySQL.

Keep backup storage separate from primary application infrastructure where practical. Phase 2 should add provider versioning, lifecycle protection, snapshots, or bucket locks where supported, but application credentials must not control retention locks.

## Network, workers, and HTTPS

Only the public reverse proxy accepts internet traffic. MySQL and Redis remain private. Enable MFA and provider security updates. The worker must be durable; the scheduler must remain active so the ten-minute backup coordinator can catch up after downtime.

Let managed hosting issue a new HTTPS certificate after DNS cutover. Do not copy the old certificate private key. A self-managed host must prove renewal before acceptance.

## Release acceptance

1. Run the release process once and confirm `/up`.
2. Confirm worker, scheduler heartbeat, Redis locks, MySQL, private uploads, and backup storage readiness.
3. Sign in with a test account and verify critical RND, FSS, and Admin workflows.
4. Create one manual restore point and verify its archive, manifest, and protected files.
5. Run a temporary-database recovery drill before changing DNS.
