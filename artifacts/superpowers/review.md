# Superpowers Review — Nutrition Engine + NCP Overhaul Plan

> Review of [`nutrition-engine-overhaul-plan.md`](nutrition-engine-overhaul-plan.md) per
> `.agent/skills/superpowers-review`. Scope = the **plan** (no code shipped yet — plan gate).
> Question answered: *does everything accommodate the plan, and is the plan sound/complete?*

**Reviewed:** 2026-06-11 · **Verdict:** ✅ Plan is coherent and implementable. No Blockers. Decisions
D1–D3 now resolved, so Phase 1 is unblocked. A few Majors to settle during execution, listed below.

---

## Blockers
*(none)*

The plan does not introduce wrong behavior, data-loss risk, or broken builds. The two destructive-leaning
moves are already mitigated: inventory `expiry_date` is **soft-removed** (column kept), and the AP BMI
flip is a deliberate, documented default with Western retained as reference.

## Majors
- **M1 — Golden-fixture must be authored before any engine edit.** Phases 1 & 2 both depend on
  `docs/logic/prescription-targets.json` (Phase 0). If engine code is touched before the fixture exists,
  the TDD red→green gate collapses and the "match research exactly" guarantee is unverifiable. → Enforce
  ordering: Phase 0 fixture is a hard prerequisite; CI parity guard (2.5) must read the same file.
- **M2 — Weight-basis ambiguity is real and unresolved (step 1.10).** The current code's
  `calcWorkingWeight` (90–120% → IBW) contradicts the research weight-basis rule (≤120% → ABW). Flat
  kcal/kg goals (CKD, liver, high-protein, refeeding) and protein g/kg must each declare *which* weight
  they use. This changes real numbers. → Make 1.10 a **named decision in `intervention-goals.md`** (per
  goal: ABW vs IBW vs AjBW), then encode it in the fixture, then implement. Do not let the executor guess.
- **M3 — Recipe micro view "macros by default" target screen is unconfirmed.** The recipe *edit* page I
  read (`food-library/recipes/[id]/page.tsx`) already shows micros only. Issues 5/6 may mean the food
  *detail* view or the in-NCP recipe panel. → Phase 3 must first **locate the exact component** that
  defaults to macros; otherwise 3.3/3.4 fix the wrong screen. Add a "confirm target component" sub-step.
- **M4 — Pediatric goal logic is a stub.** `autofillPediatric` ignores `goalType`/`stage` entirely
  (`void goalType; void stage`). The research has real pediatric targets (CKD, diabetic, liver, SAM
  F-75/F-100, refeeding). The plan's Phase 1 focuses on adult fixes; pediatric goal-specific logic is not
  explicitly scoped. → Either add a Phase 1 sub-step for pediatric goal branching, or **explicitly defer**
  it with a documented limitation so it isn't silently shipped as "done."

## Minors
- **m1 — `displayed_nutrients` source of truth.** Phase 3.1 unions `intervention.displayed_nutrients` with
  `GOAL_MICRO_FLAGS`. Confirm the column is actually populated on save and that removing a row (3.4)
  persists to `displayed_nutrients`, else the X button won't survive reload.
- **m2 — ±10% on micros needs reporting completeness.** Phase 4.3 compares each prescribed nutrient, but
  recipes only carry micros that came from USDA. A recipe missing `sodium` can't be validated — the plan
  should state whether "unknown micro" counts as pass, fail, or "cannot validate (flag as data gap)."
  Recommend the last to avoid false confidence.
- **m3 — Reconciliation determinism.** Phase 4.4's residual-target re-pick uses random top-N elsewhere in
  the service; add a fixed seed in tests so the tolerance fixture is reproducible.
- **m4 — AI cost guardrails are described but not bounded numerically.** Phase 6.3 says "rate-limit" —
  specify the cap (e.g. ≤1 AI review per visit-pair, cached) so it's testable.

## Nits
- **n1 — Qty/Unit field types (Issue 12):** now resolved in plan 7.4 — Qty numeric, Unit dropdown, Status
  read-only. No further action.
- **n2 — Phase branch names** are suggested but not all phases listed; add branch names to Phases 3–7 for
  consistency.
- **n3 — Status banner duplication:** the top status banner and Part F markers must be kept in sync by
  hand; consider making Part F the single source and the banner a pointer.

---

## Checklist results
| Check | Result |
|---|---|
| 1. Correctness vs requirements | ✅ all 12 issues mapped to phases + acceptance criteria; targets traced to research |
| 2. Edge cases & error handling | 🟡 M2 (weight basis), m2 (missing micros), M4 (pediatric) need pinning |
| 3. Tests / coverage | ✅ golden-vector + parity fixture is the right spine; M1 ordering must hold |
| 4. Security | ✅ no secrets; AI payload is compact structured data; uses existing `ai_usage_logs` |
| 5. Performance | ✅ FE preview avoids per-keystroke network; rule-based monitoring is zero-token; bounded regen |
| 6. Readability / maintainability | ✅ single resumable artifact; shared fixture doubles as spec |
| 7. Docs updated | ✅ research doc cross-links plan; Phase 0 syncs `intervention-goals.md` |

## Overall summary + next actions
The plan **accommodates all 12 issues** and the cross-cutting concerns (accuracy, source-of-truth,
cost). With D1–D3 decided, the only true prerequisites before Phase 1 coding are:
1. **Author `prescription-targets.json`** (Phase 0) — the fixture every later test reads (M1).
2. **Pin the weight-basis rule per goal** in `intervention-goals.md` (M2).
3. **Decide pediatric scope** — implement goal branching or document the deferral (M4).
4. During Phase 3, **confirm the exact recipe screen** that defaults to macros (M3).

Next action: user approves → run `/superpowers-execute-plan` → start at Phase 0, honoring the M1
ordering constraint. No code should be written in this review workflow.
