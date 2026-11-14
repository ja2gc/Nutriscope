# Accounts and information collection

Start from [the installation manual](README.md). Complete this collection before entering production configuration. Use institution-controlled email, billing, MFA and recovery access. Save values in a password-manager collection called **NutriScope** with the labels below. Labels are references, not secret values to commit. For a retained resource, obtain access and existing values instead of creating a replacement unnecessarily.

## 1. GitHub repository

1. Sign in at [GitHub](https://github.com/) using the receiving team account. Create/use the institution's organization and give at least two named custodians appropriate access.
2. Transfer the existing repository through **Settings → General → Danger Zone → Transfer ownership**, or use the institution's supplied copy. Follow [GitHub transfer requirements](https://docs.github.com/en/repositories/creating-and-managing-repositories/transferring-a-repository). Preserve the application history and `main` branch.
3. Open **Code → Local → SSH** and save the clone URL as `REPOSITORY_SSH_URL`. Save the web repository URL as `REPOSITORY_WEB_URL`.
4. In **Actions**, identify **Deploy to VPS** and **Publish FSS Android release**. Keep deployment disabled in **Actions → workflow → … → Disable workflow** while the new destination is incomplete. If retaining a live deployment, coordinate the pause with its operator. Re-enable only after the target and data branch are ready.
5. Open **Settings → Actions → General** to review the institution's allowed-action policies. Open **Settings → Deploy keys** for the server's read-only repository key in [VPS setup](vps-setup.md#2-clone-and-configure). This key is separate from Actions' SSH login key.

Do not publish a documentation edit to `main` as a harmless test: normal `main` pushes deploy. Exact secret entry comes [at the end](environment.md#github-actions-repository-secrets-obtain-enter-and-replace).

## 2. Workstation SSH keys

Use OpenSSH on a trusted workstation. Windows users can run these commands in PowerShell; `ssh`, `scp` and `ssh-keygen` must be installed. The institution's workstation administrator can enable the Windows OpenSSH Client optional feature if missing.

**State-changing — workstation, outside the repository:**

```powershell
New-Item -ItemType Directory -Force -Path "$HOME/.ssh" | Out-Null
ssh-keygen -t ed25519 -C "nutriscope-operator" -f "$HOME/.ssh/nutriscope-operator"
ssh-keygen -t ed25519 -C "nutriscope-actions" -f "$HOME/.ssh/nutriscope-actions"
```

In Bash, use `mkdir -p ~/.ssh` followed by the two `ssh-keygen` commands with `~/.ssh/...` paths. Stop if either filename already exists; use a new name instead of overwriting it. Protect the operator key with a passphrase. Leave the **dedicated Actions key** passphrase empty because the workflows have no interactive passphrase input.

Save the private key files as password-manager attachments `OPERATOR_SSH_PRIVATE_KEY` and `CI_SSH_PRIVATE_KEY`. Save their public `.pub` files as `OPERATOR_SSH_PUBLIC_KEY` and `CI_SSH_PUBLIC_KEY`. Only public keys go into provider SSH-key forms or `authorized_keys`; the CI private key goes into `DROPLET_SSH_KEY` later. Do not display private keys in shared terminals or messages.

## 3. Provision a VPS

### DigitalOcean reference route

1. Open [DigitalOcean](https://cloud.digitalocean.com/registrations/new), register with institutional email, verify access, enable MFA and establish billing/team custody.
2. Open the project, then **Create → Droplets**. Choose a region suitable for the institution and an **Ubuntu 24.04 LTS x64** image.
3. Choose any plan with **at least 2 GB RAM**. The project's reference was **Premium Intel, 2 GB RAM, 70 GB NVMe SSD**; selecting that exact plan is optional. Check CPU/disk, transfer allowance, backup options and upgrade path against [platform requirements](platform-requirements.md).
4. Select **SSH Key** authentication, add `OPERATOR_SSH_PUBLIC_KEY`, choose an identifying hostname/project and create the Droplet. Save its assigned IPv4 as `ORIGIN_IP`, the hostname as `SERVER_NAME`, and the initial login user as `INITIAL_SSH_USER` (normally `root` for this image).
5. Open the Droplet's **Access** area to locate console/recovery access. Verify it before disabling any login method.
6. Under **Networking → Firewalls**, create/apply inbound rules for TCP 22, 80 and 443. Restrict human SSH sources where practical; Actions also needs a working SSH path. Do not expose 3306, 6379, 3000 or 8080. Keep outbound DNS, HTTPS and required package/provider access available.

Use the provider's [creation guide](https://docs.digitalocean.com/products/droplets/how-to/create/) and [SSH setup](https://docs.digitalocean.com/products/droplets/how-to/add-ssh-keys/) for dashboard details.

### Another VPS provider

Register and establish billing/MFA, open its **Compute / Instances / VPS** area, create an Ubuntu 24.04 **amd64/x86-64** instance, add the operator public key, record the public IPv4 and initial sudo-capable user, and configure its security group/firewall and recovery console. Provider names and initial users differ. The Ubuntu commands in [VPS setup](vps-setup.md) are shared for this target; do not copy them unchanged onto Debian, another distribution, ARM or managed application hosting. Keep the historical `DROPLET_*` secret names on any VPS provider.

Save `VPS_DASHBOARD_URL`, `ORIGIN_IP`, `INITIAL_SSH_USER`, `SERVER_NAME` and the chosen plan/resource details privately. No DigitalOcean API token is required by the deployment workflow.

## 4. Domain and authoritative DNS

1. Register/retain the institution's domain with its registrar. Set billing, renewal and recovery contacts. Save `PUBLIC_DOMAIN` and `REGISTRAR_DASHBOARD_URL`.
2. Identify the authoritative DNS provider from the domain's nameserver delegation. Get access to that zone and save `DNS_DASHBOARD_URL`. Registration and DNS can be in different accounts.
3. If using Cloudflare DNS, sign in at [Cloudflare](https://dash.cloudflare.com/), add the domain, select the institution's chosen plan, review imported records and use the assigned nameservers at the registrar. Compare the full existing zone before changing delegation; preserve mail/verification records and coordinate DNSSEC/DS when moving an existing domain.
4. Save the zone/domain identifier and exact records privately. Enter the website A/AAAA/www records only at the [DNS and HTTPS step](vps-setup.md#5-dns-and-https), particularly during an existing-domain cutover.

Save `FRONTEND_ORIGIN` as `https://PUBLIC_DOMAIN`, `SESSION_COOKIE_DOMAIN` for the chosen domain and `STATEFUL_DOMAINS` as the hostnames that will serve the application. These are application settings, not provider-issued secrets.

## 5. R2, email, AI and food search

Follow [external integration collection](integrations.md#collect-provider-values). Save each value under the exact environment-variable name given there. Create the backup archive password separately: R2 does not issue it. A Resend sender address is not a mailbox; arrange a monitored institution mailbox for recovery and alerts.

## 6. Database and encryption values

For a genuinely empty installation, generate a strong unique database password in the password manager and save it as `DB_ROOT_PASSWORD`. Use at least 32 randomly generated letters/digits for this setup. The current container startup check embeds the database password in a PHP string, so avoid single quotes or backslashes in this newly generated value; a dotenv quoting change alone does not remove that startup constraint. The current root-based database template uses the same value for backend `DB_PASSWORD`. Linux root's login credential is unrelated. Do not rotate an existing database password merely to follow a new-install example.

Generate `APP_KEY` once through [VPS setup](vps-setup.md#2-clone-and-configure). Save `BACKUP_ARCHIVE_PASSWORD` separately for archive encryption. For a transfer, obtain the existing `APP_KEY`, historical archive passwords and database credentials from the custodian; do not generate replacements to open old encrypted data.

## 7. Android ownership

Obtain the existing Expo project, package identity, production signing credentials and signed APK through [Android ownership](mobile-release.md#ownership-and-prerequisites). Save `EXPO_PROJECT_ID`, `ANDROID_PACKAGE`, signing-secret references and artifact references privately. The current APK publication workflow does not require an Expo token in Actions; builds are submitted separately. A replacement signing key cannot update the installed app.

Next: [prepare the VPS](vps-setup.md#1-prepare-the-host), then [enter the collected values](environment.md#enter-the-collected-values).

## Domain options and configuration locations

The application name **NutriScope** is independent of its public address. `nutriscope.live` is the reference address, not a required domain. The institution can use another domain it controls, such as `nutriscope.org`, or add `nutriscope.hospital.org` beneath an existing institutional domain. Registering another extension requires ownership of that separate domain; adding a subdomain requires control of the existing domain's DNS zone.

For an existing domain serving other systems, add a distinct `nutriscope` hostname in its current authoritative DNS zone. Retain the existing website, mail and other subdomain records. There is no need to move the whole domain to Cloudflare solely to use NutriScope or R2. See [subdomain records](https://developers.cloudflare.com/dns/manage-dns-records/how-to/create-subdomain/). Use a dedicated hostname; deployment under a path such as `hospital.org/nutriscope` requires application/proxy changes beyond these setup commands.

| Choice or affected value | Exact place to edit | Procedure |
|---|---|---|
| Website hostname → VPS | Existing DNS provider's zone editor; Cloudflare **domain → DNS → Records** | Add/edit A for `nutriscope` pointing to `ORIGIN_IP`; add AAAA only for configured IPv6; preserve unrelated records |
| New SSH target | GitHub **repository → Settings → Secrets and variables → Actions → Secrets** | Set `DROPLET_HOST` to origin IP and the corresponding user/key/fingerprint; [secret steps](environment.md#github-actions-repository-secrets-obtain-enter-and-replace) |
| Public web/reset-link origin | VPS `~/Nutriscope/backend/.env.production`; start from [production example](../../backend/.env.production.example) for a new installation | Set `APP_URL` and `FRONTEND_URL` to the full chosen HTTPS origin; [edit and apply](environment.md#enter-the-collected-values) |
| Cookies and hostnames | Same protected backend file | Set `SESSION_DOMAIN` and `SANCTUM_STATEFUL_DOMAINS`; for a shared parent domain, scope cookies to the dedicated NutriScope hostname rather than unrelated sibling applications |
| Nginx and certificate | VPS `/etc/nginx/sites-available/nutriscope`, `/etc/nginx/snippets/nutriscope-mobile-api.conf` | Set `server_name` to the chosen hostname(s), retain the [committed proxy locations](../../nginx/mobile-api.locations.conf), then [issue TLS](vps-setup.md#5-dns-and-https) for those names |
| Mobile API origin | [mobile/eas.json](../../mobile/eas.json), preview/production profile `EXPO_PUBLIC_API_URL` | Set `https://CHOSEN_HOST/mobile-api`, then build/sign/publish a compatible APK; [release steps](mobile-release.md) |
| Mobile update/download URLs | [mobile/lib/appUpdate.ts](../../mobile/lib/appUpdate.ts) and [.github/workflows/release-mobile.yml](../../.github/workflows/release-mobile.yml) | Update domain-dependent metadata/verification addresses; keep old installed-client endpoints usable during transition |
| Download page and QR | [FssAppAccess.tsx](../../frontend/components/mobile-app/FssAppAccess.tsx), [QR SVG](../../frontend/public/nutriscope-mobile-qr.svg) | Button uses a relative download path; regenerate and phone-scan the encoded QR for a new host |
| Sender domain | Resend **Domains** and authoritative DNS; backend `MAIL_FROM_ADDRESS` | Sender may remain on an existing verified institutional domain; [sender setup](integrations.md#resend-email-signup-sender-dns-and-key) |

If only the VPS IP changes while the public hostname remains the same, update DNS and SSH/host settings. Domain-based app URLs and APKs do not need changing solely because the server IP changed. For a new public hostname, use [the complete change matrix](turnover.md#every-place-to-update-when-addresses-or-accounts-change), recreate backend roles after env changes and test cookies, reset links, mobile login and downloads. Never replace the production `APP_KEY` as part of an address change.
