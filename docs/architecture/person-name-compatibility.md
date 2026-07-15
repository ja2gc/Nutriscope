# Person-name compatibility architecture

## Status and scope

The July 15, 2026 additive migration separates names for accounts and patients while preserving the deployed `name` columns. It does not migrate food, recipe, ingredient, supplier, menu, shopping-list, report-title, physician, or signatory names.

The compatibility period has four goals:

1. New users and patients store a valid first and last name.
2. Existing names remain displayable without guessing how a Filipino or compound name should split.
3. Current web, mobile, API, report, and attribution consumers use one display contract.
4. Older consumers continue receiving the deprecated `name` key until a separately approved retirement release.

Route identifiers, public UUIDs, emails, roles, authorization, and historical snapshots are unchanged.

## Canonical display contract

`User` and `Patient` share `HasDisplayName`. The `display_name` accessor:

- normalizes and joins `first_name` and `last_name` with one space when both are nonblank;
- otherwise returns the stored legacy `name` exactly;
- never tokenizes, infers, or rearranges a name;
- is exposed deliberately through API resources rather than appended to every model serialization.

`SynchronizePersonName` is the one write adapter. When split fields participate in a write, it normalizes the final pair and synchronizes the deprecated column to `"{first_name} {last_name}"`. Hidden global mutators are intentionally avoided so unrelated saves cannot rewrite a legacy name.

Compound values are first-class. For example, `Maria Luisa` is a valid first name and `De la Cruz` is a valid last name. A legacy mononym remains intact in `name` and displays through the fallback until a person deliberately supplies a complete pair.

## Persistence and migration behavior

The release adds nullable `first_name` and `last_name` columns to `users` and `patients`. It retains the non-null `name` columns and the existing patient name index.

The separate data migration processes rows through `chunkById(500)`. Only a row whose two split columns are both null is backfilled, using:

```text
first_name = existing name
last_name = null
```

This preserves the legacy display value and includes soft-deleted users. Partially populated or already migrated rows are not overwritten. The migration performs direct database updates, so it does not invoke model observers or create audit events.

The schema rollback drops only the four additive columns. Because all deliberate compatibility writes keep `name` synchronized, display text survives rollback. Re-forwarding after a rollback conservatively places the complete legacy display back in `first_name` and leaves `last_name` null; it preserves text but cannot recover the semantic split that the rollback intentionally dropped.

## Validation matrix

| Operation | Accepted name state | Result |
|---|---|---|
| Create account | Nonblank `first_name` and `last_name`; deprecated `name` may also be present | Split pair wins; `name` is synchronized |
| Create patient | Nonblank `first_name` and `last_name`; deprecated `name` may also be present | Split pair wins; `name` is synchronized |
| Change a modern name | At least one split field is sent and both final values are complete, using the stored counterpart when unchanged | Pair is normalized; `name` is synchronized |
| Change a legacy incomplete name | Both final split values are required | Invalid or incomplete pair returns `422` |
| Send a changed deprecated `name` alone | Not sufficient for a deliberate rename | `422` requires the split pair |
| Make an unrelated edit to a legacy row | No split-name change, or unchanged deprecated `name` | Edit remains valid; legacy display is untouched |

Each split field is trimmed, repeated internal whitespace is collapsed, control characters are rejected, and the 255-character limit is enforced. There is no word-count rule and no surname inference.

## Public API and proxy compatibility

`UserResource` and `PatientResource` return:

```json
{
  "first_name": "Maria Luisa",
  "last_name": "De la Cruz",
  "display_name": "Maria Luisa De la Cruz",
  "name": "Maria Luisa De la Cruz"
}
```

For an incomplete legacy row, `display_name` and deprecated `name` contain the exact fallback display. The raw incomplete split fields remain visible so forms can distinguish a legacy row from a completed pair.

The deprecated `name` input key remains recognized. It can accompany the split contract and can be echoed unchanged during an unrelated legacy edit, but it cannot bypass the complete-pair rule for a new record or deliberate rename.

Next.js route handlers forward bodies and query strings unchanged. They do not reconstruct or split names. This preserves the Laravel validation boundary and the existing API paths. Patient list search still accepts the same query parameters.

## Consumer map

| Consumer | Current behavior | Compatibility boundary |
|---|---|---|
| Admin account forms | Separate required create fields and paired edit fields | Sends split values; deprecated output remains typed |
| Self-profile forms | Separate first/last inputs | Omits name fields for an unchanged legacy name |
| RND patient forms | Separate create/edit inputs | Unrelated edits to incomplete legacy rows remain possible |
| Web tables and headers | `personDisplayName()` | Raw `PersonNameLike.name` is read only inside the fallback adapter |
| Mobile login/profile | Shared `UserProfile` plus paired payload helper | `name` remains in the response DTO during compatibility |
| Nested actor/author/creator DTOs | Existing `name` key contains presentation text | Stable JSON key retained; backend value comes from `display_name` |
| Patient search | Grouped first, last, legacy name, physician, ward, and hospital-number predicates | Status remains outside the grouped OR predicate |
| Admin user ordering | Last name, first name, stable ID | Each sort expression falls back to legacy `name` |
| Eager-loaded people | Projections include `name`, `first_name`, and `last_name` | Display accessors do not trigger lazy loads |
| Reports | Current patient/staff rendering uses `display_name` | Stored historical names are not recomputed |
| Audit actor snapshots | New snapshots populate stable `actor.name` from `display_name` | Existing snapshot values remain immutable |
| Factories | Generate synchronized realistic compound Filipino names | `legacyName()` explicitly creates an unsplit fixture without guessing |
| Seeders | Explicit split account/patient names | Demo patients use hospital number, not name, as stable identity |

