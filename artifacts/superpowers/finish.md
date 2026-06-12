# Finish Notes — Nutrition Engine Overhaul (Heavy Phases 0–2)

**Branch:** `feat/nutri-engine-overhaul` · **Date:** 2026-06-11 · **Scope:** clinical/heavy phases only.
App-surface phases 3–7 (+ FE wiring 2.4) intentionally deferred to subagents pending approval.

## Verification commands run + results
| Command | Result |
|---|---|
| `python artifacts/oracle_golden.py` | wrote 90 golden cases (independent oracle) |
| `tsc -p artifacts/tsconfig.verify.json` | clean compile (no errors) |
| `node artifacts/verify_ts_golden.mjs` | **GOLDEN: 90 pass / 0 fail** (frontend engine) |
| `npx eslint lib/nutritionCalculations.ts` | clean (exit 0) |
| `php artisan test --filter=NutritionPrescriptionServiceTest` | **90 passed / 450 assertions** (backend engine) |
| direct PHP 90-case loop | 90 pass / 0 fail |
| `php -l` (service, controller, tests) | no syntax errors |
| `php artisan route:list` | autofill route registered |

## Summary of changes
- **Phase 0:** `docs/logic/prescription-targets.json` — canonical spec + 90 frozen golden cases (the
  contract both engines must match). AP BMI default, weight-basis rule (M2), D1–D3 decisions, pediatric
  deferral (M4) recorded in `intervention-goals.md` (+changelog) and the spec.
- **Phase 1:** `frontend/lib/nutritionCalculations.ts` rewritten to match spec — AP classification,
  weight-basis fix, diabetic stage handling, **liver protein corrected 0.9/0.65 → 1.35** (was clinically
  wrong), renal default 30, PDRI fiber/sodium/free-sugar, corrected refeeding wording. Backward-compatible
  signatures.
- **Phase 2:** `NutritionPrescriptionService.php` (authoritative PHP engine) + golden test + `autofill`
  endpoint (`POST /api/rnd/ncp-records/{ncp}/intervention/autofill`). Both runtimes assert the same
  fixture → drift-proof.

## Review pass (Blocker/Major/Minor/Nit)
- **Blocker:** none.
- **Major:** none open in delivered scope. (Pediatric goal-specific logic deferred by decision M4 — not a
  silent gap; documented.)
- **Minor:** the JSON spec was pretty-printed by the editor's linter (cosmetic). The `note`/`fiber_g`/
  `sodium_max_mg` fields are computed but not yet surfaced in UI (that's Phase 3).
- **Nit:** `artifacts/tsbuild/` is generated; gitignored.

## Known limitations / follow-ups
1. **DB-backed Feature tests can't run in this CLI** — `could not find driver (sqlite :memory:)` is a
   pre-existing environment issue affecting ALL Feature tests. The 2 new autofill endpoint tests are
   written and lint-clean; run them in CI / an env with the sqlite PDO driver enabled.
2. **Phase 2.4 (FE save-from-backend)** not done — `NutritionPrescriptionForm.tsx` still uses the TS
   value on save. Subagent task: call the autofill endpoint and persist the backend result; keep TS for
   live preview.
3. App-surface phases 3–7 unstarted (micro/fluid UX, meal-plan ±10%/multi-category, assessment fields,
   monitoring AI, inventory) — queued for subagents.

## Manual validation (when a full env is available)
- [ ] `php artisan test` (full) in an env with sqlite → new endpoint tests green.
- [ ] Open intervention page → autofill calls backend → values match the TS preview.
