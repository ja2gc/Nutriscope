# Local vs Deploy Environments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Separate NutriScope into two clean runtime configs — a hybrid local setup and a portable Dockerized deploy — fixing the VPS 502 and keeping a low-effort PaaS migration path.

**Architecture:** Docker Compose base file holds shared infra (MySQL, Redis, paddleocr, omr) and is the local config; a prod overlay adds the backend + frontend containers and publishes the frontend port so the existing host nginx can reach it. All hostnames are env-driven so DB/Redis can move to managed services later.

**Tech Stack:** Docker Compose v2, Laravel 13 (PHP 8.4), Next.js (standalone), MySQL 8, Redis 7, nginx (host-installed on VPS).

**Note on testing:** This is infrastructure/config work, not application code. "Verification" steps run real commands (compose validation, connectivity checks) instead of unit tests. Run each verification before committing.

---

### Task 1: Consolidate the local base compose file

**Files:**
- Modify: `docker-compose.yml`
- Delete: `docker-compose.dev.yml`

- [ ] **Step 1: Rewrite `docker-compose.yml`** as the canonical local file — shared infra only, healthchecks from the dev file, and ports bound to `127.0.0.1` so the native backend/frontend can reach them.

```yaml
services:
  mysql:
    image: mysql:8.0
    container_name: nutriscope_mysql
    ports:
      - "127.0.0.1:3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: ""
      MYSQL_ALLOW_EMPTY_PASSWORD: "yes"
      MYSQL_DATABASE: nutriscope
    volumes:
      - nutriscope_mysql:/var/lib/mysql
    networks:
      - nutriscope_net
    restart: unless-stopped
    command: --default-authentication-plugin=mysql_native_password
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:8-alpine  # pinned to 8 to match existing RDB data (local + VPS volumes)
    container_name: nutriscope_redis
    ports:
      - "127.0.0.1:6379:6379"
    volumes:
      - nutriscope_redis:/data
    networks:
      - nutriscope_net
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  paddleocr:
    build:
      context: ./paddleocr
      dockerfile: Dockerfile
    container_name: nutriscope_paddleocr
    ports:
      - "127.0.0.1:5000:5000"
    environment:
      PYTHONUNBUFFERED: "1"
    networks:
      - nutriscope_net
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:5000/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 60s

  omr:
    build:
      context: ./omr
      dockerfile: Dockerfile
    container_name: nutriscope_omr
    ports:
      - "127.0.0.1:5001:5001"
    environment:
      PYTHONUNBUFFERED: "1"
    networks:
      - nutriscope_net
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:5001/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 60s

volumes:
  nutriscope_mysql:
    driver: local
  nutriscope_redis:
    driver: local

networks:
  nutriscope_net:
    driver: bridge
```

- [ ] **Step 2: Delete the redundant dev file**

```bash
git rm docker-compose.dev.yml
```

- [ ] **Step 3: Validate the compose file parses**

Run: `docker compose -f docker-compose.yml config --quiet`
Expected: no output, exit code 0 (invalid YAML would print an error).

- [ ] **Step 4: Bring up local infra and confirm health**

Run: `docker compose up -d`
Then: `docker compose ps`
Expected: `nutriscope_mysql` and `nutriscope_redis` show `healthy`; `paddleocr`/`omr` running (may show `health: starting` for up to 60s).

- [ ] **Step 5: Commit**

```bash
git add docker-compose.yml
git rm docker-compose.dev.yml
git commit -m "refactor(docker): make docker-compose.yml the canonical local infra file"
```

---

### Task 2: Fix the local backend env to use localhost hostnames

**Files:**
- Modify: `backend/.env` (gitignored — edit in place, not committed)
- Modify: `backend/.env.example` (committed template)

- [ ] **Step 1: Update `backend/.env`** so the natively-run backend reaches the Docker infra via published localhost ports. Change these lines:

```
APP_ENV=local
APP_URL=http://localhost:8000
DB_HOST=127.0.0.1
REDIS_HOST=127.0.0.1
PADDLEOCR_URL=http://localhost:5000
OMR_URL=http://localhost:5001
SESSION_DOMAIN=localhost
FRONTEND_URL=http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
```

Leave `APP_KEY`, `DB_DATABASE=nutriscope`, `DB_USERNAME=root`, `DB_PASSWORD=` (empty) as-is.

