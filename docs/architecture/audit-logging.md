# Audit Logging and Operations Runbook

Nutriscope uses Spatie Activitylog as an append-only event store and a Nutriscope-owned contract for writes, queries, exports, and UI. Raw Spatie `properties`, PHP model names, internal numeric identifiers, and arbitrary request bodies are not public API fields. `AuditLogger` is the supported explicit writer; `AuditEventPresenter` and `AuditEventResource` produce the shared `AuditEventDto` consumed by Admin and contextual trails.

This is an operational control description, not a legal certification. The privacy owner must approve retention periods, clinical-audit access, exports, and legal-hold procedures before production enablement.

## Event taxonomy

Every event has a category, domain, action, outcome, severity, actor snapshot, server timestamp, and optional safe subject/context references.

| Category | Meaning | Domains | Allowed actions |
|---|---|---|---|
| `security` | Authentication, authorization, account, and audit-oversight events | Primarily `accounts` and `system` | `created`, `updated`, `deleted`, `profile_changed`, `login_succeeded`, `login_failed`, `authentication_failed`, `logout`, `password_changed`, `password_reset`, `recovery_email_changed`, `recovery_email_verified`, `rate_limit_exceeded`, `authorization_denied`, `audit_log_viewed`, `account_blocked`, `account_unblocked` |
| `clinical` | Patient-linked NCP state and sensitive-record access | `patients`, `ncp`, and patient-linked `reports` | `created`, `updated`, `deleted`, `viewed`, `downloaded`, `exported`, `uploaded`, `generated`, `approved` |
| `operations` | Budget, procurement, food-service, report, reference-data, and system operations | `reports`, `budget`, `procurement`, `food_service`, `system` | `created`, `updated`, `deleted`, `viewed`, `downloaded`, `exported`, `approved`, `ordered`, `received`, `reversed`, `archived`, `adjusted`, `uploaded`, `generated`, `completed`, `price_corrected`, `profile_changed`, `settings_changed` |

The backend enums and `config/audit.php` are authoritative. The Admin response exposes compatible category/action filter options; clients must not maintain a second action matrix. Reference-data changes are operations events even when an RND user performs them. Clinical is reserved for patient-linked activity.

Outcomes are `success`, `failure`, or `blocked`. Severities are `info`, `notice`, `warning`, or `critical`. Domains are `accounts`, `patients`, `ncp`, `reports`, `budget`, `procurement`, `food_service`, and `system`.

## Actor and time semantics

| Actor kind | Meaning |
|---|---|
| `user` | An authenticated user caused the event. Store a safe snapshot of public ID, display name, and role so deleted or renamed users remain understandable. |
| `system` | A named job, scheduler, listener, or service caused the event without a user. The writer must supply a non-blank process name; a system event has no Spatie causer. |
| `anonymous` | No authenticated user or named system process is available, for example a rejected login attempt. Do not infer an identity from supplied credentials. |

An event cannot have both a user and system actor. Timestamps originate on the server as ISO 8601 UTC. Web views localize them to Asia/Manila and retain the exact timestamp in the tooltip. Production application, database, worker, and proxy hosts must use synchronized clocks.

## Privacy and redaction

Audit events are metadata, not a second clinical record. Never store or return clinical values, PHI, credentials, passwords, tokens, verification codes, recovery codes, email addresses used as credentials, full URLs or query strings, OCR text, file contents, report snapshots, AI prompts, or AI outputs.

Clinical changes contain allow-listed field names only. Their public change rows always have `old_value = null`, `new_value = null`, and `redacted = true`; the UI says “Value hidden; field changed.” Request metadata is sanitized and bounded. Public responses use stable public references and safe labels, never internal numeric IDs or raw model classes. Sentinel tests cover persistence, DTOs, exports, and rendered UI.

Required clinical and financial mutations write their audit event inside the same database transaction. If persistence fails, the mutation rolls back. Non-critical security telemetry preserves the original response, reports a content-free failure, and deduplicates alerts to prevent recursion.

## Admin audit view and export

