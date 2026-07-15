# Audit Oversight and Historical Record Redesign

## Status and relationship to prior work

This specification revises the audit-log and audit-trail implementation delivered by `docs/superpowers/plan/2026-07-11-audit-logs-and-trails-revision.md`. It does not discard the existing privacy, immutability, retention, route-coverage, or contextual-trail foundations. It corrects the information architecture, removes stale compatibility behavior, makes safe operational changes understandable, and adds bounded historical views for complex nonclinical records.

The coordinated first-name/last-name migration is specified separately. It must be implemented first so future audit actor snapshots use the final `display_name` contract without rewriting historical snapshots.

## Confirmed owner decisions

- Admin remains the only global audit-log viewer.
- Admin sees the patient's `display_name` as the sole patient identity field on patient-linked audit events. Admin never sees other patient identity/demographic fields, clinical values, clinical file contents, patient-specific report contents, AI prompts, or AI outputs.
- Clinical events show patient display name, actual actor, timestamp, record type, stable pseudonymous NCP reference, and changed field names only.
- Every active RND can view and edit every patient/NCP. `rnd_user_id` and report attribution fields record who created work; they are not ownership or authorization gates.
- Admin budget access remains read-only. No approve, reject, flag, or mandatory review workflow is added.
- Budget-ledger approval and reversal behavior remains outside this design until separately confirmed. Existing immutable entries remain unchanged.
- Audit/report export remains disabled by default and Admin-only if enabled later.
- Scheduled retention deletion remains disabled by default and controlled by the existing audited Admin confirmation flow.
- Temporary IP blocking remains removed. Account activation/deactivation remains supported.
- Operational and shared-library values may be visible because they are not patient-specific.
- Destructive and corrective actions require a reason: deletion, reversal, price correction, post-approval correction, and correction of ordered/received records.
- Routine edits may have an optional reason.
- Simple changes stay in a drawer. Whole structured records use a historical read-only page.
- Base seeders must not create anonymous/noisy audit events. Demo audit history must be deliberate and truthful.

## Current system: what is stored

The audit store is Spatie's `activity_log` table through `AuditActivity`, `AuditLogger`, the `AuditsChanges` model concern, explicit domain event writers, and security deduplication.

Each structured event can store:

- canonical action;
- category: `security`, `clinical`, or `operations`;
- domain: `accounts`, `patients`, `ncp`, `reports`, `budget`, `procurement`, `food_service`, or `system`;
- outcome: `success`, `failure`, or `blocked`;
- severity: `info`, `notice`, `warning`, or `critical`;
- event timestamp and immutable public UUID;
- actual actor snapshot containing public UUID, display name, role, and actor kind;
- subject model type, internal key, and public UUID where available;
- resolved root context model type, internal key, and public UUID where available;
- sanitized detail metadata;
- changed-field names;
- selected old/new values for automatically audited nonclinical models;
- sanitized request IP, query-free URL, and user agent for writers that enable request metadata;
- clinical root patient/NCP correlation keys;
- historical NCP creator attribution in `audit_owner_id`.

Clinical model logging redacts old/new values before persistence and retains only field names plus safe status/type metadata. The sanitizer rejects PHI and clinical-value keys, credentials, secrets, tokens, verification codes, request bodies, arbitrary payloads, OCR/file contents, report snapshots, AI prompts, AI responses, and AI outputs.

## Current system: what the Admin UI displays

The list currently shows:

- time;
- action label;
- actor name;
- generic subject/context labels;
- outcome;
- severity;
- generic summary.

The drawer currently shows:

- event public UUID;
- summary;
- timestamp, category, and domain;
- actor name, role, kind, and public UUID;
- generic subject/context type, label, and public UUID;
- outcome and severity;
- a narrow detail allow-list;
- a narrow before/after allow-list.

Raw JSON, model class names, internal numeric identifiers, arbitrary properties, patient identity, and clinical values are not exposed by the current UI. The target design adds only the approved patient display-name exception defined below.

The current summary is generated as `action label + generic entity label`. This produces summaries such as `Updated intervention`, `Created assessment`, and `Updated audit event`. The drawer section named **Safe request metadata** renders event details rather than request metadata. Stored operational details are often discarded by the presenter, leaving `Not recorded` or no field changes.

## Current event inventory

