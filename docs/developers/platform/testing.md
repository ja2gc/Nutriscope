# Testing

Verified against committed source: `d873ea2f3fec63a089bc70c6948f1611ae553d7b` plus the receipt-total isolation note in this working tree (2026-10-23).

Purpose: select checks and establish isolation before execution. Commands below are defined by the repository scripts or the indicated tools; establish isolation before running them.

## Isolation comes first

Read [phpunit.xml](../../../backend/phpunit.xml), [TestCase](../../../backend/tests/TestCase.php), [Composer scripts](../../../backend/composer.json), [Vitest config](../../../frontend/vitest.config.ts) and the actual target tests. The PHPUnit template uses MySQL/root on localhost and RefreshDatabase tests can destroy data. Its names/defaults do not prove safety; shell variables and cached config can override assumptions.

Use a separately created disposable MySQL database/account, synthetic records, separate files/cache, fake mail/HTTP and blocked external network. Verify effective resolved targets before framework initialization or destructive traits. Do not run tests on the deployed server. Never inspect non-example dotenv files through an agent or use production credentials. If isolation cannot be established, stop and record the blocker.

Run only one RefreshDatabase process against a disposable database at a time. Some migration-contract tests create/drop additional databases; a database-scoped test account intentionally cannot run those. Do not broaden its privileges to make a filtered feature run pass. Record exclusions and keep their verification separate.

## Commands once isolated

| Working directory | Command | Purpose |
|---|---|---|
| backend | php artisan test --filter=UsdaServiceTest | USDA conversion/cache test examples |
| backend | php artisan test | Unit and feature suites |
| backend | vendor/bin/pint --test | PHP formatting check |
| frontend | npm test | Existing Vitest suites |
| frontend | npx tsc --noEmit --incremental false | Separate type check required by build config |
| frontend | npm run lint | ESLint |
| frontend | npm run build | Declared Next build; may load env and fetch assets |
| mobile | npx tsc --noEmit | Type checking after matching dependencies are installed |

The committed mobile manifest has no test script. Some clinical frontend tests use node:test; Vitest alone is not proof that every suite ran. Inspect the chosen file/runner before executing it.

Focus regressions on formulas/rounding, authorization, workflow states, procurement/budget transactions, API consumers, private files and reports. Record exact commands/counts/failures/skips and distinguish baseline failures from regressions. Never weaken a test to obtain a passing result.

Related: [local setup](local-setup.md), [clinical](../ncp/README.md), [food service](../food-service/README.md), [operations](../operations/README.md).