- [ ] **Step 2: Update `backend/.env.example`** to match (localhost-oriented template, empty secrets). Set the same keys as Step 1 but with `APP_KEY=` empty and `USDA_API_KEY=` / `ANTHROPIC_API_KEY=` empty.

- [ ] **Step 3: Verify the backend connects to the Dockerized DB**

Run (infra must be up from Task 1):
```bash
cd backend
php artisan config:clear
php artisan migrate --force
```
Expected: migrations run with no "Connection refused" / "could not find driver" errors. (Use `127.0.0.1`, not `mysql`.)

- [ ] **Step 4: Confirm `.env` is not tracked, then commit only the example**

```bash
git status --short backend/.env          # expect NO output (gitignored)
git add backend/.env.example
git commit -m "fix(backend): point local env template at localhost infra hosts"
```

---

### Task 3: Add frontend local env pointing at the native backend

**Files:**
- Create: `frontend/.env.local` (gitignored)
- Create: `frontend/.env.example` (committed)
- Modify: `frontend/.gitignore` (only if `.env*.local` not already ignored)

- [ ] **Step 1: Confirm `.env.local` is gitignored**

Run: `git check-ignore frontend/.env.local`
Expected: prints `frontend/.env.local` (already ignored by Next.js default). If it prints nothing, add `.env*.local` to `frontend/.gitignore`.

- [ ] **Step 2: Create `frontend/.env.local`**

```
# Local dev: Next.js server-side calls the natively-run Laravel backend
LARAVEL_API_URL=http://localhost:8000/api
```

- [ ] **Step 3: Create `frontend/.env.example`**

```
# Copy to .env.local for local development.
# Points Next.js server-side API calls at the local Laravel backend.
LARAVEL_API_URL=http://localhost:8000/api
```

- [ ] **Step 4: Verify the frontend dev server boots**

Run (in a terminal with npm on PATH — restart editor if "npm not recognized"):
```bash
cd frontend
npm run dev
```
Expected: Next.js starts on `http://localhost:3000` with no env errors. Stop it with Ctrl+C after confirming.

- [ ] **Step 5: Commit the example (and gitignore if changed)**

```bash
git add frontend/.env.example
git commit -m "feat(frontend): add local env template targeting native backend API"
```

---

### Task 4: Rewrite the prod compose as an overlay

**Files:**
- Modify: `docker-compose.prod.yml`

- [ ] **Step 1: Replace `docker-compose.prod.yml`** with an overlay that ONLY adds the app containers. The infra (mysql, redis, paddleocr, omr) comes from the base file at merge time, so paddleocr/omr are no longer missing from prod.

```yaml
services:
  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: nutriscope_backend
    restart: unless-stopped
    env_file:
      - ./backend/.env.production
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_started
    networks:
      - nutriscope_net

  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
      args:
        LARAVEL_API_URL: http://backend/api
    container_name: nutriscope_frontend
    restart: unless-stopped
    environment:
      LARAVEL_API_URL: http://backend/api
    ports:
      - "127.0.0.1:3000:3000"
    depends_on:
      - backend
    networks:
      - nutriscope_net
```

- [ ] **Step 2: Validate the merged prod config resolves all six services**

Run: `docker compose -f docker-compose.yml -f docker-compose.prod.yml config --services`
Expected output (order may vary):
```
mysql
redis
paddleocr
omr
backend
frontend
```
If `paddleocr`/`omr` are missing, the base file isn't being merged — recheck the `-f` order.

- [ ] **Step 3: Confirm the frontend port publish is present**

Run: `docker compose -f docker-compose.yml -f docker-compose.prod.yml config | Select-String "3000"`
Expected: shows `127.0.0.1:3000` published (this is the line that fixes the 502).

- [ ] **Step 4: Commit**

```bash
git add docker-compose.prod.yml
git commit -m "fix(docker): make prod compose an overlay; add missing OCR services and publish frontend port"
```

---

### Task 5: Add a committed production env template

**Files:**
- Create: `backend/.env.production.example` (committed; real `.env.production` stays gitignored on the server)

- [ ] **Step 1: Create `backend/.env.production.example`** with production-shaped values and empty secrets. Container-network hostnames here (backend runs inside Docker in prod):

