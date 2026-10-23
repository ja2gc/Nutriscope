# Database

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: find authoritative stored meaning and preserve existing records.

Start with [data semantics](data-semantics.md) to trace field ownership and consumers, then [NCP fields](../ncp/data.md) or [food-service fields](../food-service/data.md) for domain examples. Configuration variables have a separate [environment map](../operations/environment.md).

- [Migrations](../../../backend/database/migrations) declare schema evolution; [Models](../../../backend/app/Models) define casts/relationships; [Enums](../../../backend/app/Enums) contain some persisted values.
- [HasPublicId](../../../backend/app/Models/Concerns/HasPublicId.php) separates public UUIDs from internal relation keys.
- [NcpRecord](../../../backend/app/Models/NcpRecord.php) links clinical children. A HasOne relationship alone is not a database uniqueness constraint.
- [Budget](../../../backend/app/Models/Budget.php) computes fiscal-year balance from ledger entries; [BudgetLedgerListener](../../../backend/app/Listeners/BudgetLedgerListener.php) records completion deductions.
- [Schema reference](../../database-schema.md) helps navigation; migrations/models and authorized actual schema evidence outrank prose.

For a field or status change, check migrations, casts/fillables, validation, Resources, revision serializers, jobs, reports and both clients. Preserve decimals, identifiers and historical meaning. Prefer additive, forward-only changes; never rewrite applied migrations. Verify the actual migration state before deployment.

Use [factories](../../../backend/database/factories) and synthetic fixtures for legacy-shape tests. [DatabaseSeeder](../../../backend/database/seeders/DatabaseSeeder.php) includes demo accounts and patients: it is not a clean production bootstrap.

Do not infer deployed constraints, duplicate prevalence or production compatibility from local source. Migration/restore commands require a separately approved target and recovery plan.

Related: [testing](testing.md), [clinical](../ncp/README.md), [food service](../food-service/README.md), [turnover](../../operations/turnover.md).