`GET /api/admin/audit-logs` is read-only, authorized, offset-paginated, and constrained to the audit channel. It provides four UI views only: All Activity, Security, Clinical, and Operations. Server-provided filter metadata controls date, domain, action, actor, outcome, and severity choices. The structured drawer displays only labeled DTO fields and never raw JSON.

`GET /api/admin/audit-logs/export` is disabled by default with `AUDIT_EXPORT_ENABLED=false`. When explicitly approved and enabled, the policy gate and the same filter scope apply; CSV output is capped at 50,000 rows, contains only structured safe fields, and creates its own export event. Treat exports as sensitive records: use an approved encrypted destination, restrict recipients, record the case/reference and custodian, verify deletion at the approved deadline, and never attach exports to ordinary tickets or chat.

Temporary IP blocking is not part of this release: no model, migration, controller, middleware, route, capability, environment flag, or UI command exists. It is considered future work only and requires a separately approved design covering trusted-proxy behavior, shared-NAT risk, authorization, expiry, reason capture, unblock behavior, and incident-response ownership. Permanent blind blocks are prohibited.

## Contextual page trails

All trails return the same `AuditEventDto` and are authorized for the page context:

- Patient and NCP trails correlate child clinical records to the root patient/NCP and expose field-name-only changes. Opening a clinical trail is itself audited.
- Purchase-order trails include the PO lifecycle, vendor groups, attachments, corrections, receiving, meal-service linkage, and budget deductions.
- Budget trails include setup, manual adjustments, and ledger/system deductions, with user or named-system actor semantics.
- Report trails include generation outcome, view, download, export, archive, and deletion without filters, snapshots, file contents, or clinical values.

Trails use `before_id` cursor pagination and preserve deleted-subject and deleted-user labels through safe snapshots. Existing patient and PO paths remain compatibility contracts. Inventory has no activity route or history UI.

## Route coverage and proxy compatibility

Every unsafe Laravel route must have one entry in `config/audit.php` under `route_coverage`, classified as `explicit_event`, `model_event`, or `intentionally_not_audited` with an implementation source and reason. Adding or removing an unsafe route without updating that inventory fails `AuditRouteCoverageTest`; there is no generic request-audit fallback.

Every Next.js handler that calls `laravelProxy` must match a Laravel route with the same HTTP method and canonical path. `ProxyRouteCompatibilityTest` checks this against `php artisan route:list --json`. Add or change the Laravel route and Next proxy in the same change, preserve current paths until consumers migrate, and run both coverage tests before integration.

## Retention, legal hold, and integrity

Defaults in `config/audit.php` are pending privacy-owner approval:

| Class | Default retention |
|---|---:|
| Security | 365 days |
| Clinical | 2,190 days (6 years) |
| Operations | 1,095 days (3 years) |
| Uncategorized legacy | 90 days |

Each class has an independent legal-hold flag. A held category is never pruned, while other eligible categories may continue. `php artisan audit:prune` is a dry run; `php artisan audit:prune --force` deletes in bounded indexed chunks. The daily scheduler uses overlap and single-server locks. Do not automate `OPTIMIZE TABLE`; the database owner may use it only in an approved maintenance window after backup and rollback validation.

Scheduled deletion is disabled by default. The Audit Logs page shows the fixed category periods and one Admin-controlled DB-backed switch. Enabling requires an explicit confirmation that deletion runs daily, permanently removes unrecoverable rows older than each period, and must follow privacy/compliance approval; disabling is immediate. Every change records the actor, old/new booleans, and timestamp as a sanitized operations event. `AUDIT_RETENTION_ENABLED=false` is used only as the fallback until the first settings row exists. Health and volume monitoring remain active while deletion is disabled.

Application code cannot update, delete, or truncate audit rows. Only the pruning service can open its private deletion scope. The runtime mutation boundary is omitted only inside trusted Laravel `migrate*` command processes so reviewed schema migrations can backfill legacy rows; it remains active for HTTP, workers, tests, and ordinary Artisan commands. The database store is not independently tamper-proof; an external append-only sink, integrity export, or hash chain is a separately approved hardening phase.

