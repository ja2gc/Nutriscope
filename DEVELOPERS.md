# NutriScope developer map

Verified against source: `71d3634b469ea51fb668cf18e1e83a1c072db52e` plus diagnosis evidence/retry corrections in this working tree (2026-10-28).

Diagnosis keeps the existing P/E/S append builder. `DiagnosisBuilder` owns terminology formatting and selection metadata; `DiagnosisController` owns the exclusive clinician-recorded NO decision. `PesSuggestionService` owns evidence checks, duplicate protection and review reuse; `ClinicalAiContext` selects brief guidance on the server. See [NCP AI](docs/developers/ncp/ai.md) and [NCP data](docs/developers/ncp/data.md).

BMI classification is owned by `Assessment::classifyBmi` and mirrored for live web inputs by `frontend/lib/nutritionCalculations.ts`: WHO sex/month bands at 61–228 months, Asia-Pacific afterward. The two offline `who-bmi-for-age.json` files are verified copies for separately built applications. Diagnosis AI and current reports reuse the model method; screening risk and clinical PES diagnoses remain separate. See [NCP data](docs/developers/ncp/data.md).

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
| Storyboarding/ | Canonical role-specific presentation sequences |
| nginx/, .github/, ops/ | Proxy snippets, deployment/release workflows, image deployment and retention |
| .agents/ | Repository rules loaded explicitly by AGENTS.md |

## If you want to...

| Task | Exact starting point | Guide |
|---|---|---|
| Understand the system | backend/routes/api.php; frontend/app/; mobile/app/ | [Architecture](docs/developers/platform/architecture.md) |
| Set up a developer machine | backend/composer.json; frontend/package.json; mobile/eas.json | [Local setup](docs/developers/platform/local-setup.md) |
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
| Set up USDA, email, R2 or Render | backend/config/services.php; backend/config/filesystems.php | [Integrations](docs/developers/operations/integrations.md) |
| Find environment variable ownership | backend/.env.production.example; frontend/.env.example | [Environment](docs/developers/operations/environment.md) |
| Transfer personal accounts, set the five Actions secrets or change VPS IP/domain | .github/workflows/deploy.yml; .github/workflows/release-mobile.yml; mobile/eas.json; mobile/lib/appUpdate.ts; frontend/public/nutriscope-mobile-qr.svg | [Secret sources/setup](docs/developers/operations/environment.md#github-actions-repository-secrets-obtain-enter-and-replace), [account/address change matrix](docs/operations/turnover.md#every-place-to-update-when-addresses-or-accounts-change), [provider value sources](docs/developers/operations/integrations.md#replacement-account-value-sources) |
| Run checks safely | backend/phpunit.xml; frontend/vitest.config.ts | [Testing](docs/developers/platform/testing.md) |
| Maintain queues, files, reports or backups | backend/bootstrap/app.php; backend/app/Services/Backup/ | [Operations](docs/developers/operations/README.md) |
| Change report templates, branding or exports | backend/app/Services/Reports/; backend/app/Http/Controllers/ReportBrandingController.php | [Operations](docs/developers/operations/README.md) |
| Change notifications, announcements or SOP | backend/app/Services/NotificationService.php; backend/app/Http/Controllers/SopController.php | [Backend](docs/developers/platform/backend.md), [role modules](docs/README.md) |
| Change accounts, password recovery or audit | backend/app/Http/Controllers/Admin/UserController.php; backend/app/Models/User.php; backend/config/audit.php | [Backend](docs/developers/platform/backend.md), [audit reference](docs/architecture/audit-logging.md) |
| Move to a cheaper VPS; set up DNS/certificates or deployment retention | docker-compose.yml; docker-compose.prod.yml; ops/deploy.sh; .github/workflows/deploy.yml; nginx/mobile-api.locations.conf | [VPS setup](docs/operations/vps-setup.md), [turnover](docs/operations/turnover.md) |
| Build and publish an app update using EAS Cloud | mobile/app.json; mobile/eas.json; .github/workflows/release-mobile.yml | [Android releases](docs/mobile-apk-release.md) |
| Update help or presentation instructions | docs/FAQ.md; docs/ROLE-HOW-TO.md; Storyboarding/ | [Documentation index](docs/README.md) |

Read [AGENTS.md](AGENTS.md) before changes. Update only affected guides in the same change, with the source revision verified. Guides describe the current implementation. Live accounts, installed schemas, DNS and backup recovery need separate authorized verification.

Web mutation refresh behavior is owned by each screen's local loader and saved-response callback; follow the [web refresh convention](docs/developers/platform/web.md). Verified at `8a7846b3` plus the in-place update changes in this working tree (2026-10-27).

Admin AI graph timezone selection is owned by `AiUsageAnalyticsController` and `AiUsageExplorer`; Manila remains the default, with UTC available for provider comparisons. AIService requires an authenticated user before a paid request. See [AI usage semantics](docs/developers/ncp/ai.md). Verified at `86a8a3a0` plus these working-tree changes (2026-10-27).

Vendor receipt-total mode and amount are owned by `PurchaseOrderVendorGroup` and the vendor-group update action; see [food-service data](docs/developers/food-service/data.md). This is separate from per-item Actual Total, which derives quantity.

Shopping-list default names are owned by `ShoppingList::defaultName` and applied by `ShoppingListController`. PO conversion readiness is owned by `PurchaseOrderLifecycleService`; it checks suggested estimate/coverage without item-confirmation or budget gates. Web defaults use `manilaCalendarToday` and the existing save dialog for exact missing-plan dates. See [food-service data](docs/developers/food-service/data.md). Verified at `ef419e88` plus this working tree (2026-10-27).

## If your task is not listed

This map indexes current feature families; it is not an exhaustive specification or a guarantee that every future change is documented. Locate the screen in frontend/app or mobile/app, find its service/API call, then trace backend/routes/api.php through request/permission checks, controller, service and persisted model. Inspect reports/jobs/events and both clients that consume it, then matching tests. Use rg to search exact route, symbol or field names and rg --files to locate files. Check dynamic resolution and framework conventions before declaring something unused.

If a topic is absent or evidence is missing, investigate it and update the existing focused guide with verified source paths and any unresolved questions. A missing paragraph does not make the behavior safe to change. Keep one canonical maintenance location: docs/developers/; longer domain references are linked there on demand.
