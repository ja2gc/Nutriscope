# FSS Mobile App — Integration Guide

How the team's React Native (Expo) FSS app fits into this repo and connects to the
Laravel backend. The backend is the single source of truth; the mobile app is one of
three clients (web RND, web Admin, mobile FSS) that all talk to the same `/api`.

## Repo layout — `mobile/` is a top-level sibling

This is already a polyglot monorepo. The RN app gets its own folder; it does **not**
nest inside `frontend/` and shares **no UI code** with the Next.js web app (DOM/Tailwind
vs native views/NativeWind, different bundlers, different routing).

```
Nutriscope/
├─ backend/          # Laravel API — source of truth (unchanged)
├─ frontend/         # Next.js 16 web (RND + Admin)
├─ mobile/           # ← React Native (Expo) FSS app
│  ├─ app/           #   screens (their UI — KEEP)
│  ├─ src/
│  │  ├─ api/client.ts   # fetch/axios wrapper, injects Bearer token, base URL from env
│  │  ├─ api/fss.ts      # calls /api/fss/* (cleaning-logs, complete-day, inventory, …)
│  │  ├─ auth/           # login + Expo SecureStore token storage
│  │  └─ types/          # TS shapes mirroring backend API Resources
│  ├─ package.json   #   its own deps / node_modules
│  └─ app.json
└─ packages/api-types/   # OPTIONAL shared TS DTOs — add only if web+mobile duplicate
```

Keep both frontends independent (own `package.json`). Only adopt npm/pnpm **workspaces**
if `packages/api-types` becomes worth sharing.

## Auth — Sanctum Bearer token (no cookies/CSRF)

Backend issues personal-access tokens with role abilities:
`AuthController` → `$user->createToken('nutriscope-token', [$user->role])->plainTextToken`.

```
POST /api/login            → { token, user }
  store token in Expo SecureStore
  every request header:    Authorization: Bearer <token>
```

`SANCTUM_STATEFUL_DOMAINS` / CSRF is for the browser SPA only — ignore it on mobile.
Role + the `/api/fss/*` route guards (`role:FSS,RND`, RND-only write split) are enforced
server-side, so the mobile app cannot bypass them.

## Endpoints the FSS app uses

| Screen | Endpoint(s) | Notes |
|---|---|---|
| Prep & Clean | `POST /api/fss/menu-cycles/{id}/complete-day` | body: `service_date`, `population` (prepared-for), optional `served_population`, `allow_shortfall`. Returns log incl. `population_variance`, `has_shortfall`. |
| Cleaning log | `GET/POST/PATCH/DELETE /api/fss/cleaning-logs` | full CRUD |
| Inventory | `GET/POST/PATCH /api/fss/inventory`, `POST /inventory/{id}/restock` | |
| Suppliers | `GET/POST/PATCH/DELETE /api/fss/suppliers` | |
| Procurement | `GET/POST /api/fss/purchase-orders`, `POST /purchase-orders/{id}/attachments` | |
| Announcements | `GET /api/fss/announcements` | visibility `FSS|All` only |
| Menu cycles / budgets | `GET /api/fss/menu-cycles`, `GET /api/fss/budgets` | **read-only** — writes return 403 |

## Merge steps (keep their screens, replace their data + auth layer)

1. Import their app into `mobile/` (copy or `git subtree add`); commit as-is first.
2. Replace their API base URL + HTTP client with one pointing at this backend.
3. Swap their auth to `POST /api/login` → SecureStore → `Authorization: Bearer`.
4. Re-map each screen to the real endpoints above.
5. Reconcile their TS models to the backend API Resource field names (backend wins).
6. Delete any stub/placeholder backend they built.

## Dev gotcha — base URL

A device/emulator can't reach `127.0.0.1:8000` (that's the device's own loopback). Point
the base URL at your machine's LAN IP (`http://192.168.x.x:8000/api`) or an Expo tunnel /
ngrok. Make it env-driven (`EXPO_PUBLIC_API_URL`), mirroring `frontend`'s `LARAVEL_API_URL`.
