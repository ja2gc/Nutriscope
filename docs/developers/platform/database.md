# Database

Purpose: find authoritative stored meaning and preserve existing records.

Start with [data semantics](data-semantics.md) to trace field ownership and consumers, then [NCP fields](../ncp/data.md) or [food-service fields](../food-service/data.md) for domain examples. Configuration variables have a separate [environment map](../../operations/environment.md).

- [Migrations](../../../backend/database/migrations) declare schema evolution; [Models](../../../backend/app/Models) define casts/relationships; [Enums](../../../backend/app/Enums) contain some persisted values.
- [HasPublicId](../../../backend/app/Models/Concerns/HasPublicId.php) separates public UUIDs from internal relation keys.
- [NcpRecord](../../../backend/app/Models/NcpRecord.php) links clinical children. A HasOne relationship alone is not a database uniqueness constraint.
- [Budget](../../../backend/app/Models/Budget.php) computes fiscal-year balance from ledger entries; [BudgetLedgerListener](../../../backend/app/Listeners/BudgetLedgerListener.php) records completion deductions.
- Inspect the configured database with authorized schema tools for installed tables, columns, indexes and constraints; use migrations/models to trace source ownership.

For a field or status change, check migrations, casts/fillables, validation, Resources, revision serializers, jobs, reports and both clients. Preserve decimals, identifiers and historical meaning. Prefer additive, forward-only changes; never rewrite applied migrations. Verify the actual migration state before deployment.

Use [factories](../../../backend/database/factories) and synthetic fixtures for tests. [DatabaseSeeder](../../../backend/database/seeders/DatabaseSeeder.php) initializes production reference data and one configured Admin without operational history. The optional [Demo seeders](../../../backend/database/seeders/Demo) create fictional development/training data and reject the production environment. [Initial data and seeding](../../operations/initial-data.md) owns the exact commands, account prerequisites, clinical reference ownership and rerun behavior.

Do not infer deployed constraints, duplicate prevalence or production compatibility from local source. Migration/restore commands require a separately approved target and recovery plan.

Related: [testing](testing.md), [clinical](../ncp/README.md), [food service](../food-service/README.md), [turnover](../../operations/turnover.md).
