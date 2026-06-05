# Intervention Goals — Clinical Reference Docs Design Spec

> Brainstorm artifact. Approved 2026-06-05.

**Goal:** Single markdown file documenting all intervention goal calculations, formulas, and disease_stage field mappings for both adult and pediatric populations.

**Output file:** `docs/logic/intervention-goals.md`

**Scope:** Research/reference only. No code, no migrations. Used to guide intervention page implementation and for inclusion in capstone paper.

---

## Decisions Made

| Decision | Choice | Reason |
|---|---|---|
| File count | 1 file | Simpler to cite, single Ctrl+F |
| Population coverage | Adult + Pediatric | System serves both; no logic conflicts |
| Conflict prevention | Separate formula blocks per population, never shared rows | Tagged [ADULT] / [PEDIATRIC] |
| disease_stage mapping | Included per goal | Defines exact enum values for interventions.disease_stage |
| Sources | Primary guidelines only (KDOQI, ADA, AHA, ASPEN, ESPEN, WHO, GLIM, NICE) | Credible, non-empty links |

---

## disease_stage Value Reference

| goal_type | disease_stage values |
|---|---|
| renal_diet | stage_1, stage_2, stage_3, stage_4, stage_5_predialysis, hemodialysis, peritoneal |
| diabetic_control | null |
| cardiac_diet | mild, moderate, severe |
| weight_loss | overweight, class_1, class_2, class_3 |
| weight_gain | mild, moderate, severe |
| high_protein | mild_stress, moderate_stress, severe_stress, burns |
| fluid_restriction | ckd_predialysis, ckd_hemodialysis, ckd_peritoneal, heart_failure_mild, heart_failure_severe, siadh |
| liver_disease | compensated, decompensated, encephalopathy_grade_1_2, encephalopathy_grade_3_4 |
| malnutrition | moderate, severe |
| custom | null |
