# NutriScope Deployment Guide

NutriScope has two runtime configurations:

- **Local (hybrid):** infra in Docker, backend + frontend run natively for hot-reload.
- **Deploy (VPS):** the full stack runs in Docker behind host nginx with Let's Encrypt SSL.

Both use the same two compose files:

| File | Contents | Used by |
|------|----------|---------|
| `docker-compose.yml` | mysql, redis, paddleocr, omr (ports bound to `127.0.0.1`) | local + deploy (base) |
| `docker-compose.prod.yml` | backend, frontend (overlay; publishes frontend `127.0.0.1:3000`) | deploy only |

Local runs the base file alone. Deploy merges both with `-f`.

---

## Local (hybrid)

Infra runs in Docker; backend and frontend run natively.

```bash
# 1. Start infra (MySQL, Redis, paddleocr, omr)
docker compose up -d

# 2. Backend (native) — http://localhost:8000
cd backend
cp .env.example .env        # first time only; then: php artisan key:generate
php artisan migrate
php artisan serve

# 3. Frontend (native) — http://localhost:3000
cd frontend
cp .env.example .env.local  # first time only
npm install                 # first time only
npm run dev
```

Laravel Boost MCP runs as a local PHP process (configured in the Claude app), not in Docker.

---

## Deploy (VPS)

NutriScope runs on a DigitalOcean Droplet (Ubuntu 24.04) at `168.144.115.27`. App containers
run in Docker; **host nginx** (not a Docker nginx) is the reverse proxy with Let's Encrypt SSL
via Certbot.

### Architecture

```
Internet → Host nginx (port 80/443) → Docker frontend (127.0.0.1:3000)
                                     → Docker backend   (internal)
                                     → Docker mysql     (internal)
                                     → Docker redis     (internal)
                                     → Docker paddleocr (internal)
                                     → Docker omr       (internal)
```

Key points:
- Host nginx handles all public traffic and SSL termination.
- There is no Docker nginx service — starting one would conflict on port 80.
- The frontend container publishes `127.0.0.1:3000` (set in `docker-compose.prod.yml`), which
  is the upstream host nginx proxies to.
- All other containers are internal to the Docker network only.

### Prerequisites (one-time, already done)

- DNS A records for `nutriscope.live` and `www.nutriscope.live` → `168.144.115.27`.
- SSL certificate issued via Certbot, auto-renewal configured.
- `backend/.env.production` exists on the server (copy from `backend/.env.production.example`,
  fill in `APP_KEY` and secrets).

### Standard redeployment (use this every time)

Run on the VPS after pushing changes to GitHub:

```bash
cd ~/Nutriscope
git fetch origin
git reset --hard origin/main

docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend php artisan config:clear
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend php artisan cache:clear
```

This is the same command the GitHub Actions deploy (`.github/workflows/deploy.yml`) runs on
every push to `main`.

> **Note — `docker-compose.override.yml` is obsolete.** Earlier deploys used a server-only
> `docker-compose.override.yml` to publish the frontend port. That publish now lives in
> `docker-compose.prod.yml`, so the override is no longer needed. Delete it from the server
> (`rm ~/Nutriscope/docker-compose.override.yml`) to avoid confusion, since `docker compose`
> auto-merges it on plain `up` calls.

### First-time server setup (already done, for reference)

```bash
set -e
cd ~/Nutriscope
sudo apt update
sudo apt install -y nginx certbot python3-certbot-nginx

docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

echo "Waiting for frontend..."
until curl -sf http://127.0.0.1:3000 > /dev/null; do sleep 2; done

sudo tee /etc/nginx/sites-available/nutriscope >/dev/null <<'EOF'
server {
    listen 80;
    server_name nutriscope.live www.nutriscope.live;
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
EOF

sudo ln -sf /etc/nginx/sites-available/nutriscope /etc/nginx/sites-enabled/nutriscope
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
sudo ufw allow 'Nginx Full'

sudo certbot --nginx -d nutriscope.live -d www.nutriscope.live \
  --non-interactive --agree-tos --email jaredabriol2@gmail.com

curl -IL http://nutriscope.live
curl -I https://nutriscope.live
```

### Verification checks

```bash
# Containers — should show mysql, redis, backend, frontend, paddleocr, omr (no nginx container)
docker ps

# Host nginx
sudo systemctl status nginx

# Site responses
curl -IL http://nutriscope.live   # 301 → 307 → 200
curl -I https://nutriscope.live   # 307 to /login
```

### Why the 502 happened

