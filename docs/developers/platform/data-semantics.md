# Data semantics and variable tracing

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: learn what data means before changing a field, formula, status or configuration. This is a navigation guide, not a second database schema or an exhaustive variable glossary.

## Pick the type of variable

| Kind | Authority and next guide |
|---|---|
| Environment/configuration setting | [Environment map](../operations/environment.md) → backend/config or Compose/build profile; effective overrides matter |
| Clinical measurement, prescription or workflow value | [NCP data](../ncp/data.md) → model, FormRequest, calculation context and persistence service |
| Operational quantity, price, population or snapshot | [Food-service data](../food-service/data.md) → calculation/lifecycle service and model casts |
| Account, role, notification or shared setting | [Shared feature owners](../shared/README.md) → actual route/authorization and model |
| Schema column, JSON shape or persisted status | [Migrations](../../../backend/database/migrations/), [Models](../../../backend/app/Models/), [Enums](../../../backend/app/Enums/), [database guide](database.md) |
| UI-only state, filter, cache key or formatted display | Actual frontend page/hook/service or mobile client, then corresponding backend contract; [web](web.md) |
| Report, revision or job payload | [Reports](../../../backend/app/Services/Reports/), [audit serializers](../../../backend/app/Services/Audit/Revisions/Serializers/), [Jobs](../../../backend/app/Jobs/), [operations](../operations/README.md) |

## Trace a field end to end

1. Locate its model/migration and exact name using rg. Determine internal key versus public UUID; read [HasPublicId](../../../backend/app/Models/Concerns/HasPublicId.php) and the Resource rather than assuming an API id is numeric.
2. Read every writer: FormRequest defaults/validation, controller, service, model hooks, listener/job, importer, seeder and migration backfill. A fillable field is not evidence it should be freely editable.
3. Classify it as input, calculated result, override, snapshot, historical value, transient cache or display-only state. Record units, precision/casts, nullable/absent/zero/false distinctions and valid transitions from source.
4. Trace API Resources → web service/types/Next handler → page and mobile consumers. PHP decimal casts may serialize as strings; TypeScript declarations alone don't establish runtime shape.
5. Check reporting, audit/revisions, queued payloads, files, cache invalidation and old records. A current catalog edit does not automatically update historical snapshots.
6. Find relevant tests/factories. Add synthetic regression cases when changing semantics, including null/zero, boundaries and historical shapes; follow [testing](testing.md).

For a new or changed semantic rule, add a short note to the owning domain's data guide: field/path, meaning/unit, writer, consumers, preserved history and tests. Investigate unresolved meaning before documenting it; never guess or copy a label as a business rule.

Stored statuses are not all enums, and allowed input values alone don't define legal transitions. Search controllers/services/events and reports using that value. Dates can represent a calendar service date or an instant; inspect timezone boundaries before converting them. Migration files declare intended history, not proof of production schema.

Full navigation aids: [schema reference](../../database-schema.md), [person-name compatibility](../../architecture/person-name-compatibility.md), [database guide](database.md). These must be checked against source for the specific change.
