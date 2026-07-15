# First-Name and Last-Name Migration Design

## Context

Nutriscope currently stores one required `name` column for both users and patients. The same field is used by authentication/profile screens, Admin account management, patient creation/editing, search, sorting, reports, audit actor snapshots, clinical attribution, announcements, notifications, budget attribution, factories, seeders, frontend types, and mobile profile/display contracts.

This is a separate coordinated project from the audit redesign. It is implemented first so the audit redesign can use one final `display_name` contract for future actor snapshots and subject labels.

## Goals

- Store `first_name` and `last_name` separately for users and patients.
- Preserve every existing display name exactly during migration.
- Provide one canonical `display_name` for presentation.
- Migrate web, mobile, APIs, reports, audit attribution, factories and seeders without breaking routes or consumers.
- Keep historical audit and report snapshots unchanged.
- Avoid guessing how Filipino compound names, mononyms, suffixes, titles, or multi-part given names should split.
- Retire the legacy `name` database columns only after every consumer is migrated and a later deployment wave proves they are no longer needed.

## Non-goals

- Automatic parsing of existing names.
- A separate middle-name or suffix data model in this phase.
- Splitting physician free text, supplier/business names, report-template signatories, recipe names, food names, menu names, or shopping-list names.
- Rewriting historical audit actor snapshots.
- Rewriting immutable report prepared-by snapshots.
- Changing route keys, UUIDs, authentication emails, roles, or authorization.

## Current compatibility surface

### Database and models

- `users.name` is non-null and is the canonical account display value.
- `patients.name` is non-null and indexed through `patients_name_idx`.
- `User` and `Patient` fillable/resource/search behavior expects `name`.
- Audit actor snapshots store a display value at `properties.actor.name`.
- Reports persist `parameters.prepared_by_name` and related attribution snapshots.

### Backend writers/readers

- Admin account create/update/reset flows;
- profile update and authentication responses;
- patient create/update/list/search and NCP resources;
- announcement/SOP/notification authors;
- budget/report creators;
- report browser labels and report templates;
- clinical attribution and last-action actors;
- audit presenter, sanitizer and actor filters;
- eager-load projections such as `id,uuid,name,role`;
- factories and all demo seeders.

### Frontend and mobile

- Admin user forms/table;
- profile form and top bar;
- patient creation/editing/list/header/assessment/diagnosis pages;
- reports and budget attribution;
- announcement/SOP author labels;
- audit actor labels;
- mobile profile edit/display;
- mobile reports, announcements and nested actor DTOs.

Nested presentation objects continue exposing a `name` key as a display label during and after migration. The database field migration does not rename `actor.name` or `author.name` DTO properties.

## Chosen data contract

Users and patients receive:

- `first_name`;
- `last_name`;
- computed `display_name`;
- temporary deprecated `name`, synchronized to `display_name` during compatibility.

Rules:

- New user and patient records require nonblank first and last names.
- Each field accepts compound values; no word-count assumption is made.
- Existing legacy records are backfilled conservatively: `first_name = existing name`, `last_name = null`.
- Existing display text therefore remains byte-for-byte equivalent.
- A legacy record may continue displaying its original value.
- When a user/patient name is next deliberately edited, the form requires valid first and last names and synchronizes the legacy `name` value.
- Unrelated edits must not fail solely because an untouched legacy record has `last_name = null`.
- `display_name` joins nonblank first/last fields with one space and falls back to the original legacy `name` when split fields are incomplete.
- Do not append computed accessors globally to arbitrary model serialization; API Resources own the public contract.

## Migration strategy

### Wave 1: additive schema

Create new migrations; never edit deployed migrations.

- Add nullable `first_name` and `last_name` to `users` and `patients`.
- Add indexes only after measuring actual search/order predicates.
- Keep `name` and `patients_name_idx` unchanged.
- Use a separate data migration to backfill through `chunkById` because rows are updated while iterating.
- Backfill soft-deleted users as well as active users.
- Make rollback reversible by dropping only the new columns/indexes; legacy `name` remains authoritative during rollback.

### Wave 2: compatibility models and APIs

- Add model-level `display_name` accessors.
- Add an explicit synchronizer/service for writes; avoid hidden broad mutators that can unexpectedly modify names during unrelated saves.
- Resources emit `first_name`, `last_name`, `display_name`, and deprecated `name = display_name`.
- During compatibility, existing account/profile/patient endpoints accept the old `name` shape; new web/mobile forms send first/last fields.
- First/last input takes precedence when both contracts are supplied.
- Preserve routes, UUIDs and Next.js proxies.
- Keep nested actor/creator/author `name` as display text unless a consumer genuinely needs split fields.

### Wave 3: migrate consumers

