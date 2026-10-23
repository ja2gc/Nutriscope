# Environment settings

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: identify where settings belong. This is a variable-name map; supply secret values yourself through a password manager or protected server files. See [integrations](integrations.md) for account/key setup.

## Precedence and files

- [backend/.env.production.example](../../../backend/.env.production.example) is the production template. Copy it manually to backend/.env.production on the server; never commit the real file.
- [Production Compose](../../../docker-compose.prod.yml) loads that file for backend, worker, scheduler and release, **then overrides MAIL_MAILER and ANTHROPIC_MODEL** in its shared environment. Change those production settings in Compose.
- Laravel [services.php](../../../backend/config/services.php) reads ANTHROPIC_MODEL for non-Compose/local deployments; [backend/.env.example](../../../backend/.env.example) remains the local template.
- DB_ROOT_PASSWORD is required by Compose interpolation for MySQL. It does not come from the backend service's env_file. Supply it in the operator shell or approved secret manager; the current workflow passes the GitHub secret.
- [frontend/.env.example](../../../frontend/.env.example) defines server-only LARAVEL_API_URL. Production Compose supplies http://backend/api.
- [mobile/.env.example](../../../mobile/.env.example) and [mobile/eas.json](../../../mobile/eas.json) govern EXPO_PUBLIC_API_URL; profile env values must be reviewed before builds. Public mobile/build variables are visible to users, so never put keys there.

## Required configuration groups

| Setting names | Where/how to choose |
|---|---|
| APP_KEY | Generate once for a new installation; securely preserve it for an existing DB/encrypted files |
| APP_ENV, APP_DEBUG, APP_URL, FRONTEND_URL | Production mode, debug off, canonical HTTPS site and correct reset-link origin |
| DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD | Actual MySQL service/account. Current example uses bundled mysql/root; a separate DB account needs explicit provisioning/grant review |
| DB_ROOT_PASSWORD | Compose/MySQL initialization and deployment secret; match DB_PASSWORD if using the example's root account |
| DB_DUMP_SKIP_SSL, DB_DUMP_SSL_FLAG | Bundled MariaDB dump client options: review private-container certificate compatibility; never disable TLS for a remote managed DB by habit |
| CACHE_STORE, CACHE_PREFIX, REDIS_CLIENT, REDIS_HOST, REDIS_PORT, REDIS_PASSWORD | Shared Redis locks/cache; current Compose service hostname is redis |
| QUEUE_CONNECTION, REDIS_QUEUE_RETRY_AFTER | redis, and retry-after longer than the 1200-second worker timeout (example 1260) |
| APP_MAINTENANCE_DRIVER, APP_MAINTENANCE_STORE | cache/redis for coordinated maintenance across containers |
| SESSION_*, SANCTUM_STATEFUL_DOMAINS | Domain/cookie settings; don't retain an old domain when moving names |
| ANTHROPIC_API_KEY, USDA_API_KEY | Institution-owned provider keys, backend only |
| RESEND_API_KEY, RESEND_API_ENDPOINT, MAIL_FROM_ADDRESS, MAIL_FROM_NAME | Verified sender/domain; mailer is pinned by production Compose |
| UPLOADS_DISK, UPLOADS_PUBLIC_URL | Legacy/public upload routing and optional public origin |
| PRIVATE_UPLOADS_DISK, PRIVATE_UPLOADS_DRIVER | private_uploads and local for the named-volume setup; s3 only after storage migration |
| PRIVATE_UPLOADS_ACCESS_KEY_ID, PRIVATE_UPLOADS_SECRET_ACCESS_KEY, PRIVATE_UPLOADS_REGION, PRIVATE_UPLOADS_BUCKET, PRIVATE_UPLOADS_ENDPOINT, PRIVATE_UPLOADS_USE_PATH_STYLE_ENDPOINT | Conditional private S3/R2 primary storage; leave credentials blank with local storage |
| BACKUP_DISK, BACKUP_FILESYSTEM_DRIVER, BACKUP_NAME, BACKUP_QUEUE, BACKUP_TIMEZONE | Backup disk/driver/naming/worker scheduling |
| BACKUP_ACCESS_KEY_ID, BACKUP_SECRET_ACCESS_KEY, BACKUP_DEFAULT_REGION, BACKUP_BUCKET, BACKUP_ENDPOINT, BACKUP_USE_PATH_STYLE_ENDPOINT | Separate private backup storage account/bucket/access |
| BACKUP_ARCHIVE_PASSWORD | Separate archive encryption password; retain passwords needed for historical backups |
| BACKUP_ALERT_EMAIL, BACKUP_RESTORE_ENABLED | Alert recipient; leave restore disabled until independent recovery readiness is established |
| AUDIT_DATABASE_DISK_USED_PERCENT, AUDIT_RETENTION_ENABLED, AUDIT_EXPORT_ENABLED | [audit config](../../../backend/config/audit.php); retention's saved database state can supersede the initial env default |
| LOG_*, APP_LOCALE, APP_FALLBACK_LOCALE, BCRYPT_ROUNDS | Framework logging/locale/password configuration in backend/config |

Other keys are declared in [config](../../../backend/config); do not remove a setting merely because a production default hides it. The production template omits SMTP-only fields and VITE_APP_NAME for this deployment; switching mail transports requires a reviewed config change.

After backend environment changes, recreate all backend roles so env_file is reloaded. A mere docker restart does not reload container environment. Laravel config cache is rebuilt by [entrypoint](../../../backend/docker-entrypoint.sh); long-running workers/scheduler must use the same new settings.
