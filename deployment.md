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
