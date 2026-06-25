# Rate Limiting Map

Security map of every throttled endpoint in the NutriScope API, the named
limiter it uses, and why. Named limiters are defined in
[`AppServiceProvider::boot()`](../../backend/app/Providers/AppServiceProvider.php);
route bindings live in [`routes/api.php`](../../backend/routes/api.php).

Laravel's `throttle:<name>` middleware reads these limiters. Inline limiters
like `throttle:10,1` mean "10 requests per 1 minute" without a named definition.

## Named limiters

| Limiter | Limit | Keyed by | Defined for |
|---|---|---|---|
| `login` | 5 / min | `email + IP` | Brute-force / credential-stuffing on auth |
| `password-change` | 5 / hour | user id | Stop rapid credential cycling on a hijacked session |
| `ai` | 20 / hour | user id | Each call hits a paid LLM — cap budget drain per account |
| `usda` | 30 / min | user id | Protect external USDA API key quota; block scraping |
| `uploads` | 20 / hour | user id | Prevent storage exhaustion from file uploads |
| `compute` | 30 / min | user id | CPU-bound clinical calc (autofill, recommendations) |
| `reports` | 10 / min | user id | DB aggregation + PDF render; block runaway polling |

`login` is keyed on `email + IP` so one attacker cannot lock out every user
behind a shared NAT. All authenticated limiters key on user id so one
compromised account cannot exhaust quota for others.

## Endpoint bindings

### Auth (`/api/auth`)

| Method | Path | Limiter |
|---|---|---|
| POST | `login` | `login` (5/min) |
| POST | `password` | `password-change` (5/hr) |

### RND clinical (`/api/rnd`)

| Method | Path | Limiter | Reason |
|---|---|---|---|
| POST | `ncp-records/{r}/diagnoses/ai-suggest` | `ai` | Paid LLM |
| POST | `ncp-records/{r}/diagnoses/ai-approve` | `ai` | Paid LLM |
| POST | `ncp-records/{r}/monitorings/ai-review` | `ai` | Paid LLM |
| POST | `ncp-records/{r}/meal-plans/generate` | `ai` | Paid LLM |
| POST | `ncp-records/{r}/intervention/autofill` | `compute` | CPU-bound prescription calc |
| POST | `ncp-records/{r}/intervention/recommend` | `compute` | CPU-bound recommendation |
| GET | `ncp-records/{r}/intervention/recommendations` | `compute` | CPU-bound recommendation |
| POST | `ncp-records/{r}/attachments` | `uploads` | File storage |
| GET | `usda/search` | `usda` | External API quota |
| POST | `usda/import/{fdcId}` | `usda` | External API quota |
| GET | `usda/preview/{fdcId}` | `usda` | External API quota |
| POST | `announcements` | `10,1` | Spam / flood control |
| PATCH | `announcements/{a}` | `10,1` | Spam / flood control |
| DELETE | `announcements/{a}` | `10,1` | Spam / flood control |

### Reports (shared RND + FSS + Admin via `$reportRoutes`)

| Method | Path | Limiter |
|---|---|---|
| GET | `reports/{type}/render` | `reports` (10/min) |
| POST | `reports/{type}/archive` | `reports` (10/min) |

### FSS / RND food service (`/api/fss`)

| Method | Path | Limiter | Reason |
|---|---|---|---|
| POST | `purchase-orders/{po}/attachments` | `uploads` | File storage |
| POST | `shopping-lists/{sl}/approve` | `10,1` | Generates purchase orders — guard side effects |

### Admin (`/api/admin`)

| Method | Path | Limiter | Reason |
|---|---|---|---|
| POST | `users/{user}/reset-password` | `6,1` | Account takeover protection |
| POST | `users` | `20,1` | Bulk account-creation abuse |
| PUT/PATCH | `users/{user}` | `20,1` | Bulk mutation abuse |
| DELETE | `users/{user}` | `20,1` | Bulk deletion abuse |
| POST | `announcements` | `10,1` | Spam / flood control |
| PATCH | `announcements/{a}` | `10,1` | Spam / flood control |
| DELETE | `announcements/{a}` | `10,1` | Spam / flood control |

## Global fallback

Routes without an explicit limiter fall back to Laravel's default `api`
throttle group (60 requests/min/user). Read-only endpoints (index/show)
rely on this default — they carry no side effects and no external cost, so a
tighter cap would only hurt legitimate browsing.

## Gaps / future hardening

- **`reports` store + `generate-all`** (POST) are unthrottled beyond the global
  60/min. If PDF generation becomes heavier, bind them to `reports`.
- **Calendar event create** and **diet-list-count store** are write endpoints
  on the 60/min default. Low-risk today; revisit if abuse appears.
- Consider a **global per-IP limiter** at the edge (nginx / load balancer) for
  unauthenticated traffic as defense-in-depth ahead of the app layer.
