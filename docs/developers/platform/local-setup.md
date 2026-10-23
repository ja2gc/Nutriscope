# Local setup

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: prepare an isolated developer installation.

Use PHP compatible with backend/composer.lock (the container uses PHP 8.4), Composer 2, Node matching the Dockerfile (20), npm and Docker Compose. Commit lockfiles; do not solve installation failures by upgrading dependencies.

1. Clone the institution-owned repository. Read [AGENTS.md](../../../AGENTS.md).
2. Create only local dotenv files from [backend example](../../../backend/.env.example), [web example](../../../frontend/.env.example) and [mobile example](../../../mobile/.env.example). Put synthetic/local credentials there yourself. Never copy a production dotenv into development.
3. Provision isolated MySQL/Redis. The root [base Compose](../../../docker-compose.yml) publishes loopback 3306/6379 and uses an empty MySQL root password: it is a local example, never the production command by itself. Check port/volume ownership before starting it. On a fresh isolated local checkout, docker compose -f docker-compose.yml up -d mysql redis starts those two services and creates persistent local volumes; do not run it against another project's ports or volumes.
4. Install dependencies and run the apps only after reviewing configuration and scripts.

**State-changing, local-only instructions**, in separate shells with the indicated working directories:

```sh
# backend/
composer install
# Fresh local installation only; NEVER regenerate an existing system's key:
php artisan key:generate
# Only after verifying the disposable local DB:
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

```sh
# frontend/
npm ci
# Create .env.local from .env.example yourself before starting.
npm run dev
```

```sh
# mobile/
npm ci
npm start
```

The web server-only LARAVEL_API_URL points to the backend /api endpoint. A physical phone cannot use your workstation's localhost; set EXPO_PUBLIC_API_URL to a reachable **non-production** test endpoint. Check EAS profile env overrides before building.

Do not run composer setup blindly: its script generates a key and migrates. Do not run DatabaseSeeder on an institutional database; it includes demo accounts/patients. Fresh production initialization requires the explicitly reviewed reference-data/admin provisioning list in [turnover](../../operations/turnover.md).

Related: [testing](testing.md), [environment](../operations/environment.md), [mobile](../food-service/mobile.md).
