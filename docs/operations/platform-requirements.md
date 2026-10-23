# Platform requirements

Verified against committed source: `548c0e3268a8b7d26b598caf287fa11f6d236725` plus the deployment/resource changes in this working tree (2026-10-23).

Purpose: preserve the current service contract when changing hosting providers.

| Role | Requirement / source |
|---|---|
| Backend | Long-running Laravel HTTP service, [Dockerfile](../../backend/Dockerfile) |
| Frontend | Next.js standalone server, [Dockerfile](../../frontend/Dockerfile); server-side API origin |
| Database | Persistent MySQL 8; reviewed restore/test grants, no public DB port |
| Cache and queue | Redis with atomic locks and appropriate durability |
| Worker | Continuous queue:work redis for backups,default; timeout 1200 seconds |
| Scheduler | Continuous schedule:work; [actual schedule](../../backend/bootstrap/app.php) includes jobs with data/file/notification effects |
| Release | One-off [docker-release.sh](../../backend/docker-release.sh); migrations are explicit release work |
| Image deployment | Existing Actions runner builds Linux amd64 images; fingerprint-verified SSH transfers a compressed image archive; [ops/deploy.sh](../../ops/deploy.sh) loads and verifies it without server builds. ARM hosts need a reviewed platform-specific build change. |
| Runtime resource bounds | [Production Compose](../../docker-compose.prod.yml) sets per-service memory ceilings and rotating local logs; [Apache pool](../../backend/apache-prefork.conf) permits four HTTP workers. Host Docker/OS memory is outside container limits. |
| Proxy / HTTPS | Host Nginx → frontend; /mobile-api/ and /up → backend; [locations](../../nginx/mobile-api.locations.conf) |
| Primary private files | Persistent named volume or deliberately migrated private S3/R2 bucket |
| Backup destination | Separate private storage, protected credentials/archive password and tested retention/recovery |
| APK distribution | Signed APK + metadata in /var/www/nutriscope-downloads, stable HTTPS routes |
| Outbound integrations | Only the [configured services](../developers/operations/integrations.md); email/AI/USDA and storage access as required |

[Compose](../../docker-compose.yml) plus [production overlay](../../docker-compose.prod.yml) express the current container topology. No OCR/OMR container is required by these files. Report cache is reproducible; private files and database history are not.

Resource sufficiency, managed-provider equivalents, existing schema/grants and recovery completeness are Unverified. Builds run off-host; loading images and running releases still require headroom. Memory ceilings total more than 2 GiB and do not reserve RAM: simultaneous peaks can still exhaust a small host. Do not select a VPS using memory/price assumptions without checking concurrent runtime demand, swap and memory pressure.

Procedures: [VPS setup](vps-setup.md), [turnover](turnover.md), [environment](../developers/operations/environment.md), [backup/recovery](backup-recovery.md).
