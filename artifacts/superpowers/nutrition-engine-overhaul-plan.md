# Nutrition Engine + NCP Overhaul — Brainstorm & Implementation Plan

> **Superpowers artifact.** Brainstorm + plan + resumable verification checklist for the 12 issues
> raised 2026-06-11. Follows `.agent/rules/superpowers.md` (plan gate, mandatory verification, TDD,
> review pass). **Plan gate: code is NOT yet modified. Awaiting approval → then `/superpowers-execute-plan`.**
>
> **Resumability:** this file is the single source of truth for the effort. If the session/agent
> changes, a new agent can read this top-to-bottom and continue from the **Verification Checklist**
> (Part F) — each issue has a status marker and per-step checkboxes.

**Created:** 2026-06-11 · **Owner:** RND + dev · **Status:** 🟢 IMPLEMENTED — all phases landed on `feat/nutri-engine-overhaul` (only 3.3 deferred by decision; run migrations in a real env)

---

## Status banner (update this line as work proceeds)

```
PHASE 0 (docs/decisions): ✅ DONE (commit: Phase 0 spec)
PHASE 1 (calc accuracy + AP/PDRI): ✅ DONE — 90/90 golden + lint (commit: Phase 1)
PHASE 2 (backend source of truth): ✅ DONE (core) — 90/90 PHP golden; endpoint added; FE wiring 2.4 deferred to subagents
PHASE 4 (meal plan algo 4/8): ✅ BACKEND DONE (subagent A, commit 7c7bc19) — verified 31 new tests + golden 90/90
PHASE 5 (assessment data 9): ✅ DONE — BE (subagent A) + FE fields (commit 9e40735) + UpdateAssessmentRequest PATCH fix
PHASE 3 (micro display UX 3/5/6/7): 🟡 MOSTLY DONE — 3.1 empty-prompt ✅ · 3.2 water-with-macros ✅ (already in place) · 3.4 removable+locked-required rows ✅ · 3.5 GOAL_MICRO_FLAGS ✅. ⬜ 3.3 recipe-view goal-relevant default DEFERRED — needs M3 decision (food-library/recipes/[id] is patient-agnostic; no NCP goal context to thread). Awaiting decision on target screen.
PHASE 6 (monitoring + AI review 10): ✅ DONE — rule-based delta engine (zero-token) + goal eval + optional Haiku narrative (cached/rate-limited/logged) + MonitoringSummaryCard. Unit test 6/6. DB feature tests blocked locally (no sqlite driver).
PHASE 7 (inventory 11/12): ✅ DONE (commit cd78f1a)
PHASE 2.4 (FE save-from-backend): ✅ DONE — TS preview + BE-authoritative persist + dev drift guard + edema warning
NOTE: migrations 2026_06_11_000001..04 NOT run locally (no sqlite driver) — run `php artisan migrate` in a real env before frontend work relies on the new columns.
```
> Heavy/clinical phases (0–2) complete on branch `feat/nutri-engine-overhaul`. App-surface phases
> (3–7) + the FE save-from-backend wiring (2.4) are queued for subagents pending user approval.

---

# PART A — Brainstorm

### Goal
Make the nutrition prescription engine **hospital-grade accurate and Asia-Pacific/PDRI-localized**, give it
a **backend source of truth**, fix the meal-plan algorithm and micronutrient/fluid UX, capture the
**assessment data the formulas actually need**, and add a **cheap AI-assisted monitoring** loop — all
flowing cleanly through the NCP cycle (Assessment → Diagnosis → Intervention → Monitoring/Evaluation).

### Constraints
- **Hospital-grade:** every formula target must match `docs/logic/intervention-goals.md` +
  `docs/logic/intervention-goals-asia-pacific-research.md` **exactly**. No "close enough."
- **Stack:** Laravel (backend, MySQL), Next.js (App Router — **this is a modified Next.js; read
  `node_modules/next/dist/docs/` before writing frontend code, per `frontend/AGENTS.md`**), React 19,
  TS, Tailwind v4, Lucide. Use **laravel-boost `search-docs`** for version-specific Laravel APIs.
- **Backward compatibility:** existing NCP records, interventions, meal plans must keep working;
  migrations must be reversible.
- **Cost:** AI monitoring must be near-free per use (small structured payloads, cheap model, on-demand).
- **No silent data loss** (superpowers safety rule). Migrations that drop columns must be reversible.