The previous prod compose put the frontend only on the internal Docker network with no
published port (the publish lived in an uncommitted `docker-compose.override.yml`). After a
`git reset --hard`, nothing republished port 3000, so host nginx had no reachable upstream →
502. The publish is now committed in `docker-compose.prod.yml`, and the prod stack includes
`paddleocr`/`omr` (previously missing) via the merged base file.

---

## Important rules

**Never run `npm install` on the VPS host.** The frontend Dockerfile uses `npm ci`, which
requires `package.json` and `package-lock.json` to be in sync. If a build fails with a
lockfile mismatch:

1. On your local machine, inside `frontend/`, run `npm install`.
2. Commit the updated `package-lock.json`.
3. Push to GitHub.
4. Redeploy from the VPS using the standard redeployment commands above.

**Never start a Docker nginx service.** Host nginx handles all routing; a Docker nginx would
conflict on port 80 and bring down the site.

**SSL is managed by Certbot, not Docker.** The certificate lives at
`/etc/letsencrypt/live/nutriscope.live/`. Certbot auto-renews it.

---

## Mobile API on the root domain (/mobile-api)

The production APK calls Laravel **directly** through
`https://nutriscope.live/mobile-api`. This avoids a new DNS subdomain and avoids a new
Certbot certificate. It still uses production HTTPS because it reuses the existing
`nutriscope.live` certificate.

The web app keeps `https://nutriscope.live`. Next.js web API routes keep `/api/*`.
Only `/mobile-api/*` is routed by host nginx straight to the Laravel backend on
`127.0.0.1:8080` (published by `docker-compose.prod.yml`).

Run the steps below top-to-bottom in the droplet web console. nginx + certbot are already
installed for the root domain.

### Step 1 - Pull latest + redeploy (publishes backend on 127.0.0.1:8080)

```bash
cd ~/Nutriscope
git fetch origin && git reset --hard origin/main
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# confirm backend is reachable on the host (expect 405 or 422, NOT connection refused):
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8080/api/auth/login
```

### Step 2 - Add the mobile-api locations to host nginx

Edit the existing root-domain site:

```bash
sudo nano /etc/nginx/sites-available/nutriscope
```

Inside the existing `server` block for `listen 443 ssl;` and
`server_name nutriscope.live www.nutriscope.live;`, paste this before the existing
`location /` block:

```nginx
location = /mobile-api {
    return 308 /mobile-api/;
}

location /mobile-api/ {
    client_max_body_size 25M;

    proxy_pass         http://127.0.0.1:8080/;
    proxy_http_version 1.1;
    proxy_set_header   Host              $host;
    proxy_set_header   X-Real-IP         $remote_addr;
    proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header   X-Forwarded-Proto $scheme;
}
```

The same snippet is committed at `nginx/mobile-api.locations.conf` for copy/paste reference.

Then validate and reload nginx:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### Step 3 - Final API smoke test (the gate before building the APK)

```bash
curl -s https://nutriscope.live/mobile-api/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"fss@nutriscope.local","password":"nutriscope2024!","device_name":"Expo App","platform":"app"}'
```

Expected: JSON with `token` (string) and `user.role` = `"FSS"`. Only after this passes is it
worth running `eas build` because the APK bakes in this origin at build time.

---

## Database & env on redeploy — what to run, what NOT to run

### Migrations — never `migrate:fresh` on production

`git reset --hard` + `up -d --build` does not run migrations by itself unless the entrypoint
does. To apply any **pending** migrations safely:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend \
  php artisan migrate --force
```

`migrate --force` only applies new migrations and is a no-op when none are pending.
**Never run `migrate:fresh --seed` on the production database — it drops every table.**

### The `.env.production` file is NOT in git

`backend/.env.production` is gitignored, so `git pull` / `git reset` never touches the
droplet's copy. After adding new config keys to the code you must edit the live file by hand.

**Check for missing keys** (drift between the live env and what the code now expects):

```bash
cd ~/Nutriscope/backend
grep -oE '^[A-Z_]+=' .env.production.example | sort > /tmp/want.txt
grep -oE '^[A-Z_]+=' .env.production         | sort > /tmp/have.txt
comm -23 /tmp/want.txt /tmp/have.txt          # anything printed = MISSING, add it
```

**Check the API keys** (a dropped leading character silently breaks AI features):

```bash
grep -E 'ANTHROPIC_API_KEY|USDA_API_KEY' ~/Nutriscope/backend/.env.production
```

`ANTHROPIC_API_KEY` must be non-empty and start with `sk-ant-`. (This affects RND AI
diagnosis/suggest only — FSS mobile uses no AI.)

### Production email sender (password reset / recovery email)

Password reset and recovery verification emails require a real mail sender. Production uses
Resend SMTP with the verified `nutriscope.live` domain, sending from
`no-reply@nutriscope.live`.

Things to do outside the VPS:

1. Create or open a Resend account.
2. Add the domain `nutriscope.live` in Resend.
3. Add all DNS records Resend gives you where the domain DNS is hosted:
   SPF, DKIM, DMARC, and any return-path / bounce CNAME.
4. Wait until Resend marks the domain verified.
5. Create a Resend API key.

Set these in `~/Nutriscope/backend/.env.production` on the VPS:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=re_your_resend_api_key_here
MAIL_EHLO_DOMAIN=nutriscope.live
MAIL_FROM_ADDRESS="no-reply@nutriscope.live"
MAIL_FROM_NAME="${APP_NAME}"
RESEND_API_KEY=re_your_resend_api_key_here
```

