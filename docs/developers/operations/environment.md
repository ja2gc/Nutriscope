# Environment settings

Verified against committed source: `14a954e0a1167d781cf007a61c610dff297f952a` (2026-10-24).

Purpose: identify where settings belong. This is a variable-name map; supply secret values yourself through a password manager or protected server files. See [integrations](integrations.md) for account/key setup.

## Precedence and files

- [backend/.env.production.example](../../../backend/.env.production.example) is the production template. Copy it manually to backend/.env.production on the server; never commit the real file.
- [Production Compose](../../../docker-compose.prod.yml) loads that file for backend, worker, scheduler and release, **then overrides MAIL_MAILER and ANTHROPIC_MODEL** in its shared environment. Change those production settings in Compose.
- Laravel [services.php](../../../backend/config/services.php) reads ANTHROPIC_MODEL for non-Compose/local deployments; [backend/.env.example](../../../backend/.env.example) remains the local template.
- DB_ROOT_PASSWORD is required by Compose interpolation for MySQL. It does not come from the backend service's env_file. Supply it in the operator shell or approved secret manager; the current workflow passes the GitHub secret.
- [frontend/.env.example](../../../frontend/.env.example) defines server-only LARAVEL_API_URL. Production Compose supplies http://backend/api.
- [mobile/.env.example](../../../mobile/.env.example) and [mobile/eas.json](../../../mobile/eas.json) govern EXPO_PUBLIC_API_URL; profile env values must be reviewed before builds. Public mobile/build variables are visible to users, so never put keys there.

## GitHub Actions repository secrets: obtain, enter and replace

These five names are deployment inputs, not five provider accounts. `DROPLET_*` is a historical name: keep the names even when the destination is not DigitalOcean. Both workflows assume the normal SSH port 22; neither currently supplies a custom port. A different SSH port requires a reviewed workflow change before deployment.