### Known context (what exists today — file inventory)
| Area | File | Current state |
|---|---|---|
| Prescription calc | `frontend/lib/nutritionCalculations.ts` | All formulas; **frontend-only**; several values stale vs research |
| Prescription form | `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/NutritionPrescriptionForm.tsx` | Consumes calc |
| Micro toggle | `.../intervention/[ncpId]/_components/MicronutrientToggle.tsx` | Manual checkbox popover over `ALL_MICROS` |
| Meal plan UI | `.../intervention/[ncpId]/_components/MealPlanSection.tsx` | Renders macro cards + micro section |
| Meal plan algo | `backend/app/Services/MealPlanService.php` | Euclidean macro-ratio scoring; **no ±10% check**; single slot dist |
| Recipe view | `frontend/app/(rnd)/food-library/recipes/[id]/page.tsx` | Macro cards + micro popup (grouped) |
| Inventory page | `frontend/app/(rnd)/food-service/inventory/page.tsx` | Table w/ Expiry column |
| Inventory svc | `frontend/services/inventoryService.ts` | `getStockStatus`/`getRowHighlight` use expiry |
| Assessment | `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx` | Captures most data |
| Monitoring | `.../monitoring/[ncpId]/_components/{VisitTrendsChart,LogVisitForm}.tsx` | Charts + visit logging |
| Schema | `backend/database/migrations/*` | assessments, interventions, recipes(`category` single), food_items(`water_g`), inventory(`expiry_date`) |
| Clinical refs | `docs/logic/intervention-goals.md` + `...-asia-pacific-research.md` | Source of truth for targets |

### Risks
- **Clinical-safety regressions** — a wrong macro/protein number reaches a real patient. → mitigate with
  **golden-vector tests** (Part C Phase 1/2) asserting outputs against research tables.
- **Frontend/backend drift** — the core risk Issue 2 names. → single canonical engine + shared fixture
  parity test.
- **Dropping inventory `expiry_date`** could lose data. → keep the column, just stop surfacing it (soft
  removal) rather than a destructive drop.
- **AP reclassification shifts every patient's status/stage** — clinically intended, but must be a
  **deliberate, signed-off** flip (research §9), not a silent default change. → Phase 0 decision gate.
- **Modified Next.js** — assumptions from training data may be wrong. → read local Next docs first.

### Cross-cutting options & recommendations
**Engine location (Issue 2):**
- *Opt A — Backend canonical, frontend mirror for preview (RECOMMENDED).* PHP `NutritionPrescriptionService`
  is the source of truth; persisted values always come from the API. Frontend keeps a thin TS copy for
  instant live preview only, and a **shared JSON fixture** drives a parity test in both runtimes so they
  cannot drift. Pros: responsive + authoritative + drift-proof. Cons: two implementations (mitigated by
  fixture test).
- *Opt B — Backend-only, frontend always calls API (debounced).* Pros: one implementation. Cons: laggy
  live preview, network on every keystroke.
- *Opt C — Node/edge shared module imported by both.* Pros: literally one codebase. Cons: PHP can't import
  TS; would require a JS sidecar — over-engineered for this app.
→ **Recommend A.**

**AP BMI default (Issue 1, research §2/§9):** flip the system default to **Asia-Pacific** cut-points, keep
Western as a labeled reference. Requires RND sign-off (Phase 0).

**AI monitoring (Issue 10):** rule-based delta summary first (free), with an **optional** one-click AI
narrative using Haiku on a compact JSON diff of two visits. → cheap, graceful, no per-visit token burn.

### Acceptance criteria (high level)
1. Engine outputs match research tables for every goal × stage (golden test green, both runtimes).
2. Persisted prescriptions come from the backend; FE preview equals BE to the rounded value.
3. Micro sections (meal plan + recipe + prescription) default to **goal-relevant nutrients only**, never
   macros; rows removable except goal-required; empty-state prompt when none.
4. Fluid/water shown wherever macros are shown; aggregated from `water_g`; compared to `fluid_ml`.
5. Meal-plan daily totals land within **±10%** of every prescribed nutrient or are flagged.
6. Recipes can belong to multiple meal types; algo respects slot eligibility.
7. Assessment captures every input the engine consumes (incl. stress factor, edema, pregnancy).
8. Monitoring compares last vs current visit; AI review is opt-in and cheap.
9. Inventory: no Expiry column; headers `Qty · Unit · Unit/Cost · Status · Actions`; sensible unit list;
   inline edit; status auto-derived and not user-editable.

