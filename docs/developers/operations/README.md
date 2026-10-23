# Operations

Verified against committed source: `59836bdef23642b1642848270bdcdf634b09afd7` plus the CI Compose validation fix in this working tree (2026-10-23).

Purpose: find deployment, jobs, files, reports and recovery owners.

- Hosting: [VPS setup](../../operations/vps-setup.md); accounts and migration: [turnover](../../operations/turnover.md); secrets/settings: [environment](environment.md), [integrations](integrations.md).
- [Production Compose](../../../docker-compose.prod.yml) declares HTTP, backups/default worker, scheduler and one-off release roles. [bootstrap/app.php](../../../backend/bootstrap/app.php) is the schedule authority for menu maintenance, follow-ups, report files/census, backups and audit retention.
- [Deploy workflow](../../../.github/workflows/deploy.yml) builds images on the Actions runner and transfers them over fingerprint-verified SSH. [ops/deploy.sh](../../../ops/deploy.sh) owns image loading, revision/health checks, rollback tags and successful-deploy image retention. Run its isolated failure-path tests with `bash ops/tests/deploy.test.sh`.
- [CI Compose override](../../../ops/compose-validation.yml) removes production env-file references only for offline configuration validation. Never include it in a production command; normal deployment still requires the production env file.
- [StoredObjectStorage](../../../backend/app/Services/StoredObjectStorage.php), [filesystems config](../../../backend/config/filesystems.php) and [upload limits](../../../backend/config/uploads.php) own durable file routing and limits.
- [ReportController](../../../backend/app/Http/Controllers/ReportController.php), [Reports](../../../backend/app/Services/Reports) and [report config](../../../backend/config/nutriscope-reports.php) own rendering, archives and cache. [ReportPreview](../../../frontend/components/ReportPreview.tsx) is the browser consumer.
- [Backup services](../../../backend/app/Services/Backup), [backup settings](../../../backend/config/nutriscope-backups.php) and [backup filesystem](../../../backend/config/filesystems.php) own recovery paths; see [backup/recovery](../../operations/backup-recovery.md).

The worker timeout is 1200 seconds; the production example uses REDIS_QUEUE_RETRY_AFTER=1260. Check the effective combination before changing it. Scheduler tasks include retention/deletion and recovery-test dispatch; starting a scheduler has side effects.

Keep MySQL, Redis and backend/frontend published ports private to loopback. Host Nginx terminates HTTPS. Primary private uploads have a named volume; report-cache and legacy public storage are not durable shared mounts in current Compose. Inventory all file locations before replacing containers or moving hosts.

Use user www-data for manually entered mutating Artisan commands in running containers. Release entrypoint already drops privileges for its CLI. A DB rollback does not reverse object operations; image rollback does not roll back schema/data.

Production Compose bounds each container's logs with Docker's `local` driver (three 10m files), routes Laravel logs through its existing stderr channel, and sets memory ceilings. [Apache prefork settings](../../../backend/apache-prefork.conf) cap the HTTP worker pool at four and recycle children after 500 connections. These are initial limits for isolation, not a load-tested capacity guarantee; see [VPS operations](../../operations/vps-setup.md#7-future-releases) for tuning and rollout checks. Existing hourly queue recycling stays in place.

After successful health/image verification, the deploy script removes only dangling images labeled `io.nutriscope.app=true` older than 168 hours. Current and `:rollback` image tags are retained, including on same-revision redeploys. It never prunes volumes, build cache, or legacy unlabeled images. Broader Docker cleanup is not routine maintenance permission. Never remove volumes or ignored directories without checking ownership, purpose and backups. Local output/ and tmp/ are Git-ignored working directories; `.deploy/` holds the temporary image transfer archive.

Related: [platform contract](../../operations/platform-requirements.md), [Android releases](../../mobile-apk-release.md).

## Changing reports and operational behavior

For PDF layout/text, start with [Blade report views](../../../backend/resources/views/reports) and [generators](../../../backend/app/Services/Reports/Generators). Follow [ReportService](../../../backend/app/Services/Reports/ReportService.php), [ReportTemplateController](../../../backend/app/Http/Controllers/ReportTemplateController.php) and [ReportBrandingController](../../../backend/app/Http/Controllers/ReportBrandingController.php) for template/branding inputs; inspect [ReportArchiveSettingController](../../../backend/app/Http/Controllers/Admin/ReportArchiveSettingController.php) for archive settings. Check which configuration is current versus snapshotted before changing historical output. Generate and inspect the affected PDF with synthetic data rather than relying only on HTML or tests.

For a schedule change, read bootstrap/app.php and the actual command/job, its queue, timeout, locks and side effects. For file changes, trace StoredObjectStorage and its caller through authorized download, cleanup and backup handling; changing a disk setting does not migrate existing bytes. See [backup/recovery](../../operations/backup-recovery.md) for evidence required before relying on a restore.
