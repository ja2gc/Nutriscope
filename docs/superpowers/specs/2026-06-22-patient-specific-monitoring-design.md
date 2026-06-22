# Patient-Specific Monitoring & Evaluation — Design

**Date:** 2026-06-22
**Status:** Approved (design); implementation plan pending
**Goal:** Make the Monitoring & Evaluation step track, chart, and AI-review exactly the indicators relevant to each patient — derived from everything gathered across the NCP (PES diagnoses, nutrition prescription, assessment-flagged abnormalities, intervention goal, and calculated anthropometrics) — instead of the current `goal_type`-only lab set.

---

## Problem with current behavior

- Monitored/charted labs are chosen solely by `intervention.goal_type` via `GOAL_LAB_FLAGS` (e.g. `weight_loss` → no labs at all).
- Charts (`VisitTrendsChart`) plot only per-visit monitoring `lab_values`; the initial assessment is never the first data point, so trends don't run from intake → follow-ups.
- Assessment-flagged abnormal labs and calculated anthropometrics (IBW, %IBW, BMR, TEE) are not carried into monitoring.
- The monitoring AI compares only the two most recent visits, not the trajectory across all visits.
- Lab reference ranges are duplicated (inlined in `AiDiagnosisController`, mirrored in `MonitoringSummaryService` and frontend `LAB_REFERENCE_RANGES`).

## Approved decisions

1. **Indicator source** = union of: flagged-abnormal assessment labs + prescription nutrients + goal-flagged labs + PES-implied labs + calculated anthropometrics from the assessment (IBW, BMI, BMR, TEE, %IBW, etc.).
2. **Baseline** = the assessment, labeled **"Visit 1"**, seeded as the first point of every trend; monitoring entries are **"Follow-up 1, 2, …"**.
3. **Monitoring AI** = click-to-generate; receives the **full trajectory across all visits for the tracked set only** (plus PES, goal, prescription targets); suggests a course of action.
4. **Visit form** shows exactly the patient's tracked set; calculated values (BMI, %IBW) auto-recompute from entered weight + height.

## Architecture — Approach A (backend single source of truth)

A backend `MonitoringPlanService::build(NcpRecord)` produces one **Monitoring Plan** object, served at `GET /api/rnd/ncp-records/{id}/monitoring-plan`. The visit form, charts, progress tracker, and AI all consume this one object — no logic duplicated across PHP/TS.

### Monitoring Plan object

```
{
  visits: [
    { label: "Visit 1",     type: "assessment", date },
    { label: "Follow-up 1", type: "monitoring",  id, date },
    ...
  ],
  pes_statements: string[],          // active diagnoses (header + AI)
  goal_type: string | null,
  nutritional_status: string | null,
  indicators: [
    {
      key, label, unit,
      category: "lab" | "anthro" | "intake",
      sources: string[],             // any of: flagged_abnormal, goal, pes, prescription, calculated
      reference: { min?: number, max?: number, lowerIsBetter?: bool },  // sex/age-adjusted
      target: number | null,         // prescription target (intake only)
      series: [ { visit: string, value: number|null, status: string }, ... ],
      latest_status: "met" | "in_progress" | "not_met" | "no_data"
    }
  ]
}
```

### Indicator derivation (union, deduped by key)

- **Labs** = flagged-abnormal assessment labs (`LabFlagService`) ∪ `GOAL_LAB_FLAGS[goal_type]` ∪ PES-implied labs. Baseline value = assessment `biochemicalData`. Per-visit value = monitoring `lab_values[key]`.
- **Anthropometrics** = weight, BMI, %IBW — trended (re-measured/recomputed each visit). IBW / BMR / TEE surfaced as reference targets, not separate trend lines. Baseline from the assessment's stored/calculated fields.
- **Intake** = prescribed nutrients (intervention `energy_kcal`, `protein_g`, `carbs_g`, `fat_g`, `fluid_ml` + `displayed_nutrients` micros). `target` = prescribed amount; per-visit value = logged intake; status = % of target (<90 under, >110 over, else on-target). No assessment baseline (series starts Follow-up 1).

### PES-implied labs (keyword map)

