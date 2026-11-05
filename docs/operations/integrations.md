# External integrations

Document version: 1.0 | Issued: 5 November 2026

Set up institution-owned accounts and collect their configuration. Dashboard labels and available plans can change; use the linked provider instructions alongside the steps below. The institution chooses its plans. Store keys outside Git/docs/chat.

## Collect provider values

Complete these steps before [entering configuration](environment.md#enter-the-collected-values). Save each value using the exact label shown. Set institution-controlled recovery, MFA and billing for every paid account. For retained resources, collect access and existing credentials rather than rebuilding by default.

### R2 backup storage: signup to saved values

1. Register/sign in at [Cloudflare](https://dash.cloudflare.com/), select the institutional account and open **Storage & databases → R2 Object Storage** (or **R2 Object Storage** in the account navigation). Enable R2 and complete billing activation if prompted.
2. Choose **Create bucket**, supply a unique private bucket name and choose the institution's storage/location settings. Save the name as `BACKUP_BUCKET`. Leave public development access/custom-domain publishing disabled.
3. Open the bucket's **Settings / bucket details** and copy its **S3 API endpoint** as `BACKUP_ENDPOINT`. Preserve any jurisdiction suffix. The endpoint is not the public bucket URL.
4. From R2 overview, open **Account Details → API Tokens → Manage**. Create an **Account API token** where permitted; select **Object Read & Write**, scope it to this backup bucket and give it an institutional name.
5. Save the S3 **Access Key ID** as `BACKUP_ACCESS_KEY_ID` and **Secret Access Key** as `BACKUP_SECRET_ACCESS_KEY` before leaving the creation screen. Do not substitute the Cloudflare API token value.
6. Keep `BACKUP_DEFAULT_REGION=auto`. Generate `BACKUP_ARCHIVE_PASSWORD` separately in the password manager. Choose `BACKUP_ALERT_EMAIL` as a monitored mailbox.

Follow [R2 activation](https://developers.cloudflare.com/r2/get-started/), [bucket creation](https://developers.cloudflare.com/r2/buckets/create-buckets/) and [S3 authentication](https://developers.cloudflare.com/r2/api/tokens/). Account-owned credentials avoid depending on the outgoing user's membership. Review token expiry and bucket deletion/retention permissions. Do not add lifecycle deletion that conflicts with application retention. Private R2 backup access needs no application DNS record or public bucket domain.

For optional primary private uploads, repeat with a **different private bucket and credential pair**, saving the analogous `PRIVATE_UPLOADS_*` values. Keep `PRIVATE_UPLOADS_DRIVER=local` until existing bytes have been migrated and access verified. This option is not required for the current local-volume deployment.

### Resend email: signup, sender DNS and key

1. Register/sign in at [Resend](https://resend.com/signup), verify institutional email and select the receiving team. Choose a plan appropriate to expected sending volume; the application does not require a named paid tier.
2. Open **Domains → Add Domain**, enter the owned sender domain/subdomain and follow its verification instructions. Save the displayed DNS record set privately as `RESEND_SENDER_DNS_RECORDS`.
3. Open the authoritative DNS zone, choose **Add record** for each displayed record and enter its exact type, name, value and MX priority where applicable. In Cloudflare this is **domain → DNS → Records**. Preserve unrelated mailbox MX records. Avoid creating duplicate SPF records at the same name.
4. Return to Resend, request verification and wait for the domain to verify. Choose a sender address under it; save `MAIL_FROM_ADDRESS` and the institution's `MAIL_FROM_NAME`.
5. Open **API Keys → Create API Key**, name the application key, select sending access for the intended domain, and save the one-time value as `RESEND_API_KEY`.
6. Save `BACKUP_ALERT_EMAIL` and verify the receiving/recovery mailboxes exist separately. Sending through Resend does not create an inbox. Keep `RESEND_API_ENDPOINT=https://api.resend.com/emails`.

See [sender verification](https://resend.com/docs/dashboard/domains/introduction), [key management](https://resend.com/docs/dashboard/api-keys/introduction) and [DNS record editing](https://developers.cloudflare.com/dns/manage-dns-records/how-to/create-dns-records/). Configure the backend later, then verify controlled password-reset/recovery and backup-alert delivery. The production transport is the Resend HTTP API, not SMTP.

### Anthropic API

Register/sign in to [Claude Console](https://platform.claude.com/), select the institutional organization/workspace, configure API billing/spending controls, open its API-key settings and create a dedicated server key. Save it as `ANTHROPIC_API_KEY`. Confirm that the account can access the model selected in production Compose. A consumer chat subscription is separate from API billing/access. See [API prerequisites](https://platform.claude.com/docs/en/api/overview) and the application's [AI guide](../developers/ncp/ai.md). No DNS record is needed. A blank key leaves provider-dependent AI actions unavailable; it is not a substitute for configuration when AI is required.

### USDA food search

Open [FoodData Central API-key signup](https://fdc.nal.usda.gov/api-key-signup/), register the institution-controlled identity/email and save the issued value as `USDA_API_KEY`. Retain mailbox access for recovery. No DNS record is needed. The external search/import feature needs the key; the existing pinned offline food seeder does not. See [initial-data handling](initial-data.md).

### Render is optional alternative hosting

Render is distinct from **Resend**. No Render account/service is required by the current VPS deployment. Choosing Render instead requires mapping HTTP, frontend, database, Redis, worker, scheduler, release and persistent files to its supported services; the existing SSH-to-VPS deploy workflow does not provision those services.

For an institution-approved Render deployment, sign up at [Render](https://dashboard.render.com/), choose its workspace and **New** service, connect the institutional repository and configure each required role with its reviewed build/start/release commands and storage. Choose plans supporting those roles; do not assume a sleeping service or ephemeral disk meets the contract. In each public service open **Settings → Custom Domains → Add Custom Domain**, then publish the exact DNS targets shown and verify the domain. Render manages TLS for its own services; do not run host Certbot there. See [Render custom domains](https://render.com/docs/custom-domains) and [platform requirements](platform-requirements.md). This is a hosting change, not a drop-in alternate VPS command sequence.

## Replacement-account value sources

The backend reads these from the protected server environment, **not** the five GitHub deployment secrets. Use the receiving institution's account, project and billing context in each dashboard before creating anything. Save one-time secret values directly in its secret manager and protected server file. After approved changes, recreate backend HTTP, worker and scheduler roles together; see [environment precedence](environment.md#precedence-and-files). Do not revoke working old credentials until controlled acceptance is complete.

| Service/value | Where to obtain it and where to set it | Verify before retiring the old account |
|---|---|---|
| `USDA_API_KEY` | Register the institution email at [FoodData Central key signup](https://fdc.nal.usda.gov/api-key-signup/); store the issued key as backend `USDA_API_KEY`. | Receiving mailbox can recover access; an authorized synthetic search/preview works. Deliberate import writes data. |
| `ANTHROPIC_API_KEY` | In the receiving [Claude Console](https://platform.claude.com/), select the intended organization/workspace, configure billing and create a dedicated API key under its API-key settings. Set backend `ANTHROPIC_API_KEY`; model selection remains in production Compose. | Account has the configured model access and spending limits; perform a controlled synthetic request. API billing/access is separate from a consumer chat subscription. See [API prerequisites](https://platform.claude.com/docs/en/api/overview) and the [AI guide](../developers/ncp/ai.md). |
| `RESEND_API_KEY` | Receiving [Resend API Keys dashboard](https://resend.com/api-keys): create a key permitted to send for the intended verified domain; save as backend `RESEND_API_KEY`. Existing key values cannot be redisplayed. [Key management](https://resend.com/docs/dashboard/api-keys/introduction). | Correct receiving team/domain, authorized test message arrives, and password-reset/recovery mail works. `RESEND_API_ENDPOINT` stays the provider endpoint unless deliberately changed. |
| `MAIL_FROM_ADDRESS`, sender DNS | Resend **Domains**: add the institutional sender domain; copy each exact required record to its authoritative DNS zone, then wait for verification. Choose the From address under that domain. This is an operator-chosen address, not an API token. | Domain verified in the receiving account, sender address matches, and receiving mailbox access exists separately. Preserve unrelated mailbox MX records. Resolve any already-registered-domain conflict with the provider before switching accounts. |
| `BACKUP_BUCKET`, `BACKUP_ENDPOINT` | Receiving Cloudflare account → **R2 Object Storage**: enable billing, create/select a private bucket; copy its name and exact S3 API endpoint from bucket details. Use the displayed endpoint, including any jurisdiction suffix; do not substitute a public/custom-domain URL. | Destination bucket exists privately; migrated archives and protected objects are available through receiving credentials. New-account endpoint/account ID is reflected in configuration. |
| `BACKUP_ACCESS_KEY_ID`, `BACKUP_SECRET_ACCESS_KEY` | R2 overview → **Account Details → API Tokens → Manage**; create an account-owned token where permitted, choose Object Read & Write scoped to the backup bucket, then save the issued **S3 Access Key ID and Secret Access Key**, not the general Cloudflare API token. Set `BACKUP_DEFAULT_REGION=auto`; keep the existing S3 driver/path-style setting unless reviewed. [R2 authentication](https://developers.cloudflare.com/r2/api/tokens/). | Account-owned token remains usable after outgoing-user removal. User-owned tokens can become inactive when that user is removed. Check bucket access and isolated restore, including required deletion/retention behavior. The S3 secret is shown at creation; replace it if lost. |
| `BACKUP_ARCHIVE_PASSWORD` | For a new backup lineage, generate a separate strong password in the institution's secret manager and set the backend value. For existing archives, obtain the original password from the outgoing custodian. This value is not issued by R2. | Keep passwords associated with historical archives until those archives expire under the accepted retention plan. A replacement password cannot decrypt old archives. Preserve `APP_KEY` and any other actual file-encryption keys independently. |
| `BACKUP_ALERT_EMAIL` | Receiving operations team chooses a monitored mailbox; set it explicitly if the default From address is not a real monitored inbox. | Approved test alert arrives, recovery contacts own the inbox and notification delivery is monitored. |
| Conditional `PRIVATE_UPLOADS_*` | Only if primary private uploads deliberately move to S3/R2: obtain a separate private bucket/endpoint/credential pair by the same provider process. Set driver `s3`, disk alias `private_uploads`, region `auto` for R2 and the matching values. | Copy existing bytes with paths/identities preserved and verify authorized file access before enabling new writes. Current Compose default is a local named volume, so no primary R2 credentials are required for that default. |
| Conditional `AWS_*` | Only if public uploads use `UPLOADS_DISK=s3`: provision the intended bucket and obtain its `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, optional public `AWS_URL` and reviewed path-style setting from that provider. These optional names are in filesystem config, not all in the production example. | Verify public asset URLs and migrate bytes first. Do not use this public disk for patient/private files or backups. Do not invent AWS accounts just because optional framework settings exist. |

To move R2 accounts, first retain access to the old bucket, inventory the required archive/object set, copy it through an approved encrypted operator process, and verify destination readability and a restore drill. Keep old data until acceptance/retention allows retirement. Switching `BACKUP_ENDPOINT`/credentials changes future access; it neither migrates history nor proves recovery. Follow [backup/recovery](backup-recovery.md) for scope and evidence.

## USDA FoodData Central

1. Open [Get an API key](https://fdc.nal.usda.gov/api-key-signup/) and register using an institution-controlled email; follow the signup instructions.
2. Put the issued key in USDA_API_KEY in the backend's protected production environment.
3. Recreate backend roles through the approved release process so config is refreshed. In an approved test environment, try a food search/preview, then an intentional import.
4. Follow [UsdaService](../../backend/app/Services/UsdaService.php) → [UsdaController](../../backend/app/Http/Controllers/RND/UsdaController.php) → [foodLibraryService](../../frontend/services/foodLibraryService.ts). Nutrient IDs/category mapping and cached results live in UsdaService; preserve units and existing nutrient keys. [UsdaServiceTest](../../backend/tests/Unit/UsdaServiceTest.php) uses fixtures.

USDA needs no domain/DNS record. Limits/key handling: [official API guide](https://fdc.nal.usda.gov/api-guide/).

## Claude / Anthropic

Create the institution's API account/project, arrange billing and issue a backend-only key as ANTHROPIC_API_KEY. Changing the model, request schema, usage caps or cost estimates: [AI guide](../developers/ncp/ai.md). Production model selection is in Compose, not the dotenv template. No Anthropic DNS record is required.

## Resend email

Create a receiving-team account and add its sender domain. Copy **the exact DNS records shown by Resend** to the authoritative DNS provider; verify the domain before configuring MAIL_FROM_ADDRESS and RESEND_API_KEY. Production Compose selects resend-api; no SMTP username/password is needed for this transport.

[Resend domain instructions](https://resend.com/docs/dashboard/domains/introduction) cover verification; retain existing mailbox MX records unless a planned mail migration requires changes. Check password reset and recovery-email verification with an authorized test account, using an inbox the team controls. [Mail config](../../backend/config/mail.php) and [User notification routing](../../backend/app/Models/User.php) define the application side.

## Cloudflare R2

Create the institution's Cloudflare account, enable R2 and create a **private backup bucket**. Generate bucket-scoped Object Read & Write S3 credentials; put the Access Key ID/Secret Access Key into BACKUP_ACCESS_KEY_ID/BACKUP_SECRET_ACCESS_KEY. Use the bucket's S3 API endpoint for BACKUP_ENDPOINT, its name for BACKUP_BUCKET, region auto and the S3 driver. The endpoint is not a public asset URL.

Primary uploads currently use a local volume. If intentionally moving them to R2, use a **different private bucket and credentials** in PRIVATE_UPLOADS_* with driver s3; copy existing objects preserving identity and verify them before switching. Changing driver/keys does not move existing bytes. Backups and primary files must not share deletion authority.

Public images use the s3 disk's AWS_* settings only if UPLOADS_DISK=s3 is deliberately selected; see [filesystems.php](../../backend/config/filesystems.php). Sensitive files/backups must never be public. Public bucket custom domains are optional and need Cloudflare setup; the private S3 API alone needs no application DNS record. See [S3 credentials](https://developers.cloudflare.com/r2/api/tokens/), [public bucket domains](https://developers.cloudflare.com/r2/buckets/public-buckets/) and [pricing](https://developers.cloudflare.com/r2/pricing/). R2 has included monthly usage, not an unlimited free guarantee; monitor usage and billing.

## Render and other hosting

No active Render-specific service is declared by the current Compose/config inspected here. Before replacing an account, inventory its services, env names, jobs, domains, persistent data and external consumers; don't recreate a retired OCR service.

If the institution chooses Render, connect its repository/account, reproduce the roles in [platform requirements](platform-requirements.md), and add each custom domain in Render. Publish exactly the DNS target/verification records its dashboard supplies. Render manages its own certificates; host Certbot applies to a self-managed VPS. See [Render domains](https://render.com/docs/custom-domains).

VPS/DNS/GitHub accounts: [turnover](turnover.md). Expo/EAS ownership: [Android releases](mobile-release.md). Config entries for Postmark/SES/Slack are framework/provider options, not evidence that those services need accounts in this deployment.