| Action | Current source and meaning | Current display limitation |
|---|---|---|
| `created` | Automatic patient/NCP/assessment/diagnosis/intervention/meal-plan/monitoring/screening-document events; automatic food-service item/recipe/menu/meal-service/PO/shopping-list events; explicit account, supplier, RND Food Library, RND recipe, announcement, budget, shopping-list item, menu-template and related creation | Generic subject; inconsistent initial values; some business actions also emit a duplicate `created` event |
| `updated` | Automatic clinical and selected operational models; explicit accounts, suppliers, catalogs, recipes, announcements, shopping lists, menu templates, report branding/templates | Unknown legacy actions also become `updated`; many explicit writers provide field names without old/new values |
| `deleted` | Clinical records/documents, accounts, reports, suppliers, catalogs, recipes, menus, shopping lists, POs and attachments | Usually no useful final pre-delete snapshot |
| `viewed` | Patient chart, screening document, live/archived report | Generic summary; safe route/report context often absent |
| `downloaded` | Screening document and report | Document/report content correctly excluded; reference remains vague |
| `exported` | Admin audit CSV export only | Export is disabled, but action remains globally visible in filter metadata |
| `approved` | AI diagnosis approval and shopping-list conversion/PO approval | Often paired with generic creation; procurement context is thin |
| `ordered` | PO transition to ordered | Selected status fields only |
| `received` | Whole-PO or vendor-group receiving | Vendor/receipt/variance context is insufficient |
| `reversed` | Meal-service completion reversal | Does not represent a budget-ledger reversal |
| `archived` | PO or report archive | Generic subject/status |
| `adjusted` | Budget setup/ledger effects and meal-service population correction | Budget amounts, reason, reference and resulting balance are not fully presented |
| `uploaded` | Screening document or PO attachment | Usually duplicates screening-document creation |
| `generated` | AI diagnosis suggestion, meal plan, suggested shopping list, report, accomplishment report | May duplicate created state; generated structure is not inspectable |
| `completed` | Meal service, PO lifecycle, audit pruning | Different workflows share one generic label |
| `price_corrected` | PO line price correction | Reason and complete before/after correction are not consistently displayed |
| `profile_changed` | User self-profile update | Changed fields may display `Not recorded` values |
| `settings_changed` | Retention toggle, AI limits, food-service per-head/day limit | Per-head/day old/new values are stored under keys the presenter does not expose |
| `login_succeeded` | Successful login | Actor/outcome only; platform context may be stored but hidden |
| `login_failed` | Invalid credential, inactive account, or platform rejection | Account/platform/reason context is incomplete by design and presentation |
| `authentication_failed` | Protected route returned 401 | Route/status/recurrence, no semantic subject |
| `logout` | User logout | No semantic subject/context |
| `password_changed` | User changed own password | Credential data correctly excluded; subject context weak |
| `password_reset` | Admin reset or password-reset completion | Some flows have user subject, others remain generic |
| `recovery_email_changed` | Recovery email changed | Email correctly excluded; account context should be clearer |
| `recovery_email_verified` | Recovery email verified | Verification code and email correctly excluded |
| `rate_limit_exceeded` | Limiter returned 429 | Limiter/route/status/retry/recurrence supported; stored IP is not presented |
| `authorization_denied` | Protected route returned 403 | Route and actor, but affected business subject is absent |
| `audit_log_viewed` | Admin listed audit logs, deduplicated per review window | No subject/context; should say which oversight surface was reviewed |
| `account_blocked` | Enum/filter option only | No active writer emits it |
| `account_unblocked` | Enum/filter option only | No active writer emits it |

## Current automatic model coverage

Clinical field-name-only model events cover:

- Patient;
- NCP record;
- Assessment;
- Diagnosis;
- Intervention;
- Meal plan;
- Monitoring;
- Screening document.

Nonclinical automatic model events cover:

- FSS item catalog;
- Food-service recipe;
- Meal-preparation log;
- Menu cycle;
- Purchase order;
- Shopping list.

RND Food Library items and RND meal-planning recipes are currently written explicitly as `operations / food_service`, even though repository navigation and seeders explicitly identify them as NCP/Nutrition Care resources. FSS items and food-service recipes are correctly hospital-wide Food Service Operations resources.

## Current contextual trails

One structured `AuditEventDto` and one `AuditTrail` component serve:

- patient activity;
- NCP-cycle activity;
- purchase-order activity;
- budget activity;
- report activity.

Contextual trails are newest-first and cursor-paginated. Clinical trails are available to active RNDs and remain field-name-only at the audit-event level. Admin global review uses offset pagination.

## Confirmed gaps and stale behavior

The redesign must treat stale-feature discovery as a contract task, not assume Domain is the only outdated element.

### Information architecture

- Top-level tabs use retention/privacy categories rather than modules users recognize.
- `Operations` misleadingly contains reports, budget, procurement, food service, settings, and some account/system activity.
- A second global Domain dropdown duplicates navigation and exposes implementation taxonomy.
- Reports have no top-level view.
- RND Food Library and RND recipes are classified as Food Service despite belonging to Nutrition Care.

### Presentation

