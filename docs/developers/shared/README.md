# Shared features and role-specific consumers

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: locate features used across roles and distinguish their shared backend rules from role-specific presentation. Shared does not mean every role can perform every action.

| Feature | Backend/source authority | Consumers/reference |
|---|---|---|
| Login, profile/photos, password and onboarding | [AuthController](../../../backend/app/Http/Controllers/Auth/AuthController.php), [User](../../../backend/app/Models/User.php), [auth routes](../../../backend/routes/api.php) | [Web auth](../../../frontend/contexts/AuthContext.tsx), [mobile auth](../../../mobile/lib/auth.ts), [backend map](../platform/backend.md) |
| Recovery email and forgotten password | [RecoveryEmailController](../../../backend/app/Http/Controllers/Auth/RecoveryEmailController.php), [PasswordResetController](../../../backend/app/Http/Controllers/Auth/PasswordResetController.php), User notification routing | [Mail setup](../operations/integrations.md); preserve verified recovery-address requirements |
| Announcements, images and SOP | [Admin announcements](../../../backend/app/Http/Controllers/Admin/AnnouncementController.php), [RND announcements](../../../backend/app/Http/Controllers/RND/AnnouncementController.php), [SopController](../../../backend/app/Http/Controllers/SopController.php) | [announcementService](../../../frontend/services/announcementService.ts), [sopService](../../../frontend/services/sopService.ts), [mobile screens](../../../mobile/app/) |
| Notification delivery/read/dismiss | [NotificationService](../../../backend/app/Services/NotificationService.php), [NotificationLifecycleService](../../../backend/app/Services/NotificationLifecycleService.php), [NotificationController](../../../backend/app/Http/Controllers/RND/NotificationController.php) | [notificationService](../../../frontend/services/notificationService.ts), [schedules](../../../backend/bootstrap/app.php) |
| Help questions, navigation and wording | [FAQ](../../FAQ.md), [Role How-To](../../ROLE-HOW-TO.md) describe behavior; code implements it | [Web helpContent](../../../frontend/lib/helpContent.ts), [mobile helpContent](../../../mobile/lib/helpContent.ts); update each actual consumer, not just Markdown |
| Dashboards and summary cards | [Admin DashboardController](../../../backend/app/Http/Controllers/Admin/DashboardController.php), [FSS DashboardController](../../../backend/app/Http/Controllers/FSS/DashboardController.php), [FssDashboardService](../../../backend/app/Services/FSS/FssDashboardService.php), [API routes](../../../backend/routes/api.php) | [RND dashboard](../../../frontend/app/%28rnd%29/dashboard/page.tsx), [Admin dashboard](../../../frontend/app/admin/dashboard/page.tsx), [mobile tabs](../../../mobile/app/%28tabs%29/); trace each card's actual client/query |
| Report branding, templates, archives and downloads | [ReportController](../../../backend/app/Http/Controllers/ReportController.php), [ReportTemplateController](../../../backend/app/Http/Controllers/ReportTemplateController.php), [ReportBrandingController](../../../backend/app/Http/Controllers/ReportBrandingController.php) | [Operations/report map](../operations/README.md), [reportService](../../../frontend/services/reportService.ts), domain generators |
| Accounts/admin controls and audit oversight | [UserController](../../../backend/app/Http/Controllers/Admin/UserController.php), [Audit services](../../../backend/app/Services/Audit/), [audit config](../../../backend/config/audit.php) | [Backend map](../platform/backend.md), [audit reference](../../architecture/audit-logging.md) |

## Meaning and safe changes

User role/is_active and onboarding/recovery flags in User affect access and setup; don't interpret a hidden control as authorization. Check route middleware, request authorization and ownership, then both clients. A controller under RND/ can still serve shared routes.

Notification read state, dismissal, source linkage and retention are distinct from the source business event. Trace NotificationLifecycleService and scheduled pruning before changing their meaning. Don't make announcements/SOP changes for only one recipient role.

Dashboards combine different domain data. A shared layout is not evidence that counts, permissions, filters or time windows should be identical. Report data may use historical snapshots; don't replace it with live catalog values without tracing its generator.

Related: [data tracing](../platform/data-semantics.md), [web controls](../platform/web.md), [food-service mobile](../food-service/mobile.md), [operations](../operations/README.md). Verify affected role consumers and notification behavior when making changes.
