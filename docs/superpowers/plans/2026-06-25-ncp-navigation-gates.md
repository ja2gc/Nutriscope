# NCP Navigation Gates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make NCP navigation reachable without a selected patient while enforcing ADI step order inside real cycles.

**Architecture:** Add a pure `ncpWorkflow` helper for step availability, then consume it in sidebar links, patient profile quick actions, and step page guards. Keep Laravel backend gates unchanged because they already enforce write order.

**Tech Stack:** Next.js 16, React 19, TypeScript, Vitest, Laravel API.

---

### Task 1: Workflow Helper

**Files:**
- Create: `frontend/lib/ncpWorkflow.ts`
- Test: `frontend/lib/ncpWorkflow.test.ts`

- [ ] Write tests proving Assessment is always open, Diagnosis requires Assessment, Intervention requires Diagnosis, Monitoring requires Intervention, and sidebar placeholder URLs remain reachable.
- [ ] Implement the helper with `getNcpStepState`, `getPlaceholderStepHref`, and `getCycleStepHref`.
- [ ] Run `npm test -- ncpWorkflow.test.ts` from `frontend`.

### Task 2: Navigation Consumers

**Files:**
- Modify: `frontend/components/layout/Sidebar.tsx`
- Modify: `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx`

- [ ] Sidebar uses placeholder step URLs when no real cycle is active.
- [ ] Patient profile renders disabled step buttons with reasons for skipped steps.
- [ ] Diagnosis renders a locked state until assessment exists.
- [ ] Intervention renders a locked state until assessment and diagnosis exist.
- [ ] Monitoring renders follow-up/second-visit explanation until intervention exists.

### Task 3: Verification and Release

- [ ] Run targeted frontend tests.
- [ ] Run `npx tsc --noEmit`.
- [ ] Review `git diff`.
- [ ] Commit without Co-authored-by trailer.
- [ ] Push current branch.
