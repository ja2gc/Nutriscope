# NCP Summary Report — per-patient Nutrition Care Plan (ADIME) as a filed/printable document

- **Date:** 2026-06-14
- **Status:** Draft design, pending review
- **Depends on:** Spec 4 (Reports-UX) — reuses its browse/render/archive/preview machinery end-to-end.
- **Reference form:** `docs/Nutriscope Forms/nutrition care plan.jpg` ("NUTRITION AND DIETETICS SERVICE — Medical Nutrition Therapy (Nutrition Care Plan)").

## 1. Background / why

The system captures a full NCP cycle per patient — Assessment, Diagnosis (PES), Intervention, Monitoring/Evaluation — but there is **no way to print or file it**. Clinicians need the care plan as (a) an **educational handout** to give the patient and (b) the hospital's **filed clinical record**. The `adime_individual` report type was reserved in the schema but never built. This spec adds it, presenting existing NCP data as the standard Nutrition Care Plan form. No new data is captured — it is a presentation layer over `NcpRecord` and its relations.

## 2. Goals / non-goals

**Goals**
1. A per-NCP-record PDF that mirrors the Nutrition Care Plan form, auto-filled from live system data.
2. Plugs into the Spec-4 reports browser: browse → view (in-app preview) → download / archive, exactly like the other reports.
3. RND-only (contains PHI) — enforced by the existing `guardClinical()` gate.

**Non-goals**
- Changing the NCP data model or any clinical workflow.
- Editing the care plan from the report (it is read-only output).
- Aggregate/multi-patient ADIME (`adime_aggregate` stays out of scope).

## 3. Browse axis (decisions locked)

- **One instance per `NcpRecord`** (each care cycle is its own filed document). `EntityInstanceSource` over `NcpRecord` (RND scope), newest first, labelled `"{patient name} — {date} ({status})"`, params `{ ncp_record_id }`.
- **Monitoring & Evaluation: all entries, dated** — list every `Monitoring` row chronologically (record-keeping + progress), not just the latest.

## 4. Design

### 4.1 Data (generator)
`App\Services\Reports\Generators\NcpSummaryGenerator` (implements `ReportGenerator`), `type() = 'ncp_summary'`, view `reports.ncp-summary`, paper `['a4','portrait']`. `data(Report $report)` loads:
`NcpRecord::with('patient', 'assessment.biochemicalData', 'diagnoses', 'intervention', 'monitorings')->findOrFail($params['ncp_record_id'])` and shapes:
- **Patient header:** name, hospital_number, age (from dob/admission_date), sex, physician, admission_date, medical_diagnosis, religion (Patient + Assessment.religion).
- **Assessment:** present_diet, energy_intake_status, physical_assessment, functional_assessment, chewing/swallowing, constipation, diarrhea_notes, allergies, food_intolerance, nutrient_drug_interaction; anthropometrics (height, weight, bmi, usual_weight, weight_loss_% / period, ibw_%); **biochemical data** (from `Assessment.biochemicalData`); nutritional_status; risk (`NcpRecord.risk_score` → Low/Moderate/High band).
- **Diagnosis:** each `Diagnosis` PES (prefer `pes_statement`, else build from problem/etiology/signs_symptoms via `Diagnosis::buildPes`).
- **Intervention:** energy_kcal, protein_g, carbs_g, fat_g, fluid_ml, micronutrient_limits, education_notes, counseling_goals, session_type, disease_stage.
- **Monitoring/Evaluation:** all `monitorings` (dated): weight, bmi, intake_notes, symptoms, goal_achievement, clinical_summary.

Pure shaping helpers (age calc, risk band, PES fallback) are unit-testable; `data()` is the thin loader. **No recompute** of clinical figures — values are read as stored.

### 4.2 View
`resources/views/reports/ncp-summary.blade.php` extending `reports.layout`, using the shared `partials/letterhead` (editable branding header) and `partials/signatories`. Sections mirror the form: Patient info → Nutrition Assessment (incl. biochem grid) → Nutritional Status/Risk → Nutrition Diagnosis → Nutrition Intervention → Monitoring & Evaluation. Empty fields render blank (never crash).

### 4.3 Template + signatories
Seed a `ncp_summary` `ReportTemplate` (blade `reports.ncp-summary`) with signatories matching the form footer — **names blank, positions only** (consistent with the 2026-06-14 fix):
- `prepared_by` — "Prepared by:" / "Nutritionist-Dietitian II" (auto-fills the logged-in RND).
- `conforme` — "Conforme (Attending Physician):" / "Attending Physician" (blank name).

### 4.4 Browse / render / archive wiring
- Register `'ncp_summary'` in `ReportService::GENERATORS`, `ReportBrowser` (EntityInstanceSource over NcpRecord), and `ReportController::CLINICAL_TYPES` (RND-only).
- Add to the frontend `CATALOG` under the **Clinical** group; everything else (instances/render/archive/view/preview) is reused unchanged.
- `hasData(params)` = the `ncp_record_id` exists (and, for safety, has at least an assessment or diagnosis — TBD in plan; default: record exists).

## 5. Security / privacy
- RND-only via `guardClinical()` on instances/render/archive (already enforced for clinical types).
- Archived copies are owner-scoped (per the 2026-06-14 decision) and stream frozen bytes.
- Audited as a mutation on archive (Spec 5 trait). The PDF itself carries PHI — same handling as patient_menu_plan.

## 6. Testing
- **Enumeration:** seed NCP records → `instances('ncp_summary')` lists one per record, RND-only (FSS → 403).
- **Render:** a record with assessment/diagnosis/intervention/monitoring → 200 `application/pdf`, no persisted row; unknown/empty record → 404.
- **Pure helpers:** age-from-dob, risk band, PES fallback, "all monitorings dated" ordering.
- **Archive:** persists + snapshot; download/view serve frozen bytes.
- Full suite stays green.

## 7. Flaws / risks
1. **Sparse records** — a half-finished NCP cycle yields a mostly-empty form. Acceptable (blanks are valid on a care plan); `hasData` guards the truly-empty case.
2. **Biochemical data shape** — confirm `BiochemicalData` field names during the plan; map field-for-field to the form's biochem grid.
3. **Patient age** — derive from dob vs admission_date consistently (reuse the census generator's approach).