---

# PART B — Decisions (carried from clinical research)

These come from `docs/logic/intervention-goals-asia-pacific-research.md`. **D1–D3 DECIDED 2026-06-11**
(developer call, delegated by user); remaining items are research-confirmed. RND should ratify D1–D3 at
clinical handover but they are no longer blocking.

| # | Decision | Value |
|---|---|---|
| D1 ✅ | BMI classification | **Asia-Pacific is the system default**; Western kept as a labeled reference column. <18.5 UW · 18.5–22.9 Normal · 23–24.9 Overweight · 25–29.9 Obese I · ≥30 Obese II |
| D2 ✅ | Weight-loss stage→BMI remap | **Keep the 4 existing `disease_stage` enum values, re-cut to AP** (no enum migration, backward-compatible): `overweight` 23–24.9 · `class_1` 25–29.9 · `class_2` 30–34.9 · `class_3` ≥35 |
| D3 ✅ | Diabetic `stage_2` trigger | BMI **≥ 23** (was ≥25) |
| D4 | CKD baseline energy | individualized 25–35 kcal/kg, **default 30** (was flat 32.5) |
| D5 | Liver encephalopathy protein | **1.2–1.5 g/kg** (restriction contraindicated); temp 1.0 only if intolerant — fixes current 0.9/0.65 |
| D6 | Baseline macro split (PDRI) | carb **55–75%**, fat **15–30%**, protein 10–15% — for *normal* diets; disease caps override |
| D7 | Adult fiber baseline | **20–25 g/day** (PDRI; was 25–38) |
| D8 | Baseline sodium | **< 2000 mg** (PDRI/WHO; was 2300) |
| D9 | Free sugars | add cap **< 10% E**, surface in diabetic goal |
| D10 | Adult protein | clinical floor 0.8 g/kg IBW (disease states); healthy maintenance ~1.0–1.2 g/kg (PDRI) |
| D11 | GLIM low-BMI (Asian) | <18.5 (<70y)/<20 (≥70y); severe <17.0 (<70y)/<17.8 (≥70y) |
| D12 | Refeeding ramp wording | **5–10 → 30–35 kcal/kg, full needs by day 4–7** (remove "33% every 3–5 days") |
| D13 | Anthropometry cut-points | waist ≥90/≥80 cm; AWGS ASMI 7.0/5.7; calf <34/<33 cm |

---

# PART C — Phased Implementation Plan

> TDD throughout: write failing test → minimal code → green → commit. Small steps. Each step has a verify.
> Branch per phase (`feat/nutri-p1-accuracy`, …). Never commit to `main` directly.

## PHASE 0 — Decisions & doc sync (no code)
**Files:** `docs/logic/intervention-goals.md`, `docs/logic/intervention-goals-asia-pacific-research.md`
- [x] **D1–D3 decided (2026-06-11):** AP default + Western reference; weight-loss stages re-cut to AP
      (4 enum values kept); diabetic `stage_2` at BMI ≥ 23. RND to ratify at handover (non-blocking).
- [ ] Record D1–D3 + remaining §9 answers (D4 obese-class count is now resolved by D2) in research §9.
- [ ] Fold approved AP/PDRI values into `intervention-goals.md` (BMI table, fiber, sodium, free-sugars,
      liver protein, CKD default, refeeding wording); bump its changelog + `Last updated`.
- [ ] **(M2) Pin the weight-basis rule per goal** in `intervention-goals.md`: for each flat-kcal/kg goal
      (CKD, liver, high-protein, refeeding) and each protein g/kg target, state explicitly whether it uses
      **ABW, IBW, or AjBW**. Resolves the current `calcWorkingWeight` (90–120%→IBW) vs research
      (≤120%→ABW) contradiction. This is a prerequisite for the fixture below — the executor must not guess.
- [ ] **(M4) Decide pediatric scope:** either (a) add pediatric goal-specific branching to Phase 1, or
      (b) explicitly defer it as a documented limitation (current `autofillPediatric` ignores
      goal/stage). Record the choice here so pediatric isn't silently shipped as "done."