## Operator runbook

### Ownership and cadence

| Cadence/trigger | Owner | Action |
|---|---|---|
| Each business day | Security/on-call owner | Review new `critical` and `warning` security events, audit-writer failures, authorization/rate-limit spikes, and unresolved alerts. Escalate critical alerts immediately. |
| Weekly | Application owner | Review failed/blocked trends, audit route-coverage changes, prune job status, and slow-query alerts. Confirm export and retention controls remain at their approved values. |
| Monthly | Privacy owner with security and database owners | Review aggregate category/action counts, retained bytes, prune counts/failures, audit-writer failures, slow queries, access grants, open exports, and legal holds. Do not include event payloads in the report. |
| Before release | Release owner | Run route/proxy coverage, privacy sentinels, authorization, migrations, performance gate, full backend/frontend verification, and clock checks. |

The security/on-call owner acknowledges high-severity alerts and coordinates containment. The privacy owner decides whether clinical/security data needs legal hold or breach assessment. The database owner protects backups and performs database maintenance. The application owner handles writer, query, route, and scheduler failures.

### Incident workflow

1. Record an incident reference and time; do not paste payloads into tickets or chat.
2. Preserve database/application logs and relevant infrastructure evidence with access controls.
3. Confirm affected category, time window, actors, authorization scope, and whether audit access/export occurred.
4. If evidence may be needed, have the privacy owner enable the category legal hold through the controlled configuration/deployment process, record approver/reason/start time, and verify the next dry run reports it held.
5. Contain through the narrowest approved control. Disabling a compromised account or revoking tokens is preferred to IP blocking.
6. Notify security/privacy leadership under the organization’s incident policy. Assess regulatory notification separately; the application does not make that legal determination.
7. After recovery, verify writer health, route coverage, event volume, clock synchronization, and scheduled pruning. Record corrective actions without copying sensitive payloads.

### Legal-hold release

Only the privacy owner may approve release. Record the incident/reference, category, approver, release date, and resulting retention cutoff. Back up and verify recoverability first, remove the flag through reviewed configuration, run a dry run, obtain a second-person review of counts, then allow the scheduled prune or run `--force` under change control.

### Export handling

Keep export disabled unless an approved case requires it. Confirm requester authorization and minimum filters, enable only through reviewed environment configuration, export to encrypted controlled storage, record the export event/reference and custodian, transmit through an approved channel, and disable the feature when the window closes. Confirm disposal when the case retention period ends unless a legal hold applies.

### Prune monitoring

Confirm the daily job ran once, its completion/failure event contains counts only, held categories were skipped, and deleted counts match reviewed eligibility. Investigate missed locks, writer alerts, unexpected zero/high counts, or partial failures before retry. Back up before first production prune and after retention-policy changes.

### Clock synchronization

Infrastructure owns NTP/managed time synchronization. At deployment and monthly, compare application, worker, database, reverse-proxy, and monitoring timestamps; alert on material drift and correct the host before relying on sequence analysis.

### Future temporary IP blocking

Temporary IP blocking is not shipped. There is no model, migration, controller, middleware, route, capability, environment flag, cache entry, or unblock workflow to operate or reverse. A future implementation would require a separate owner-approved design covering trusted-proxy identity, shared-cache propagation, expiry, NAT collateral risk, audited block/unblock events, emergency reversal, and network-owner boundaries. Until then, incident response uses the existing rate-limit telemetry and infrastructure controls; audit events must never be deleted to simulate remediation.

## Verification commands

From `backend`: `php artisan route:list --except-vendor`, `php artisan test`, and `vendor/bin/pint --test`.

From `frontend`: `npm test`, `npx tsc --noEmit`, `npm run lint`, and `npm run build`.

For the 100,000-row MySQL performance gate, use production-compatible schema and representative distribution, refresh statistics, warm caches, collect at least 30 default/date/context samples, require intended indexes with no full table scan, and require combined p95 at or below 250 ms. Preserve hardware, MySQL version, distribution, sample count, and p95 in release evidence.