- Summaries are generic concatenations rather than factual sentences.
- Subject/context labels lack safe human-readable references.
- Security and system events commonly show no subject/context.
- The request-metadata heading is inaccurate.
- Safe stored operational values are discarded.
- Created events do not consistently show initial values.
- Deleted events do not consistently preserve final safe values.
- Complex records cannot be inspected as they existed at event time.
- Opening the current live page cannot prove historical state after later edits.

### Taxonomy and compatibility

- Null legacy categories are presented and queried as Operations.
- Unknown legacy action strings are presented and filtered as Updated.
- `AccountBlocked` and `AccountUnblocked` are exposed but unused.
- Account deactivation is logged as generic Updated or Deleted.
- Disabled export remains a generic filter action even when unavailable.
- Category/action metadata is globally hard-coded rather than module- and capability-aware.
- Retired Inventory remains in resolver/presenter compatibility paths even though active inventory auditing was removed.
- Automatic CRUD plus explicit business events can duplicate one user action.
- Generic `recordMutation()` always assigns Operations, so callers must not use it for clinical events and must carry the correct module/domain explicitly.

### Budget

- New fiscal-year allocation records `fiscal_year` and `allocated_amount`, but the presenter hides them.
- `per_head_day_limit` lives in Food Service Settings, not the budget row; its old/new values are recorded under non-presented keys.
- Manual ledger entries record amount/type/source but presentation omits reason, reference, and resulting balance.
- PO deduction events lack a useful balance-before/balance-after explanation.
- No new ledger approval or reversal workflow is authorized by this design.

### Authorization discovered during stale scan

- `ReportBrowser` still restricts demographic, patient-menu-plan, and NCP-summary browse sources through `rnd_user_id`.
- Archived clinical report authorization still uses `audit_owner_id` or report `user_id` as an ownership gate.
- These gates contradict the confirmed shared-RND rule. Attribution fields must not authorize access.
- Admin report allow-lists and clinical privacy restrictions remain unchanged.

### Seed/demo accuracy

- `DatabaseSeeder` does not mute model events.
- Audited model creation during normal seeding can generate anonymous events with seeding-time timestamps and little business context.
- There is no deliberate, idempotent, local/demo-only audit scenario seeder covering all modules and display modes.

## Goals

- Make every visible event answer who, what, where, when, outcome, and safe change context.
- Use modules for Admin navigation while retaining category for privacy/retention.
- Show safe before/after values for nonpatient records.
- Preserve final safe values for deleted nonpatient records.
- Keep clinical audit values out of storage and Admin responses.
- Provide historical read-only views for whole structured nonclinical records.
- Remove or explicitly quarantine stale taxonomy, fallback behavior, compatibility code, and dead filters.
- Preserve existing routes/proxies until coordinated consumers migrate and tests pass.
- Keep audit export and retention deletion disabled by default.
- Produce credible demo data without anonymous seed noise.

## Out of scope

- Clinical old/new values in audit events or Admin UI.
- Patient identity in Admin audit logs beyond the explicitly approved patient `display_name`.
- Historical read-only clinical record pages for Admin.
- Raw JSON display.
- Full file/PDF/OCR contents in audit storage.
- AI prompts or outputs in audit storage.
- A new budget approval, rejection, flag, or reversal workflow.
- Temporary IP blocking.
- External append-only sink, hash chain, or integrity export.
- Re-identification workflow.
- Per-category retention editing UI.

## Chosen information architecture

Introduce a canonical, indexed audit module distinct from category and domain:

- `security_administration`;
- `nutrition_care`;
- `food_service_operations`;
- `reports`.

Admin tabs are:

1. All Activity;
2. Security & Administration;
3. Nutrition Care;
4. Food Service Operations;
5. Reports.

Module mapping:

| Module | Included sources |
|---|---|
| Security & Administration | authentication, authorization, rate limiting, account administration, audit oversight, retention/pruning, AI limits, announcements, SOPs, system settings |
| Nutrition Care | patients, NCP cycles, assessments, diagnoses, interventions, monitoring, patient meal plans, screening documents, RND Food Library, USDA imports, RND clinical recipe library |
| Food Service Operations | FSS catalog, FSS recipes, menu cycles, hospital weekly menus, meal service/population, suppliers, shopping lists, procurement, POs, receiving, budget and ledger |
| Reports | clinical and nonclinical report lifecycle, report archive, report branding and templates |

Category remains the privacy and retention axis. A Nutrition Care Food Library event may be category Operations because it contains no patient information, while a patient meal-plan event is category Clinical. Both appear in Nutrition Care.

Domain remains an internal query/context axis. Add a dedicated `nutrition_library` domain so RND Food Library/recipe events no longer masquerade as Food Service. Remove Domain from the normal Admin filter surface.

