# External integrations

Verified against committed source: `14a954e0a1167d781cf007a61c610dff297f952a` (2026-10-24).

Purpose: set up institution-owned accounts and locate integration code. Provider value-source instructions checked 2026-10-24; dashboard labels/pricing can change. Store keys outside Git/docs/chat.

## Replacement-account value sources

The backend reads these from the protected server environment, **not** the five GitHub deployment secrets. Use the receiving institution's account, project and billing context in each dashboard before creating anything. Save one-time secret values directly in its secret manager and protected server file. After approved changes, recreate backend HTTP, worker and scheduler roles together; see [environment precedence](environment.md#precedence-and-files). Do not revoke working old credentials until controlled acceptance is complete.

| Service/value | Where to obtain it and where to set it | Verify before retiring the old account |
|---|---|---|
| `USDA_API_KEY` | Register the institution email at [FoodData Central key signup](https://fdc.nal.usda.gov/api-key-signup/); store the issued key as backend `USDA_API_KEY`. | Receiving mailbox can recover access; an authorized synthetic search/preview works. Deliberate import writes data. |
| `ANTHROPIC_API_KEY` | In the receiving [Claude Console](https://platform.claude.com/), select the intended organization/workspace, configure billing and create a dedicated API key under its API-key settings. Set backend `ANTHROPIC_API_KEY`; model selection remains in production Compose. | Account has the configured model access and spending limits; perform a controlled synthetic request. API billing/access is separate from a consumer chat subscription. See [API prerequisites](https://platform.claude.com/docs/en/api/overview) and the [AI guide](../ncp/ai.md). |
| `RESEND_API_KEY` | Receiving [Resend API Keys dashboard](https://resend.com/api-keys): create a key permitted to send for the intended verified domain; save as backend `RESEND_API_KEY`. Existing key values cannot be redisplayed. [Key management](https://resend.com/docs/dashboard/api-keys/introduction). | Correct receiving team/domain, authorized test message arrives, and password-reset/recovery mail works. `RESEND_API_ENDPOINT` stays the provider endpoint unless deliberately changed. |
| `MAIL_FROM_ADDRESS`, sender DNS | Resend **Domains**: add the institutional sender domain; copy each exact required record to its authoritative DNS zone, then wait for verification. Choose the From address under that domain. This is an operator-chosen address, not an API token. | Domain verified in the receiving account, sender address matches, and receiving mailbox access exists separately. Preserve unrelated mailbox MX records. Resolve any already-registered-domain conflict with the provider before switching accounts. |
| `BACKUP_BUCKET`, `BACKUP_ENDPOINT` | Receiving Cloudflare account → **R2 Object Storage**: enable billing, create/select a private bucket; copy its name and exact S3 API endpoint from bucket details. Use the displayed endpoint, including any jurisdiction suffix; do not substitute a public/custom-domain URL. | Destination bucket exists privately; migrated archives and protected objects are available through receiving credentials. New-account endpoint/account ID is reflected in configuration. |
| `BACKUP_ACCESS_KEY_ID`, `BACKUP_SECRET_ACCESS_KEY` | R2 overview → **Account Details → API Tokens → Manage**; create an account-owned token where permitted, choose Object Read & Write scoped to the backup bucket, then save the issued **S3 Access Key ID and Secret Access Key**, not the general Cloudflare API token. Set `BACKUP_DEFAULT_REGION=auto`; keep the existing S3 driver/path-style setting unless reviewed. [R2 authentication](https://developers.cloudflare.com/r2/api/tokens/). | Account-owned token remains usable after outgoing-user removal. User-owned tokens can become inactive when that user is removed. Check bucket access and isolated restore, including required deletion/retention behavior. The S3 secret is shown at creation; replace it if lost. |
| `BACKUP_ARCHIVE_PASSWORD` | For a new backup lineage, generate a separate strong password in the institution's secret manager and set the backend value. For existing archives, obtain the original password from the outgoing custodian. This value is not issued by R2. | Keep passwords associated with historical archives until those archives expire under the accepted retention plan. A replacement password cannot decrypt old archives. Preserve `APP_KEY` and any other actual file-encryption keys independently. |
| `BACKUP_ALERT_EMAIL` | Receiving operations team chooses a monitored mailbox; set it explicitly if the default From address is not a real monitored inbox. | Approved test alert arrives, recovery contacts own the inbox and notification delivery is monitored. |
| Conditional `PRIVATE_UPLOADS_*` | Only if primary private uploads deliberately move to S3/R2: obtain a separate private bucket/endpoint/credential pair by the same provider process. Set driver `s3`, disk alias `private_uploads`, region `auto` for R2 and the matching values. | Copy existing bytes with paths/identities preserved and verify authorized file access before enabling new writes. Current Compose default is a local named volume, so no primary R2 credentials are required for that default. |
| Conditional `AWS_*` | Only if public uploads use `UPLOADS_DISK=s3`: provision the intended bucket and obtain its `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, optional public `AWS_URL` and reviewed path-style setting from that provider. These optional names are in filesystem config, not all in the production example. | Verify public asset URLs and migrate bytes first. Do not use this public disk for patient/private files or backups. Do not invent AWS accounts just because optional framework settings exist. |

To move R2 accounts, first retain access to the old bucket, inventory the required archive/object set, copy it through an approved encrypted operator process, and verify destination readability and a restore drill. Keep old data until acceptance/retention allows retirement. Switching `BACKUP_ENDPOINT`/credentials changes future access; it neither migrates history nor proves recovery. Follow [backup/recovery](../../operations/backup-recovery.md) for scope and evidence.

## USDA FoodData Central

1. Open [Get an API key](https://fdc.nal.usda.gov/api-key-signup/) and register using an institution-controlled email; follow the signup instructions.
2. Put the issued key in USDA_API_KEY in the backend's protected production environment.
3. Recreate backend roles through the approved release process so config is refreshed. In an approved test environment, try a food search/preview, then an intentional import.
4. Follow [UsdaService](../../../backend/app/Services/UsdaService.php) → [UsdaController](../../../backend/app/Http/Controllers/RND/UsdaController.php) → [foodLibraryService](../../../frontend/services/foodLibraryService.ts). Nutrient IDs/category mapping and cached results live in UsdaService; preserve units and existing nutrient keys. [UsdaServiceTest](../../../backend/tests/Unit/UsdaServiceTest.php) uses fixtures.

USDA needs no domain/DNS record. Limits/key handling: [official API guide](https://fdc.nal.usda.gov/api-guide/).

## Claude / Anthropic

Create the institution's API account/project, arrange billing and issue a backend-only key as ANTHROPIC_API_KEY. Changing the model, request schema, usage caps or cost estimates: [AI guide](../ncp/ai.md). Production model selection is in Compose, not the dotenv template. No Anthropic DNS record is required.

## Resend email

Create a receiving-team account and add its sender domain. Copy **the exact DNS records shown by Resend** to the authoritative DNS provider; verify the domain before configuring MAIL_FROM_ADDRESS and RESEND_API_KEY. Production Compose selects resend-api; no SMTP username/password is needed for this transport.

[Resend domain instructions](https://resend.com/docs/dashboard/domains/introduction) cover verification; retain existing mailbox MX records unless a planned mail migration requires changes. Check password reset and recovery-email verification with an authorized test account, using an inbox the team controls. [Mail config](../../../backend/config/mail.php) and [User notification routing](../../../backend/app/Models/User.php) define the application side.

## Cloudflare R2

Create the institution's Cloudflare account, enable R2 and create a **private backup bucket**. Generate bucket-scoped Object Read & Write S3 credentials; put the Access Key ID/Secret Access Key into BACKUP_ACCESS_KEY_ID/BACKUP_SECRET_ACCESS_KEY. Use the bucket's S3 API endpoint for BACKUP_ENDPOINT, its name for BACKUP_BUCKET, region auto and the S3 driver. The endpoint is not a public asset URL.

Primary uploads currently use a local volume. If intentionally moving them to R2, use a **different private bucket and credentials** in PRIVATE_UPLOADS_* with driver s3; copy existing objects preserving identity and verify them before switching. Changing driver/keys does not move existing bytes. Backups and primary files must not share deletion authority.

Public images use the s3 disk's AWS_* settings only if UPLOADS_DISK=s3 is deliberately selected; see [filesystems.php](../../../backend/config/filesystems.php). Sensitive files/backups must never be public. Public bucket custom domains are optional and need Cloudflare setup; the private S3 API alone needs no application DNS record. See [S3 credentials](https://developers.cloudflare.com/r2/api/tokens/), [public bucket domains](https://developers.cloudflare.com/r2/buckets/public-buckets/) and [pricing](https://developers.cloudflare.com/r2/pricing/). R2 has included monthly usage, not an unlimited free guarantee; monitor usage and billing.

## Render and other hosting

No active Render-specific service is declared by the current Compose/config inspected here. Before replacing an account, inventory its services, env names, jobs, domains, persistent data and external consumers; don't recreate a retired OCR service.

If the institution chooses Render, connect its repository/account, reproduce the roles in [platform requirements](../../operations/platform-requirements.md), and add each custom domain in Render. Publish exactly the DNS target/verification records its dashboard supplies. Render manages its own certificates; host Certbot applies to a self-managed VPS. See [Render domains](https://render.com/docs/custom-domains).

VPS/DNS/GitHub accounts: [turnover](../../operations/turnover.md). Expo/EAS ownership: [Android releases](../../mobile-apk-release.md). Config entries for Postmark/SES/Slack are framework/provider options, not evidence that those services need accounts in this deployment.