`no-reply@nutriscope.live` does not need an inbox. Resend sends from it because the domain
is verified by DNS. Users can receive email at Gmail, Outlook/Microsoft, Yahoo, or work email.

After deployment, test the real email path:

1. Log in as a user.
2. Open Profile / Settings.
3. Set recovery email to a real inbox.
4. Send verification code.
5. Verify the code.
6. Log out and use Forgot Password with that recovery email.
7. Confirm the reset email arrives and the password reset completes.

**After editing the env, recreate the backend and rebuild config cache:**

```bash
cd ~/Nutriscope
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --force-recreate backend
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend php artisan config:clear
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend php artisan config:cache
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend php artisan migrate --force
```

---

## Demo users — verify and seed safely

Demo users are seeded by `AdminUserSeeder` and `FoodServiceDemoSeeder`. Check and seed
without wiping production data using the commands below.

### Check demo users exist

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend \
  php artisan tinker --execute="
    use App\\Models\\User;
    foreach(['admin@nutriscope.local','rnd@nutriscope.local','fss@nutriscope.local'] as \$e) {
      \$u = User::where('email', \$e)->first();
      echo \$e . ': ' . (\$u ? 'OK role='.\$u->role.' active='.(\$u->is_active?'yes':'no') : 'MISSING') . PHP_EOL;
    }
  "
```

### Safe demo seed (non-destructive — uses firstOrCreate)

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend \
  php artisan db:seed --class=AdminUserSeeder --force
```

For full operational demo data (menu cycles, POs, diet lists):

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend \
  php artisan db:seed --class=FoodServiceDemoSeeder --force
```

> **Warning:** `FoodServiceDemoSeeder` truncates operational FS tables (not users or food items)
> before re-seeding. Only run on a disposable demo database.
>
> **Never run `migrate:fresh --seed --force` on a production database** — this wipes all data.
> Use the targeted seeders above instead.

---

## FSS mobile APK — build order checklist

1. `/mobile-api` nginx route is live on `https://nutriscope.live` (mobile-api section above).
2. API smoke test returns a `token` (Step 3 above).
3. Demo `fss@nutriscope.local` exists and is active (demo-users section above).
4. `mobile/eas.json` preview and production profiles point at
   `https://nutriscope.live/mobile-api` (already committed).
5. Build (requires an Expo account — run locally, not on the VPS):

   ```powershell
   cd mobile
   npx eas login
   npx eas build -p android --profile production
   ```

The APK bakes in `EXPO_PUBLIC_API_URL` at build time, so the API origin **must** be live and
passing the smoke test before building — otherwise the installed app cannot log in.

---

## Moving to managed MySQL / Redis (PaaS path)

No code or compose changes needed — only env:

1. In `backend/.env.production`, set `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD` (and
   `REDIS_HOST` / `REDIS_PASSWORD`) to the managed endpoints.
2. Remove the `mysql` / `redis` services from the deploy if no longer self-hosted, or leave
   them unused.
3. Redeploy. Each service has its own Dockerfile, so the same images run on any Docker host
   or container PaaS (Railway, Render, Fly, Coolify).

---

## Key file locations

| File | Location | Purpose |
|------|----------|---------|
| Host nginx config | `/etc/nginx/sites-available/nutriscope` | Reverse proxy and SSL config |
| SSL certificate | `/etc/letsencrypt/live/nutriscope.live/` | Managed by Certbot |
| Compose base | `~/Nutriscope/docker-compose.yml` | mysql, redis, paddleocr, omr |
| Compose prod overlay | `~/Nutriscope/docker-compose.prod.yml` | backend, frontend (+ frontend port publish) |
| Backend prod env | `~/Nutriscope/backend/.env.production` | Production environment variables |

---

## Accessing the server

Via DigitalOcean web console (no SSH setup needed):
1. Log in to DigitalOcean
2. Open the Droplet
3. Click **Console**

Or via SSH:
```bash
ssh root@168.144.115.27
```
