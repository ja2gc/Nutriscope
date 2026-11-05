# Initial data and seeding

Document version: 1.0 | Issued: 5 November 2026

Database migrations create/update the schema. Seeders insert or update application data. Restoring an existing installation is a third operation: it preserves its accounts, records and files. Choose the applicable path before starting worker/scheduler roles or the release.

## Existing installation transferred to a new VPS

Follow [turnover](turnover.md#existing-system-migration-sequence). Restore a coherent database and protected-file set, preserve `APP_KEY`, and retain the passwords for historical backups. Do not run `DatabaseSeeder`, `AdminUserSeeder` or a demo reset. Existing institutional administrators remain in the restored database; use **Admin → Manage Users** to create named receiving-team accounts and complete their password/recovery onboarding.

After restoring the compatible dataset, the normal release applies pending migrations and runs `FoodServiceMenuTemplateSeeder`. Check that its RND account, food-service catalog and referenced dishes exist before release. Do not use a failed deployment as permission to reseed operational data.

## New empty database

The repository does not contain a dedicated institution-only bootstrap command. Do not describe `php artisan db:seed --force` as a clean production initialization: the default seeder creates exhibition accounts, fictional patients and operational history. A new empty database also cannot complete the normal release until its menu-template dependencies exist.

Before accepting a clean installation, the application maintainer must supply the approved initial administrator, an RND account, reference data and the food-service dishes required by the release. Use a separately prepared institutional dataset or an approved initialization procedure. **Stop before the first full release if this dataset/procedure has not been supplied.** Do not remove seeders, bypass the release checks, reuse known demo passwords or invent patients/purchase orders to make deployment pass.

This boundary applies to a clean client database; it does not prevent the host, accounts, configuration, images, DNS or TLS from being prepared. Keep public traffic and scheduled writers disabled until initialization and acceptance are complete.

## What the current seeders do

Source: [DatabaseSeeder](../../backend/database/seeders/DatabaseSeeder.php) and the named classes in [database/seeders](../../backend/database/seeders).

| Seeder/group | Current content and handling |
|---|---|
| `AdminUserSeeder` | Fixed exhibition Admin/RND/FSS identities and demo passwords; not an institution-only administrator bootstrap |
| `ProfilePhotoSeeder`, `PatientSeeder` | Fictional demonstration identities/assets and clinical records; exclude from a clean client installation |
| `FoodItemsSeeder` | Synchronizes the pinned, local USDA-derived clinical-food dataset; no runtime USDA key/network call is needed for this import |
| `ClinicalRulesSeeder` | Synchronizes clinical recommendation rules and removes obsolete rule identities; requires clinical-owner approval, not blind routine reseeding |
| `RecipeSeeder`, `FoodItemPricesSeeder` | RND-owned clinical recipes and illustrative market pricing; reseeding can replace ingredients/prices and recalculate costs |
| `InterventionMealPlanTemplateSeeder` | RND-owned clinical templates with demo-labelled names; requires the corresponding recipes and RND owner |
| `FsCatalogSeeder` | Food-service catalog with sample prices; synchronizes matching names and can overwrite institutional costs |
| `FoodServiceDemoSeeder` | Food-service recipes plus fictional suppliers, cycles, purchasing, consumption and budgets; not a reference-only recipe import |
| `FoodServiceMenuTemplateSeeder` | Reusable seven-day templates; requires an existing RND and referenced food-service recipe/item names; runs in every normal release |
| `AiUsageLimitSeeder` | Writes configured usage limits and cost settings; review against institutional settings/provider billing rather than rerunning as maintenance |
| `ReportTemplateSeeder` | Report catalog/signatory defaults; review receiving institution's names/titles and configure report settings before use |
| `AnnouncementSeeder`, `NotificationSeeder`, `SopSeeder` | Exhibition communications/history; exclude from a clean client installation |

Food library imports are useful reference data, but that does not make the entire default seeder production-safe. Importing foods does not provide the distinct food-service catalog or its dish recipes. Recipe/template ownership must refer to the intended institutional RND account.

## Release-owned data operations

[docker-release.sh](../../backend/docker-release.sh) runs the following automatically during a normal deployment:

```sh
php artisan migrate --force
php artisan db:seed --class=FoodServiceMenuTemplateSeeder --force
php artisan reports:demographic-census-catch-up
```

These commands are **state-changing reference excerpts**, not an additional step to run after every deployment. The release also caches configuration/routes/views and creates the storage link. It does not call the full `DatabaseSeeder` or provision the initial staff accounts.

`FoodServiceMenuTemplateSeeder` stops if an RND user is missing or a planned dish/item is missing. Its required names are defined in its `templates()` method. Dishes currently come from the exhibition food-service recipe setup; `FsCatalogSeeder` alone does not supply all of them. This is why a generic empty-database deployment is insufficient.

## Exhibition dataset only

On a **confirmed disposable exhibition installation**, after its images, protected configuration and database are ready, use the same Bash `dc` function as [VPS setup](vps-setup.md#2-clone-and-configure).

**State-changing — exhibition VPS, deploy user, `~/Nutriscope`; never a client/retained database:**

```sh
dc up -d mysql redis
dc --profile release run --rm --pull never backend_release php artisan migrate --force
dc --profile release run --rm --pull never backend_release php artisan db:seed --force
dc --profile release run --rm --pull never backend_release
dc up -d --no-build --pull never backend backend_worker backend_scheduler frontend
```

The command after the service name overrides its normal release command; the container entrypoint still waits for MySQL and drops CLI privileges. Seeding creates the full fictional graph. It can reset parts of its named demo graph and update catalog/reference settings. Do not treat it as harmless because some seeders use upserts. Never run `migrate:fresh`, delete a volume or clear tables to convert a retained installation into an exhibition installation.

## Maintaining this procedure when seeding changes

The maintainer of [DatabaseSeeder](../../backend/database/seeders/DatabaseSeeder.php) and [docker-release.sh](../../backend/docker-release.sh) owns the initialization contract. When those files change, update this page in the same change: exact command/order, initial account creation and recovery, retained reference imports, data each command writes, required ownership/dependencies, and rerun behavior. Verify on an isolated empty database, then verify rerunning does not create duplicates or overwrite institutional configuration unexpectedly. Do not leave removed class names in operator commands.

Related: [database maintenance](../developers/platform/database.md), [food-service data](../developers/food-service/data.md), [NCP data](../developers/ncp/data.md).
