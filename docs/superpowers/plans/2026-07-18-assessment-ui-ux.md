# NutriScope Assessment UI/UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Assessment tabs materially shorter and clearer, slim the shared patient header, and add safe deterministic summary generation without changing clinical logic.

**Architecture:** Keep existing Assessment API and `rnd_summary` persistence. Extract summary construction into a tested pure frontend module; keep generation status/undo as page-local UI state. Refactor only presentation/grouping inside the existing assessment page and shared header.

**Tech Stack:** Next.js 16.2.6, React 19.2.4, TypeScript, Tailwind CSS 4, Vitest 4.1.9, Laravel 13.11.2/MySQL existing API.

---

## File map

- Create `frontend/lib/assessmentSummary.ts`: deterministic category builder, input signature, formatting helpers.
- Create `frontend/lib/assessmentSummary.test.ts`: full/partial/empty/abnormal/stability tests.
- Modify `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx`: tab grouping/density, referral sub-sections, lab disclosure, summary UI state and action.
- Modify `frontend/app/(rnd)/ncp/_components/NcpPatientHeader.tsx`: compact responsive row only.
- Modify `frontend/app/(rnd)/ncp/active-patient-header.test.ts`: compact-header accessibility/source contract.
- Modify `docs/superpowers/specs/2026-07-18-assessment-ui-ux-design.md`: only if implementation evidence requires a correction.

Backend files are verified exclusions: current model, resource, Form Requests, controller, and migration already persist `rnd_summary`; no backend edit.

### Task 1: Summary builder

- [ ] Write failing tests for empty input, partial categories, full category order, abnormal labs, whitespace normalization, and deterministic signatures.
- [ ] Run `npm test -- --run lib/assessmentSummary.test.ts`; confirm expected failures.
- [ ] Implement smallest pure summary builder and signature function.
- [ ] Re-run focused tests.
- [ ] Commit `feat(assessment): add summary draft builder`.

### Task 2: Compact patient header

- [ ] Extend source contract test for compact row, full diagnosis fallback, and accessible Change Patient target.
- [ ] Run header test and confirm failure.
- [ ] Refactor shared header classes/markup only; preserve props, links, callbacks, labels, IDs, and badges.
- [ ] Re-run header and NCP workflow tests.
- [ ] Commit `style(ncp): slim active patient header`.

### Task 3: Compact Assessment tabs

- [ ] Add light fieldset helper and referral-section UI state.
- [ ] Re-group Dietary, Anthropometric, and Client fields without changing update keys or values.
- [ ] Convert Biochemical notes/documents to progressive disclosure and full-width responsive lab grid.
- [ ] Split Referral into Details, Clinical Conditions, and Intake/Weight internal panels; preserve all handlers/state.
- [ ] Keep visible labels, focus rings, logical DOM order, and at least 44 px primary targets.
- [ ] Run TypeScript and focused tests.
- [ ] Commit `style(assessment): compact clinical entry tabs`.

### Task 4: Summary generation UX

- [ ] Add failing source/component contract assertions for Generate/Regenerate, stale status, editable textarea, and Undo.
- [ ] Integrate builder with current derived metrics, labs, client context, and risk values.
- [ ] Establish initial source signature after load; mark stale only after later source changes.
- [ ] Keep summary editable; regeneration replaces deliberately and offers Undo.
- [ ] Place summary and risk side-by-side at `xl`; remove duplicate weight fields above summary.
- [ ] Run summary, page calculation, and workflow tests.
- [ ] Commit `feat(assessment): add safe summary generation`.

### Task 5: Verification and handoff

- [ ] Run `npm test`.
- [ ] Run approved `npx tsc --noEmit`.
- [ ] Run ESLint on changed TypeScript/TSX files.
- [ ] Run `npm run build` if environment permits; report genuine environment blockers only.
- [ ] Audit `git diff` for assessment/shared-header scope, unchanged clinical formulas/payload keys, and no unrelated files.
- [ ] Confirm commit metadata remains neutral and project-focused.
- [ ] Push accumulated commits directly to `main`.