- Web and mobile forms use separate first/last inputs.
- Tables, headers, attribution, reports and snapshots use `display_name`.
- Search covers first name, last name and legacy display fallback inside one grouped predicate.
- Patient status and other filters must remain outside the grouped search ORs.
- User ordering becomes last name, first name, stable ID, with legacy fallback documented.
- Eager-load projections selecting `name` add the split fields required to compute display name.
- Future audit actor snapshots keep the stable `actor.name` JSON key but populate it from `display_name`.
- Historical `properties.actor.name` values are never rewritten.
- Future `prepared_by_name` snapshots use `display_name`; historical snapshot keys/values remain unchanged.

### Wave 4: later retirement gate

Do not drop legacy `name` in the additive release.

Retirement requires:

- no backend writer/read query depends on stored `name`;
- no frontend/mobile request depends on old name input;
- no report/export/search/order path requires stored `name`;
- all real legacy rows have been reviewed and split as desired;
- fresh, legacy-upgrade and rollback verification passes;
- a separately reviewed forward migration removes legacy columns/indexes.

## Validation and forms

- New account: First name and Last name required.
- Account edit: separate optional fields for routine edits; if either name field changes, validate and require both final values.
- Self-profile edit: same paired-change rule.
- New patient: First name and Last name required.
- Patient edit: same paired-change rule.
- Trim surrounding whitespace, collapse internal repeated whitespace, reject control characters, and enforce current safe maximum lengths.
- Do not infer surname from the last token.
- Preserve compound values such as `Maria Luisa`, `De la Cruz`, or a legacy mononym without destructive parsing.

## Search and sorting

Patient search currently risks allowing name/physician OR clauses to bypass the status filter. The migration must fix this by nesting all search alternatives in one grouped `where` closure.

Search covers:

- first name;
- last name;
- legacy name fallback during compatibility;
- existing allowed patient search fields such as physician/hospital reference, without changing privacy or authorization.

Sort users by normalized last name, then first name, then ID. Legacy records with no last name remain deterministic and display correctly. Patient ordering follows the product's current ordering unless explicitly changed by an existing screen contract; every order includes a stable ID tie-breaker.

## Audit interaction

- Existing audit actor snapshots remain immutable.
- New actor snapshots write `display_name` into the existing `name` snapshot key.
- Account-name changes audit `first_name` and `last_name` as safe account fields with before/after values.
- Patient-name changes remain clinical: Admin sees field names only, never values.
- Clinical attribution DTOs keep `actor.name` as presentation text.
- Audit actor search searches first/last fields while returning display labels.
- The actor filter remains available through the later audit redesign and gains paginated search.

## Report interaction

- Patient-facing reports render patient `display_name`.
- Prepared-by and creator snapshots use user `display_name` going forward.
- Historical reports keep stored `prepared_by_name` and other signatory snapshot values unchanged.
- Admin remains unable to access patient-specific reports.
- Shared RND access corrections are owned by the audit/history redesign because they concern report authorization, not name storage.

## Seeder and factory updates

Update:

- `UserFactory` and `PatientFactory` to create first/last fields and synchronized compatibility name;
- `AdminUserSeeder` with explicit first/last demo users;
- `PatientSeeder` with explicit first/last demo patients;
- patient cleanup identity from mutable name to stable `hospital_number`;
- any Food Service demo/report preparation that selects user names;
- tests/fixtures that manually create user or patient arrays.

Do not split unrelated `name` keys for foods, recipes, menus, suppliers, shopping lists, report-template signatories, physician free text, or organization roles.

The later audit demo seeder consumes `display_name` and named actors after this migration is complete.

## Mobile compatibility

- Mobile profile types and edit form migrate to first/last inputs.
- Authentication/user response continues to include deprecated `name` during compatibility.
- Mobile displays use `display_name` when full user objects provide it.
- Nested report/announcement/SOP author DTOs continue using `name` as a presentation label.
- No food-service entity `name` types are changed.
- Mobile typecheck/tests and login/profile flows are required before legacy input retirement.

## Error handling and compatibility

- Invalid paired name updates return normal validated `422` responses.
- Old clients continue to receive `name` until retirement.
- Compatibility writes keep legacy `name` synchronized so old reports/searches remain correct.
- Migration backfill never invokes application audit/model events.
- A failed schema/data migration rolls back without losing the legacy name.
- No existing account or patient becomes inaccessible because its name cannot be heuristically parsed.

## Blast radius and risk register

### Affected systems

The name migration crosses:

- users/patients schema, indexes, soft-deleted rows and backfill migrations;
- authentication/profile/Admin account and patient CRUD contracts;
- patient search, filtering and user ordering;
- reports, archived prepared-by values and clinical attribution;
- future audit actor snapshots and historical actor presentation;
- announcement/SOP/notification/budget creator DTOs;
- frontend forms, types, headers, tables and reports;
- mobile login/profile/report/author contracts;
- factories, seeders, fixtures and demo cleanup logic.

### Ranked risks and controls