- [ ] Produce **`docs/logic/prescription-targets.json`** — machine-readable target table (goal × stage →
      {energy_method, weight_basis, kcal/kg or modifier, protein g/kg, fat%, carb%, fiber, sodium, fluid,
      micros}). This becomes the **shared fixture** for the parity test (Phase 2) and the spec the engine
      must match. **(M1) Hard prerequisite:** this file must exist and validate before ANY engine edit in
      Phase 1/2; the parity guard (2.5) reads this same file.
- **Verify:** doc changelog updated; JSON validates; weight-basis + pediatric scope recorded; RND
  ratifies D1–D3 + targets (note in research §9).

## PHASE 1 — Calculation accuracy + AP/PDRI (Issue 1)
**Files:** `frontend/lib/nutritionCalculations.ts` (+ new `frontend/lib/__tests__/nutritionCalculations.test.ts`)
- [ ] **1.1** Add golden-vector test from `prescription-targets.json`: for each goal×stage + a fixed test
      patient set (adult M/F, pediatric, obese, underweight), assert `autofillPrescription` output. Run →
      **must fail** (current code is stale). *(TDD red)*
- [ ] **1.2** Fix `classifyNutritionalStatus` to **Asia-Pacific** cutoffs (D1); add age≥70 GLIM low-BMI
      band (D11); keep "more severe of BMI/%IBW" convention.
- [ ] **1.3** Fix `diabetic_control` to honor stage: `stage_2` applies −500 kcal + floors; `stage_3`
      protein ≈0.8 g/kg + sodium <2000; add free-sugars note.
- [ ] **1.4** Fix `liver_disease` protein to 1.2–1.5 g/kg all stages (D5); update encephalopathy note.
- [ ] **1.5** CKD energy default → 30 kcal/kg, expose 25–35 range (D4).
- [ ] **1.6** Weight-loss deficits/stage map → AP (D2); re-verify floors (F1200/M1500).
- [ ] **1.7** Refeeding notes (weight_gain severe, malnutrition severe) → D12 wording.
- [ ] **1.8** Baseline macros: fat default within 15–30%, carb remainder within 55–75% (D6); add
      `fiber_g` (D7), `sodium_max_mg` (D8), `free_sugar_max_pct` (D9) to `Prescription` interface +
      autofill (relevant goals).
- [ ] **1.9** Relabel pediatric protein table source note → PDRI; cross-check g/day vs research §5b.
- [ ] **1.10** Reconcile `calcWorkingWeight` vs doc weight-basis rule (≤120%→ABW, >120%→AjBW) — document
      which weight each goal's kcal/kg and protein uses; fix mismatches.
- **Verify:** `cd frontend && npm test nutritionCalculations` → **all green**; `npm run lint`.

## PHASE 2 — Backend source of truth (Issue 2)
**Files:** `backend/app/Services/NutritionPrescriptionService.php` (new), controller + route, tests.
- [ ] **2.1** Port the (now-correct) engine to PHP `NutritionPrescriptionService::autofill($goal,$stage,$metrics)`.
- [ ] **2.2** Backend test consuming the **same `prescription-targets.json`** fixture → assert PHP output
      equals fixture (TDD).
- [ ] **2.3** Endpoint `POST /api/ncp/{ncp}/intervention/autofill` returns authoritative prescription;
      persist intervention values from **backend** result, not the FE number.
- [ ] **2.4** FE: on goal/stage/metrics change show TS preview instantly; on **save**, call the API and
      store the BE value. Add a dev-only assertion that |FE−BE| ≤ rounding.
- [ ] **2.5** **Parity CI guard:** a test (or script) that loads `prescription-targets.json` and checks
      both runtimes still match it — fails the build if either drifts.
- **Verify:** `cd backend && php artisan test --filter=NutritionPrescription`; FE save writes BE values
  (network tab); parity test green.

## PHASE 3 — Micro/fluid display UX (Issues 3, 5, 6, 7)
**Files:** `MealPlanSection.tsx`, `MicronutrientToggle.tsx`, `recipes/[id]/page.tsx`,
`nutritionCalculations.ts` (GOAL_MICRO_FLAGS), recipe backend model (water aggregation).
- [ ] **3.1 (Issue 3)** Meal-plan micro section: default-display = `intervention.displayed_nutrients ∪
      GOAL_MICRO_FLAGS[goal]`, **excluding macros**. Empty → prompt "RND: add relevant micronutrients."