Best-effort map from PES statement text → labs (case-insensitive substring):
- "glucose" / "diabetes" / "glycemic" / "hyperglycemia" → `glucose`, `hba1c`
- "renal" / "kidney" / "dialysis" / "ckd" → `creatinine`, `potassium`, `bun`
- "protein" / "albumin" / "wound" / "malnutrition" → `albumin`
- "anemia" / "hemoglobin" → `hemoglobin`, `hematocrit`
- "lipid" / "cholesterol" / "cardiac" / "dyslipidemia" → `ldl`, `cholesterol`, `triglycerides`

The strong signals are flagged-abnormal + prescription + goal; PES keyword matching is supplementary. Unmatched PES text contributes no labs (still shown as context + fed to AI).

### Shared `LabFlagService`

Holds the single sex-aware LOW/HIGH reference table (the one currently inlined in `AiDiagnosisController`). Given `BiochemicalData` + sex, returns `key → { value, status, reference }`. `AiDiagnosisController` is refactored to call it (removes the duplicated inline ranges). `MonitoringPlanService` uses it for lab flagging and reference ranges.

## Frontend

- **`CarePlanHeader`** (new, top of monitoring): PES statements + intervention goal + prescription targets + flagged-abnormality chips.
- **`LogVisitForm`**: fields = the plan's tracked indicators (labs + weight + prescribed intake). Weight entry auto-recomputes BMI/%IBW via existing `nutritionCalculations` helpers.
- **`VisitTrendsChart`**: plots `indicator.series` seeded at Visit 1 → Follow-up N. Reference lines from `indicator.reference`; dashed target line from `indicator.target` for intake.
- **`GoalProgressTracker`**: rows = plan indicators (baseline → current → reference → status → sparkline); data source switched to the plan.
- Monitoring `page.tsx`: single `fetchMonitoringPlan(ncpId)` replaces the 3-call assembly.

## Monitoring AI (course of action)

- `AIService::narrateMonitoring` input = the plan's tracked-set trajectory + `pes_statements` + `goal_type` + prescription targets + latest statuses. Nothing else (keeps tokens minimal and focused).
- Prompt: "Given these indicator trajectories across visits, suggest a concrete course of action addressing the PES problem(s); cite specific indicator trends; 2–4 sentences; use only the indicators provided."
- Keeps `timeout`/`connectTimeout`, token-cap enforcement, and per-visit-count caching. Button + cached-badge UX unchanged.

## Testing

- **Backend (PHPUnit):**
  - `LabFlagService`: LOW / HIGH / normal classification, sex-specific ranges.
  - `MonitoringPlanService`: union + dedup of sources; Visit 1 baseline seeding; series ordering (Visit 1 → Follow-up N); intake %-of-target + flag; PES keyword map; empty/edge cases.
  - Endpoint feature test: RND-only auth, per-NCP-cycle scoping, shape.
  - AI narrate test with `Http::fake` + `preventStrayRequests`: payload contains only tracked indicator keys; asserts request shape.
- **Frontend (vitest):** pure plan→chart-data mapper; weight→BMI/%IBW recompute helper.

## Files

**Backend — new:** `app/Services/LabFlagService.php`, `app/Services/MonitoringPlanService.php`, `app/Http/Controllers/RND/MonitoringPlanController.php` (+ route in `routes/api.php`).
**Backend — modified:** `app/Http/Controllers/RND/AiDiagnosisController.php` (use `LabFlagService`), `app/Services/AIService.php` (`narrateMonitoring` input + prompt), `app/Services/MonitoringSummaryService.php` (reuse status logic in the plan).
**Frontend — new:** `_components/CarePlanHeader.tsx`, plan mapper/helper module(s).
**Frontend — modified:** `services/monitoringService.ts`, monitoring `page.tsx`, `_components/LogVisitForm.tsx`, `_components/VisitTrendsChart.tsx`, `_components/GoalProgressTracker.tsx`.

## Out of scope

- Changing the assessment or intervention authoring UIs (they remain the data sources).
- Altering report generation (NCP summary already renders biochem + monitoring; unaffected).
- Cross-runtime sharing of the keyword map (lives backend-only; frontend consumes the resolved plan).
