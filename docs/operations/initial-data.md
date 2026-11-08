# Initial data and seeding

Migrations prepare the schema. Production seeds initialize accounts and reference catalogs. Demo seeds create fictional records for a separate training installation. A restore preserves an existing installation's data and files.

## Choose the correct path

| Purpose | Use | Result |
|---|---|---|
| New production installation | `php artisan db:seed --force` | One initial Admin and production reference data; no fictional patients or operational history |
| Local development or demonstration | `php artisan db:seed --class='Database\Seeders\Demo\DatabaseSeeder'` | Fictional Admin/RND/FSS accounts, patients, menus, purchasing, attendance and supporting demo content |
| Automated tests | PHPUnit with an explicitly isolated test database | Each test creates its own required fixtures; no manual demo initialization |
| Existing installation or server transfer | Restore and normal release procedure | Retains accounts, records, settings and protected files; no automatic seeding |

Production seeders are in [backend/database/seeders](../../backend/database/seeders). Optional demo seeders are in [seeders/Demo](../../backend/database/seeders/Demo). The production food-service definitions are in [data/food-service.php](../../backend/database/seeders/data/food-service.php); the local clinical-food dataset is in the same data directory. Profile/receipt assets under `seeders/assets` belong to the demo path.

## Fresh production installation

These commands change the selected database. Use them only for the approved new installation, after confirming its database, configuration and protected storage. Keep public traffic and scheduled writers stopped until setup is complete.

1. Prepare the environment from the [production example](../../backend/.env.production.example), following [environment setup](environment.md). Preserve an existing `APP_KEY`; generate one only for a new installation.
2. Supply `SEED_ADMIN_FIRST_NAME`, `SEED_ADMIN_LAST_NAME`, `SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD` privately. Use the receiving administrator's real account details and a unique temporary password of at least eight characters. There is no default production login.
3. From `backend/`, with the intended production environment selected, run:

```sh
php artisan config:cache
php artisan migrate --force
php artisan db:seed --force
```

