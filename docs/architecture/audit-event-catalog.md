# Audit Event Catalog

This catalog describes the current Admin Audit Logs interface and its write policy. Executable sources are `AuditLogger`, `AuditEventPolicy`, `AuditQuery`, `AuditFilterMetadata`, and their feature tests.

## Taxonomy

Admin has four tabs: **All Activity**, **Security & Administration**, **Nutrition Care**, and **Food Service Operations**. The selected module tab scopes results. Context identifies the related record; it is not a filter.

The only action filters are **Created**, **Updated**, **Deleted**, and **Approved**. Workflow-specific labels such as ordered, received, adjusted, corrected, archived, and completed normalize to these actions. Purchase-order conversion is Created; receiving, corrections, and other edits are Updated. Approval remains distinct because it is a review decision.

Outcome and severity are not filters or action names. New audit entries must represent successful events. Read access, report activity, authentication failures, access denials, rate limits, routine profile/settings changes, and audit-page access do not create audit events. Failed business mutations roll back with their transaction; they do not create audit rows.

## Audited records

| Admin tab | Reviewable records and changes |
|---|---|
| Security & Administration | Account creation, role/status changes, deletion, and security-sensitive credential changes. Credentials and recovery codes never appear in audit details. |
| Nutrition Care | Patient/NCP records, assessments, diagnoses, interventions, monitoring, meal plans, screening documents, the RND Food Library, USDA imports, and RND recipes. Clinical details show changed field names only. |
| Food Service Operations | Food catalog and recipes, suppliers, menus, shopping lists, purchase orders, receiving, budgets, and ledger changes. Details use safe fields and registered event-time revisions. |

Automated budget deductions remain tied to the completed purchase order because they change fiscal balances. Routine background work, pruning, report generation, preview, and download do not create audit entries.

## Removed report and access trails

Reports remain available through their normal report pages. Report generation, view, download, archive, deletion, branding, and template edits have no audit trail or audit-log tab. Admin edits shared report branding and signatories under **Reports → Template Edit**.

Opening Audit Logs or a contextual history page does not write an access event. Patient/NCP and authorized purchase-order/budget histories remain available under their existing role policies.

## Privacy and history

Clinical events expose approved patient references, actor, record type, timestamp, and changed field names only. They never expose clinical before/after values, credentials, file contents, AI prompts/outputs, or report contents. Operational revisions use registered serializers and preserve event-time values; revisions remain immutable and share retention with their parent event.

Audit rows are immutable at runtime. The forward-only `simplify_audit_history` migration removes report, passive-access, rejected-request, and noncanonical rows; it normalizes historical action names. Its cleanup is intentionally irreversible, so keep the migration in source control for new installations and environments.
