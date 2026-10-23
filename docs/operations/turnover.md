# Ownership transfer and VPS cutover

Verified against committed source: `14a954e0a1167d781cf007a61c610dff297f952a` (2026-10-24).

Purpose: transfer a maintainable existing system without losing data, keys or installed-client access. This is a checklist, not authorization or evidence of production access.

## Start here: transfer ownership or rebuild resources?

Changing the human/account owner does **not** inherently require reinstalling the application, generating new encryption/signing keys or changing the public domain. Prefer institution-controlled organizations/teams with named administrators and recovery contacts rather than handing over a personal login. Provider-specific transfer availability must be checked for the actual account/resource.

| Situation | Required work |
|---|---|
| Existing resource retained; institution gains ownership/billing/access | Verify the receiving team controls the same repository, VPS, domain, buckets and build project. Verify IPs/keys rather than assuming they changed. Replace personal credentials through a controlled rotation after testing. |
| New VPS in another account/provider | Expect a different origin address and SSH host key unless a verified provider mechanism preserves them. Rebuild host/proxy/TLS/access, restore compatible data/files, replace CI host/key settings and switch DNS using the sequence below. |
| New provider accounts for mail, AI or storage | Issue institution-owned credentials and configure their exact account/domain/bucket endpoints. Copy and verify data where applicable before switching. A new key alone does not transfer a bucket, account balance or sender verification. |
| New public domain | Complete every URL/cookie/mobile/QR change in the matrix below. Keep old endpoints compatible for supported installed apps; a new APK cannot rewrite old installed binaries. |

Use this order: institutional account access → inventory/private secret custody → destination provisioning and restore rehearsal → controlled credential and DNS cutover → receiving-team acceptance → revoke/retire. Keep one maintained procedure here; link other setup guides instead of copying it into multiple deployment documents.

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

### Where the receiving team starts