4. Sign in as the initial Admin, change the temporary password and complete recovery-email setup. Create named RND/FSS accounts through **Admin → Manage Users**.
5. Review **Reports → Template Edit**, institutional signatories, AI limits/cost settings and the food-service catalog's estimated prices before operational use.
6. Assign clinical references to the real RND as described below. Remove the four bootstrap Admin values from the protected environment and rebuild the configuration cache. Retain the institution's normal application settings and keys.
7. Complete the [installation acceptance checks](maintenance.md#acceptance-checklist), then start the normal service roles.

An existing active Admin is preserved: the seeder does not rename it, reset its password or create additional staff accounts. If no active Admin exists, incomplete or invalid bootstrap details stop seeding.

### Production sign-in credentials

| Seeded role | Sign-in email | Initial password | Required first-login work |
|---|---|---|---|
| Admin | The operator-supplied value of `SEED_ADMIN_EMAIL` | The privately supplied value of `SEED_ADMIN_PASSWORD` | Change the temporary password and verify a recovery email |
| RND / FSS | No production account is seeded | Admin supplies a unique temporary password when creating each named account | Complete the same account-setup requirements |

There is no universal production email/password pair. Record the institution's actual credentials in its authorized password manager or private turnover record, separately from repository documentation. The configured email is the initial sign-in identifier; `SEED_REFERENCE_EMAIL` selects a reference-data owner and does not create another login.

### Production Docker Compose

Use the Bash `dc` function and protected root-password setup from [VPS setup](vps-setup.md#2-clone-and-configure), in the repository root. After preparing the backend image and protected environment, these are state-changing initialization commands:

```sh
dc up -d mysql redis
dc --profile release run --rm --pull never backend_release php artisan migrate --force
dc --profile release run --rm --pull never backend_release php artisan db:seed --force
dc --profile release run --rm --pull never backend_release
dc up -d --no-build --pull never backend backend_worker backend_scheduler frontend
```

The container entrypoint prepares its configuration and drops CLI commands to `www-data`. Supplying a command after `backend_release` overrides that service's normal release command. Complete first-login, reference-data review and acceptance before enabling public traffic.

### Clinical references for a real RND

Clinical foods, recipes, prices, rules and intervention templates remain in production seeding. The initial Admin can hold bootstrap reference attribution without becoming an RND or gaining clinical permissions.

Clinical templates and automatic recipe selection are scoped to their owner. After creating an RND, set `SEED_REFERENCE_EMAIL` to that active RND's email in the selected environment and rebuild configuration. Then, from `backend/`:

```sh
php artisan config:cache
php artisan db:seed --class=RecipeSeeder --force
php artisan db:seed --class=FoodItemPricesSeeder --force
php artisan db:seed --class=InterventionMealPlanTemplateSeeder --force
```

With Docker, use `dc --profile release run --rm --pull never backend_release` before each `php artisan db:seed` command; each new container rebuilds its own configuration from the environment.

The email must identify an active RND or Admin. Without it, reference seeders select the first active RND by ID, otherwise the first active Admin. Use an explicit email when several RND accounts exist. Repeat for another RND only when that account should receive these clinical references.

The retained clinical templates include their existing example names/content. They are starting references for RND review, not patient prescriptions. These commands synchronize that owner's named clinical recipes/templates and illustrative clinical prices; they can replace edits to those canonical definitions. They do not create patients or change clinical snack-generation behavior.

## Production content and reruns

| Seeder | Content and rerun behavior |
|---|---|
| `AdminUserSeeder` | Creates only the initial Admin when no active Admin exists; preserves existing credentials |
| `FoodItemsSeeder` | Synchronizes the pinned local USDA-derived clinical foods; preserves unrelated manual foods; no network access |
| `ClinicalRulesSeeder` | Synchronizes canonical clinical rules and removes obsolete rule identities |
| `RecipeSeeder`, `FoodItemPricesSeeder`, `InterventionMealPlanTemplateSeeder` | Retained clinical definitions and illustrative prices, scoped as described above; canonical recipe ingredients/template lines are synchronized |
| `FsCatalogSeeder` | Creates missing ingredients/supplies with prices per named buying format and the supplier named in the purchasing forms; preserves matching existing catalog records; stops for review of recognizable older production package definitions |
| `FoodServiceRecipeSeeder` | Creates missing food-service recipes with 50-serving baselines; preserves existing recipes and ingredient edits |
| `FoodServiceMenuTemplateSeeder` | Creates missing September menu-post rotations; preserves existing templates and their lines |
| `AiUsageLimitSeeder` | Initializes missing usage settings; preserves existing settings |
| `ReportTemplateSeeder` | Creates missing report definitions; preserves existing signatories and configuration |

Production seeds create no patients, clinical histories, actual menu cycles, shopping lists, purchase orders, budgets, attendance, announcements, notifications, SOP revision history, report files or uploaded assets. The production seed sequence is transactional; it restores the prior audit-suppression setting after completion or failure.

Food-service recipes and template references require the catalog first. A catalog unit incompatible with a required ingredient stops recipe seeding for review. Seeding is not a tool for converting existing manually configured units or deleting demo records from a retained database.

For menu, ingredient, price and grocery semantics, see [food-service data](../developers/food-service/data.md#seeded-food-service-references). Review estimates with the kitchen before purchasing; a reconciled spreadsheet total does not prove an exact recipe yield or current price.

## Local development and demo training

Use a separate disposable local database and local storage, following [local setup](../developers/platform/local-setup.md). Set `APP_ENV=local`; leave `SEED_REFERENCE_EMAIL` unset for the standard demo. Do not copy production configuration or patient data.

From `backend/`, after confirming the local database:

```sh
composer install
# New local installation only:
php artisan key:generate
php artisan config:clear
php artisan migrate
php artisan db:seed --class='Database\Seeders\Demo\DatabaseSeeder'
php artisan serve --host=127.0.0.1 --port=8000
```

The demo seed does not require production bootstrap Admin values. Its fictional accounts are:

| Role | Sign-in email | Public demo password |
|---|---|---|
| Admin | `admin@nutriscope.local` | `nutriscope2024!` |
| RND | `rnd@nutriscope.local` | `nutriscope2024!` |
| FSS | `fss@nutriscope.local` | `nutriscope2024!` |

The public demo password is `nutriscope2024!`. These are local demonstration credentials only.

Rerunning the same demo command refreshes its named fictional graph and retains unrelated records, but can replace demonstration recipes, catalogs and history. Demo seeders are blocked when the application environment is `production`, including when `--force` is supplied. Changing the environment label does not make an institutional database disposable.

The separate `Demo\FoodServiceOctoberBackfillSeeder` is a date-specific exhibition utility, not part of ordinary production or demo initialization. It requires its source demo menus and must not be used to manufacture institutional service history.

To test a production-style setup locally instead, supply synthetic `SEED_ADMIN_*` values and run the default `php artisan db:seed` on a separate clean local database. Do not combine both datasets to assess a clean production installation.

## Automated tests

Follow [test isolation](../developers/platform/testing.md#isolation-comes-first) before running any database test. Confirm the effective database/account and configuration cache; a database name ending in `_test` alone is not proof of isolation. Tests use factories and explicitly selected seeders, not a manually populated demonstration database.

From `backend/`, with the isolated test environment established:

```sh
vendor/bin/phpunit tests/Feature/ProductionSeederTest.php
vendor/bin/phpunit tests/Feature/DatabaseSeederRepeatabilityTest.php
vendor/bin/phpunit
```

The production tests check Admin-only initialization, no operational history, all 84 planned meal cells, grocery exclusions, compatible package units/costs, 50-to-100 scaling, retained clinical references, edited-record preservation, older-package review and the production demo guard. The repeatability test exercises the full optional demo sequence twice. Neither test command is an instruction to seed a running website.

## Existing installations and normal releases

For a server transfer, restore the coherent database/protected-file set, preserve keys and existing accounts, and follow [turnover](turnover.md#existing-system-migration-sequence). Do not reseed as a substitute for restoration.

The normal [release script](../../backend/docker-release.sh) applies migrations and runs census catch-up. It does not run production or demo seeders and has no dependency on demo accounts or demo recipes. Adding missing references to an existing institution is an explicit maintenance operation; review the synchronization behavior above first. Existing demo records are not automatically removed by the production seeder.

If default seeding reports **Review the existing packaged Inventory items**, it found recognizable older production definitions using generic names and measured-unit prices. The full default seed rolls back; it does not add parallel package references or rewrite linked quantities. Repeating the command will not perform a conversion.

Review that database on a restored local copy first. For each affected item, establish the actual package, price per package and matching recipe amounts; review direct-item portions in editable menus and templates as well. Inventory's unit-correction workflow can update incompatible editable recipe/menu ingredients, but a catalog rename alone cannot determine new quantities or package yields. Preserve staff edits and frozen purchasing/service history. Apply the reviewed correction through an explicitly authorized maintenance operation, then rerun the default seed if missing references are still needed. Do not delete institutional records, change only unit labels, or switch to demo seeding to bypass the check. A new production-style installation uses the default seed directly on its clean database.

Related: [database maintenance](../developers/platform/database.md), [food-service data](../developers/food-service/data.md), [NCP data](../developers/ncp/data.md).
