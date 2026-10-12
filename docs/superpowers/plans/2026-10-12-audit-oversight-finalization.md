# Audit Oversight Finalization Implementation Plan

> **For agentic workers:** Use the existing in-session native execution method. Steps use checkbox syntax for tracking.

**Goal:** Finish the requested audit taxonomy, report placement, and legacy-record normalization without changing the procurement or clinical workflows themselves.

**Architecture:** Keep the canonical action mapping in the existing legacy normalizer and apply it through the existing backfill command. Correct report visibility in the shared audit query, and preserve the separate related-context data shown in audit details.

**Tech Stack:** Laravel 13, Eloquent, PHPUnit; existing React audit UI and tests for read-only review.

**Spec:** `docs/superpowers/specs/2026-07-15-audit-oversight-and-history-redesign.md`

## Global Constraints

- Scope code changes to audit event recording, presentation, visibility, backfill, and tests.
- Keep reports under Nutrition Care or Food Service Operations by report type; keep the Reports tab removed.
- Keep only the agreed concise audit actions; only report records may use `viewed`.
- Preserve actor IP for new records and preserve related-context information in audit details.
- Do not change operational workflows or files belonging to the parallel procurement work.
- Do not modify production data or merge/deploy from this worktree during local verification.

## Review Focus

- Report access with a missing legacy domain remains visible in its module and gains `reports` domain during backfill.
- Non-access events on Report subjects and `viewed` events on non-Report subjects stay hidden or are removed by backfill.
- Legacy action aliases map to the agreed canonical event, including revisions linked to the same activity.
- Retired IP-blocking events are removed while the production IP-blocking feature remains absent.
- Audit details retain their separate related-context record and actor IP.

### Task 1: Report visibility and backfill

**Files:**
- Modify: `backend/app/Services/Audit/AuditQuery.php`
- Modify: `backend/app/Services/Audit/AuditOversightBackfill.php`
- Test: `backend/tests/Feature/Audit/AuditLegacyCompatibilityTest.php`

- [x] Add a failing test for a report `viewed` row with no domain, a report `created` row with no domain, and a non-report `viewed` row.
- [x] Verify the visibility assertions fail against the current query.
- [x] Fix shared visibility by report subject and access action, not by requiring a prefilled domain.
- [x] Backfill surviving Report rows to the Reports domain and correct module.
- [x] Verify the legacy audit compatibility test passes and repeated backfill remains idempotent.

### Task 2: Canonical legacy actions and revisions

**Files:**
- Modify: `backend/app/Services/Audit/AuditLegacyEventNormalizer.php`
- Modify: `backend/app/Services/Audit/AuditEventPresenter.php`
- Modify: `backend/app/Services/Audit/AuditOversightBackfill.php`
- Modify: `backend/tests/Feature/Audit/AuditLegacyCompatibilityTest.php`
- Modify: `backend/tests/Feature/Audit/IpBlockingRemovalContractTest.php`
- Modify: `backend/tests/Unit/AuditStaleFeatureInventoryTest.php`

- [x] Add failing coverage for `ordered`, `approved`, `logout`, account suspension aliases, generated subject types, and linked revision actions.
- [x] Verify the new assertions fail before changing production code.
- [x] Align the normalizer with the established audit mappings and update matching revision actions in the backfill.
- [x] Keep IP-blocking cleanup aliases only in the legacy normalizer; keep the source-contract checks focused on live feature code.
- [x] Run focused audit tests, then the full available backend and frontend audit test sets.

**Completion check:** review the complete audit diff, run formatting and `git diff --check`, and record local versus production verification separately.