- [ ] **3.2 (Issue 7)** Aggregate `water_g` in recipe totals (backend `Recipe` accessor `total_water`)
      and meal-plan daily totals; **show Fluid/Water alongside macros everywhere macros render**
      (recipe macro cards, meal-plan macro cards, prescription); compare daily water vs `fluid_ml`.
- [ ] **3.0 (M3) Confirm target component first:** the recipe *edit* page (`recipes/[id]/page.tsx`)
      already shows micros-only — locate the exact screen that defaults to **macros** (likely the food
      detail view or in-NCP recipe panel) before applying 3.3/3.4, so the fix lands on the right view.
- [ ] **3.3 (Issue 5)** Recipe micro profile: default to **goal-relevant** micros when opened inside an
      NCP context (pass patient goal); patient-agnostic library view keeps a small default set. Never
      default to macros.
- [ ] **3.4 (Issue 6)** Add per-row **X** to remove a micro row; rows in `GOAL_MICRO_FLAGS[goal]` render
      the X **disabled with tooltip** "Required by the {goal} intervention goal."
- [ ] **3.5** Extend `GOAL_MICRO_FLAGS`: diabetic += `sodium`,`free_sugars`; cardiac += `potassium`
      (DASH); liver += `fluid` — align to research.
- **Verify:** prescribe renal goal → only K/Phos/Na show; remove a non-required row works, required row
  X is disabled; water appears on macro rows; empty goal shows prompt. Use preview tools per workflow.

## PHASE 4 — Meal-plan algorithm (Issues 4, 8)
**Files:** `backend/app/Services/MealPlanService.php`, recipe migration (`meal_types`), recipe UI, tests.
- [ ] **4.1 (Issue 8)** Migration: add `recipes.meal_types` JSON (e.g. `["breakfast","lunch","dinner"]`);
      backfill from existing `category`; keep `category` for display. Recipe form = multi-select chips.
- [ ] **4.2** `MealPlanService` filters slot candidates by `meal_types` eligibility (snacks slots accept
      `snack`/`any`).
- [ ] **4.3 (Issue 4)** Post-generation **±10% validation**: compute per-day totals (energy + each
      prescribed nutrient incl. micros + water), compare to prescription; mark `MealPlanDay.flagged` and
      store `variance` JSON for any nutrient outside ±10%. **(m2)** A prescribed nutrient that a recipe
      doesn't report = **"cannot validate — data gap"** (distinct flag), never a silent pass. **(m3)**
      tests use a fixed RNG seed so the tolerance fixture is reproducible.
- [ ] **4.4** Bounded **reconciliation pass**: for flagged days, re-pick the worst-contributing slot using
      a **residual-target** score (bias toward closing the remaining gap) up to N iterations; re-validate.
      *(Better than pure per-slot Euclidean — accounts for cumulative daily total. Inspired by how meal
      planners do "remaining macros" budgeting.)*
- [ ] **4.5** Surface variance in `MealPlanSection` (per-day badge: "within tolerance" / "Na +14% ⚠").
- **Verify:** `php artisan test --filter=MealPlanService` incl. a fixture where a naive pick busts ±10%
  and the reconciliation pass brings it in or flags it; generate a plan in UI and confirm badges.

## PHASE 5 — Assessment data completeness (Issue 9)
**Files:** assessment migration (new), assessment page, `PatientMetrics` mapping.
- [ ] **5.1** Audit: engine consumes age, sex, weight, height, activityFactor, stressFactor. Confirmed
      gaps → **`stress_factor`** (needed by `high_protein`; currently no assessment field),
      **`edema_present`** (invalidates weight math), **`pregnancy_lactation_status`** (energy/protein
      add-ons), **`calf_circumference_cm`** (AWGS muscle proxy, D13). Add columns (nullable, reversible).
- [ ] **5.2** Confirm `physical_activity_level` enum values map 1:1 to `ACTIVITY_FACTORS` keys; fix any
      mismatch so the dropdown actually drives TEE.
- [ ] **5.3** Add stress-factor dropdown (maps to research §1 table) shown when goal context = high_protein
      / acute illness; add edema + pregnancy fields to Anthropometric/Client-History sections.
- [ ] **5.4** Thread new fields into `PatientMetrics` → engine; pregnancy adds +300/+500 kcal, +27 g
      protein (PDRI); edema flags weight as unreliable in UI.
