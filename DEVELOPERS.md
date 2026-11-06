# NutriScope developer map

Procurement report kinds and PPA daily output are owned by `ReportController`, `ProgramProjectActivityGenerator`, `PrepareSavedReport` and `ReportsBrowser`. AIR rendering lives in `reports/partials/acceptance-inspection`; its editor labels name inputs by position. Shared report layout owns Calibri and signature margins. Completion captures daily served counts; manual food/supplies omit population-only presentation. Linked PO labels use `displayName`. Source review: `0e1927e2` plus the AIR changes, 2026-11-06; see [food-service data](docs/developers/food-service/data.md#purchasing-report-types-and-ppa-output) and [report operations](docs/developers/operations/README.md#changing-reports-and-operational-behavior).

Web uploads share `FileUploadField`; `ImageUploadGallery` owns thumbnails, `ImageViewer` owns image viewing, and `ImageFilePicker` owns single-logo selection. `RemoveButton` is shared with Menu Calendar/template actions. See [web controls](docs/developers/platform/web.md).

Announcement audiences: `Announcement::audienceRoles()` owns legacy visibility fallback; the shared controller owns role-feed/image access and `NotificationService::fanOutAnnouncement` owns recipients. Both web composers reuse `AnnouncementAudienceSelect`. Source review: `1ba3d15f` plus the audience change, 2026-11-05; see [shared features](docs/developers/shared/README.md).

PES review: `AIService` requires supplied Problem selections; `DiagnosisBuilder` maps existing terms to selected controls. `PesSuggestionService` withholds incomplete proposals, retains complete drafts requiring review, and prioritizes reopened Problems after dismissal/deletion. Read-only refresh remains cached. Source review: `36d63e53` plus diagnosis changes, 2026-11-06; see [AI](docs/developers/ncp/ai.md).

Diagnosis lab-unit metadata reuses `MonitoringPlanService::laboratoryUnit`; `PesEvidenceBuilder` adds units to abnormal lab evidence without changing measurements or ranges. `DiagnosisBuilder::problemLabel` matches existing terms after cleaning display hints and case.

PES review: `AIService` owns shared Sonnet configuration and concise category/domain guidance. `PesSuggestionService` preserves readable clinical proposals, separates ordinary drafts from those needing closer review, and retains independent P/E/S source roles. `ClinicalAiContext` routes direction-specific lab references using existing flags. `PesSuggestionService` converts provider review comments into a fixed request to check all Assessment fields. The diagnosis page owns PES-only draft presentation, review notes, accessible help and selection-aware Problem groups; final save owns text limits. `NcpNoPatientSelected` composes existing `EmptyState` and `NcpBreadcrumb` for all four ADIME placeholder routes, keeping card sizing, copy, action and directory consistent. Saved diagnoses and calculation snapshots are unchanged. See [AI](docs/developers/ncp/ai.md) and [NCP data](docs/developers/ncp/data.md).

Activity estimates: `NutritionPrescriptionService` owns adult factors and report labels; `nutritionCalculations.ts` mirrors them for Assessment, Monitoring and summary generation. Sedentary is separate from bed-bound functional status. `PesEligibilityService` excludes prior-education history alone as knowledge-deficit proof; `PesSuggestionService` rejects identical cause/sign findings after grounding; see [NCP data](docs/developers/ncp/data.md).

Nutrient reference discovery: `ClinicalAiContext` searches recorded findings and `AIService` permits one lookup for PES reasoning. The separate Possible concerns output/card has been removed; review notes belong to individual complete drafts. See [AI guidance](docs/developers/ncp/ai.md) and [NCP data](docs/developers/ncp/data.md).

Diagnosis keeps the existing P/E/S append builder. `DiagnosisBuilder` owns terminology formatting and selection metadata; `DiagnosisController` owns the exclusive clinician-recorded NO decision. `PesSuggestionService` owns evidence checks, duplicate protection and review reuse; `ClinicalAiContext` selects brief guidance on the server. See [NCP AI](docs/developers/ncp/ai.md) and [NCP data](docs/developers/ncp/data.md).

BMI classification is owned by `Assessment::classifyBmi` and mirrored for live web inputs by `frontend/lib/nutritionCalculations.ts`: WHO sex/month bands at 61–228 months, Asia-Pacific afterward. The two offline `who-bmi-for-age.json` files are verified copies for separately built applications. Diagnosis AI and current reports reuse the model method; screening risk and clinical PES diagnoses remain separate. See [NCP data](docs/developers/ncp/data.md).

Assessment summary generation is owned by `frontend/lib/assessmentSummary.ts`; the Assessment page supplies current referral and assessment inputs. Generate Summary creates up to three editable sentences without AI. See [summary inputs and persistence](docs/developers/ncp/data.md).

Census uses each counted cycle's initial Assessment, with ordered pediatric/adult BMI groups and all Nutrition care categories, including zeros. `DemographicCensusGenerator` owns the calculation basis; `ReportController` recalculates obsolete-basis periods on read without overwriting stored snapshots. `CensusPanel` and the census download view consume the same summary.

NutriScope manages patient nutrition care, clinical meal planning, food-service operations and administration. Start with the task below, then follow its source links.

Nutrition support routing and captured formula collections are owned by `InterventionPlanService` / `FormulaPlanService`, using `Recipe::planSnapshot` for nutrient arithmetic. The web reuses `FoodRecallCalculator` for the Food Library calculator and `MenuItemCreateForm` for on-the-spot tube formulas. See [NCP owners and data](docs/developers/ncp/README.md) for modes, templates, reports and census. Operational menu portion/reset snapshots remain in `MenuCycleController` / `MenuCycleDay`; see [food-service data](docs/developers/food-service/data.md).

Prefer browsing by area? Open the [grouped developer index](docs/developers/README.md): [NCP](docs/developers/ncp/README.md), [food service and FSS mobile](docs/developers/food-service/README.md), [shared role features](docs/developers/shared/README.md), [platform](docs/developers/platform/architecture.md), or [operations](docs/developers/operations/README.md).

| Directory | Purpose |
|---|---|
| backend/ | Laravel API, validation, permissions, calculations, persistence, jobs and reports |
| frontend/ | Next.js web routes, API proxies, components, hooks and clients |
| mobile/ | Expo food-service screens, auth and API clients |
| docs/ | Current system guides and focused domain references |
| Storyboarding/ | Canonical RND, FSS and Admin user manuals with prerequisites, task steps and daily routines |
| nginx/, .github/, ops/ | Proxy snippets, deployment/release workflows, image deployment and retention |
| .agents/ | Repository rules loaded explicitly by AGENTS.md |

## If you want to...

| Task | Exact starting point | Guide |
|---|---|---|
| Understand the system | backend/routes/api.php; frontend/app/; mobile/app/ | [Architecture](docs/developers/platform/architecture.md) |
| Plan slow-connection improvements or future offline workflows | frontend/components/layout/Sidebar.tsx; frontend/components/layout/TopBar.tsx; frontend/contexts/AuthContext.tsx; mobile/app/_layout.tsx; backend/app/Http/Controllers/Auth/AuthController.php | [Developer recommendations](docs/developers/recommendations/README.md#connectivity-current-behavior-and-future-work) |
| Set up a developer machine | backend/composer.json; frontend/package.json; mobile/eas.json | [Local setup](docs/developers/platform/local-setup.md) |
| Change account profile, photo refresh or recovery-email cancellation | frontend/components/profile/ProfilePageShell.tsx; frontend/contexts/AuthContext.tsx; backend/app/Http/Controllers/Auth/RecoveryEmailController.php | [Web](docs/developers/platform/web.md), [shared accounts](docs/developers/shared/README.md), [auth API](docs/operations/api-overview.md#authentication-and-account-access) |
| Install or transfer the client system | docker-compose.yml; docker-compose.prod.yml; .github/workflows/deploy.yml; ops/deploy.sh | [Installation and turnover manual](docs/operations/README.md), [initial data](docs/operations/initial-data.md) |
| Recover manually when Admin cannot open backups | backend/app/Services/Backup/BackupArchiveInspector.php; BackupManifestService.php; ProtectedObjectStore.php; MysqlDatabaseRestoreManager.php | [Manual R2 recovery](docs/operations/backup-recovery.md#manual-recovery-when-the-admin-screen-is-unavailable) |
| Train RND, FSS or Admin staff | Storyboarding/; frontend/components/layout/Sidebar.tsx; mobile/app/(tabs)/_layout.tsx | [Role user manuals](docs/ROLE-HOW-TO.md), [daily routines](docs/ROLE-HOW-TO.md#daily-and-periodic-work) |
| Add an endpoint or permission | backend/app/Http/; backend/app/Policies/ | [Backend](docs/developers/platform/backend.md) |
| Change web screens, inputs, validation or uploads | frontend/components/ui/; frontend/hooks/; frontend/lib/uploadValidation.ts | [Web](docs/developers/platform/web.md) |
| Change visual styling or shared controls | frontend/app/globals.css; frontend/components/ui/ | [Web](docs/developers/platform/web.md) |
| Change the native app or API URL | mobile/app/; mobile/lib/; mobile/eas.json | [Mobile](docs/developers/food-service/mobile.md) |
| Change fields, statuses or stored values | backend/database/migrations/; backend/app/Models/ | [Database](docs/developers/platform/database.md) |
| Understand what a variable/field means and where it is used | models/casts → requests/writers → API Resources → clients/reports/jobs | [Data semantics](docs/developers/platform/data-semantics.md), [NCP data](docs/developers/ncp/data.md), [food-service data](docs/developers/food-service/data.md) |
| Change clinical formulas or NCP steps | backend/app/Services/NutritionPrescriptionService.php | [Clinical logic](docs/developers/ncp/README.md) |
| Change patients, visits, calendar, labs, monitoring or meal suggestions | backend/app/Http/Controllers/RND/; backend/app/Services/MonitoringPlanService.php | [Clinical feature map](docs/developers/ncp/README.md) |
| Change recipes, menus, purchasing or budgets | backend/app/Services/FSS/ | [Food service](docs/developers/food-service/README.md) |
| Change Claude model, prompts, context or usage limits | backend/app/Services/AIService.php; backend/app/Services/Diagnosis/PesSuggestionService.php; backend/app/Support/ClinicalAiContext.php; backend/resources/clinical-ai-context.json; backend/app/Support/AiReviewAllowance.php; backend/config/services.php | [AI](docs/developers/ncp/ai.md) |
| Set up USDA, email, R2 or Render | backend/config/services.php; backend/config/filesystems.php | [Integrations](docs/operations/integrations.md) |
| Find environment variable ownership | backend/.env.production.example; frontend/.env.example | [Environment](docs/operations/environment.md) |
| Transfer personal accounts, set the five Actions secrets or change VPS IP/domain | .github/workflows/deploy.yml; .github/workflows/release-mobile.yml; mobile/eas.json; mobile/lib/appUpdate.ts; frontend/public/nutriscope-mobile-qr.svg | [Secret sources/setup](docs/operations/environment.md#github-actions-repository-secrets-obtain-enter-and-replace), [account/address change matrix](docs/operations/turnover.md#every-place-to-update-when-addresses-or-accounts-change), [provider value sources](docs/operations/integrations.md#replacement-account-value-sources) |
| Run checks safely | backend/phpunit.xml; frontend/vitest.config.ts | [Testing](docs/developers/platform/testing.md) |
| Maintain queues, files, reports or backups | backend/bootstrap/app.php; backend/app/Services/Backup/ | [Operations](docs/developers/operations/README.md) |
| Change report templates, branding or exports | backend/app/Services/Reports/; backend/app/Http/Controllers/ReportBrandingController.php | [Operations](docs/developers/operations/README.md) |
| Change notifications, announcements or SOP | backend/app/Services/NotificationService.php; backend/app/Http/Controllers/SopController.php | [Backend](docs/developers/platform/backend.md), [role modules](docs/README.md) |
| Change accounts, password recovery or audit | backend/app/Http/Controllers/Admin/UserController.php; backend/app/Models/User.php; backend/config/audit.php | [Backend](docs/developers/platform/backend.md), [audit reference](docs/architecture/audit-logging.md) |
| Move to a cheaper VPS; set up DNS/certificates or deployment retention | docker-compose.yml; docker-compose.prod.yml; ops/deploy.sh; .github/workflows/deploy.yml; nginx/mobile-api.locations.conf | [VPS setup](docs/operations/vps-setup.md), [turnover](docs/operations/turnover.md) |
| Build and publish an app update using EAS Cloud | mobile/app.json; mobile/eas.json; .github/workflows/release-mobile.yml | [Android releases](docs/operations/mobile-release.md) |
| Update help, role manuals or daily routines | docs/FAQ.md; docs/ROLE-HOW-TO.md; Storyboarding/ | [User manual index](docs/ROLE-HOW-TO.md), [documentation index](docs/README.md) |

Read [AGENTS.md](AGENTS.md) before changes. Update only affected guides in the same change, describing the inspected implementation. Guides describe the current implementation. Live accounts, installed schemas, DNS and backup recovery need separate authorized verification.

Web mutation refresh behavior is owned by each screen's local loader and saved-response callback; follow the [web refresh convention](docs/developers/platform/web.md).

Procurement's page and `SuppliersPanel` reuse the shopping-list table layout for horizontal scrolling and single-line names on narrow screens. Shared `Pagination` owns wrapping and button targets; directory footers remain outside table scrollers. Source review: `50752dd4` plus this layout change, 2026-11-06; see [web table guidance](docs/developers/platform/web.md).

Website section-tab containment is owned by shared `Tabs`, `TabBar` and the existing page-local tab rows: single-line buttons scroll within the row instead of shrinking or stacking at phone widths. Existing NCP section tabs already follow it. Source review: `6ad8a880` plus this tab layout change, 2026-11-06; see [web controls](docs/developers/platform/web.md).

Low-bandwidth loading stays with the existing screen/service owners: Diagnosis separates AI status; Monitoring separates history pagination; patient profiles separate past cycles; Procurement/Inventory defer supplier choices. Native Menu/Meal Prep share `mobile/lib/menuQueries.ts`; `AnnouncementResource`/`AnnouncementController` own opt-in protected image delivery. See [web](docs/developers/platform/web.md), [mobile](docs/developers/food-service/mobile.md) and [shared contracts](docs/developers/shared/README.md). No offline persistence or workflow-rule change is introduced.

Mobile dashboard task rows and today's single-column meals remain owned by `mobile/app/(tabs)/index.tsx`. `AppHeader` owns the unboxed two-color branding shared across primary tabs; `AnnouncementMedia`'s existing avatar is reused by announcements, header and account menu, waiting for bearer authentication before fetching protected photos and falling back to initials. `ReportsScreen` disables future-day navigation and shows period progress before opening; the daily-log screen also guards future dates. Source review: `6ad8a880` plus this mobile UI change, 2026-11-06; see [mobile maintenance](docs/developers/food-service/mobile.md).

Intervention's page-local `loadPlans` also reuses the latest full plan when its public UUID matches the selection; older-plan links retain their separate read. See [web loading ownership](docs/developers/platform/web.md).

Admin AI graph timezone selection is owned by `AiUsageAnalyticsController` and `AiUsageExplorer`; Manila remains the default, with UTC available for provider comparisons. AIService requires an authenticated user before a paid request. See [AI usage semantics](docs/developers/ncp/ai.md).

Vendor receipt-total mode and amount are owned by `PurchaseOrderVendorGroup` and the vendor-group update action; see [food-service data](docs/developers/food-service/data.md). This is separate from per-item Actual Total, which derives quantity.

Shopping-list default names are owned by `ShoppingList::defaultName` and applied by `ShoppingListController`. PO conversion readiness is owned by `PurchaseOrderLifecycleService`; it checks suggested estimate/coverage without item-confirmation or budget gates. Web defaults use `manilaCalendarToday` and the existing save dialog for exact missing-plan dates. See [food-service data](docs/developers/food-service/data.md).

## If your task is not listed

The linked roadmap describes future work; offline synchronization is not implemented.

This map indexes current feature families; it is not an exhaustive specification or a guarantee that every future change is documented. Locate the screen in frontend/app or mobile/app, find its service/API call, then trace backend/routes/api.php through request/permission checks, controller, service and persisted model. Inspect reports/jobs/events and both clients that consume it, then matching tests. Use rg to search exact route, symbol or field names and rg --files to locate files. Check dynamic resolution and framework conventions before declaring something unused.

If a topic is absent or evidence is missing, investigate it and update the existing focused guide with verified source paths and any unresolved questions. A missing paragraph does not make the behavior safe to change. Keep one canonical maintenance location: docs/developers/; longer domain references are linked there on demand.
Patient directory Last Clinical Review is computed by `ClinicalAttributionService` from Assessment/lab and Monitoring saves plus valid visit start/finish times, across all cycles. `PatientResource.last_clinical_review_at` is displayed with Manila calendar-day labels by `calendarDate.manilaRelativeDate`. Discarded starts and scheduled/cancelled/no-show/rescheduled appointments do not contribute. Source review: `99d9056c` plus clinical-review changes, 2026-11-06; see [NCP data](docs/developers/ncp/data.md).
