# Contextual PES Drafts Implementation Plan

**Goal:** Generate concise, evidence-supported nutrition diagnoses across NI, NC and NB, with reliable abstention, provenance and three generations per patient per Philippine calendar day.

**Architecture:** Extend the existing evidence builder, rule catalog, suggestion state and diagnosis editor. The model assesses context before proposing diagnoses; the server verifies references, source excerpts, numeric direction, selections, limits and duplicate coverage. Send one bounded evidence map and compact candidate descriptions per call. Reuse cached results; never spend a generation on status, dismissal or an empty candidate set.

**Tech stack:** Laravel/PHP, MySQL, Next.js/React, PHPUnit, Vitest. No new dependencies.

**Approved design:** The October 20 discussion and explicit request to implement all recommendations, amended to three generations daily and checkbox-controlled Other fields.

## Constraints

- Preserve unrelated edits; no secret inspection, subagents or worktrees. Deployment was initially excluded and was explicitly authorized in the October 21 follow-up.
- NC and NB problem groups, and every etiology/signs group: Other checkbox reveals a required 75-character field.
- Additional notes contain reasoning only and never enter PES. Generated reasoning is at most 100 characters; human notes retain the existing 125-character API limit.
- NI retains its nutrient/direction builder. Generated unmatched NI problems must map to supported intake terminology.
- Store structured builder fields separately from composed P/E/S for faithful edits and note separation. Preserve legacy saved statements.
- Maximum three returned drafts per generation; existing pending drafts may accumulate while further uncovered findings are reviewed. Prefer one supported diagnosis per domain in each generation. Never manufacture domain coverage.
- Match equivalent existing diagnoses and pending/approved/dismissed proposals with stable problem identity and evidence references, across changed wording. Materially changed evidence can be assessed again. Reuse of corroborating evidence for a different independently supported problem is allowed.
- Patient quota spans users/cycles; midnight Asia/Manila reset. Successful real provider calls count, including successful abstention. Cached retrieval, no eligible evidence, dismissal, acceptance and failed requests do not count. Serialize patient operations to avoid concurrent bypass.
- Context interpretation is required before classifying a finding as a problem. Normal, negated, resolved, historical, ambiguous or unsupported claims do not establish diagnoses. Weight gain cannot support unintended weight loss. Do not infer cause from BMI, medical labels or a numeric flag alone.
- Keep provider payload bounded and de-identified, prevent instructions in notes, and validate every returned evidence/source reference. Server-issued draft identity is required when accepting or editing an AI draft.

## Tasks

- [x] 1. Add failing tests for grounded contextual evidence, normal/gain rejection, Other fields, reasoning/PES separation, duplicate coverage, patient quota and stale/forged approvals.
- [x] 2. Extend stored diagnosis builder/provenance and suggestion state metadata; add patient daily generation counters using forward migrations. Add status retrieval without generation.
- [x] 3. Expand bounded de-identified evidence and all-domain candidates. Interpret notes once; validate outputs and evidence excerpts. Persist/reconcile pending drafts and evidence coverage.
- [x] 4. Update the diagnosis editor and API contracts: conditional Other checkboxes, reasoning fields, correct PES composition, faithful saved draft editing, visible generation counter and accessible disabled explanation.
- [x] 5. Run focused backend/frontend tests, formatting, TypeScript, lint and production build; inspect rendered editor states where available. Run broader checks required by affected contracts.
- [x] 6. Update existing FAQ/help/storyboard descriptions without overwriting concurrent edits. Review complete diff and report exact verification and release state.

## Review focus

- Normal/no-change assessments must not produce drafts or spend quota before provider use.
- Free text may contain denials, resolved history, patient identifiers or injected instructions.
- Evidence changes and accepted/edited/manual diagnoses must not reintroduce equivalent drafts or allow stale acceptance.
- Quota must survive reloads, span cycles and prevent concurrent calls past three.
- Legacy joined components and current manual PES overrides must remain editable without silently changing saved clinical records.

## Verification and delivery

- Backend: 81 related tests passed, 859 assertions, covering diagnosis CRUD/pagination, contextual drafts, appointments, AI service contracts, audit attribution and evidence/eligibility units. Provider responses were mocked. A negative-excerpt regression first reproduced a normal finding being accepted from a mixed-content field; the server now checks the cited excerpt as well as the field.
- Frontend: 24 diagnosis-related tests passed across seven files. Targeted ESLint, the latest full TypeScript check, and the production build passed. The build skips types by project configuration, so TypeScript was checked separately.
- Earlier broad runs encountered concurrent Monitoring failures and shared test-database migration collisions. Unrelated files were preserved; subsequent diagnosis runs completed successfully. The full frontend suite was not rerun after the concurrent session's later edits.
- Local browser: inspected desktop and 320 px layouts using fictional API fixtures. Checked visible disabled quota action and tooltip, keyboard checkbox toggling, conditional Other fields, 75-character enforcement and PES exclusion of reasoning notes. No browser page errors. This was local UI verification, not deployed acceptance or live clinical/provider validation.
- Pint passed for owned PHP files; diagnosis routes and `git diff --check` passed. Existing Next.js middleware deprecation remains outside this task.
- Applied only `2026_10_20_110855_add_contextual_pes_fields_and_daily_generations` to the confirmed local MySQL `nutriscope` database, guarded by local environment/host/database checks. Test migrations used disposable `nutriscope_test`.
- Existing FAQ, Help and the Diagnosis step in the canonical NCP storyboard were updated. Unrelated concurrent edits were retained. No commit, push or deployment was performed.
- Clinical limitations: conservative source/excerpt and wording checks can abstain on valid but insufficiently documented findings. Academy NCP, WHO and public GLIM guidance inform the rules; RND review remains required. No runtime web retrieval or new dependency was added.

## October 21 clarification and release request

- The three-draft limit applies to each generation, with three successful generations per patient per Philippine day. Pending drafts do not block another generation and are not capped at three total. Pending labels join existing diagnoses in duplicate checks.
- A regression covers a second generation while the original three drafts remain pending: all four drafts remain accessible and two generations are counted. The frontend keeps generation enabled when the server reports uncovered findings and remaining allowance.
- User authorized committing and pushing this session's diagnosis changes to `main`, observing deployment, and live QA with a disposable fictional patient. Capture only the AI output/controls for the requested screenshot, then remove the patient through the normal deletion workflow. Keep the cycle incomplete so clinical protection does not prevent cleanup.
- Live assessment notes should be concise or bulleted, including documented nutrition-related behavioral concerns and smoking as context. Smoking alone must not be converted into a nutrition diagnosis.
