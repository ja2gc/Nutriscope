# Audit Event Catalog

This catalog describes the current Admin Audit Logs interface and its write policy. Executable sources are `AuditLogger`, `AuditEventPolicy`, `AuditQuery`, `AuditFilterMetadata`, and their feature tests.

## Taxonomy

Admin has four tabs: **All Activity**, **Security & Administration**, **Nutrition Care**, and **Food Service Operations**. The selected module tab scopes results. Context identifies the related record; it is not a filter.

Canonical actions are **Created**, **Updated**, **Deleted**, **Viewed**, **Downloaded**, **Archived**, **Failed**, **Suspended**, **Unsuspended**, and **Discontinued**. **Viewed**, **Downloaded**, and **Archived** apply to report records; other specific edits and transitions use the concise action with the subject describing what changed. Purchase-order conversion is Created; receiving and corrections are Updated.

Outcome and severity are not filters or action names. Audit entries cover reviewable business changes, report views/downloads/archives in their owning module, and failed authentication or rate-limit events. Generic access denials, routine profile/settings changes, and audit-page access do not create audit events. Failed business mutations roll back with their transaction; they do not create audit rows.

## Audited records

| Admin tab | Reviewable records and changes |
|---|---|
| Security & Administration | Account creation, role/status changes, deletion, and security-sensitive credential changes. Credentials and recovery codes never appear in audit details. |
| Nutrition Care | Patient/NCP records, assessments, diagnoses, interventions, monitoring, meal plans, screening documents, the RND Food Library, USDA imports, and RND recipes. Clinical details show changed field names only. |
| Food Service Operations | Food catalog and recipes, suppliers, menus, shopping lists, purchase orders, receiving, budgets, and ledger changes. Details use safe fields and registered event-time revisions. |

Automated purchase-order deductions remain in the budget ledger; they do not create a separate system audit event or revision. Routine background work, pruning, report generation, and preview do not create audit entries.

## Removed report and access trails

Reports remain available through their normal report pages. A report view, download, or archive is recorded in the owning module tab with its report type. Report generation, deletion, branding, and template edits have no audit trail or audit-log tab. Admin edits shared report branding and signatories under **Reports → Template Edit**.

Opening Audit Logs or a contextual history page does not write an access event. Patient/NCP and authorized purchase-order/budget histories remain available under their existing role policies.

## Privacy and history

Clinical events expose approved patient references, actor, record type, timestamp, and changed field names only. They never expose clinical before/after values, credentials, file contents, AI prompts/outputs, or report contents. Operational revisions use registered serializers and preserve event-time values; revisions remain immutable and share retention with their parent event.

Audit rows are immutable at runtime. The one-time `simplify_audit_history` cleanup was applied to production to remove retired report/access trails and normalize retained history. After confirming it had run, its migration and migration-only test were removed from source control. New audit events use the canonical taxonomy directly.
