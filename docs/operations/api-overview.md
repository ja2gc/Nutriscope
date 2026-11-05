# API overview

Document version: 1.0 | Issued: 5 November 2026

Laravel owns authentication, authorization, validation, business rules and persistence. Next.js proxies browser API calls. The Expo FSS app calls Laravel through the mobile proxy. This overview explains those contracts; exact endpoint fields and response variants are defined by [routes](../../backend/routes/api.php), [FormRequests](../../backend/app/Http/Requests), [Resources](../../backend/app/Http/Resources) and their controllers. There is no generated OpenAPI/Swagger specification in the repository.

## URLs and routing

Replace `example.org` with the configured public domain.

| Caller | Base/path | Behavior |
|---|---|---|
| Browser | `https://example.org/api/...` | Next.js API handlers read HttpOnly cookies and forward permitted calls to Laravel |
| Mobile/native API client | `https://example.org/mobile-api/api/...` | Host Nginx removes `/mobile-api/` and forwards to Laravel's `/api/...` |
| Next.js inside Compose | `http://backend/api` | Server-only `LARAVEL_API_URL`; not a public DNS name |
| Host diagnostics | `http://127.0.0.1:8080/up` | Backend health, bound to loopback |
| Public health | `https://example.org/up` | Nginx forwards to backend health; no account/data access implied |

Set mobile `EXPO_PUBLIC_API_URL=https://example.org/mobile-api`; mobile callers add `/api/...`. Do not add a blanket Nginx `/api` rule to Laravel: it would bypass the browser handlers. Routing source: [mobile/API snippet](../../nginx/mobile-api.locations.conf), [web proxy](../../frontend/lib/laravelProxy.ts), [mobile client](../../mobile/lib/api.ts).

## Authentication and account access

Direct Laravel login is `POST /api/auth/login` (public URL `/mobile-api/api/auth/login`). JSON fields are `email`, `password`, optional `device_name` and optional `platform` (`app` or `web`). Native login sends `platform=app`; FSS must use the native platform. RND/Admin use web. Successful direct login returns `token` and `user`; the user resource excludes the password. Reusing the same device name replaces that account's previous token for that device name.

Subsequent direct requests send `Accept: application/json` and `Authorization: Bearer TOKEN`. The native client stores the token in SecureStore, clears it on 401 and returns to login. Web `POST /api/auth/login` returns the user while setting HttpOnly `nutriscope_token` and `nutriscope_role` cookies; production cookies are secure, SameSite Lax, with a seven-day lifetime. Browser callers do not need provider keys or a token exposed to browser JavaScript.

`GET /api/auth/me` returns the current account; `POST /api/auth/logout` signs out. Profile, password, recovery-email verification and onboarding routes are in the same authenticated auth group. Password-reset delivery requires the account's verified recovery address, not only a configured sending provider. Accounts must be active. Roles do not replace resource-level authorization checks.

Sources: [login validation](../../backend/app/Http/Requests/Auth/LoginRequest.php), [auth controller](../../backend/app/Http/Controllers/Auth/AuthController.php), [web login](../../frontend/app/api/auth/login/route.ts), [User resource](../../backend/app/Http/Resources/UserResource.php).

## Endpoint families

All paths below are Laravel paths, before the external `/mobile-api` prefix.

| Family | Representative endpoints | Access/ownership |
|---|---|---|
| Authentication | `/api/auth/login`, `/api/auth/me`, `/api/auth/logout` | Login is public/throttled; authenticated actions require an active account |
| Shared notices | `/api/notifications`, `/api/sop` | Authenticated roles; some SOP authoring is RND/Admin only |
| Clinical | `/api/rnd/patients`, `/api/rnd/ncp-records/{ncpRecord}/assessment` | RND; requests/controllers enforce action and record constraints |
| Diagnosis/intervention/monitoring | Routes under `/api/rnd` | RND; [clinical guide](../developers/ncp/README.md) describes workflow and saved meaning |
| Food service | `/api/fss/menu-cycles`, `/api/fss/fs-items`, `/api/fss/food-service-recipes` | Outer group permits FSS/RND; planning/catalog/recipe writes are nested RND-only |
| Procurement/execution | `/api/fss/shopping-lists`, `/api/fss/purchase-orders`, meal-prep and diet-list routes | RND authors planning; FSS has specifically permitted actuals/evidence/execution actions |
| Administration | `/api/admin/users` and other admin routes | Admin-only group; Admin is not implicitly authorized for every RND endpoint |
| Reports | Role-group report prepare/render/export/download routes | Authentication, role and report authorization; file response differs from ordinary JSON |

The name `fss` in a URL does not mean every action is allowed to FSS. Inspect nested middleware and controller checks. Use public UUIDs from returned resources for route-bound records; do not guess internal integer foreign keys. [HasPublicId](../../backend/app/Models/Concerns/HasPublicId.php) owns that boundary.

## Requests, responses and errors

Send JSON with `Content-Type: application/json` for JSON actions. Endpoint-specific uploads use their documented multipart/body shape and size limits; use the existing web/mobile clients as examples. Protected file endpoints require authentication even when rendering an image or PDF. Do not turn private storage paths into public URLs.

Laravel Resource responses commonly use `data`; paginated Resource collections include `links` and `meta`. Some endpoints intentionally return their own JSON structures, files, 204 responses or compact representations. Do not impose one global envelope on every endpoint.

The shared [pagination rules](../../backend/app/Http/Requests/Concerns/HasPaginationRules.php) accept `page` (minimum 1), `per_page` (1–10, default 10) and `search` (up to 100 characters). Endpoint-specific histories and feeds can use different scopes/pagination; inspect that endpoint's request/controller instead of assuming all collections use these settings. See [PatientController](../../backend/app/Http/Controllers/RND/PatientController.php) for a paginated Resource example.

| HTTP status | Handling |
|---|---|
| 200 / 201 / 204 | Success according to endpoint; 204 has no JSON body |
| 401 | Missing/invalid authentication or failed credentials; request a new login |
| 403 | Inactive account, wrong platform/role or denied action; do not retry as another guessed record |
| 404 | Missing or unavailable resource; do not infer internal identifiers |
| 422 | Validation failure; Laravel normally returns `message` and field `errors` |
| 429 | Rate limit; respect retry information instead of issuing tight retries |
| 5xx | Server/provider failure; operator checks health and sanitized evidence |

Business endpoints may use additional statuses/messages. Preserve their existing response contract. Rate limits are registered in [AppServiceProvider](../../backend/app/Providers/AppServiceProvider.php); middleware/exception behavior is in [bootstrap/app.php](../../backend/bootstrap/app.php).

## Inspect and test

Use [operator shell setup](maintenance.md#open-an-operator-session) first.

**Read-only — VPS, deploy user, `~/Nutriscope`:**

```sh
dc exec --user www-data backend php artisan route:list --path=api --except-vendor
curl --fail --silent --show-error https://example.org/up
```

For authenticated exploration use a trusted API client such as the institution's approved client: set base URL `https://example.org/mobile-api`, send the login JSON with a controlled account and the correct platform, store the returned token in a private local client variable, then call `/api/auth/me` with bearer authentication. Login/logout change session state. Disable response/token logging and do not sync secrets into public collections. Use fictional records in an isolated test environment for create/update/delete examples. Health and route listing do not prove full API acceptance.

To change an endpoint, trace its FormRequest, permission checks, controller/service, Resource, Next.js handler and native caller, then relevant tests. See [backend maintenance](../developers/platform/backend.md), [data semantics](../developers/platform/data-semantics.md) and [testing](../developers/platform/testing.md).