```
APP_NAME=NutriScope
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://nutriscope.live

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# DB — service name on the Docker network. Swap host/credentials here to use managed MySQL.
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=nutriscope
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.nutriscope.live

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis

CACHE_STORE=redis
CACHE_PREFIX=nutriscope_

# Redis — service name on the Docker network. Swap host here to use managed Redis.
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@nutriscope.live"
MAIL_FROM_NAME="${APP_NAME}"

ANTHROPIC_API_KEY=
USDA_API_KEY=

# OCR/OMR microservices on the Docker network
PADDLEOCR_URL=http://paddleocr:5000
OMR_URL=http://omr:5001

AI_DAILY_TOKEN_LIMIT=100000

FRONTEND_URL=https://nutriscope.live
SANCTUM_STATEFUL_DOMAINS=nutriscope.live,www.nutriscope.live
VITE_APP_NAME="${APP_NAME}"
```

- [ ] **Step 2: Verify it is committable but the real file is not**

Run: `git check-ignore backend/.env.production.example`
Expected: prints nothing (NOT ignored — the `.example` suffix dodges the `.env.production` ignore rule).
Run: `git check-ignore backend/.env.production`
Expected: prints `backend/.env.production` (ignored).

- [ ] **Step 3: Commit**

```bash
git add backend/.env.production.example
git commit -m "docs(backend): add committed production env template"
```

---

### Task 6: Update the deploy workflow to the merged invocation

**Files:**
- Modify: `.github/workflows/deploy.yml`

- [ ] **Step 1: Update the SSH `script` block** so it merges base + overlay (previously only `-f docker-compose.prod.yml`, which omitted the infra/OCR services).

```yaml
          script: |
            cd Nutriscope
            git pull origin main
            export DB_ROOT_PASSWORD=${{ secrets.DB_ROOT_PASSWORD }}
            # Requires backend/.env.production to exist on the server.
            docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
            docker image prune -f
```

- [ ] **Step 2: Validate the workflow YAML parses**

Run: `docker run --rm -i ghcr.io/rhysd/actionlint:latest < .github/workflows/deploy.yml` if actionlint is available; otherwise visually confirm indentation matches the surrounding YAML.
Expected: no syntax errors.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/deploy.yml
git commit -m "ci: deploy with merged base + prod compose files"
```

---

### Task 7: Add deployment documentation

**Files:**
- Create: `docs/DEPLOYMENT.md`

- [ ] **Step 1: Create `docs/DEPLOYMENT.md`**

````markdown
# NutriScope Environments

## Local (hybrid)

Infra runs in Docker; backend and frontend run natively for hot-reload.

```bash
# 1. Start infra (MySQL, Redis, paddleocr, omr)
docker compose up -d

# 2. Backend (native) — http://localhost:8000
cd backend
cp .env.example .env        # first time only; then set APP_KEY via: php artisan key:generate
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

## Moving to managed MySQL / Redis (PaaS path)

No code or compose changes needed — only env:

1. In `backend/.env.production`, set `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD` (and
   `REDIS_HOST` / `REDIS_PASSWORD`) to the managed endpoints.
2. Remove the `mysql` / `redis` services from the deploy if no longer self-hosted, or leave
   them unused.
3. Redeploy. Each service has its own Dockerfile, so the same images run on any Docker host
   or container PaaS (Railway, Render, Fly, Coolify).
````

- [ ] **Step 2: Commit**

```bash
git add docs/DEPLOYMENT.md
git commit -m "docs: add deployment guide for local, VPS, and PaaS paths"
```

---

## Verification (whole-plan)

- [ ] Local: `docker compose up -d` → `docker compose ps` shows mysql/redis healthy; `php artisan migrate` succeeds against `127.0.0.1`; `npm run dev` serves on :3000.
- [ ] Deploy config: `docker compose -f docker-compose.yml -f docker-compose.prod.yml config --services` lists all six services and the frontend publishes `127.0.0.1:3000`.
- [ ] Exactly two compose configs exist (`docker-compose.yml`, `docker-compose.prod.yml`); `docker-compose.dev.yml` is gone.
- [ ] Committed templates exist: `backend/.env.example`, `backend/.env.production.example`, `frontend/.env.example`; no real `.env*` files are tracked.
