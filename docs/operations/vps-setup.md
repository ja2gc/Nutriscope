# Set up a replacement VPS

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: operator runbook for the existing Compose roles on a fresh Ubuntu 24.04 LTS VPS. This is a documented target setup, **not evidence that a new host was configured or tested**. They change the target host unless marked read-only.

For an existing system, follow [turnover/cutover](turnover.md) before the data/release steps. Do not treat a move as a fresh empty installation.

## 1. Prepare the host

Use a sudo-capable operator account, SSH public-key login and provider console recovery. Allow only SSH (actual configured port), HTTP 80 and HTTPS 443 at the provider firewall. Keep another SSH session open while changing access. Do not expose MySQL/Redis/container ports publicly.

**State-changing — fresh host packages:**

```sh
sudo apt-get update
sudo apt-get install -y ca-certificates curl git nginx snapd
```

Install Docker from the [official Ubuntu repository instructions](https://docs.docker.com/engine/install/ubuntu/) (check supported OS and existing-package conflicts first):

```sh
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
. /etc/os-release
printf 'Types: deb\nURIs: https://download.docker.com/linux/ubuntu\nSuites: %s\nComponents: stable\nArchitectures: %s\nSigned-By: /etc/apt/keyrings/docker.asc\n' "$VERSION_CODENAME" "$(dpkg --print-architecture)" | sudo tee /etc/apt/sources.list.d/docker.sources >/dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker nginx
```

**Read-only:** sudo docker version; sudo docker compose version; free -h; df -h; sudo ss -lntp. Use current Compose with support for the production overlay's !reset tag. Use sudo for Docker or grant a trusted deploy account Docker access (equivalent to root); don't add untrusted users.

## 2. Clone and configure

On a fresh host, if the provider has not already supplied the intended deploy account, create one from the privileged setup account. Replace deploy below consistently with the actual GitHub Actions SSH user. These commands change host accounts/permissions; Docker group membership grants root-equivalent control.

```sh
sudo adduser deploy
sudo usermod -aG sudo,docker deploy
sudo install -d -m 0700 -o deploy -g deploy /home/deploy/.ssh
sudoedit /home/deploy/.ssh/authorized_keys
# Add only the intended operator/CI PUBLIC keys in the editor, then:
sudo chown deploy:deploy /home/deploy/.ssh/authorized_keys
sudo chmod 0600 /home/deploy/.ssh/authorized_keys
sudo install -d -m 0755 -o deploy -g deploy /var/www/nutriscope-downloads
```

Verify a new SSH session as that account before changing/removing old access. Reconnect after group changes. Confirm it can run docker without a password prompt: the current CI workflow invokes docker directly, not through interactive sudo. Store the CI private SSH key only in the institution's Actions secret/secret manager. A private repository also needs clone/pull authentication: use an institution-owned read-only repository deploy key or approved credential helper, never a token embedded in the clone URL. Verify Git host fingerprints through a trusted channel before accepting them.

Clone the institution-owned repository using its actual URL (replace the placeholder) into the deploy account's home directory. The current GitHub workflow expects ~/Nutriscope.

```sh
cd ~
git clone REPLACE_WITH_INSTITUTION_REPOSITORY_URL Nutriscope
cd Nutriscope
git rev-parse HEAD
git status --short
cp -n backend/.env.production.example backend/.env.production
chmod 600 backend/.env.production
```

Privately edit backend/.env.production as an operator; never paste it into a ticket/chat. Use the [environment map](../developers/operations/environment.md) and [integration setup](../developers/operations/integrations.md). New installs need a newly generated APP_KEY; moves must preserve the existing one. Provision the backup bucket/password and sender domain before enabling their jobs.

The example's MySQL root account requires DB_PASSWORD to match DB_ROOT_PASSWORD for a newly initialized volume. Existing MySQL volumes do not change passwords when initialization env changes; use a separately reviewed DB credential-rotation procedure.

In a Bash shell, read the Compose-only password without putting it in history; set a stable project name. If using sudo docker, preserve these two variables explicitly.

```sh
read -rsp 'MySQL root password: ' DB_ROOT_PASSWORD
printf '\n'
export DB_ROOT_PASSWORD
export COMPOSE_PROJECT_NAME=nutriscope
dc() { sudo --preserve-env=DB_ROOT_PASSWORD,COMPOSE_PROJECT_NAME docker compose -f docker-compose.yml -f docker-compose.prod.yml "$@"; }
dc config --quiet
```

config --quiet validates without printing resolved secret values. Never paste plain docker compose config output. Preserve the same project identity on later deployments or Compose may select different named volumes. Record volume names privately before a move.

## 3. Build, restore/initialize and start

The following start services and may write data. Take the approved branch:
- **Move existing data:** pause old writers, capture/restore a coherent MySQL+file set at the compatible revision and preserve keys, following [turnover](turnover.md). Complete that reviewed transfer before release migrations. Do not seed demo data.
- **Fresh installation:** the empty DB requires migrations and a reviewed reference-data/admin setup. [DatabaseSeeder](../../backend/database/seeders/DatabaseSeeder.php) includes demo identities and patients; do not run it wholesale. There is no verified turnkey institution-only bootstrap command in this runbook: approve the initial admin/reference-data list before acceptance.

```sh
dc up -d mysql redis
dc --profile release build backend
dc build frontend
# AFTER the chosen data plan, backup and migration review:
dc --profile release run --rm backend_release
dc up -d backend backend_worker backend_scheduler frontend
```

[Release script](../../backend/docker-release.sh) caches config/routes/views, runs migrate --force, seeds FoodServiceMenuTemplateSeeder and runs demographic-census catch-up. It is a data-changing release step, not just a build. Review its suitability before each migration/transfer.

[Production ports](../../docker-compose.prod.yml) are backend 127.0.0.1:8080 and frontend 127.0.0.1:3000. MySQL and Redis also publish loopback in the base file. No PaddleOCR/OMR, Nginx-container or Certbot-container service is declared.

## 4. Host Nginx

Create /etc/nginx/sites-available/nutriscope using sudoedit. Replace example.org and www.example.org with the chosen domain. This is an example **host** configuration; the old nginx/default.conf expects a container hostname and is not this setup.

```nginx
server {
    listen 80;
    server_name example.org www.example.org;
    client_max_body_size 80M;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    include /etc/nginx/snippets/nutriscope-mobile-api.conf;
}
```

Copy the committed [mobile/API/download locations](../../nginx/mobile-api.locations.conf) into that included host path. Do not add a broad /api → Laravel rule: browser /api routes belong to Next.js.

```sh
sudo install -m 0644 nginx/mobile-api.locations.conf /etc/nginx/snippets/nutriscope-mobile-api.conf
sudo ln -s /etc/nginx/sites-available/nutriscope /etc/nginx/sites-enabled/nutriscope
sudo nginx -t
sudo systemctl reload nginx
```

On an existing host inspect enabled sites first to avoid duplicate names; don't overwrite working configuration. The download directory /var/www/nutriscope-downloads must contain a verified signed APK and metadata, readable by Nginx and writable by the approved publishing account. Do not make it world-writable; grant the account controlled ownership or a dedicated group. An empty directory means downloads are not ready.

## 5. DNS and HTTPS

At the authoritative DNS provider, point the intended VPS hostname's A record to the new IPv4 address. Use AAAA only with working IPv6. For www, use the agreed CNAME/A arrangement. Preserve mail and provider-verification records; see the [DNS inventory](turnover.md#dns-inventory).

For testing an existing-domain move, issue a certificate using controlled DNS validation or use a temporary test hostname with valid DNS; don't switch real users solely to obtain a certificate. For the normal HTTP challenge below, both named domains must already reach this host on port 80. If using Cloudflare, validate DNS-only first and then use Full (strict) after origin HTTPS works.

**State-changing — fresh-host Certbot installation and certificate issuance:**

```sh
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/local/bin/certbot
sudo certbot --nginx -d example.org -d www.example.org
sudo nginx -t
sudo systemctl reload nginx
sudo certbot renew --dry-run
```

Follow [Certbot's current Nginx instructions](https://certbot.eff.org/instructions?os=snap&ws=nginx), including conflicts with OS-packaged Certbot. Check renewal timers with systemctl list-timers. Generate a new host certificate; do not copy the old private certificate key. nginx/init-ssl.sh is stale for this topology: it refers to absent nginx/certbot services and must not be used.

## 6. Verify before accepting traffic

**Read-only operator checks** (health requests may initialize framework/database checks):

```sh
dc ps
curl --fail --silent --show-error http://127.0.0.1:8080/up
curl --fail --silent --show-error https://example.org/up
curl --fail --silent --show-error --output /dev/null https://example.org/login
curl --head --fail https://example.org/downloads/nutriscope-fss.apk
```

Then use controlled test accounts for web login/logout, mobile login, an authorized file upload/download, reports, USDA search and intended mail delivery. Confirm worker/scheduler and one harmless backup plus isolated restore drill. Treat these as side-effecting acceptance tests, not read-only checks. Never send real notifications while testing.

Keep the old host recoverable until [turnover acceptance](turnover.md) passes. Validate destination resources, certificates, DNS, database grants and complete restore before cutover.

## 7. Future releases

The [deploy workflow](../../.github/workflows/deploy.yml) runs on main pushes, checks frontend types, SSHes into ~/Nutriscope, fast-forwards origin/main, builds, runs the release and starts services. Even docs-only pushes currently trigger this workflow; publishing documentation is not automatically production-neutral.

Configure DROPLET_HOST, DROPLET_USER, DROPLET_SSH_KEY and DB_ROOT_PASSWORD in repository Actions secrets, even when the replacement VPS is not DigitalOcean. Review SSH host trust and account privileges. Ensure its Docker project name, directory and permissions match the setup above. Run frontend type/lint/test/build checks before publishing application changes; build itself skips TypeScript validation.

Changing only backend secrets: recreate backend, backend_worker and backend_scheduler with the approved new environment; a restart alone retains old values. Preserve APP_KEY and archive passwords. No deploy/push is performed by maintaining this guide.
