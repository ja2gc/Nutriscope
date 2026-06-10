# NutriScope VPS Deployment Guide

## Overview

NutriScope runs on a DigitalOcean Droplet (Ubuntu 24.04) at `168.144.115.27`.
The stack uses Docker for the app containers and host nginx (not Docker nginx) as the reverse proxy with Let's Encrypt SSL via Certbot.

---

## Architecture

```
Internet → Host nginx (port 80/443) → Docker frontend (127.0.0.1:3000)
                                     → Docker backend (internal)
                                     → Docker mysql (internal)
                                     → Docker redis (internal)
```

Key points:
- Host nginx handles all public traffic and SSL termination
- The Docker nginx service has been removed from `docker-compose.prod.yml`
- The frontend container is only exposed to `127.0.0.1:3000` via `docker-compose.override.yml`
- All other containers are internal to the Docker network only

---

## Prerequisites (one-time, already done)

- DNS A records for `nutriscope.live` and `www.nutriscope.live` pointing to `168.144.115.27`
- No provider-level firewall on the DigitalOcean Droplet (default)
- SSL certificate issued via Certbot, auto-renewal configured
- `docker-compose.override.yml` committed to the repo

---

## First-Time Server Setup (already done, for reference)

```bash
set -e

cd ~/Nutriscope
sudo apt update
sudo apt install -y nginx certbot python3-certbot-nginx

cat > docker-compose.override.yml <<'EOF'
services:
  frontend:
    ports:
      - "127.0.0.1:3000:3000"
EOF

docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.prod.yml -f docker-compose.override.yml up -d --build mysql redis backend frontend

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

---

## Standard Redeployment (use this every time)

Run on the VPS after pushing changes to GitHub:

```bash
cd ~/Nutriscope
git fetch origin
git reset --hard origin/main

docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.prod.yml -f docker-compose.override.yml up -d --build mysql redis backend frontend

docker compose -f docker-compose.prod.yml exec backend php artisan config:clear
docker compose -f docker-compose.prod.yml exec backend php artisan cache:clear
```

---

## Verification Checks

After any deploy, run these to confirm everything is healthy:

```bash
# Check containers — should show mysql, redis, backend, frontend only (no nginx container)
docker ps

# Check host nginx
sudo systemctl status nginx

# Check site responses
curl -IL http://nutriscope.live   # should show 301 → 307 → 200
curl -I https://nutriscope.live   # should show 307 to /login
```

---

## Important Rules

**Never run `npm install` on the VPS host.** The frontend Dockerfile uses `npm ci` which requires `package.json` and `package-lock.json` to be in sync. If the build fails with a lockfile mismatch:

1. On your local machine, inside `frontend/`, run `npm install`
2. Commit the updated `package-lock.json`
3. Push to GitHub
4. Then redeploy from the VPS using the standard redeployment commands above

**Never start the Docker nginx service.** It has been removed from `docker-compose.prod.yml`. Host nginx handles all routing. Starting the Docker nginx container will cause a port 80 conflict and bring down the site.

**SSL is managed by Certbot, not Docker.** The certificate lives at `/etc/letsencrypt/live/nutriscope.live/`. Certbot auto-renews it. Do not manage SSL inside Docker.

---

## Key File Locations

| File | Location | Purpose |
|------|----------|---------|
| Host nginx config | `/etc/nginx/sites-available/nutriscope` | Reverse proxy and SSL config |
| SSL certificate | `/etc/letsencrypt/live/nutriscope.live/` | Managed by Certbot |
| Docker compose prod | `~/Nutriscope/docker-compose.prod.yml` | Main compose file (no nginx service) |
| Docker compose override | `~/Nutriscope/docker-compose.override.yml` | Exposes frontend to 127.0.0.1:3000 only |
| Backend env | `~/Nutriscope/backend/.env.production` | Production environment variables |

---

## Accessing the Server

Via DigitalOcean web console (no SSH setup needed):
1. Log in to DigitalOcean
2. Open the Droplet
3. Click **Console**

Or via SSH from your local machine:
```bash
ssh root@168.144.115.27
```