| Risk | Impact | Required control and verification |
|---|---|---|
| Heuristic splitting corrupts Filipino compound/legacy names | Critical | Never parse tokens; backfill exact legacy text into `first_name`, leave `last_name` null; byte-for-byte display-preservation tests |
| Dropping `name` breaks old web/mobile/API/report consumers | Critical | Additive compatibility waves; continue accepting/returning deprecated `name`; stale-consumer scan; legacy input/output tests; no drop in this release |
| Dual-write drift makes `name` differ from `display_name` | High | One explicit synchronization service used by all user/patient writers; paired-field contract tests and database assertions |
| Legacy record cannot be edited because last name is null | High | Require both fields only when name is deliberately changed; unrelated-edit regression tests for user/profile/patient |
| Incomplete eager-load projections produce blank/truncated display names | High | Inventory every `select(...name...)`; include first/last/legacy fields or central projection helper; resource/report/attribution tests |
| Patient search OR clauses bypass status/privacy filters | High | Group all search alternatives in one closure; status + first/last/physician/hospital-number regression tests |
| Name changes leak patient identity into Admin audit output | Critical | Patient name changes remain Clinical field-name-only; privacy sentinels across storage/API/export/UI |
| Historical audit/report snapshots are rewritten or lose original attribution | High | Never rewrite `properties.actor.name` or `prepared_by_name`; legacy snapshot fixtures before/after migration |
| Mobile profile/login breaks after backend contract changes | High | Preserve deprecated `name`; migrate mobile in same compatibility wave; mobile typecheck/tests/login/profile verification |
| Reports overflow or format differently with compound names | Medium | Render tests for patient, NCP, menu-plan and accomplishment reports using long compound names |
| New sort/index strategy degrades user/patient listing | Medium | Deterministic last/first/ID ordering tests, MySQL explain/index verification and pagination stability tests |
| Seeder reruns duplicate patients after name changes | Medium | Patient demo cleanup uses stable `hospital_number`; seeder idempotence and fresh-seed smoke tests |
| Migration misses soft-deleted or high-ID rows | High | `withTrashed`, `chunkById`, pre/post row counts, boundary fixtures and legacy-upgrade tests |
| Rollback loses names entered after additive migration | High | Legacy `name` synchronized on every compatibility write; rollback drops only new columns; rollback/re-forward data-preservation test |
| Unrelated business/entity `name` fields are accidentally migrated | Medium | Typed model/file inventory and stale scan distinguishing User/Patient from food, recipe, supplier, menu, physician and signatory names |

### Rollout and rollback boundaries

- Schema addition and conservative backfill are separate migrations.
- Compatibility model/resources deploy before new web/mobile inputs become mandatory.
- Backend continues accepting the old input until web and mobile migrations are verified.
- Legacy `name` remains synchronized and indexed through this release, making application rollback safe.
- Historical audit/report snapshots remain untouched and require no data migration.
- Seeders/factories migrate only after compatibility models exist.
- The legacy-column retirement is a separate future plan and deployment, not a final task hidden in this plan.
- Each implementation-plan task must state affected API consumers, rollback behavior and focused backend/frontend/mobile verification.

## Testing requirements

### Migration

- fresh migration;
- legacy database upgrade;
- soft-deleted user backfill;
- exact display preservation;
- compound and single-name preservation;
- chunked backfill completeness;
- rollback/re-forward;
- no migration-generated audit events.

### Backend

- new account/patient first/last validation;
- paired name edit validation;
- unrelated legacy-record edit remains allowed;
- compatibility `name` input/output;
- resource contract with first, last, display and deprecated name;
- profile/auth responses;
- grouped patient search plus status-filter regression;
- user ordering;
- reports and prepared-by snapshots;
- audit actor snapshot and deleted-user fallback;
- clinical attribution;
- announcement/SOP/notification/budget creator display;
- RND shared-access behavior unchanged;
- seeder idempotence through stable patient identity.

### Frontend

- Admin user create/edit forms;
- self-profile form;
- patient create/edit forms;
- table/header/display-name rendering;
- validation messages and focus behavior;
- reports, budget and audit actor labels;
- TypeScript contracts, tests, typecheck, lint and build.

### Mobile

- login response compatibility;
- profile first/last edit and display;
- nested author/report labels;
- typecheck/tests and build verification used by the repository.

### Full verification

- backend full suite;
- frontend full test/typecheck/lint/build;
- mobile verification;
- migrations/fresh seed;
- privacy sentinels;
- route/proxy compatibility;
- stale-reference scan distinguishing human names from food/business/entity `name` fields.

## Acceptance criteria

- New users and patients are created with separate first/last names.
- Existing names display exactly as before without heuristic splitting.
- Unrelated edits do not lock legacy records.
- Web/mobile/API/report/audit consumers use one coherent display-name contract.
- Historical audit/report snapshots remain unchanged.
- Patient name changes remain clinically private to Admin.
- Search and status filtering are correct and deterministic.
- Seeders/factories produce realistic split-name data and remain idempotent.
- No unrelated entity `name` field is migrated.
- Legacy `name` columns remain until a later verified retirement wave.
