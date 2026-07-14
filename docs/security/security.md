All API routes protected by auth:sanctum
Role guards on every route group: role:RND, role:FSS, role:Admin
All inputs validated via Laravel Form Requests
File uploads: PDF/JPG/PNG only, max 5MB
  - Screening forms: PDF/JPG/PNG, max 5MB per file
  - Lab results: PDF/JPG/PNG, max 5MB per file
  - Procurement documents: PDF/JPG/PNG, max 5MB per file
  - Receipt images: JPG/PNG only, max 5MB
  - All uploads stored in private disk, served via signed URLs
Anthropic API key: Laravel backend only, never in frontend
USDA API key: Laravel backend only
APP_DEBUG=false in production
Audit logging: structured audit events record security, clinical, and operational actions. Clinical changes retain field names only; clinical values, PHI, credentials, tokens, verification codes, file/OCR contents, and AI prompts or outputs are never audit payloads. The audit API is read-only; there are no HTTP update or delete routes for audit rows.

The authoritative event taxonomy, actor/system semantics, page-trail behavior, export policy, route/proxy coverage rules, incident workflow, and operator runbook are in [`docs/architecture/audit-logging.md`](../architecture/audit-logging.md). Audit export remains disabled by default. Temporary IP blocking is not implemented and has no runtime/configuration/unblock path; any future capability requires a separate approved design.

## Audit retention and legal hold

Retention is category-specific and uses the indexed `(category, created_at, id)` range. Current defaults, pending privacy-owner approval, are security 365 days, clinical 2,190 days, operations 1,095 days, and uncategorized legacy events 90 days. Each category has an independent `legal_hold` setting in `config/audit.php`. A legal hold refuses pruning for that category while allowing eligible categories to continue. The runtime row-mutation boundary is omitted only within trusted Laravel `migrate*` command processes so reviewed legacy backfills can run; it remains active for HTTP, workers, tests, and ordinary Artisan commands.

Run `php artisan audit:prune` for a dry-run count. Run `php artisan audit:prune --force` only after reviewing the counts; it deletes eligible rows in bounded chunks. The command runs daily under overlap-prevention and multi-server locks; production nodes must share the configured central cache for those locks. Completion or failure events contain counts only—never row data, exception messages, or event contents. If a later chunk or category fails, partial prune progress is preserved: the failure event and monthly counters report the actual eligible, deleted, and held counts completed or established before failure. Audit rows are immutable. A database pre-execution boundary blocks Eloquent, query-builder, raw SQL, and truncate update/deletion attempts; only the retention service can open its private, per-connection deletion scope, which always closes in a `finally` block.

Back up and verify the audit table before first production pruning. `OPTIMIZE TABLE` never runs automatically because it can lock MySQL. If space reclamation is required, the database owner must assess and run it manually in an approved maintenance window with a tested rollback plan.

## Audit monitoring and incidents

Minimum monitoring emits protected, content-free alerts for:

- an unauthorized audit-row mutation or deletion attempt;
- an audit writer failure, identified only by exception class and deduplicated to avoid recursive alert flooding; and
- an event-volume spike, containing only aggregate counts and configured thresholds.

The daily monitor compares the last complete day with the trailing 30-day daily average and alerts when it is more than three times that baseline. It also alerts when retained audit-table storage exceeds 1 GiB or database disk usage exceeds 70%. MySQL exposes table bytes through `information_schema`; database disk usage must come from the infrastructure-provided `AUDIT_DATABASE_DISK_USED_PERCENT` metric. All thresholds are configurable and may be lowered for smaller environments. The daily monitor runs with cluster locks.

Monthly metrics contain only a retained-row snapshot by category/action, retained bytes, and the previous calendar month's prune run/failure/eligible/deleted counts, audit writer failure count, and slow audit-query count. They never contain event descriptions, properties, exception messages, SQL, bindings, or clinical/security values. Route monthly metrics plus application warnings and critical alerts to the production monitoring sink. On alert, preserve database and application logs, confirm access scope, place the affected category on legal hold when appropriate, and notify the security/privacy owner. Do not copy event payloads into tickets or chat.

Every `activity_log INSERT` persistence failure is converted to a safe audit-writer exception at the activity model boundary, covering manual `AuditLogger` calls and Spatie model events. Laravel's exception reporter also recognizes direct audit inserts. Both paths emit and count the same exception object only once. They never report SQL or bindings, database messages, exception messages, or audit payload values; unrelated database failures are not counted as audit-writer failures.

Required financial and clinical mutation audit writes use the same database transaction as the mutation. If the audit writer is unavailable, the mutation must fail and roll back. Non-critical security telemetry must preserve the original HTTP response, report the writer failure without secrets, and avoid recursively flooding logs.

The database audit trail is not independently tamper-proof. A hash chain, periodic integrity export, or external append-only sink is an optional production-hardening phase; deployment requires privacy-owner approval, encrypted transport/storage, access controls, and a defined retention/legal-hold process.

## Audit query performance gate

Staging must use the production schema on MySQL 8.0-compatible infrastructure with at least 100,000 representative audit events. After statistics are refreshed and caches warmed, collect at least 30 default, date-range, and context query samples. `EXPLAIN` must select the intended composite indexes and must not use a full table scan; combined query p95 must be at or below 250 ms. `AuditRetentionTest` generates 100,000 events and enforces the same index-plan and p95 acceptance gate. Record staging hardware, MySQL version, row distribution, sample count, and measured p95 with the release evidence.
Rate limiting: login (5/min), password reset (5/hour per email+IP), password change (5/hour per user), AI endpoints, uploads, USDA, compute, and reports
Password reset: generic forgot-password response, signed broker token, frontend reset URL, all existing Sanctum tokens revoked after reset
Password change: current password required, all existing Sanctum tokens revoked after change
Logout: current Sanctum token revoked and frontend clears `nutriscope_token` + `nutriscope_role`
Profile photos: PNG/JPEG/WebP data URLs only, capped by frontend preflight and Laravel validation; raw data is not written to audit payloads
Daily AI token limit: 100,000 tokens enforced in AIService
Monthly spend cap: $10 set in Anthropic console
Extraction pipeline: extracted data stored with confidence scores, always requires RND review before finalizing
Report files: stored in private storage. RND/FSS access is owner scoped except documented supervision paths; Admin access is limited to non-patient report types and aggregate census.
