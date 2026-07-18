# Assessment Follow-up UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply approved Assessment corrections, clarify shared NCP context and save behavior, improve AI diagnosis editing, and compact SOP history.

**Architecture:** Keep persistence APIs and clinical scoring unchanged. Use page-local React state for unsaved edits, tested pure formatting helpers, native disclosures for secondary detail, and existing service calls only.

**Tech Stack:** Next.js 16, React 19, TypeScript, Tailwind CSS, Vitest, existing Laravel API unchanged.

---

## File map

- Modify `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx`: auto-grow fields, top-aligned grids, save placement, upload order, BMI card, risk explanation, header inputs.
- Modify `frontend/lib/assessmentSummary.ts` and test: concise deterministic summaries.
- Modify `frontend/lib/nutritionCalculations.ts` and test: BMI-only wrapper over existing Asia-Pacific classification.
- Modify `frontend/app/(rnd)/ncp/_components/NcpPatientHeader.tsx` and test: allow-listed text context only.
- Modify Diagnosis/Intervention/Monitoring NCP pages only to supply header context or clarify explicit save UI.
- Modify `frontend/lib/diagnosisComponentSplit.ts` and tests only if AI wording needs an existing checkbox alias.
- Modify `frontend/components/announcements/SopBanner.tsx` and add a contract test: Edit label and collapsible date history.

### Task 1: Documentation checkpoint

- [ ] Save approved supplemental design and plan.
- [ ] Scan for placeholders, contradictions, and scope drift.
- [ ] Commit `docs(ux): plan assessment follow-up fixes`.

### Task 2: Assessment field and layout corrections

- [ ] Extend `assessment-page-ux.test.ts` with failing assertions for auto-height, hidden textarea overflow, top-aligned grids, top save placement, bottom uploads, removed banner, and risk explanation.
- [ ] Run focused test; confirm failures describe missing UI.
- [ ] Implement ref-driven textarea height using `scrollHeight`, retained `rows`, `overflow-hidden`, and `resize-none`.
- [ ] Top-align grids, reorder Lab attachments, move save above tabs, remove cycle text/banner, and show reused BMI class in BMI card.
- [ ] Run focused tests and commit `fix(assessment): correct dense form behavior`.

### Task 3: Concise summary and clinical header

- [ ] Write failing summary tests for abnormal-only labs, compact notes, selective anthropometrics, and capped risk factors.
- [ ] Write failing header tests rejecting IDs/icons/ward/arbitrary badges and requiring allow-listed labels.
- [ ] Run both test files and confirm expected failures.
- [ ] Implement concise summary formatting without altering source values.
- [ ] Refactor header to explicit physician/risk/food/goal/diagnosis props and wire available context from all four NCP pages.
- [ ] Run tests and commit `feat(ncp): focus patient context header`.

### Task 4: Diagnosis UX and save clarity

- [ ] Add failing tests for `Edit`, AI checkbox hydration, light previews, and explicit save labels across NCP steps.
- [ ] Run focused tests and confirm expected failures.
- [ ] Hydrate problem/etiology/sign selections from AI text using existing match helpers; preserve unmatched text in notes.
- [ ] Replace dark previews with light accessible cards; remove post-edit accept wording.
- [ ] Rename intervention goal confirmation to `Save Goal`; keep persistence mechanics.
- [ ] Run tests and commit `fix(diagnosis): simplify AI draft editing`.

### Task 5: SOP history

- [ ] Add a failing SOP contract test for Edit labels, automatic date-labeled disclosures, and collapsed bodies.
- [ ] Run focused test and confirm expected failures.
- [ ] Implement native collapsible history items and neutral Edit wording; add no custom history-name input.
- [ ] Run tests and commit `fix(sop): compact revision history`.

### Task 6: Verification and push

- [ ] Run focused then full Vitest.
- [ ] Run `npx tsc --noEmit`, `npm run lint`, and production build.
- [ ] Browser-test 1440 px and 390/375 px: textarea growth, header wrapping, summary generation, AI edit, and SOP collapse.
- [ ] Audit persistence triggers: field edits remain local until explicit Save; uploads remain explicit upload actions.
- [ ] Keep `.codex/config.toml` and `AGENTS.md` unstaged.
- [ ] Push task commits directly to `main`.
