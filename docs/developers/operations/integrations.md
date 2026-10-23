# External integrations

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: set up institution-owned accounts and locate integration code. Provider instructions checked 2026-10-23; dashboard labels/pricing can change. Store keys outside Git/docs/chat.

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
