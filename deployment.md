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

## Mobile API subdomain (api.nutriscope.live) — full VPS runbook

The mobile app (Expo / APK) calls Laravel **directly** via `https://api.nutriscope.live`.
This is a separate nginx virtualhost that proxies to the backend container on
`127.0.0.1:8080` (published by `docker-compose.prod.yml`). The web app keeps
`nutriscope.live` (Next.js cookie proxies); mobile must never use it.

> **No new domain or paid SSL needed.** `api.nutriscope.live` is a free subdomain of a domain
> you already own, and Let's Encrypt certs are free. You only add one DNS record and run
> certbot once. Your existing root+www cert is untouched — certbot issues a **separate** cert
> for the subdomain and auto-renews both.

Run the steps below top-to-bottom in the droplet web console. nginx + certbot are already
installed (the root domain uses them).

### Step 1 — Add the DNS record (in your DNS provider, not the server)

```
Type: A    Host: api    Value: 168.144.115.27
```

`Host: api` resolves to `api.nutriscope.live`. Skip only if a wildcard `*.nutriscope.live`
A record already exists. Then verify on the server before continuing:

```bash
dig +short api.nutriscope.live    # MUST print 168.144.115.27
```

Do not continue until this resolves — certbot validates over HTTP and will fail otherwise.

### Step 2 — Pull latest + redeploy (publishes backend on 127.0.0.1:8080)

```bash
cd ~/Nutriscope
git fetch origin && git reset --hard origin/main
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# confirm backend is reachable on the host (expect 405 or 422, NOT connection refused):
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8080/api/auth/login
```

### Step 3 — Create an HTTP-only vhost (certbot adds the HTTPS block itself)

> Do **not** copy `nginx/api.nutriscope.live.conf` directly — it hardcodes cert paths that do
> not exist until Step 4, so nginx would refuse to start (chicken-and-egg). Use this minimal
> HTTP vhost and let certbot write the SSL part.

```bash
sudo tee /etc/nginx/sites-available/api.nutriscope.live >/dev/null <<'EOF'
server {
    listen 80;
    server_name api.nutriscope.live;
    client_max_body_size 25M;
    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
    }
}
EOF

sudo ln -sf /etc/nginx/sites-available/api.nutriscope.live /etc/nginx/sites-enabled/api.nutriscope.live
sudo nginx -t && sudo systemctl reload nginx     # must say "test is successful"
```

If `nginx -t` complains the firewall blocks port 80: `sudo ufw allow 'Nginx Full'`.

### Step 4 — Issue the SSL certificate (free, auto-configures HTTPS + redirect)

```bash
sudo certbot --nginx -d api.nutriscope.live \
  --non-interactive --agree-tos --email jaredabriol2@gmail.com --redirect

sudo nginx -t && sudo systemctl reload nginx
```

`--redirect` forces HTTP→HTTPS. Success prints "Congratulations" / "Deploying certificate".

### Step 5 — Verify HTTPS + auto-renewal

```bash
curl -I https://api.nutriscope.live      # expect HTTP/2 200 or 405
sudo certbot renew --dry-run             # "all simulated renewals succeeded" = renews itself
```

### Step 6 — Final API smoke test (the gate before building the APK)

```bash
curl -s https://api.nutriscope.live/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"fss@nutriscope.local","password":"nutriscope2024!","device_name":"Expo App","platform":"app"}'
```

Expected: JSON with `token` (string) and `user.role` = `"FSS"`. Only after this passes is it
worth running `eas build` — the APK bakes in this origin at build time.

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

**After editing the env, recreate the backend and rebuild config cache:**

```bash
cd ~/Nutriscope
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --force-recreate backend
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend php artisan config:clear
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend php artisan config:cache
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

1. DNS + SSL for `api.nutriscope.live` live (Steps 1–5 above).
2. API smoke test returns a `token` (Step 6 above).
3. Demo `fss@nutriscope.local` exists and is active (demo-users section above).
4. `mobile/eas.json` preview profile points at `https://api.nutriscope.live` (already committed).
5. Build (requires an Expo account — run locally, not on the VPS):

   ```powershell
   cd mobile
   npx eas login
   npx eas build -p android --profile preview
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