Contextual subfilters are module-specific:

- Nutrition Care: Food Library, Patients/NCP;
- Food Service Operations: Catalog, Menus, Procurement, Budget;
- Reports: report type;
- Security & Administration: Authentication, Accounts, Audit Oversight, Settings.

Action filters are returned per selected module and active capability. Dead actions and disabled-only capabilities are not shown.

## Event sentence and subject contract

Every event DTO must provide:

- module;
- category and internal domain;
- factual sentence summary;
- actor snapshot;
- action;
- outcome/severity;
- timestamp;
- safe subject descriptor;
- safe root/context descriptor;
- optional reason;
- typed details;
- typed field changes;
- optional historical-view descriptor;
- optional current-record URL when authorized and the record still exists.

Patient-linked events additionally provide one narrowly typed patient identity object containing only `display_name`. They retain the stable pseudonymous NCP reference. No patient public/internal ID, hospital number, date of birth, sex, address, contact, ward, physician, diagnosis, admission data, screening result, risk value, or other demographic/clinical field is part of that identity object.

Summaries use event-specific formatters, never one generic concatenation. Examples:

- `Maria Santos changed Brown Rice serving size from 100 g to 150 g.`
- `Juan Dela Cruz changed estimated population from 140 to 160 for Shopping List SL-2026-0715.`
- `Budget ledger added a ₱24,500 PO deduction for FY 2026 from PO-2026-0142.`
- `Maria Santos updated Energy Target and Protein Target in NCP NCP-8D3A; values hidden.`
- `Anonymous login failed through the Admin web portal.`
- `System completed audit pruning; 1,245 expired operations events were removed.`

Security/system events without an Eloquent subject receive a semantic subject such as `Admin web login`, `Protected route`, `Audit oversight`, `Retention setting`, or `Audit pruning`. The list never says `No subject or context` when the writer can describe the workflow safely.

## Value-display rules

### Created

Show the initial safe state. There is no artificial Before column. Examples include food nutrition fields, recipe ingredients/yield, fiscal-year allocation, supplier details, menu dates/status, or account role/active state.

### Imported

Add the canonical `imported` action. Show source, safe source identifier, importer, timestamp, and curated resulting fields. Never store or display the raw USDA response.

### Updated

Show changed fields only with typed before/after values. Avoid unrelated unchanged fields.

### Deleted

Show the final safe state immediately before deletion plus required reason. Deleted complex records remain available through their audited historical view until retention removes the event/version.

### Lifecycle events

Show state transition, key totals/counts, actor/system actor, linked safe reference, and reason when corrective/destructive. Use business actions such as ordered, received, approved, completed, reversed, archived, and price corrected instead of generic Updated.

### Clinical events

Show patient display name, stable pseudonymous NCP reference, and changed field names only. Both old and new clinical values remain null/redacted. A deletion may show which clinical fields were present, but never their contents. A patient-name change still does not show the previous/new name as a field diff; the event subject shows the approved patient display-name snapshot only.

## Simple drawer versus historical page

### Drawer-only events

Use the drawer when the event is understandable as a bounded list of fields:

- food nutrient/unit changes;
- ingredient quantity;
- recipe serving count when structure did not change;
- population count;
- price;
- status;
- fiscal-year amount;
- per-head/day limit;
- account role/active state;
- retention/system setting.

### Historical read-only page

Use a dedicated historical page when an entire structured record was created, structurally changed, deleted, archived, approved, ordered, received, or completed:

- RND recipe;
- FSS recipe;
- menu cycle/weekly hospital menu;
- shopping list;
- purchase order including vendor groups and line items;
- receiving state;
- complex budget/fiscal-year history where the ledger context is needed;
- other explicitly allow-listed nonclinical structured records.

Actions are labeled:

- View created version;
- View audited changes;
- View deleted version;
- View archived version;
- View current record, separately, only when it exists and the viewer is authorized.

The default historical update view renders the After version with added/changed/removed sections highlighted and provides a Before/After toggle. It reuses existing domain components in read-only mode and follows the current design system, font, spacing, colors, focus treatment, and responsive behavior.

## Historical version storage

Use a separate append-only operational revision store linked one-to-one to the audit event. It contains:

- public revision UUID;
- audit-event reference;
- module/domain;
- allow-listed subject type and public ID;
- action and serializer schema version;
- bounded typed `before` snapshot when applicable;
- bounded typed `after` snapshot when applicable;
- timestamp.

Rules:

