# FSS Documentation and Role Storyboards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce an evidence-backed consolidation report, correct current FSS documentation, and create organized RND/FSS recording scripts outside the repository.

**Architecture:** Application source and current Laravel role gates are authoritative. Repository documentation describes current behavior; historical plans remain untouched. Recording scripts live in one external `Documents\Storyboarding` folder so a future role-based storyboard page has clear source files without duplicating application architecture.

**Tech Stack:** Markdown, Expo Router/React Native source contracts, Laravel routes/controllers, Next.js routes/middleware, Git.

---

### Task 1: Preserve the completed implementation record

**Files:**
- Create: `docs/fss-native-app-consolidation-report.md`

- [x] Derive the report scope from commits `39bc159` through `e5b432b`.
- [x] Record removed architecture, native features, backend changes, release flow, production evidence, verification counts, and remaining platform constraints.
- [x] Cross-check every version, hash, route, and test count against repository or live evidence.

### Task 2: Reconcile current documentation

**Files:**
- Modify: `docs/overview.md`
- Modify: `docs/FAQ.md`
- Modify: `docs/ROLE-HOW-TO.md`
- Modify: `docs/STORYBOARD.md`
- Modify: `docs/module-workflow-flowchart.md`
- Modify: `docs/modules/fss.md`
- Modify: `docs/modules/STORYBOARD-SCREENSHOT-GUIDE.md`
- Modify: `docs/modules/Flowcharts/FSS Mobile Execution Flow.md`
- Modify: `docs/modules/Flowcharts/Food Service Operations.md`

- [x] Verify current labels and actions from the Expo screens and Laravel routes.
- [x] Replace stale service-completion, Menu-population, FSS-web, and five-tab claims.
- [x] Refresh verification dates only on files actually checked end to end.
- [x] Leave historical plans/specifications unchanged.

### Task 3: Organize external role storyboards

**Files:**
- Move: `C:\Users\jared\Documents\Food Service Operations Video Storyboard.md`
- Create: `C:\Users\jared\Documents\Storyboarding\RND Food Service Operations Video Storyboard.md`
- Create: `C:\Users\jared\Documents\Storyboarding\FSS Mobile App Video Storyboard.md`

- [x] Resolve and verify the exact source and destination paths.
- [x] Move the RND script without losing content, then correct only verified stale FSS handoff wording.
- [x] Create the FSS native-app script from current visible screens, including setup, exact actions, narration, outcomes, privacy, and cleanup.

### Task 4: Verify and deliver

**Files:**
- Verify all files from Tasks 1-3.

- [x] Search current docs and both external scripts for obsolete workflow claims.
- [x] Validate Markdown links and balanced code fences.
- [x] Confirm both external scripts exist and the loose original is gone.
- [x] Run `git diff --check` and inspect all task changes.
- [x] Stage only task documentation and commit with neutral metadata. Delivery verification is performed after the commit.
