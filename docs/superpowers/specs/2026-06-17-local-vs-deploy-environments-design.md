# Local vs Deploy Environments — Design

**Date:** 2026-06-17
**Status:** Approved (direction), pending spec review

## Goal

Give NutriScope two clean, well-separated runtime configurations:

1. **Local** — fast hybrid development on the developer's machine.
2. **Deploy** — a portable Dockerized stack that runs on the current DigitalOcean VPS today and can move to a managed/PaaS host later with minimal effort.

The deployment must avoid platform lock-in: switching hosts (another VPS, or a Docker PaaS such as Railway/Render/Fly/Coolify) or swapping in managed MySQL/Redis should require only env-var changes, not rebuilds or architectural changes.

## Decisions (from brainstorming)

- **Deploy direction:** Hybrid / undecided — keep Docker working now, design for easy PaaS migration via env-var externalization.
- **Local model:** Hybrid — infra (MySQL, Redis, paddleocr, omr) in Docker; backend (`php artisan serve`) and frontend (`npm run dev`) run natively for hot-reload. Laravel Boost MCP runs as a local PHP process (already fixed separately).
- **Edge/TLS:** Keep the existing host-installed Ubuntu nginx + SSL cert on the VPS. The compose stack only publishes the frontend port to localhost so host nginx can reach it.
- **Compose structure:** Option A — base file + prod overlay (no duplication).

## Current State (as found)

| File | Services | Role |
|------|----------|------|
| `docker-compose.yml` | mysql, redis, paddleocr, omr | local base |
| `docker-compose.dev.yml` | mysql, redis, paddleocr, omr (+ healthchecks/limits) | local dev (redundant) |
| `docker-compose.prod.yml` | mysql, redis, backend, frontend | VPS deploy |
| `.github/workflows/deploy.yml` | — | SSH to droplet → `git pull` + `compose -f docker-compose.prod.yml up -d --build` |
| `nginx/default.conf` | — | HTTP reverse proxy → frontend:3000 (orphaned — real nginx is on the host) |

### Problems identified

1. **VPS 502 Bad Gateway** — `docker-compose.prod.yml` publishes no ports for `frontend`/`backend`; they live only on the internal Docker network. Host nginx has no reachable upstream → 502.
2. **prod stack missing `paddleocr` + `omr`** — backend OCR/OMR features break in production.
3. **Local `backend/.env` uses Docker hostnames** (`DB_HOST=mysql`, `REDIS_HOST=redis`, `PADDLEOCR_URL=http://paddleocr:5000`) — these don't resolve when the backend runs natively (the chosen hybrid-local model).
4. **`backend/.env.production` not in repo** — referenced by prod compose but only present on the server (no committed template).
5. **No frontend local env** — `LARAVEL_API_URL` is baked at build time for prod; native `npm run dev` has nothing pointing it at the local backend.
6. **Two near-identical local compose files** (`docker-compose.yml` and `docker-compose.dev.yml`) — ambiguity about which to use.

## Target Architecture

| Concern | Local (hybrid) | Deploy (VPS / portable) |
|---|---|---|
| MySQL | Docker, `127.0.0.1:3306` published | Docker (internal net) or managed (env swap) |
| Redis | Docker, `127.0.0.1:6379` published | Docker (internal net) or managed (env swap) |
| paddleocr | Docker, `127.0.0.1:5000` published | Docker (internal net) |
| omr | Docker, `127.0.0.1:5001` published | Docker (internal net) |
| Backend (Laravel) | Native `php artisan serve` :8000 | Docker container |
| Frontend (Next.js) | Native `npm run dev` :3000 | Docker container, `127.0.0.1:3000` published |
| Edge / TLS | none (direct localhost) | Existing host nginx (unchanged) → `127.0.0.1:3000` |
| Boost MCP | Local PHP (already fixed) | n/a |

## Compose File Plan (Option A — base + overlay)

- **`docker-compose.yml`** (canonical local file): mysql, redis, paddleocr, omr only. Includes the healthchecks + resource limits currently in `docker-compose.dev.yml`. Publishes each service port to `127.0.0.1` so native backend/frontend can reach them. Run with `docker compose up -d`.
- **`docker-compose.prod.yml`** (overlay): adds `backend` + `frontend` services, restart policies, `env_file: ./backend/.env.production`, the frontend build-arg `LARAVEL_API_URL=http://backend/api`, and publishes `127.0.0.1:3000:3000` on the frontend. Run with `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build`.
- **Delete `docker-compose.dev.yml`** — folded into the base.

Result: exactly two configs. Local = base. Deploy = base + overlay.

## Environment Variable Plan

| File | Scope | Key hosts | In git? |
|------|-------|-----------|---------|
| `backend/.env` | local native | `DB_HOST=127.0.0.1`, `REDIS_HOST=127.0.0.1`, `PADDLEOCR_URL=http://localhost:5000`, `OMR_URL=http://localhost:5001`, `APP_ENV=local`, `APP_URL=http://localhost:8000` | no (gitignored) |
| `backend/.env.production` | deploy container | `DB_HOST=mysql`, `REDIS_HOST=redis`, `PADDLEOCR_URL=http://paddleocr:5000`, `OMR_URL=http://omr:5001`, `APP_ENV=production`, `APP_DEBUG=false`, real domain/secrets | no (gitignored, lives on server) |
| `backend/.env.example` | template | documented defaults, empty secrets | yes |
| `backend/.env.production.example` | template (new) | production-shaped defaults, empty secrets | yes |
| `frontend/.env.local` | local native | `LARAVEL_API_URL=http://localhost:8000/api` | no (gitignored) |
| `frontend/.env.example` | template (new) | documented | yes |

Both `.env` files are 12-factor: switching to managed MySQL/Redis later means editing `DB_HOST`/`REDIS_HOST`/credentials only.

## The 502 Fix

1. Add `paddleocr` and `omr` to the prod overlay (or inherit from base via the merge).
2. Publish `127.0.0.1:3000:3000` on the prod `frontend` service.
3. Verify host nginx uses `proxy_pass http://127.0.0.1:3000;`.

After this, host nginx has a reachable upstream and the OCR services exist → site serves again.

## Deploy Workflow Update

Update `.github/workflows/deploy.yml` to:
- Use the merged invocation: `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build`.
- Document the prerequisite that `backend/.env.production` exists on the server.

## Documentation

Add `docs/DEPLOYMENT.md` covering:
- Local hybrid startup (infra in Docker, native backend + frontend).
- Deploy flow and prerequisites.
- How to point at managed MySQL/Redis (PaaS migration path).

## Out of Scope

- Migrating off the VPS to a specific PaaS (deferred; design only keeps it cheap).
- Containerizing the edge / changing TLS (host nginx stays).
- CI test/build pipeline changes beyond the deploy invocation.

## Success Criteria

- `docker compose up -d` + native `php artisan serve` + `npm run dev` gives a working local app with API calls resolving.
- `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build` brings up all six services with the frontend reachable at `127.0.0.1:3000`; host nginx serves the site over HTTPS with no 502.
- Switching `DB_HOST`/`REDIS_HOST` to an external host requires no other code/compose changes.
- Exactly two compose configs exist; `docker-compose.dev.yml` is gone.