- Only explicit serializers may write snapshots.
- Patient, NCP, assessment, diagnosis, intervention, monitoring, patient meal plan, screening document, and patient-specific report types are rejected at the revision boundary.
- The approved patient display-name snapshot lives on the parent audit event, not inside operational revision JSON.
- No arbitrary model serialization, request payload, raw JSON response, file data, report contents, prompts, or outputs.
- API presenters return typed historical DTOs, never raw stored JSON.
- Revision payloads have per-type size caps and serializer-version tests.
- Revision rows are immutable and pruned with their parent audit event under the same category retention/legal-hold rules.
- Deleting/pruning an event cannot leave an orphaned revision.
- Historical routes use public UUIDs and Admin authorization. Existing domain page authorization remains intact for current-record links.

## Canonical event policy and duplicate removal

One user intent produces one primary business event whenever a meaningful action exists:

- screening-document upload emits Uploaded, not Created plus Uploaded;
- approved AI diagnosis emits Approved, not Created plus Approved;
- generated meal plan emits Generated, not Created plus Generated;
- shopping-list conversion emits Approved, not generic Created noise;
- receiving emits Received, not unrelated child Updated events;
- structural menu update emits one parent Updated event with line-level diff inside its revision.

Generic Created/Updated/Deleted remains for ordinary direct CRUD without a better business action. Related financial/system side effects remain separate when they represent distinct accountable actions, such as PO Completed and Budget PO Deduction.

## Change-reason policy

Require a bounded, validated reason for:

- deletion;
- reversal;
- price correction;
- correction after approval;
- correction to ordered or received records;
- manual financial correction.

Reason is optional for routine draft edits. The UI provides a purpose-specific field/modal; audit writers receive only validated reason text. Do not accept arbitrary request bodies as audit context.

## Budget audit design

Budget appears under Food Service Operations with a Budget subfilter and contextual budget trail.

### New fiscal year

Display:

- fiscal year;
- allocated amount;
- creator;
- creation time;
- initial remaining balance;
- any open-execution POs re-evaluated as a result, summarized as a count with links where authorized.

### Budget per head per day

`per_head_day_limit` remains a Food Service Setting. Display:

- previous limit;
- new limit;
- actor;
- timestamp;
- optional routine-change reason.

### Manual ledger entry

Display:

- fiscal year;
- addition or deduction;
- amount and signed amount;
- required reason;
- optional external reference;
- balance before and after;
- actor and timestamp.

### PO deduction

Display:

- fiscal year;
- PO public reference/number;
- deduction amount;
- balance before and after;
- system actor;
- timestamp.

Ledger entries remain immutable. This design does not add approval/rejection or a new reversal workflow. If reversal is approved later, it must be a new linked entry that preserves the original and records its reason.

## RND Food Library and recipe audit design

RND Food Library and RND recipes move to Nutrition Care, not Food Service Operations.

Food Library created/imported/updated/deleted events may show:

- food name;
- source and safe external identifier;
- serving size/unit;
- calories and supported nutrient values;
- category and readiness/active flags;
- creator/importer;
- timestamp;
- before/after values or final deletion snapshot.

Recipe history may show:

- recipe name/category;
- servings/yield;
- ingredient names, quantities, and units;
- calculated nonpatient nutrition;
- calculated cost where applicable;
- added, changed, and removed ingredients;
- historical read-only recipe version for structural changes.

Once a library item/recipe is associated with a specific patient's plan, the event is Clinical. Admin then sees patient display name, changed field names and pseudonymous NCP context, but no selected food, recipe, quantity, prescription or other plan content.

## Food Service Operations historical details

The drawer and historical pages must support:

- FSS catalog values, units, supplier, price and active state;
- FSS recipe ingredients, quantities, servings and cost;
- weekly menu dates, meals, recipes/items, servings, totals and status;
- meal-service planned/served population, variance, shortfall and total value;
- shopping-list period, population, quantities, totals, coverage and status;
- PO vendor groups, line quantities/prices/totals, attachments as metadata only, status and lifecycle;
- receiving quantities, price evidence metadata, variances and completion state;
- budget and ledger details defined above.

A parent correction that cascades through many lines produces one parent event with a summary count and expandable line diff. Example: changing estimated population from 140 to 160 after conversion can show linked shopping list, linked draft PO, 12 recalculated lines, total before/after, actor, reason and timestamp. Do not emit twenty vague child Updated rows.

Corrections must respect domain locking. An ordered/received/completed record is not silently mutated merely to improve audit display.

## Legacy and stale-data handling

