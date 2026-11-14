# NutriScope installation and turnover manual

Prepared by: EMPhive — Louis Figo, Jhobe Fajardo, Maria Beatriz Dela Cruz and Jared Abriol. The receiving institution assigns the system owner, technical administrator, backup administrator and support contact in its private handoff register.

Use this manual to collect account information, prepare a server, enter configuration and operate NutriScope. Choose **new installation** for an empty database, or **existing-system transfer** to retain records and files. A transfer preserves encryption keys, signing identity and data; it is not an empty installation.

## Read in this order

| Step | Guide | Result |
|---|---|---|
| 1 | [Accounts and information collection](accounts.md) | Institution owns the accounts; values are saved under consistent labels |
| 2 | [Platform requirements](platform-requirements.md) | Chosen provider/plan supports the application roles |
| 3 | [VPS setup](vps-setup.md) sections 1–2 | Ubuntu, Docker, SSH account and source checkout are ready |
| 4 | [Environment configuration](environment.md#enter-the-collected-values) | Collected values entered into the protected server file and Actions secrets |
| 5 | [VPS setup](vps-setup.md) sections 3–5 and [initial data](initial-data.md) | Images, database, proxy, DNS and certificate prepared |
| 6 | [Acceptance and maintenance](maintenance.md) | Operator checks application, integrations, queues, backups and renewal |
| Transfer branch | [Ownership transfer and cutover](turnover.md) | Existing database/files move together, with one active writer set |
| Recovery | [Backup and recovery](backup-recovery.md), [manual R2 recovery without Admin UI](backup-recovery.md#manual-recovery-when-the-admin-screen-is-unavailable) | Retrieve/decrypt a restore set, import into an isolated database, restore mapped protected files, verify and cut over |
| Android | [Android release and ownership](mobile-release.md) | Existing signing identity and stable APK distribution maintained |
| Technical reference | [API overview](api-overview.md) | Maintainer understands API URLs, authentication, roles and contracts |
| Staff training | [Role user manual](../ROLE-HOW-TO.md), [page directory](../ROLE-HOW-TO.md#page-directory) and [daily workflow](../ROLE-HOW-TO.md#daily-and-periodic-work) | Admin, RND and FSS can find each screen and follow their work sequence |
| Role manuals and daily routines | [User manual directory](../STORYBOARD.md) | Separate RND, FSS and Admin prerequisites, navigation, task steps and daily sequences |

Follow the linked order rather than running every command on every page. State-changing command blocks say where to run them. Replace `example.org`, `ORIGIN_IP`, repository URLs and account placeholders with the values in your private handoff register. Commands marked Bash run in Ubuntu/WSL Bash, not Windows PowerShell. Workstation instructions identify PowerShell alternatives where relevant.

## What deployment does

GitHub Actions builds backend/frontend images, transfers them over SSH, updates an **existing** `~/Nutriscope` checkout, runs the release and checks local HTTP/image health. The release runs migrations and census catch-up; it does not run seeders. It does not create provider accounts, provision Ubuntu, clone the repository initially, write production secrets or configure DNS/Nginx/Certbot. A fresh institution uses the explicit production Admin/reference-data procedure in [initial data](initial-data.md); the same guide separates local demo initialization and automated testing.

The website uses Next.js and Laravel; MySQL, Redis, a queue worker and a scheduler run in Docker. Host Nginx terminates HTTPS. Private uploads use a persistent local volume by default; R2 is the separate backup destination. There is no active Render or OCR service in this Compose deployment.

## Plans and providers

The receiving institution chooses its own plans, budget and providers. The project used **DigitalOcean Premium Intel, 2 GB RAM, 70 GB NVMe SSD** as a reference VPS. **Minimum RAM is 2 GB.** Clients can choose any plan meeting that minimum, or another VPS provider, and configure the affected areas in the [provider-change checklist](platform-requirements.md#changing-the-vps-provider-or-plan). Ubuntu 24.04 amd64 is the command target. The reference plan is not a required purchase; the 70 GB disk size is an example, not a universal storage minimum. Size disk/CPU for actual records, files, images and backup work.

The project used R2's included free usage allowance and Expo Free-plan Android builds. The institution may use available free allowances, choose paid capacity, retain existing accounts or select other compatible providers. R2 usage beyond the included allowance can be billed; see [R2 pricing](https://developers.cloudflare.com/r2/pricing/). Check storage/request usage and build quota against expected demand. Resend and Anthropic billing/access are configured for the institution's actual use. Changing a service tier normally changes limits/billing; changing an OS, CPU architecture or hosting model can require command or workflow changes. See [platform requirements](platform-requirements.md).

An existing compatible VPS can be reused. Check available capacity alongside its other workloads, preserve its existing sites and use a separate Nginx server block and reviewed port bindings for NutriScope. An institution can also reuse its existing domain/DNS provider by adding a dedicated subdomain such as `nutriscope.hospital.org`. The application remains named **NutriScope**. See [domain choices and exact edit locations](accounts.md#domain-options-and-configuration-locations).

## Hospital use and technical custody

Romana Pangan District Hospital is the partner institution and intended hospital user. Its designated technical operator may be hospital IT or authorized Pampanga Provincial Government IT personnel. Record the receiving entity, authorized signatory, technical custodian, billing owner and support contact in the formal turnover/private handoff record. Hosting the application does not independently determine ownership; project rights are addressed in [LICENSE.md](../../LICENSE.md) and the applicable executed agreements.

Provincial IT may prepare and operate the infrastructure for the hospital using these instructions. Further development for additional hospitals is a separate implementation exercise: assess record isolation, roles, data access, branding, reporting, backups and operational responsibility before expansion. The current deployment instructions do not establish a ready-made shared multi-hospital service.

## Information custody

Keep secrets in an institution-controlled password manager, never in this repository or the printed manual. Record owner, backup administrator, recovery/MFA custody, billing responsibility and secret-entry references privately. Provider signup and key-creation steps tell you the exact labels to save. The [final configuration table](environment.md#enter-the-collected-values) tells you where each value goes.

Documentation does not establish that a particular host or backup works. Acceptance requires the [operator checks](maintenance.md#acceptance-checklist). A clean institutional installation must satisfy the [initial-data prerequisites](initial-data.md); do not use exhibition seeders to bypass them.