- **Verify:** fill assessment → intervention autofill consumes stress/pregnancy correctly (golden test
  patient with stress factor); edema flag visible; activity dropdown changes TEE.

## PHASE 6 — Monitoring & evaluation + cheap AI review (Issue 10)
**Files:** monitoring components, new `MonitoringSummaryService` (backend), AI endpoint.
- [ ] **6.1** Define a compact **visit metric set** (weight, BMI, %IBW, intake % of Rx energy/protein, key
      labs, MUAC/calf, status) persisted per `monitoring` row so deltas are computable without re-parsing.
- [ ] **6.2** **Rule-based delta summary (free):** backend computes last-vs-current diffs and emits a
      structured "what changed + flags" object (e.g. "weight −1.2 kg (−2%) ↓; energy intake 78% of Rx").
      Render in monitoring UI. This is the default — **zero tokens**.
- [ ] **6.3** **Optional AI narrative (cheap):** one-click button sends only the small JSON diff (not full
      NCP text) to **Haiku** with a tight prompt → 2–3 sentence clinical interpretation + suggested next
      action. Cache per visit-pair; rate-limit; log tokens via existing `ai_usage_logs`.
- [ ] **6.4** Evaluation tie-in: compare current metrics vs the intervention's goal targets → "goal
      met / partial / not met" indicator that flows into the NCP Evaluation step.
- **Verify:** log two visits → rule-based summary shows correct deltas with no AI call; AI button returns
  a short narrative and records a small token count; goal-met indicator reflects targets.

## PHASE 7 — Inventory (Issues 11, 12)
**Files:** `inventory/page.tsx`, `inventoryService.ts`, (optional migration to relax `expiry_date`).
- [ ] **7.1 (Issue 11)** Remove **Expiry** column; headers → `Qty · Unit · Unit/Cost · Status · Actions`.
- [ ] **7.2** Strip expiry from `getStockStatus`/`getRowHighlight`; `StockStatus` becomes `ok | low |
      no_stock | untracked` (drop `expiring`). Keep `expiry_date` column in DB (soft-remove, reversible).
- [ ] **7.3** Unit dropdown = small curated list: `pc, pack, bundle, g, kg, mL, L, serving` (no bloat).
- [ ] **7.4 (Issue 12)** Inline edit: clicking **Edit** turns the row's Qty/Unit/Unit-Cost fields
      editable in place (no separate element); **Status is not editable** (auto-derived from qty vs
      threshold → `low`/`no_stock`/`ok`). Save = PATCH; cancel reverts.
      **Field types (decided 2026-06-11):** Qty = **numeric input** (free number); Unit = **dropdown**
      (curated list from 7.3); Unit/Cost = numeric input; Status = read-only badge.
- **Verify:** table shows 5 columns, no Expiry; edit row inline, status read-only and auto-updates when
  qty crosses threshold; unit dropdown shows curated list; `npm run lint`.

---

# PART D — "Better service" additions (proposed, optional)
- **D-a Prescription provenance:** store engine version + input snapshot on each intervention so a
  prescription is reproducible/auditable later (hospital-grade traceability).
- **D-b Range display, not just point:** show the target as a band (e.g. "energy 1800–1980; using 1900")
  so RNDs see the clinically acceptable window, not a false-precision single number.
- **D-c Allergen + religion hard-filter in meal plan** already partially present (allergies) — extend to
  `patients.religion` dietary law filtering in `MealPlanService`.
- **D-d Water-intake adequacy widget** in monitoring (estimated intake vs `fluid_ml`) using `water_g`.
- **D-e Unit tests as living spec:** the `prescription-targets.json` fixture doubles as documentation for
  the capstone paper.

---

# PART E — Risks & Rollback
| Risk | Mitigation | Rollback |
|---|---|---|
| Wrong number reaches patient | Golden-vector tests gate every change | Revert phase branch |
| FE/BE drift | Shared fixture parity test in CI | Revert; FE falls back to API value |
| AP flip surprises clinicians | Phase 0 sign-off; Western shown as reference | Toggle default back via `bmi_classification_system` |
| Inventory expiry data loss | Soft-remove (keep column) | Re-add column to UI |
| Meal-plan regen loops | Bounded iterations + flag-and-stop | Disable reconciliation, keep flagging |
| AI cost creep | Rule-based default; Haiku; cache; rate-limit; `ai_usage_logs` | Hide AI button (flag) |