- Add an explicit `legacy_unclassified` presentation state instead of defaulting null category/domain/module values.
- Do not translate unknown action strings to Updated.
- Deterministically backfill module/domain only when source type, route metadata, and existing domain prove classification.
- Reclassify legacy RND `FoodItem` and `Recipe` events from Food Service to Nutrition Care when the model type is deterministic.
- Preserve ambiguous rows as Legacy/Unclassified with their original sanitized action label.
- Wire `is_active: true -> false` to Account Blocked and `is_active: false -> true` to Account Unblocked. Account deletion remains Deleted. Do not keep dead filter cases.
- Remove retired Inventory from active writer/filter/presenter paths after verifying it is needed only for legacy rendering. If legacy Inventory rows exist, isolate their read-only label in a legacy adapter rather than active context resolution.
- Hide disabled export actions/capabilities from normal filters while retaining future-compatible backend support.
- Replace category-action metadata with module-aware active action metadata.
- Rename the drawer details section according to its actual content; show sanitized request metadata in a separate section only when it materially helps security review.

## Shared-RND compatibility correction

Before report audit/history work proceeds:

- remove `rnd_user_id` predicates from RND demographic, patient-menu-plan, and NCP-summary browse queries;
- replace archived clinical-report `audit_owner_id` and `user_id` ownership gates with role/policy-based RND access to the related NCP context;
- retain `audit_owner_id`, `user_id`, and prepared-by fields as attribution only;
- preserve Admin report type allow-lists and prevent Admin access to patient-specific reports/contents;
- permit patient display name on the audit metadata for a patient-specific report event without granting Admin access to the report or its parameters/content;
- add RND A/RND B browse, view, preview, download, delete-as-authorized, and report-trail tests as applicable to the existing product rules.

## Authorization and privacy

- Admin can view global metadata and nonpatient operational values.
- Admin can view patient `display_name` on patient-linked audit events but cannot view other patient identity/demographics, patient-specific report contents, clinical values, clinical snapshots, files, OCR, prompts or outputs.
- Active RND users share clinical record access under existing role policies; audit attribution always uses the actual actor.
- FSS access remains limited to authorized operational areas and FSS report types.
- Historical operational pages are Admin read-only from global audit oversight. Current-record links follow the current record's normal authorization.
- Every list/detail/history/filter endpoint is policy-authorized.
- Subject/context labels and reasons are escaped and sanitized.

## Retention and immutability

Existing fixed periods remain:

- Security: 365 days;
- Clinical: 2,190 days;
- Operations: 1,095 days;
- Legacy: 90 days.

The new module does not change retention; category controls retention. Historical revision rows follow the parent event. Legal holds protect both. Scheduled deletion remains OFF until explicitly confirmed through the existing Admin control. Audit/revision updates and deletes remain blocked outside reviewed migration/pruning boundaries.

### Minimal patient identity snapshot

- Add one nullable `patient_display_name_snapshot` text field to the audit event model.
- Encrypt the field through Laravel's encrypted cast; never index, search, sort or filter by its ciphertext.
- Populate it from `Patient::display_name` for future patient-linked clinical/report events.
- Backfill existing patient-linked clinical/report events from the currently related patient when that patient still exists. Events without a resolvable patient retain only their pseudonymous NCP reference.
- The public presenter exposes it only through authorized Admin audit and authorized RND contextual-trail DTOs.
- Do not copy it into arbitrary details, request metadata, logs, metrics, revision JSON or URLs.
- Patient-name updates do not expose old/new name values; later events capture the then-current display name.
- It follows Clinical retention and legal hold. Encryption-key backup/rotation procedures must preserve decryptability for retained rows.
- Audit export remains disabled. Any future export enablement must separately decide whether this identity field is included; default export serialization omits it.

## Demo seeder design

Use Laravel 13's `WithoutModelEvents` on the base database seeding flow so ordinary data setup does not create anonymous audit noise.

Add a dedicated local/demo-only audit scenario seeder after all domain seeders. It must:

- never run in production by default;
- be idempotent through a deterministic scenario marker;
- use explicit named Admin, RND, FSS, and system actors;
- use a controlled clock to create realistic chronological history;
- write through supported audit/domain services rather than direct raw activity rows;
- create no clinical values in audit storage;
- create coherent current records and historical snapshots;
- cover all five tabs and both drawer/history-page interactions.

Minimum demo scenarios:

- account activation/deactivation or settings change under Security & Administration;
- RND Food Library manual creation, USDA import, nutrient/unit update, and safe deletion example;
- RND recipe structural update;
- clinical intervention/monitoring change showing field names only and actual actor;
- FSS item price/unit change;
- FSS recipe creation/update;
- weekly menu structural change with historical version;
- meal-service population correction;
- shopping-list population correction with linked draft PO recalculation;
- PO approval/order/receiving/completion and related budget deduction;
- new fiscal-year allocation, per-head/day setting change, and manual ledger adjustment;
- report generation/view/download/archive metadata.

Seeder tests verify no anonymous base-seed activity, deterministic reruns, valid actor attribution, module distribution, privacy sentinels, and working historical links.

