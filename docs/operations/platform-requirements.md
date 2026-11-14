# Platform requirements

Preserve the service contract below when choosing a plan or changing hosting providers. **Minimum VPS RAM: 2 GB.** The project's reference VPS was **DigitalOcean Premium Intel, 2 GB RAM, 70 GB NVMe SSD**. Clients may choose any plan with at least 2 GB RAM or use another VPS provider. The reference disk size and branded plan are examples, not required purchases.

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
| Outbound integrations | Only the [configured services](integrations.md); email/AI/USDA and storage access as required |

[Compose](../../docker-compose.yml) plus [production overlay](../../docker-compose.prod.yml) express the current container topology. No OCR/OMR container is required by these files. Report cache is reproducible; private files and database history are not.

Builds run off-host; image loading and releases still need memory/disk headroom. The 2 GB minimum is the installation floor, not a capacity guarantee for every workload. Container memory ceilings total more than 2 GiB and do not reserve RAM; simultaneous peaks can exhaust a small host. Keep swap available and measure reports, concurrent requests, queues and backup work. Increase capacity when sustained pressure or growth requires it. Validate the selected host and recovery target before accepting traffic.

## Changing the VPS provider or plan

The institution may change provider, resource tier or disk size. The application is not tied to DigitalOcean. Keep existing secret names; `DROPLET_*` names are a deployment contract, not a provider restriction.

| Change | Configure/check |
|---|---|
| Different provider, same Ubuntu amd64 target | Use its signup/instance/console/firewall screens, then the same [Ubuntu setup commands](vps-setup.md); record the actual initial sudo user |
| New public address | GitHub `DROPLET_HOST`, website A/AAAA records, provider/host allowlists and monitoring targets |
| New SSH server/OS reinstall | Independently obtain the new host fingerprint and replace `DROPLET_HOST_FINGERPRINT`; re-establish operator/CI keys |
| Different deploy user | Ownership/permissions, `DROPLET_USER`, its `authorized_keys`, repository key and `~/Nutriscope` checkout |
| Custom SSH port | Both workflows currently assume port 22; change and review their SSH/SCP/upload configuration before changing the live port |
| Different CPU architecture | Current images are built on Linux amd64 runners; ARM needs an explicit compatible image-build/platform workflow change |
| Different OS | Adapt package manager, Docker repository, SSH/systemd, firewall and Certbot instructions; Ubuntu commands are not universal Linux commands |
| More/less RAM, CPU or disk | Keep at least 2 GB RAM, review Compose ceilings and swap, monitor pressure and retain disk headroom for database, uploads and two image generations |
| New disk/host | Preserve Compose project identity, restore database and protected files with keys, configure Nginx/TLS, downloads and backup access; never treat a volume change as an empty-data reset |
| Same domain, different VPS IP | Public application URLs can stay unchanged; update DNS and SSH target, then verify web/mobile/API/download paths |
| New domain | Update backend URLs/cookies, Nginx certificate/server names, mobile build/API/update URLs and QR/download consumers using [the full matrix](turnover.md#every-place-to-update-when-addresses-or-accounts-change) |
| Managed application host such as Render | Reproduce all roles, release process and durable storage; the SSH deploy workflow and host Certbot commands do not apply unchanged |

Changing VPS provider does not itself require replacing Resend, USDA, Anthropic, R2 or Expo accounts. Review IP restrictions and institutional ownership. Changing R2 credentials does not copy stored objects. Use [turnover](turnover.md) when any existing data is retained.

Procedures: [VPS setup](vps-setup.md), [turnover](turnover.md), [environment](environment.md), [backup/recovery](backup-recovery.md).
