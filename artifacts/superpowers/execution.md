# Execution Log — NCP Diagnosis Page

## Files Created / Modified

| File | Action | Notes |
|---|---|---|
| `artifacts/superpowers/plan.md` | Created | Full plan with backend audit, G-NCP references |
| `frontend/services/diagnosisService.ts` | Created | Typed service: CRUD + AI suggest/approve |
| `frontend/app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx` | Replaced | Full 6-tab implementation |

## Backend Gaps Found (non-blocking for diagnosis page)
- `AIService::suggestDiagnoses` uses outdated model string `claude-haiku-20240307` — should be updated to `claude-haiku-4-5-20251001`. Functional but will use old model until updated.
- AI prompt in `AIService` is bare (just JSON dump) — for best results should use structured system prompt requesting JSON output with `suggestions` array containing `domain`, `label`, `etiology`, `signs`, `confidence`, `reasoning`.

## Diagnosis Page Architecture

### Tab Flow
```
Tab 1 (Table) ←→ Tab 2 (P) → Tab 3 (E) → Tab 4 (S) → Tab 5 (PES → Save)
                                                       ↑
                                               Tab 6 (AI) → Accept/Edit
```

### State Design
- `builder: BuilderState` — single state object for all PES builder state
- `diagnoses: Diagnosis[]` — loaded from API, mutated on save/delete
- PES auto-assembles via computed strings from builder; editable override in Tab 5
- AI tab reads patient.medical_diagnosis + existing diagnoses as `conditions[]`

### G-NCP Options Implemented
- NI: Direction (Inadequate/Excessive) + 9 nutrient options
- NC: 9 standardized clinical problems
- NB: 9 standardized behavioral-environmental problems
- Etiologies: 9 per domain
- Signs/Symptoms: 10 per domain

## Verified
- [ ] Page renders without TS errors (pending browser check)
- [ ] Tab 1 table shows diagnoses with filter and actions
- [ ] Tab 2-5 builder flow saves to API
- [ ] Tab 6 AI generates suggestions and accept/reject/edit works

---

# Execution Log — Nutrition Engine Overhaul (feat/nutri-engine-overhaul)

Plan: `artifacts/superpowers/nutrition-engine-overhaul-plan.md`. Heavy/clinical phases (0–2) run here;
app-surface phases (3–7) deferred to subagents pending user approval.

## Phase 0 — Decisions & spec (DONE)
- **Files:** `docs/logic/prescription-targets.json` (new, canonical spec + 90 frozen golden cases),
  `docs/logic/intervention-goals.md` (AP BMI default table, weight-basis rule, PDRI baselines, changelog),
  `artifacts/oracle_golden.py` (independent oracle).
- **What changed:**
  - Authored machine-readable engine contract `prescription-targets.json` (all adult goals×stages,
    baselines, AP BMI, weight_basis, activity factors) — single source of truth for both runtimes.
  - Resolved M2 (weight basis: energy/fluid→working wt >120%?AjBW:actual; protein→IBW) and recorded D1–D3.
  - M4: pediatric goal-specific logic explicitly DEFERRED (documented limitation).
  - Generated 90 golden cases via independent Python oracle; hand-verified patient A renal (IBW 66.71,
    E 2400, P 53, F 67, C 396, fluid 2600) — exact match.
- **Verify:** `python artifacts/oracle_golden.py` → "wrote 90 golden cases"; JSON validates (linter
  pretty-printed it). PASS.

## Phase 1 — Frontend engine accuracy + AP/PDRI (DONE)
- **Files:** `frontend/lib/nutritionCalculations.ts` (rewritten, signatures additive/backward-compatible),
  `artifacts/verify_ts_golden.mjs` + `artifacts/tsconfig.verify.json` (verification tooling).
- **What changed:**
  - Fixed `calcWorkingWeight` to the M2 rule (>120%→AjBW else actual; removed 90–120%→IBW band).
  - `classifyNutritionalStatus` → Asia-Pacific cut-points (Normal <23, OW <25, Obese I <30, Obese II ≥30)
    with D2 weight-loss stage suggestions (overweight/class_1/class_2/class_3 split at 35).
  - `diabetic_control` now honors stage: stage_2 −500 kcal + floor; stage_3 protein 0.8; +fiber/sodium/free-sugar.
  - `liver_disease` protein fixed to 1.35 g/kg ALL stages (was 0.9/0.65 — clinically wrong); updated notes.
  - `renal_diet` energy default 30 kcal/kg (was 32.5); per-stage sodium added.
  - Refeeding notes (weight_gain/malnutrition severe) → corrected day-4–7 wording (D12).
  - Added fiber_g/sodium_max_mg/free_sugar_max_pct/cholesterol_max_mg to Prescription; fat% per spec.
  - GOAL_MICRO_FLAGS extended (diabetic +sodium+free_sugars; cardiac +potassium); ALL_MICROS +free_sugars.
- **Verify:**
  - `tsc -p artifacts/tsconfig.verify.json` → clean compile.
  - `node artifacts/verify_ts_golden.mjs` → **GOLDEN: 90 pass / 0 fail (of 90)**.
  - `npx eslint lib/nutritionCalculations.ts` → clean (exit 0).


