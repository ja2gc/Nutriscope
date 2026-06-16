# NutriScope Environments

## Local (hybrid)

Infra runs in Docker; backend and frontend run natively for hot-reload.

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

## Deploy (VPS, portable Docker)

The full stack runs in Docker. The existing host nginx terminates TLS and proxies to the
frontend container on `127.0.0.1:3000`.

Prerequisite on the server: `backend/.env.production` exists (copy from
`backend/.env.production.example`, fill in `APP_KEY` and secrets).

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Host nginx must proxy to the published frontend port:

```nginx
location / {
    proxy_pass http://127.0.0.1:3000;
    # ... existing proxy headers ...
}
```

CI (`.github/workflows/deploy.yml`) runs this on every push to `main`.

### Why the 502 happened

The previous prod compose put the frontend only on the internal Docker network with no
published port, so host nginx had no reachable upstream. The frontend now publishes
`127.0.0.1:3000`, and the prod stack includes `paddleocr`/`omr` (previously missing) via the
merged base file.

## Moving to managed MySQL / Redis (PaaS path)

No code or compose changes needed — only env:

1. In `backend/.env.production`, set `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD` (and
   `REDIS_HOST` / `REDIS_PASSWORD`) to the managed endpoints.
2. Remove the `mysql` / `redis` services from the deploy if no longer self-hosted, or leave
   them unused.
3. Redeploy. Each service has its own Dockerfile, so the same images run on any Docker host
   or container PaaS (Railway, Render, Fly, Coolify).