1. **GitHub:** receiving organization and administrators first. In the existing repository use **Settings → General → Danger Zone → Transfer ownership**, following [GitHub's transfer requirements](https://docs.github.com/en/repositories/creating-and-managing-repositories/transferring-a-repository). A repository transfer retains associated secrets/deploy keys, but receiving-team access, policy and credential custody still need verification. A new separate repository needs its own settings/secrets. Review Actions policy, runners, branch rules, webhooks, deploy keys and organization-secret access. Pause deployment runs while targets are incomplete. Check both `main` workflows before pushing.
2. **Repository clone:** change `origin` to the receiving repository on developer machines **and the VPS clone**, using `git remote set-url origin INSTITUTION_REPOSITORY_URL` (state-changing local Git configuration). Keep CI's expected `~/Nutriscope` path and `main` branch. Verify `git ls-remote --exit-code origin refs/heads/main` with the receiving credential; this contacts GitHub but does not deploy. Do not rely indefinitely on old-owner URL redirects. Revoke superseded deploy keys only after fetch access works.
3. **VPS:** log in to the receiving provider dashboard with institution billing/recovery access. If retaining a server, verify what was actually transferred. If creating a server, select a compatible Linux amd64 host, record its assigned public IPv4, establish console/SSH recovery and follow [VPS setup](vps-setup.md). The five Actions secrets have [exact value sources and setup steps](../developers/operations/environment.md#github-actions-repository-secrets-obtain-enter-and-replace); setting them does not provision the server.
4. **Registrar/DNS:** identify the registrar from the domain account and authoritative nameservers, then get receiving-team access to both. Registrar controls registration/renewal and nameserver delegation; authoritative DNS controls A/AAAA/CNAME/TXT/MX records. A registrar transfer alone need not change the DNS zone. If moving DNS accounts/providers, recreate and compare the full zone before changing registrar nameservers; coordinate DNSSEC/DS with both providers to avoid broken validation. Never copy obsolete nameservers from a historical guide.
5. **External services:** follow [integration value sources](../developers/operations/integrations.md#replacement-account-value-sources) for R2, email, AI and USDA. Use provider-supported team/resource transfer where available; otherwise create replacement resources and migrate/test them. Recoveries, billing and token ownership must survive removal of the outgoing personal user.
6. **Expo and app distribution:** follow [Android ownership](../mobile-apk-release.md#ownership-and-prerequisites). Preserve the existing EAS project identity, Android package and production signing certificate. Transfer the project through its settings; do not create an unrelated project merely to change the login.

The private handoff register must contain: service/dashboard URL, institution owner and backup administrator, resource/project identifier, billing/renewal responsibility, secret-manager entry references, MFA/recovery custody, current/destination topology, accepted test evidence and outgoing-access revocation status. Store actual host addresses, secret values and backup locations privately. Mark unknown/unverified items explicitly; do not sign off from documentation alone.

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

## Every place to update when addresses or accounts change

This matrix describes inspected source, not a live-zone inventory. If only the VPS IP changes while the public domain stays the same, keep the domain-based application URLs; DNS directs them to the new host. Container service names and loopback addresses are internal and do not become the VPS public IP.

| Change surface | Where to obtain/set the destination | Required checks |
|---|---|---|
| Deployment SSH target | Provider dashboard public IP → GitHub `DROPLET_HOST`; target Linux user/key/fingerprint → the other `DROPLET_*` secrets | Independently verify host key and CI login. Both deploy and APK publishing share these host/user/key names. They cannot target separate hosts without a workflow change. |
| Website/API origin routing | Authoritative DNS zone: edit A for apex and each active origin hostname; update/remove stale AAAA if destination lacks IPv6; review `www` A/CNAME | Lower TTL sufficiently ahead of cutover for the old TTL to expire; caches still vary. Query authoritative and public resolvers. Proxied Cloudflare answers show edge addresses, so verify the configured origin in its dashboard as well. |
| New DNS provider/account | Export/recreate full zone in receiving provider; get its assigned authoritative nameservers; update registrar delegation and coordinated DNSSEC/DS | Verify NS, A/AAAA, mail and provider-verification records after delegation. Moving a DNS zone does not move R2 buckets or register the domain. [Cloudflare record management](https://developers.cloudflare.com/dns/manage-dns-records/how-to/create-dns-records/). |
| Firewall and external allowlists | Receiving VPS firewall/security group, host firewall and any actual provider token IP restrictions/monitoring targets | Allow reviewed SSH/80/443 paths; remove stale origin allowlists only after the replacement works. Keep DB/Redis/container ports private. Inventory external consumers; repo cannot enumerate dashboard-only settings. |
| Host proxy and certificate | `/etc/nginx/sites-available/nutriscope`, enabled site, mobile snippet, new certificate/renewal | Set `server_name` for intended domain(s); upstreams remain `127.0.0.1:3000`/`:8080`. Validate certificate SANs, renewal and all `/mobile-api`, `/up`, download and web routes. |
| Backend public domain | Protected server `backend/.env.production`: `APP_URL`, `FRONTEND_URL`, `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`; `UPLOADS_PUBLIC_URL` if used | Same domain/new IP requires no URL change. New domain requires correct HTTPS origins, cookie domain/stateful hostnames and reset-link tests; recreate every backend role to reload env/cache. |
| Internal DB/Redis/frontend routing | Compose and protected backend env | With this same topology keep `DB_HOST=mysql`, `REDIS_HOST=redis`, `LARAVEL_API_URL=http://backend/api`. Use provider connection details only for a deliberately migrated external service. |
| Mobile API URL | `mobile/eas.json` preview/production `EXPO_PUBLIC_API_URL`; inspect local example/build settings | Current production path is `https://PUBLIC_DOMAIN/mobile-api`. A new IP behind the same domain needs no APK rebuild solely for that IP. A new domain requires a signed compatible APK and continued old API access for older apps. The mobile example's alternate API hostname is not the current production EAS endpoint. |
| Mobile update/download host | `mobile/lib/appUpdate.ts`; `.github/workflows/release-mobile.yml` metadata and public verification URLs; published APK/JSON under `/var/www/nutriscope-downloads` | These contain domain-dependent values. Replace source values and publish verified artifacts if domain changes. Old apps still fetch the old update URL. Preserve old HTTPS/API compatibility; do not assume redirects preserve authenticated requests. |
| Desktop download QR | `frontend/public/nutriscope-mobile-qr.svg`, displayed by `frontend/components/mobile-app/FssAppAccess.tsx`; any printed/distributed QR | Scan the actual QR with a phone after cutover. The SVG encodes the QR as vector paths; replacing domain text elsewhere cannot rewrite its payload. The button's `/downloads/nutriscope-fss.apk` is relative to the current site. Regenerate the QR for a domain change and keep old distributed links usable. |
| Mail sender/account | Receiving Resend account domain records/API key; `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`, `BACKUP_ALERT_EMAIL` | A VPS IP change alone does not imply changing the Resend sender records. For a new sender/domain/account, verify exact provider records and authorized reset/alert delivery. Receiving mailbox ownership is separate from sending API access. |
| R2 storage account | Receiving bucket's S3 endpoint, name and S3 credential pair → `BACKUP_*`; conditional `PRIVATE_UPLOADS_*`/`AWS_*` | Copy and verify required historical archives/objects before switching; preserve decryption keys. New Cloudflare account can mean a new endpoint/account ID. Credential/env changes do not copy objects. |
| Other outbound services/build owner | Receiving Anthropic/USDA credentials and EAS project permissions | Update backend-only keys, verify billing/model access and test synthetic requests. Preserve EAS project/package/signing identity; confirm project access with the receiving login. |

**Read-only network checks** from a trusted workstation with DNS tools installed; replace examples before running:

```sh
dig NS example.org
dig A example.org
dig AAAA example.org
dig CNAME www.example.org
# Before DNS cutover: valid TLS for example.org must already be installed.
curl --fail --silent --show-error --resolve example.org:443:NEW_IPV4 https://example.org/up
curl --head --fail --resolve example.org:443:NEW_IPV4 https://example.org/login
```

`--resolve` tests that origin with the real hostname/TLS without changing public DNS; never add `--insecure` to make a failed certificate check appear healthy. An origin intentionally restricted to a proxy may require a controlled test route. After cutover repeat normal HTTPS requests without `--resolve`, then complete application acceptance. For Windows without `dig`, use `Resolve-DnsName -Name example.org -Type A` (and NS/AAAA as applicable). Capture sanitized results privately.

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
