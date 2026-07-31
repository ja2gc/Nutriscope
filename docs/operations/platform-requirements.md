# NutriScope platform requirements

This Phase 1 hosting contract describes what a new platform must provide without choosing a vendor.

## Required services

- **Web:** run Laravel and Next.js continuously. Route `/mobile-api/` to the backend and `/` to the frontend.
- **Worker:** continuously process the Laravel `backups,default` queues.
- **Scheduler:** continuously run `php artisan schedule:work`.
- **Release:** run `/usr/local/bin/docker-release.sh` once per release. Do not run migrations whenever web starts.
- **Database:** MySQL 8 with persistent storage.
- **Cache and queue:** Redis with persistent storage. Redis is not a MySQL backup.
- **Health check:** use the backend endpoint `/up`.

`docker-compose.prod.yml` defines the web, worker, scheduler, and release roles. A managed platform may express the same roles with its own process configuration.

## Production configuration

Start from `backend/.env.production.example`. Enter values in the platform's encrypted secret manager. Never commit production values or paste them into documentation, tickets, screenshots, or chat.

Important groups are `APP_*`, `DB_*`, `REDIS_*`, `CACHE_*`, `SESSION_*`, `QUEUE_*`, `MAIL_*`, `UPLOADS_DISK`, and `BACKUP_*`. Keep the existing `APP_KEY` during relocation so existing encrypted application data remains readable.

## Storage boundaries

- Database backups use the private `BACKUP_DISK`, normally a separate S3-compatible bucket.
- Purchase-order attachments and branding assets use `UPLOADS_DISK`. The default `public` disk requires persistent Laravel storage.
- Clinical documents remain private local files. Managed hosting must provide a persistent private volume. Moving them to object storage needs a separate security-reviewed change.
- Generated reports remain temporary artifacts and are not part of the database backup.

Do not place backups on the application server's disk. Loss of one provider account or disk must not destroy both the live system and recovery copies.

## Network and HTTPS

Only the public reverse proxy should accept internet traffic. MySQL and Redis must remain private. Restrict administrative access, use MFA, and apply provider security updates.

On managed hosting, let the platform issue and renew HTTPS certificates after DNS points to it. Do not copy the old Certbot private key. On a self-managed server, issue a fresh certificate and verify automatic renewal before cutover.

## Release check

1. Run the release process once.
2. Confirm `/up` is healthy.
3. Confirm the worker and scheduler are running.
4. Sign in with a test account and verify one critical workflow.
5. Create and verify a backup before changing DNS.