---

# PART F — VERIFICATION CHECKLIST (resumable — start here if resuming)

> Legend: ⬜ not started · 🟡 in progress · ✅ done · ⛔ blocked. Update the marker + check boxes as you go.
> A new agent: read Parts A–C for context, then work top-down here. Run the **Verify** of each phase.

### Phase 0 — Decisions & docs ⬜
- [ ] RND signed off D1–D3 (recorded in research §9)
- [ ] `intervention-goals.md` updated (BMI/fiber/sodium/sugars/liver/CKD/refeeding) + changelog bumped
- [ ] `docs/logic/prescription-targets.json` created and validates

### Phase 1 — Calc accuracy + AP/PDRI ⬜
- [ ] Golden test added and initially RED (1.1)
- [ ] AP BMI classification (1.2) · diabetic stages (1.3) · liver protein (1.4) · CKD default (1.5)
- [ ] weight-loss AP remap (1.6) · refeeding wording (1.7) · fiber/sodium/sugars added (1.8)
- [ ] pediatric PDRI relabel (1.9) · weight-basis reconciliation (1.10)
- [ ] `npm test nutritionCalculations` GREEN · `npm run lint` clean

### Phase 2 — Backend source of truth ⬜
- [ ] PHP `NutritionPrescriptionService` ports engine (2.1) · backend fixture test GREEN (2.2)
- [ ] autofill endpoint + persist BE value (2.3) · FE preview + save-from-BE (2.4) · parity guard (2.5)
- [ ] `php artisan test --filter=NutritionPrescription` GREEN

### Phase 3 — Micro/fluid UX ⬜
- [ ] Meal-plan micros default to goal nutrients, no macros, empty prompt (3.1)
- [ ] Water aggregated + shown with macros everywhere; vs fluid_ml (3.2)
- [ ] Recipe micro default goal-relevant (3.3) · removable rows + locked required (3.4) · flags extended (3.5)
- [ ] Verified in preview (renal goal → K/Phos/Na only; water visible)

### Phase 4 — Meal-plan algorithm ⬜
- [ ] `meal_types` migration + backfill + multi-select UI (4.1) · slot eligibility filter (4.2)
- [ ] ±10% post-gen validation + flag/variance (4.3) · reconciliation pass (4.4) · UI variance badges (4.5)
- [ ] `php artisan test --filter=MealPlanService` GREEN incl. tolerance fixture

### Phase 5 — Assessment data ⬜
- [ ] Migration: stress_factor, edema_present, pregnancy_lactation_status, calf_circumference_cm (5.1)
- [ ] activity enum ↔ ACTIVITY_FACTORS verified (5.2) · new fields in UI (5.3) · threaded to engine (5.4)
- [ ] Verified: stress/pregnancy affect autofill; edema flagged

### Phase 6 — Monitoring + AI review ⬜
- [ ] Visit metric set persisted (6.1) · rule-based delta summary, zero-token (6.2)
- [ ] Optional Haiku narrative, cached + logged (6.3) · goal-met evaluation indicator (6.4)
- [ ] Verified: deltas correct w/o AI; AI button cheap; goal indicator correct

### Phase 7 — Inventory ⬜
- [ ] Expiry column removed; headers Qty·Unit·Unit/Cost·Status·Actions (7.1)
- [ ] expiry stripped from status logic; `expiring` removed (7.2) · curated unit list (7.3)
- [ ] inline edit; status read-only auto-derived (7.4) · `npm run lint` clean

### Global done-criteria
- [ ] All phase Verify commands green · superpowers **review pass** done (Blocker/Major/Minor/Nit listed)
- [ ] `docs/logic/*` and this artifact reflect final state · status banner shows all phases done

---

## Execution note (superpowers gate)
Per `.agent/rules/superpowers.md`: **do not begin implementation until the user approves this plan and
runs `/superpowers-execute-plan`.** Implement phase-by-phase, TDD, commit per green step, and run a
review pass before the final response.

*Sources for all clinical targets: `docs/logic/intervention-goals.md` and
`docs/logic/intervention-goals-asia-pacific-research.md` (WHO Asia-Pacific BMI, PDRI 2015, KDOQI 2020,
ADA, ESPEN/EASL, GLIM/AWGS, NICE CG32).*
