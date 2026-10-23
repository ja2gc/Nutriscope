# Stack and version authority

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

| Layer | Source of truth |
|---|---|
| Laravel / PHP | [composer.json](../../backend/composer.json), [composer.lock](../../backend/composer.lock), [backend Dockerfile](../../backend/Dockerfile): Laravel ^13.8, container PHP 8.4 |
| Next.js / React | [frontend package](../../frontend/package.json), [lockfile](../../frontend/package-lock.json): Next ^16.3.3, React 19.2.4 |
| Expo / React Native | Committed mobile/package.json and [lockfile](../../mobile/package-lock.json): Expo ~54.0.37, React Native 0.81.5 |
| Database / cache | [base Compose](../../docker-compose.yml): MySQL 8.0 and Redis 8 images |
| Frontend runtime | [Dockerfile](../../frontend/Dockerfile): Node 20, Next standalone output |
| PDF / storage | Composer lock/config: DomPDF, Flysystem S3; [filesystem config](../../backend/config/filesystems.php) |
| Styling / controls | Frontend manifest: Tailwind 4, Radix primitives |
| Checks | PHPUnit, Vitest, TypeScript, ESLint, Pint; [testing guide](../developers/platform/testing.md) |

Versions above describe declared ranges/configuration, not installed production versions. Lockfiles resolve packages; image tags can move. The checked PHPUnit dependencies require a compatible PHP runtime despite the root's broader PHP ^8.3 constraint. Mobile AGENTS' v56 docs conflict with the committed Expo54 manifest; reconcile before code work.

Next builds use Next's build pipeline; the backend's scaffold Vite config does not make Vite the Next bundler. The production MySQL topology is not SQLite.

See [architecture](../developers/platform/architecture.md), [local setup](../developers/platform/local-setup.md) and [operations](../developers/operations/README.md).
