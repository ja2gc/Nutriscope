# Operations

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: find deployment, jobs, files, reports and recovery owners.

- Hosting: [VPS setup](../../operations/vps-setup.md); accounts and migration: [turnover](../../operations/turnover.md); secrets/settings: [environment](environment.md), [integrations](integrations.md).
- [Production Compose](../../../docker-compose.prod.yml) declares HTTP, backups/default worker, scheduler and one-off release roles. [bootstrap/app.php](../../../backend/bootstrap/app.php) is the schedule authority for menu maintenance, follow-ups, report files/census, backups and audit retention.
- [StoredObjectStorage](../../../backend/app/Services/StoredObjectStorage.php), [filesystems config](../../../backend/config/filesystems.php) and [upload limits](../../../backend/config/uploads.php) own durable file routing and limits.
- [ReportController](../../../backend/app/Http/Controllers/ReportController.php), [Reports](../../../backend/app/Services/Reports) and [report config](../../../backend/config/nutriscope-reports.php) own rendering, archives and cache. [ReportPreview](../../../frontend/components/ReportPreview.tsx) is the browser consumer.
- [Backup services](../../../backend/app/Services/Backup), [backup settings](../../../backend/config/nutriscope-backups.php) and [backup filesystem](../../../backend/config/filesystems.php) own recovery paths; see [backup/recovery](../../operations/backup-recovery.md).

The worker timeout is 1200 seconds; the production example uses REDIS_QUEUE_RETRY_AFTER=1260. Check the effective combination before changing it. Scheduler tasks include retention/deletion and recovery-test dispatch; starting a scheduler has side effects.

Keep MySQL, Redis and backend/frontend published ports private to loopback. Host Nginx terminates HTTPS. Primary private uploads have a named volume; report-cache and legacy public storage are not durable shared mounts in current Compose. Inventory all file locations before replacing containers or moving hosts.

Use user www-data for manually entered mutating Artisan commands in running containers. Release entrypoint already drops privileges for its CLI. A DB rollback does not reverse object operations; image rollback does not roll back schema/data.

Docker cleanup/pruning is not routine maintenance permission. Never remove volumes or ignored directories without checking ownership, purpose and backups. Local output/ and tmp/ are Git-ignored working directories.

Related: [platform contract](../../operations/platform-requirements.md), [Android releases](../../mobile-apk-release.md).

## Changing reports and operational behavior

For PDF layout/text, start with [Blade report views](../../../backend/resources/views/reports) and [generators](../../../backend/app/Services/Reports/Generators). Follow [ReportService](../../../backend/app/Services/Reports/ReportService.php), [ReportTemplateController](../../../backend/app/Http/Controllers/ReportTemplateController.php) and [ReportBrandingController](../../../backend/app/Http/Controllers/ReportBrandingController.php) for template/branding inputs; inspect [ReportArchiveSettingController](../../../backend/app/Http/Controllers/Admin/ReportArchiveSettingController.php) for archive settings. Check which configuration is current versus snapshotted before changing historical output. Generate and inspect the affected PDF with synthetic data rather than relying only on HTML or tests.

For a schedule change, read bootstrap/app.php and the actual command/job, its queue, timeout, locks and side effects. For file changes, trace StoredObjectStorage and its caller through authorized download, cleanup and backup handling; changing a disk setting does not migrate existing bytes. See [backup/recovery](../../operations/backup-recovery.md) for evidence required before relying on a restore.