In the **destination repository**, open **Settings → Secrets and variables → Actions → Secrets → New repository secret**. Enter each exact name below and its value. For an existing secret, use its edit action. Do not put these in the Variables tab. The jobs do not select a GitHub Environment, so an environment-only secret will not satisfy the existing workflow. Organization secrets must explicitly allow this repository. See [GitHub's secret setup](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets).

| Name | Purpose and exact value source | What changes during turnover |
|---|---|---|
| `DB_ROOT_PASSWORD` | The password for the bundled MySQL **root database user**, supplied to Compose by [deploy.yml](../../../.github/workflows/deploy.yml). For an existing database, obtain it from the approved institutional secret manager/outgoing custodian. For an empty installation, generate a unique strong password there before initializing MySQL. It is not the VPS login password. | Preserve for an unchanged/restored physical database unless a reviewed DB rotation changes it. With the current `DB_USERNAME=root` template, protected server `DB_PASSWORD` must match. Changing the GitHub secret does not change an existing MySQL user's password. A logical import into a separately initialized DB must use that destination's provisioned credential. |
| `DROPLET_HOST` | The destination VPS's public IPv4 address from its provider dashboard: on DigitalOcean, select the Droplet and its public IPv4/network details. A direct SSH DNS hostname is also possible if it resolves to that same origin. No `https://`, port, username or path. | Replace for a new host/IP. Do not use a Cloudflare-proxied website hostname as the SSH target; use the origin IP or a dedicated DNS-only SSH name. |
| `DROPLET_USER` | The Linux account that owns `~/Nutriscope` and can run `docker` noninteractively, e.g. the `deploy` account created in [VPS setup](../../operations/vps-setup.md#2-clone-and-configure). Confirm the account in the provider console with `whoami` when logged in as that user. | Replace if the new host uses a different account. This is not a GitHub username, email, database user or provider-account name. |
| `DROPLET_SSH_KEY` | The complete **private** OpenSSH key, including header/footer and newlines, whose public `.pub` partner is installed in that Linux user's `~/.ssh/authorized_keys`. Generate a dedicated CI pair using the procedure below. Provider dashboards generally accept the public key; they do not supply the private key. | Generate a receiving-team CI pair, install its public key, then replace the secret. Keep the old key until the replacement is accepted; revoke it afterward. Current workflows provide no passphrase input, so the dedicated CI key must work without an interactive passphrase. |
| `DROPLET_HOST_FINGERPRINT` | The destination SSH **server's public host-key fingerprint**, in `SHA256:...` form, obtained through its trusted provider console as below. This identifies the server; it is not the fingerprint of the CI/user key. | Re-verify and replace after host replacement, OS reinstall or host-key rotation, even if the IP is reused. An account/billing change alone does not require replacement if the host key is unchanged. |

GitHub does not reveal a saved secret's plaintext value. The “Last updated” column proves only when it was edited; it does not prove correctness, server reachability or receiving-team ownership. Recover missing credentials through the approved custodian or arrange replacement/rotation; do not guess passwords or reset a database to make CI pass.

### Create the CI SSH pair

**State-changing — receiving operator's trusted workstation only**, outside any repository. With OpenSSH in Bash/WSL/Linux/macOS:

```sh
mkdir -p ~/.ssh
ssh-keygen -t ed25519 -C "nutriscope-actions" -f ~/.ssh/nutriscope-actions
```

Or in PowerShell with OpenSSH installed:

```powershell
New-Item -ItemType Directory -Force -Path "$HOME/.ssh" | Out-Null
ssh-keygen -t ed25519 -C "nutriscope-actions" -f "$HOME/.ssh/nutriscope-actions"
```

Use a new filename if it already exists; never overwrite an operator's key. For this dedicated automation key, leave the passphrase empty at the interactive prompts. Protect the private file with access limited to its owner and store it in the institution's secret manager. Through an already trusted console/session, append **only** `nutriscope-actions.pub` to the target user's `authorized_keys`, preserving existing entries and the permissions in [VPS setup](../../operations/vps-setup.md#2-clone-and-configure). Put the private file in `DROPLET_SSH_KEY` through GitHub's private secret form; do not paste it into docs, logs, chat or a terminal command argument.

The CI-to-VPS key and the VPS-to-GitHub clone/pull credential have different directions and purposes. For a private repository, generate a separate read-only repository deploy key on the VPS, add its **public** half under **Repository Settings → Deploy keys**, and keep its private half protected on the VPS. Granting CI SSH access to the VPS does not grant `git fetch origin main` access to GitHub. See [GitHub deploy keys](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/managing-deploy-keys).

### Verify the server fingerprint independently

**Read-only — run in the destination provider's authenticated console**, not an unverified first SSH connection:

```sh
sudo ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub -E sha256
```

Copy only the `SHA256:...` field to `DROPLET_HOST_FINGERPRINT`. If the server/client negotiates a different host-key algorithm, independently obtain that algorithm's public host-key fingerprint instead. Never read/copy `/etc/ssh/ssh_host_*_key` private files. Verify the workstation's SSH prompt against this trusted fingerprint before accepting the host and testing the CI key:

```sh
# Read-only authentication check; replace the placeholders and verify the prompt.
ssh -o IdentitiesOnly=yes -i ~/.ssh/nutriscope-actions DEPLOY_USER@ORIGIN_IP 'whoami && test -d "$HOME/Nutriscope/.git" && docker version --format "{{.Server.Version}}"'
```

First acceptance writes the workstation's known_hosts entry. A network `ssh-keyscan` result alone is not independent identity proof. A changed fingerprint needs investigation against the console; do not disable host checking or blindly replace it to clear an error.

### Which workflow reads which secret?

- [Web/backend deployment](../../../.github/workflows/deploy.yml) uses all five. It validates fingerprint format, pins the SSH/SCP connection fingerprint, builds images, fetches `main` on the host and runs data-changing release migrations.
- [APK publication](../../../.github/workflows/release-mobile.yml) uses `DROPLET_HOST`, `DROPLET_USER` and `DROPLET_SSH_KEY`; it does not use `DB_ROOT_PASSWORD` or `DROPLET_HOST_FINGERPRINT`. **Known gap:** its SSH action steps do not pin a fingerprint, and its chunk upload trusts a freshly collected network `ssh-keyscan`. Setting the fingerprint secret does not fix this workflow. Review/remediate host authentication before relying on it for a replacement host; this documentation change does not change the workflow.

Do not trigger either workflow as a harmless secret test. A push to `main` normally deploys web/backend; a push changing only `mobile/release.json` publishes the APK instead. A push changing that file and other files can trigger both. Provision the host, approve data migration/release effects and complete [cutover readiness](../../operations/turnover.md#existing-system-migration-sequence) before enabling production runs.

## Backend and client configuration groups

| Setting names | Where/how to choose |
|---|---|
| APP_KEY | Generate once for a new installation; securely preserve it for an existing DB/encrypted files |
| APP_ENV, APP_DEBUG, APP_URL, FRONTEND_URL | Production mode, debug off, canonical HTTPS site and correct reset-link origin |
| DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD | Actual MySQL service/account. Current example uses bundled mysql/root; a separate DB account needs explicit provisioning/grant review |
| DB_ROOT_PASSWORD | Compose/MySQL initialization and deployment secret; match DB_PASSWORD if using the example's root account |
| DB_DUMP_SKIP_SSL, DB_DUMP_SSL_FLAG | Bundled MariaDB dump client options: review private-container certificate compatibility; never disable TLS for a remote managed DB by habit |
| CACHE_STORE, CACHE_PREFIX, REDIS_CLIENT, REDIS_HOST, REDIS_PORT, REDIS_PASSWORD | Shared Redis locks/cache; current Compose service hostname is redis |
| QUEUE_CONNECTION, REDIS_QUEUE_RETRY_AFTER | redis, and retry-after longer than the 1200-second worker timeout (example 1260) |
| APP_MAINTENANCE_DRIVER, APP_MAINTENANCE_STORE | cache/redis for coordinated maintenance across containers |
| SESSION_*, SANCTUM_STATEFUL_DOMAINS | Domain/cookie settings; don't retain an old domain when moving names |
| ANTHROPIC_API_KEY, USDA_API_KEY | Institution-owned provider keys, backend only |
| RESEND_API_KEY, RESEND_API_ENDPOINT, MAIL_FROM_ADDRESS, MAIL_FROM_NAME | Verified sender/domain; mailer is pinned by production Compose |
| UPLOADS_DISK, UPLOADS_PUBLIC_URL | Legacy/public upload routing and optional public origin |
| PRIVATE_UPLOADS_DISK, PRIVATE_UPLOADS_DRIVER | private_uploads and local for the named-volume setup; s3 only after storage migration |
| PRIVATE_UPLOADS_ACCESS_KEY_ID, PRIVATE_UPLOADS_SECRET_ACCESS_KEY, PRIVATE_UPLOADS_REGION, PRIVATE_UPLOADS_BUCKET, PRIVATE_UPLOADS_ENDPOINT, PRIVATE_UPLOADS_USE_PATH_STYLE_ENDPOINT | Conditional private S3/R2 primary storage; leave credentials blank with local storage |
| BACKUP_DISK, BACKUP_FILESYSTEM_DRIVER, BACKUP_NAME, BACKUP_QUEUE, BACKUP_TIMEZONE | Backup disk/driver/naming/worker scheduling |
| BACKUP_ACCESS_KEY_ID, BACKUP_SECRET_ACCESS_KEY, BACKUP_DEFAULT_REGION, BACKUP_BUCKET, BACKUP_ENDPOINT, BACKUP_USE_PATH_STYLE_ENDPOINT | Separate private backup storage account/bucket/access |
| BACKUP_ARCHIVE_PASSWORD | Separate archive encryption password; retain passwords needed for historical backups |
| BACKUP_ALERT_EMAIL, BACKUP_RESTORE_ENABLED | Alert recipient; leave restore disabled until independent recovery readiness is established |
| AUDIT_DATABASE_DISK_USED_PERCENT, AUDIT_RETENTION_ENABLED, AUDIT_EXPORT_ENABLED | [audit config](../../../backend/config/audit.php); retention's saved database state can supersede the initial env default |
| LOG_*, APP_LOCALE, APP_FALLBACK_LOCALE, BCRYPT_ROUNDS | Framework logging/locale/password configuration in backend/config |

Other keys are declared in [config](../../../backend/config); do not remove a setting merely because a production default hides it. The production template omits SMTP-only fields and VITE_APP_NAME for this deployment; switching mail transports requires a reviewed config change.

After backend environment changes, recreate all backend roles so env_file is reloaded. A mere docker restart does not reload container environment. Laravel config cache is rebuilt by [entrypoint](../../../backend/docker-entrypoint.sh); long-running workers/scheduler must use the same new settings.
