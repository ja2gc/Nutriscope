# Testing

Windows cached-configuration handling.

Purpose: select checks and establish isolation before execution. Commands below are defined by the repository scripts or the indicated tools; establish isolation before running them.

## Deployment gate

[deploy.yml](../../../.github/workflows/deploy.yml) runs the complete PHPUnit suite in the `backend_tests` job using PHP 8.4 and a disposable MySQL 8.4 service. Its synthetic database credentials and API placeholders are independent of production secrets. The job verifies the database identity and UTC clock before running `vendor/bin/phpunit`; the separate `frontend_typecheck` job checks web types. Deployment requires both jobs to pass before images are built or transferred.

The disposable MySQL account can create/drop the additional temporary schemas required by migration-contract tests. Those privileges apply only to the runner's service. Production, local demo data, private dotenv files and provider credentials are not test inputs.

## Isolation comes first

Read [phpunit.xml](../../../backend/phpunit.xml), [TestCase](../../../backend/tests/TestCase.php), [Composer scripts](../../../backend/composer.json), [Vitest config](../../../frontend/vitest.config.ts) and the actual target tests. The PHPUnit template uses MySQL/root on localhost and RefreshDatabase tests can destroy data. Its names/defaults do not prove safety; shell variables and cached config can override assumptions.

Use a separately created disposable MySQL database/account, synthetic records, separate files/cache, fake mail/HTTP and blocked external network. Verify effective resolved targets before framework initialization or destructive traits. Do not run tests on the deployed server. Never inspect non-example dotenv files through an agent or use production credentials. If isolation cannot be established, stop and record the blocker.

Keep the disposable MySQL server/session in UTC to match Laravel's `app.timezone`. Report-operation fixtures should set explicit `available_at` values rather than rely on a database-created timestamp. Preserve expected configuration contracts such as the private S3-compatible backup disk; use storage fakes in tests that exercise bytes rather than globally changing its driver. Route-inventory tests inspect the booted test application's routes without requiring development-tool endpoints.

On Windows, do not assume a drive-letter APP_CONFIG_CACHE value resolves as absolute. Inspect the application's resolved cached-config path and require configurationIsCached() before boot; fail closed when it is false. A backend-relative native path can avoid this issue. Verify both SELECT DATABASE() and CURRENT_USER() against the authorized disposable schema/account before RefreshDatabase. A pre-test identity guard should use the generated test configuration directly, without booting a second Laravel application and registering exception handlers outside PHPUnit's lifecycle.

Run only one RefreshDatabase process against a disposable database at a time. Some migration-contract tests create/drop additional databases; a database-scoped test account intentionally cannot run those. Do not broaden its privileges to make a filtered feature run pass. Record exclusions and keep their verification separate.

Procurement regression checks: run `PurchaseOrderTrailTest::test_deduction_guard_migration_preserves_legacy_duplicates_and_round_trips_exactly` on a separate disposable MySQL server where its test account may create/drop schemas. Verify that server's identity as well as its database/account before PHPUnit starts. Keep the ordinary scoped test account and the localhost demo database unchanged; temporary configuration/runners belong in ignored `backend/tmp/`. The migration test creates its own schema and removes it in `finally`.

Report generators return domain data only. HTML assertions against report views must also supply the branding, signatories, generation time and orientation that `ReportService::buildPdf` adds. `PurchaseOrderCompletionPatternTest` uses the real service's returned metadata and verifies PDF bytes and frozen branding, rather than rendering generator data alone or weakening the letterhead template.

## Commands once isolated

| Working directory | Command | Purpose |
|---|---|---|
| backend | php artisan test --filter=UsdaServiceTest | USDA conversion/cache test examples |
| backend | php artisan test | Unit and feature suites |
| backend | vendor/bin/pint --dirty --format agent | Required PHP formatting pass |
| frontend | npm test | Existing Vitest suites |
| frontend | npx tsc --noEmit --incremental false | Separate type check required by build config |
| frontend | npm run lint | ESLint |
| frontend | npm run build | Declared Next build; may load env and fetch assets |
| mobile | npx tsc --noEmit | Type checking after matching dependencies are installed |

The committed mobile manifest has no test script. Some clinical frontend tests use node:test; Vitest alone is not proof that every suite ran. Inspect the chosen file/runner before executing it.

Focus regressions on formulas/rounding, authorization, workflow states, procurement/budget transactions, API consumers, private files and reports. Record exact commands/counts/failures/skips and distinguish baseline failures from regressions. Never weaken a test to obtain a passing result.

Related: [local setup](local-setup.md), [clinical](../ncp/README.md), [food service](../food-service/README.md), [operations](../operations/README.md).