## API and frontend compatibility

- Extend the structured DTO; never expose raw activity properties or revision JSON.
- Preserve existing audit-list and contextual-trail routes until all Next.js/mobile consumers migrate.
- Add module filtering without removing category/domain backend support in the first compatibility wave.
- URL-persist module, contextual subfilter, action, actor, outcome, severity and dates.
- Replace the global Domain control only after module URL/query compatibility is live and tested.
- Actor selector must use complete paginated/searchable user data rather than silently loading only one page.
- Keep current design-system components and typography. Reuse existing domain display components in read-only mode.
- Maintain keyboard access, focus trapping, 44-pixel targets, responsive tables/cards, loading, empty, unauthorized and retry states.

## Performance and query design

- Persist/index module for stable filtering rather than performing model-type classification in PHP for every row.
- Preserve newest-first deterministic order.
- Avoid N+1 actor/subject/context/revision lookups.
- Select only fields required by list responses; load revision payloads only on historical detail routes.
- Compute module counts with one conditional aggregate when counts are displayed.
- Add compound indexes based on actual module/date/actor/action filters and verify with MySQL explain plans.
- Keep 100,000-row audit-list performance coverage and add bounded revision payload/lookup gates.

## Blast radius and risk register

### Affected systems

This redesign can affect more than the Admin audit page:

- `activity_log` schema, indexes, legacy rows, retention and legal holds;
- model-event writers and explicit audit writers;
- clinical, procurement, receiving, budget, report and account transactions;
- shared `AuditEventDto`, contextual patient/NCP/PO/budget/report trails and Next.js proxies;
- Admin audit filters, drawer, pagination, actor search and historical routes;
- report browsing/authorization for all active RND users;
- Food Library, recipes, menus, shopping lists, POs and budget UI components reused in read-only mode;
- seeders, factories, demo data and test databases;
- storage growth, pruning duration and MySQL query plans.

### Ranked risks and controls

| Risk | Impact | Required control and verification |
|---|---|---|
| Clinical/PHI leakage beyond the approved patient-name exception | Critical | Revision writer rejects every patient/NCP/clinical/report-content type; only encrypted `patient_display_name_snapshot` is allowed; per-serializer allow-lists; storage/API/export/log/UI privacy sentinels; Admin authorization tests |
| Patient-name snapshot is exposed through export, logs, filters or unauthorized routes | Critical | Dedicated encrypted non-indexed field; typed DTO only; export omission; direct-route denial, log/metric sentinel and ciphertext-at-rest tests |
| Shared-RND report fix accidentally grants Admin/FSS clinical access | Critical | Role/policy tests for RND A/RND B/Admin/FSS across browse, preview, view, download, delete and trail endpoints; retain Admin report allow-list |
| Attribution fields remain hidden authorization gates | High | Repository scan for `rnd_user_id`, `audit_owner_id`, report `user_id` gates; contract tests state they are attribution only |
| Module/domain backfill misclassifies historical rows | High | Deterministic model/action mapping only; ambiguous rows stay Legacy/Unclassified; pre/post migration count report; rollback/re-forward test |
| New historical store becomes an unbounded shadow database | High | Explicit supported-type list, payload caps, one revision per qualifying event, no raw files/report contents, category retention cascade, legal-hold coupling and storage monitoring |
| Snapshot/audit failure leaves an unlogged destructive or financial mutation | High | Required destructive, corrective, complex structural and financial audit/revision writes share the business transaction; fault-injection rollback tests |
| Duplicate suppression removes a distinct accountable side effect | High | User-intent event matrix; keep separate PO lifecycle and budget-ledger events; focused event-count/order tests per workflow |
| Canonical event cleanup breaks legacy filters or contextual trails | Medium | Add module/DTO compatibility first; migrate every web/proxy consumer; retain backend category/domain query compatibility during transition; stale-route/proxy tests |
| Required reason validation blocks legitimate existing workflows | Medium | Add coordinated backend request and frontend modal/form in the same task; reason required only for confirmed destructive/corrective states; `422` and success-path tests |
| Operational values expose data outside Admin authorization | High | Admin-only global endpoints, escaped typed presenters, no arbitrary values, authorization tests and direct-route denial tests |
| Historical page is mistaken for current state | Medium | Prominent event timestamp/version label; read-only styling; separate `View current record`; no edit controls on history routes |
| Budget presentation changes financial behavior | High | Presenter/snapshot changes remain read-only; no approval/reversal workflow; assert ledger immutability, balance calculations and PO-deduction idempotency |
| Account Blocked is confused with removed IP blocking | Medium | Bind actions only to user `is_active` transitions; preserve tests proving IP-block model/config/routes remain absent |
| Retired Inventory cleanup hides needed legacy evidence | Medium | Count/inspect legacy Inventory events first; move label support to legacy adapter before removing active resolver code; legacy presentation tests |
| Query/index changes degrade 100,000-row performance | High | MySQL `EXPLAIN`, composite-index assertions, query-count/N+1 tests and existing 100,000-row gate before each integration wave |
| Base/demo seeders create fake production audit history | High | `WithoutModelEvents` for base seed; strict local/demo environment guard; deterministic marker; production seeder test; no raw activity inserts |
| Revision pruning violates legal hold or leaves orphans | High | Parent-child retention transaction, held-category tests, foreign-key/orphan assertions, dry-run/force pruning tests |
| Large frontend reuse creates editable controls on history pages | Medium | Explicit read-only props/contracts, component tests proving mutation buttons/handlers absent, route authorization and visual interaction tests |

