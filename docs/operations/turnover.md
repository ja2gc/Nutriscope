# Ownership transfer and VPS cutover

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: transfer a maintainable existing system without losing data, keys or installed-client access. This is a checklist, not authorization or evidence of production access.

## Account ownership checklist

Record owner, recovery contact, MFA/access handoff, billing, renewal dates and acceptance status **in the institution's private handoff register**, not credentials in this repository.

| Account/resource | Transfer/setup and verify |
|---|---|
| Repository and GitHub Actions | Institution organization/access, branch rules, deploy/release secrets, SSH access to new host; builds and releases are separate workflows |
| VPS | Institution billing/admin, SSH keys, recovery console, firewall, resource/disk monitoring and backups |
| Domain registrar and authoritative DNS | Domain renewal/ownership and full DNS-zone export; registrar and DNS provider may differ |
| Cloudflare/R2 | Institution admins, backup bucket/token/password access, retention, usage/billing; separate primary files if moving those too |
| Anthropic | Institution billing/project/key, model access and usage limits; [AI guide](../developers/ncp/ai.md) |
| USDA | Institution email/API key; [integration steps](../developers/operations/integrations.md) |
| Resend and alert/recovery mailboxes | Verified sender DNS, sending API key, mailbox access and delivery tests |
| Render, if still used | Inventory actual services, domains, env names, persistent data and recurring jobs; current dependency/ownership is Unverified |
| Expo/EAS | Transfer existing project, preserve Android package/signing credentials, verify build quota/billing; [mobile release](../mobile-apk-release.md) |
| Application administrator accounts | Create named receiving-team users through existing Admin flow; verify role, recovery email and password-change onboarding |

Inventory paid services before choosing a cheaper VPS. The repository does not prove a particular CPU/RAM plan can handle builds, reports, queues and backups. Keep enough disk/RAM headroom and verify with a realistic synthetic workload.

## DNS inventory

This table lists **possible records by selected service**, not a claim that every record exists. Obtain exact current values from the authoritative DNS zone/provider dashboards.

| Purpose | Typical record/source | When needed |
|---|---|---|
| Website/mobile API on VPS | A → destination IPv4; AAAA only for configured IPv6; optional www CNAME | Self-managed site host |
| Render hostname/custom domain | Dashboard-provided CNAME/A/verification records | Only for active services hosted there |
| Resend sender authentication | Dashboard-provided SPF/DKIM and Return-Path records (TXT/MX as shown); review DMARC | Sending institutional email |
| Public R2 assets | Cloudflare-managed public bucket custom-domain setup | Only if intentionally using public assets/custom hostname |
| Private R2 S3 API | No application-zone record needed for the provider endpoint | Private backup/upload API access |
| Certificate domain validation | Temporary/automated _acme-challenge TXT with DNS validation | If using DNS challenge; ordinary HTTP challenge uses port 80 instead |
| USDA / Anthropic API keys | No DNS record required | Backend outbound integrations |
| EAS-hosted build artifacts | No application DNS record required | APK still published by the repository's release workflow |

Preserve unrelated mailbox MX/TXT records. Avoid publishing conflicting SPF records at one name. Validate all used hostnames, including old APK API/update URLs, before dropping records.

## Existing-system migration sequence

1. **Inventory:** record actual running revision, image IDs, schema/migration state, DB size, volumes, all upload locations (including legacy public/local files and protected conversion artifacts), R2 buckets, queue jobs and downloadable APK/metadata. Never assume MySQL backup alone covers files.
2. **Securely preserve:** original APP_KEY, passwords required for historical archives, applicable file-encryption keys, Android signing material, environment values and provider credentials in the institution's secret manager. Changing VPS is not an APP_KEY rotation.
3. **Prepare destination:** follow [VPS setup](vps-setup.md), initially with the same compatible application revision. Establish accounts/DNS/TLS using a test hostname or controlled DNS validation before real cutover.
4. **Prove recovery:** capture a coherent DB+file set and rehearse restore in an isolated non-production target. The application's ordinary recovery path and a whole-server migration have different scopes; see [backup/recovery](backup-recovery.md). Actual backup completeness is Unverified until checked.
5. **Freeze writers:** schedule the maintenance window. Block web **and mobile** writes at the old proxy/ingress, drain/stop workers, stop scheduler and wait for in-flight writes before the final capture. Stopping only the web container doesn't stop jobs. A DB transaction alone doesn't freeze object uploads.
6. **Transfer:** use an operator-reviewed, encrypted transfer/restore plan for that exact DB, volume and provider topology. Preserve object IDs/paths and stored relationships. Do not run demo seeders or change schema blindly. No universally safe database/volume restore command is supplied without the actual source/target facts.
7. **Validate privately:** compare schema/migration versions, sanitized record/object counts and sample authorized file access; verify app login/roles, NCP, purchasing, reports, worker/scheduler, mail and mobile contracts. Confirm the intended reference-data/release migrations before running the release.
8. **Cut over once:** change the intended DNS records, start only the new active writer set, verify HTTPS, cookie/reset URLs, app API and download endpoints. Keep the old endpoint in maintenance/read-only during DNS propagation to avoid split writes.
9. **Acceptance and retirement:** record tests/results, verify a new backup and isolated restore, confirm institution admin recovery and renewal reminders, revoke superseded developer/provider/SSH tokens. Retire the old VPS only after agreed recovery/retention and data sanitization.

## Recovery if cutover fails

Before new writes, a controlled DNS return to the frozen old system may be possible. **After new writes, pointing DNS back can lose or split records**: freeze both endpoints, reconcile which DB/files are authoritative, then forward-fix or restore a proven coherent set. Image rollback does not undo migrations or data; do not run blanket migration rollback.

The receiving team must approve the restore/rollback method, backup location, retention and acceptance owner. Record the actual topology, accounts, DNS and supported client versions privately before cutover. Documentation does not close known clinical, financial or recovery concerns; investigate any release-blocking behavior before turnover.

Related: [platform requirements](platform-requirements.md), [integrations](../developers/operations/integrations.md), [environment](../developers/operations/environment.md), [developer map](../../DEVELOPERS.md).
