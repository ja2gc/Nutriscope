# Acceptance and maintenance

Use this page after [VPS setup](vps-setup.md). Keep the same deploy user, `~/Nutriscope` directory and Compose project on every visit. Do not paste application logs, database contents, environment values or private paths into tickets.

## Open an operator session

**Connection — trusted workstation:**

```sh
ssh -i ~/.ssh/nutriscope-operator deploy@ORIGIN_IP
```

**Shell setup — VPS, deploy user, Bash; prompts hide the database password:**

```sh
cd ~/Nutriscope
read -rsp 'MySQL root password: ' DB_ROOT_PASSWORD
printf '\n'
export DB_ROOT_PASSWORD
export COMPOSE_PROJECT_NAME=nutriscope
dc() { docker compose -f docker-compose.yml -f docker-compose.prod.yml "$@"; }
```

Use the institution's database password, not the Linux sudo password. Defining this function does not deploy. At the end, `unset DB_ROOT_PASSWORD` removes it from the shell; close the session. Do not save password exports in `.bashrc` or shell history.

## Acceptance checklist

**Read-only checks — VPS, deploy user, `~/Nutriscope`:**

```sh
git rev-parse HEAD
git status --short
dc config --quiet
dc ps
free -m
df -h
swapon --show
docker stats --no-stream
curl --fail --silent --show-error http://127.0.0.1:8080/up
curl --fail --silent --show-error --output /dev/null http://127.0.0.1:3000/login
curl --fail --silent --show-error https://example.org/up
curl --fail --silent --show-error --output /dev/null https://example.org/login
curl --head --fail https://example.org/downloads/nutriscope-fss.apk
sudo nginx -t
sudo certbot certificates
systemctl list-timers --all
dc exec --user www-data backend php artisan migrate:status
dc exec --user www-data backend php artisan schedule:list
```

Expected: intended revision, clean source checkout, no unresolved Compose errors, all six long-running roles (`mysql`, `redis`, backend, worker, scheduler, frontend) running, healthy data services, successful HTTP responses, valid certificate and renewal scheduling. APK checks apply only after the verified signed artifact is installed. The release container is one-off; it should exit successfully, not stay running. HTTP `/up` does not prove mail, file access or recovery.

Use authorized fictional test records/accounts for the following **state-changing acceptance checks**. Record results privately with date, hostname, app revision and responsible operator:

| Check | Required result |
|---|---|
| Web login/logout | Named Admin and RND accounts can use their own permitted screens; FSS uses mobile |
| Accounts/recovery | Receiving administrator owns access; password change, recovery-email verification and password-reset mail work |
| Clinical/food service | RND can perform the required workflow; FSS can complete permitted native tasks and cannot author restricted planning data |
| Files | Harmless private upload downloads only with appropriate authentication; unauthorized requests are denied |
| Reports | A synthetic report prepares/downloads correctly; worker processes queued work |
| Provider features | Authorized USDA search/preview and, if enabled, a controlled synthetic AI request succeed |
| Email | Verified sender delivers to a controlled inbox; sending permission is not mistaken for mailbox ownership |
| Mobile distribution | APK bytes/MIME, metadata, QR, installed-app API origin and signed update work |
| Backups | Harmless file and matching DB data appear in a usable backup; isolated restore succeeds, not merely bucket upload |
| Resources | Representative report, backup and request activity fits the chosen host without sustained OOM, disk exhaustion or unhealthy restarts |

Confirm the institution controls billing, renewal, MFA recovery, administrator recovery and historical backup decryption. Accept traffic only after applicable checks pass. Retain the old host/data through the agreed cutover recovery period.

## Routine releases

Use [the Actions deployment instructions](vps-setup.md#enable-the-workflow-and-deploy). The workflow already migrates and seeds its release-owned template. It does not update provider accounts, secrets or the host OS. Avoid editing tracked code on the VPS: divergent changes prevent the required fast-forward checkout.

After changing only server environment settings use [recreate backend roles](environment.md#apply-a-later-environment-change). Restarting containers is insufficient for new `env_file` values. After changing Nginx, run `sudo nginx -t` before `sudo systemctl reload nginx` (state-changing reload). Do not reload invalid configuration.

## Certificate and host upkeep

**State-changing validation — VPS, sudo-capable operator:**

```sh
sudo certbot renew --dry-run
```

The dry run tests renewal against the certificate authority; check its result and timer. Keep port 80/DNS validation working for the configured challenge. Monitor domain expiry, certificate expiry, billing, disk/memory and backup failure alerts. Provider monitoring/alerts are operator configuration; Compose does not create them.

Review Ubuntu/security updates and Docker upgrades in a maintenance window. Check installed services, compatibility, resource health and backups first; do not run unattended distribution upgrades or reboot during active clinical work. After an approved reboot, verify Docker/Nginx, all Compose roles, renewal timers and application acceptance.

Successful deploys automatically prune only dangling images older than 24 hours on this dedicated host. Current/rollback tags and container-referenced images remain. This is not permission for `docker system prune`, `image prune --all` or volume deletion. Database/uploads are durable data; image rollback does not roll back migrations or new records.

## Troubleshooting

Start with the read-only checks above and the failed Actions step. Inspect logs only in a restricted terminal: even error logs can contain personal data or access links. Do not publish raw logs or environment dumps.

| Symptom | Check and next action |
|---|---|
| SSH permission denied | Origin IP, actual user, matching CI public key, permissions and provider firewall; distinguish CI-to-VPS from VPS-to-GitHub keys |
| Host fingerprint mismatch | Verify the server key through the provider console; investigate replacement/reinstall before updating the secret |
| `git fetch` denied | Repository deploy key, its read-only permission, SSH configuration and `origin` URL |
| Checkout/revision mismatch | Current `origin/main` versus run SHA; rerun a current release, not a stale workflow |
| Compose missing password | Supply `DB_ROOT_PASSWORD` to that operator shell or Actions secret; backend dotenv alone does not provide interpolation |
| MySQL authentication failure | Match backend credentials to actual initialized database; changing dotenv does not rotate MySQL passwords |
| Release template-seed failure | Follow [initial-data dependencies](initial-data.md); do not run full demo seeding against a client database |
| HTTP 502 | Backend/frontend container state, loopback ports and Nginx upstream; inspect memory/OOM and release result |
| SSL issuance/renewal failure | A/AAAA, both requested hostnames, port 80, DNS proxy/challenge configuration and Nginx syntax |
| Mail/AI/USDA failure | Receiving account access, current key/domain/model permissions, provider quota/billing and recreated backend roles |
| Backup failure | Worker/scheduler, separate R2 bucket/endpoint/credentials, archive password, free disk and retention permissions; prove restore separately |
| App still uses old server | Domain DNS versus baked mobile API URL, existing installed APK and old-host compatibility; see [address matrix](turnover.md#every-place-to-update-when-addresses-or-accounts-change) |

For recovery use [backup/recovery](backup-recovery.md). If Admin cannot open the application or restore screen, use [manual R2 recovery](backup-recovery.md#manual-recovery-when-the-admin-screen-is-unavailable). Freeze writers and preserve the authoritative DB/file set before choosing restore or forward repair. Never use `migrate:fresh` or volume deletion as troubleshooting.