### Rollout and rollback boundaries

- Use additive schema/API changes before removing or hiding any existing contract.
- Backfill module/domain in a dedicated migration and preserve ambiguous legacy values.
- Deploy backend module/DTO compatibility before switching frontend tabs and filters.
- Introduce revision storage and serializers before exposing historical links.
- Migrate each complex record type independently; a type receives a historical link only after its serializer, API, read-only page and tests pass together.
- Keep category/domain query parameters through the compatibility wave even after the normal UI stops showing Domain.
- Do not remove active compatibility code until route/proxy, stale-reference and legacy-presentation tests prove no consumer remains.
- A failed integration wave rolls back application code while additive columns/tables remain harmless; destructive schema cleanup occurs only in a later verified wave.
- Each implementation-plan task must state its touched workflows, failure mode, rollback boundary and focused verification commands.

## Testing requirements

### Backend

- taxonomy/module contract and deterministic legacy backfill;
- module filtering and module-aware active actions;
- every action formatter produces a factual non-generic summary;
- subject/context semantic fallback for security/system events;
- operational create/update/delete value rules;
- clinical field-name-only privacy sentinels across store, DTO, history, export, logs and UI fixtures;
- revision serializer allow-list, size cap, immutability and pruning cascade;
- historical route authorization and current-record link authorization;
- duplicate-event suppression for upload/generate/approve/receive workflows;
- required reason validation and rollback behavior;
- budget fiscal-year, per-head/day, ledger and PO-deduction presentation;
- shared-RND report browse/access correction;
- account activation taxonomy;
- retired Inventory and dead-action stale scans;
- unsafe-route coverage and Laravel/Next.js proxy compatibility;
- fresh/legacy migration, rollback/re-forward and performance/index tests;
- demo seeder idempotence/privacy/actor/history coverage.

### Frontend

- five module tabs and module-aware filters;
- no global Domain dropdown;
- factual rows and structured drawer sections;
- correct Created/Updated/Deleted/Lifecycle rendering;
- field-name-only clinical rendering;
- history/current action labels and routing;
- read-only recipe/menu/shopping-list/PO version rendering;
- Before/After toggle and highlighted add/change/remove states;
- Budget and per-head/day values;
- URL persistence, pagination, searchable actor selector;
- responsive, keyboard, loading, empty, unauthorized and retry behavior;
- typecheck, lint, tests and production build.

### Full verification

- route coverage;
- privacy sentinel;
- authorization;
- migrations and legacy upgrade;
- MySQL performance/index plan;
- backend full suite;
- frontend tests/typecheck/lint/build;
- mobile compatibility tests for any shared DTO actor/name changes;
- seeder smoke test;
- stale-reference scans.

## Acceptance criteria

- Admin can identify who performed every event and understand the safe business context.
- No visible event uses `Updated audit event` or `No subject or context` when semantic context exists.
- Tabs match Nutriscope modules rather than retention categories.
- RND Food Library and RND recipes appear under Nutrition Care; FSS catalogs/recipes remain Food Service Operations.
- Safe nonpatient values display for create/update/delete events.
- Clinical events show patient display name, actual actor, pseudonymous NCP reference and changed field names; they expose no other patient details or clinical values to Admin.
- Complex nonclinical records open a truthful event-time read-only version; current record is a separate link.
- Budget setup, per-head/day changes, manual adjustments and PO deductions are understandable and attributable.
- Required reasons exist for destructive/corrective actions.
- One user intent does not produce redundant generic/business events.
- Shared RND report browsing/access no longer uses attribution as authorization.
- Dead/outdated actions, filters, labels and retired feature paths are removed or explicitly isolated as legacy.
- Base seeders produce no anonymous audit noise; demo seeding creates coherent safe history.
- Retention/export defaults and clinical privacy remain unchanged.
