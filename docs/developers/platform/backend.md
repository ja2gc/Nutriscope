# Backend

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: maintain the shared Laravel API without moving business rules into clients.

| Concern | Current owner |
|---|---|
| Routes and role groups | [routes/api.php](../../../backend/routes/api.php) |
| Input validation and response contracts | [Requests](../../../backend/app/Http/Requests), [Resources](../../../backend/app/Http/Resources) |
| Permissions | [Policies](../../../backend/app/Policies), [Middleware](../../../backend/app/Http/Middleware) |
| Business operations | [Services](../../../backend/app/Services), [Actions](../../../backend/app/Actions) |
| Stored meaning and identifiers | [Models](../../../backend/app/Models), [HasPublicId](../../../backend/app/Models/Concerns/HasPublicId.php) |
| Audit mutation boundary | [Controller::audited](../../../backend/app/Http/Controllers/Controller.php), [audit config](../../../backend/config/audit.php) |

Add an endpoint by following a neighboring route's request, authorization, service, Resource and matching web/mobile consumers. Public UUIDs are not integer foreign keys. Preserve API shape, errors, transactions and audit serializers/events.

Read [backend instructions](../../../backend/AGENTS.md). Use PHP compatible with the lockfile; the container uses PHP 8.4. Do not infer installed versions from a broad manifest constraint.

Tests live in [Feature](../../../backend/tests/Feature) and [Unit](../../../backend/tests/Unit). [composer.json](../../../backend/composer.json) declares composer test, which clears config and invokes Artisan tests. Establish isolation first: [testing](testing.md).

Related: [clinical](../ncp/README.md), [food service](../food-service/README.md), [AI](../ncp/ai.md), [database](database.md).

## Shared features and maintenance entry points

| Change | Start at |
|---|---|
| Login, logout, onboarding, own profile/photo/password | [AuthController](../../../backend/app/Http/Controllers/Auth/AuthController.php), [auth routes](../../../backend/routes/api.php), [auth requests](../../../backend/app/Http/Requests/Auth) |
| Forgotten password and verified recovery address | [PasswordResetController](../../../backend/app/Http/Controllers/Auth/PasswordResetController.php), [RecoveryEmailController](../../../backend/app/Http/Controllers/Auth/RecoveryEmailController.php), [User](../../../backend/app/Models/User.php), [mail setup](../operations/integrations.md) |
| Staff accounts and administrative password reset | [UserController](../../../backend/app/Http/Controllers/Admin/UserController.php), [admin requests](../../../backend/app/Http/Requests/Admin) |
| Notification creation, read/dismiss lifecycle and expiry | [NotificationService](../../../backend/app/Services/NotificationService.php), [NotificationLifecycleService](../../../backend/app/Services/NotificationLifecycleService.php), [NotificationController](../../../backend/app/Http/Controllers/RND/NotificationController.php), [schedules](../../../backend/bootstrap/app.php) |
| Announcements and SOP authoring/history | [Admin announcements](../../../backend/app/Http/Controllers/Admin/AnnouncementController.php), [RND announcements](../../../backend/app/Http/Controllers/RND/AnnouncementController.php), [SopController](../../../backend/app/Http/Controllers/SopController.php) |
| Audit capture, history, redaction, retention and search | [Audit services](../../../backend/app/Services/Audit), [audit config](../../../backend/config/audit.php), [audit reference](../../architecture/audit-logging.md), [event catalog](../../architecture/audit-event-catalog.md) |
| Search ranking and throttling | [Search support](../../../backend/app/Support/Search), [provider rate limiters](../../../backend/app/Providers/AppServiceProvider.php), [search reference](../../architecture/search.md), [rate limits](../../architecture/rate-limiting.md) |

The controller's directory name alone does not establish its allowed roles. Check route middleware, FormRequests, policies and caller ownership; shared notifications, for example, use shared authenticated routes. Trace both web and mobile consumers. Keep secrets, clinical content and AI prompts out of audit payloads.