Static stale-consumer guards enforce these boundaries in Laravel, Next.js, and Expo. They also fail if unrelated operational entity types acquire person-name fields.

## Reports and historical data

Current patient menu-plan and NCP reports resolve patient display names at render time. Current accomplishment-report staff rows and future prepared-by/archive snapshots use user display names.

Historical `prepared_by_name`, report staff/signatory values, and audit `properties.actor.name` values remain exactly as stored. The migration does not rewrite report snapshots or audit JSON. This preserves the event-time identity that existed when the record was created.

## Seed and demo behavior

The account and patient factories emit nonblank split fields and a synchronized deprecated value. Their explicit `legacyName()` state is reserved for compatibility tests.

`AdminUserSeeder` uses stable email addresses and upgrades the seeded names without resetting established passwords. `PatientSeeder` uses hospital numbers `HN-2026-0042` and `HN-2026-0078`, retains patient IDs and public UUIDs across reruns, removes only the deterministic demo clinical graph, and leaves another patient with the same display name untouched.

Base/demo seed execution is wrapped with activity logging disabled while model events remain enabled. UUID creation therefore still runs, and setup does not produce anonymous audit noise.

## Deployment and rollback order

Forward deployment:

1. Back up the database and record the current migration state.
2. Run the additive schema migration.
3. Run the conservative backfill migration.
4. Deploy the compatibility backend that reads both contracts and dual-writes `name`.
5. Deploy the migrated Next.js and Expo consumers.
6. Verify legacy rows, current writes, reports, attribution, search, ordering, and seeders before considering retirement work.

Rollback:

1. Stop new writes or place the application in the normal deployment maintenance window.
2. Roll the application clients/backend back to the previous legacy-compatible version first.
3. Confirm recent compatibility writes have synchronized `name`.
4. Roll back the backfill marker and additive schema migrations.
5. Verify the retained `name` values before reopening writes.

The application must not run code that requires split columns after the schema has been rolled back. A later re-forward preserves display text but treats every rolled-back row as legacy incomplete data.

## Performance decisions

No new name index was added. MySQL `EXPLAIN` showed patient search using the existing `patients_status_idx` before applying the grouped text predicates. The leading-wildcard text search and expression-based fallback ordering cannot use an ordinary first/last B-tree effectively. Admin account volume is small, and deterministic user ordering performs a table scan plus filesort. Adding speculative indexes would increase write/migration cost without improving the measured plans.

Resource and report query-count tests prove that the extra projected fields add no per-row queries. The stable-ID tiebreaker preserves deterministic ordering.

## Verification evidence

The final gate used PHP 8.4, Laravel 13.11.2, MySQL, Sanctum 4.3.2, PHPUnit 12.5.26, Boost 2.4.8, and Pint 1.29.1.

- MySQL migrations passed forward, two-step rollback, and re-forward. The live legacy row remained `name = first_name = "Reset Test User"`, `last_name = null`; both tables regained nullable split columns.
- MySQL plans used `patients_status_idx` for filtered patient search; deterministic user ordering used the documented table scan/filesort. No speculative index was added.
- Frontend verification passed 57 files/195 tests, TypeScript, ESLint, and the Next.js production build of 91 pages.
- Mobile verification passed all 13 Node tests, TypeScript, and an Android Expo export of 3,241 modules with a 6.16 MB Hermes bundle; generated export output was removed.
- Report verification covers current patient/staff displays, real PDF response signatures, future prepared-by snapshots, historical snapshot immutability, and query-count stability.
- Seeder verification runs account and patient seeders twice, comparing hashes, IDs, UUIDs, deterministic keys, counts, unrelated rows, and audit-row count.
- The Windows link-escape security check now uses a directory-junction fallback when symlink privilege is unavailable, so the full suite no longer hides that boundary behind a platform skip.
- Final full Laravel verification passed 1,029 tests and 6,235 assertions on MySQL with `--fail-on-skipped`; no test was skipped.

## Retirement gate and trade-offs

Keeping both contracts costs four nullable columns, dual-write logic, compatibility DTO fields, and explicit stale-consumer allowlists. In return, it avoids destructive parsing, preserves rollback safety, and lets web/mobile/report deployments move in verified waves.

The legacy columns must not be removed in this release. Retirement requires a separately reviewed migration after every real legacy row has been deliberately split, all deprecated input/output consumers have been removed, search/order/report/audit fallbacks no longer depend on `name`, and fresh upgrade plus rollback verification passes again.
