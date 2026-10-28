# Nutrition care process

Verified against committed source: `ce7078a41a9082eba5ae749639bcfa0cf1b45a82` plus diagnosis changes in this working tree (2026-10-28).

Classification paths additionally verified against `79ee23b53a0ba047faa791cdb8e5b7fcb1211429` plus this working tree (2026-10-25): `Assessment::classifyBmi`, its derived API field, diagnosis/monitoring evidence and current census/NCP report presentation. See [data meaning](data.md) and [AI maintenance](ai.md).

Purpose: locate nutrition calculations and NCP workflow authority.

Census classification uses each counted cycle's initial Assessment and the same age/sex-aware BMI classifier as AssessmentResource. Pediatric and adult labels are separate ordered groups. Older calculation-basis snapshots are recalculated for display without changing stored rows; current-basis frozen periods retain their captured counts. See [data meaning](data.md) for the API fields and consumers.

This group includes clinical backend rules, NCP web workflows, clinical food/recipe/meal-plan logic and AI-assisted wording. Start with [data meaning](data.md) for fields/units/history or [AI maintenance](ai.md) for provider/model/prompt changes. Shared accounts, announcements, help and dashboards live in [shared features](../shared/README.md).

| Responsibility | Source |
|---|---|
| Prescription arithmetic and historical paths | [NutritionPrescriptionService](../../../backend/app/Services/NutritionPrescriptionService.php): autofill / autofillLegacy |
| Calculation context and saved intervention | [InterventionCalculationContextService](../../../backend/app/Services/InterventionCalculationContextService.php), [InterventionPlanService](../../../backend/app/Services/InterventionPlanService.php) |
| Risk scoring | [RiskScoreCalculator](../../../backend/app/Services/RiskScoreCalculator.php) |
| Meaningful ADIME completion | [ClinicalCompletenessService](../../../backend/app/Services/ClinicalCompletenessService.php) |
| Appointments and work tracking | [NcpAppointmentWorkflow](../../../backend/app/Services/NcpAppointmentWorkflow.php) |
| Patients, NCP cycles and appointments/calendar | [PatientController](../../../backend/app/Http/Controllers/RND/PatientController.php), [NcpRecordController](../../../backend/app/Http/Controllers/RND/NcpRecordController.php), [NcpAppointmentController](../../../backend/app/Http/Controllers/RND/NcpAppointmentController.php), [CalendarEventController](../../../backend/app/Http/Controllers/RND/CalendarEventController.php) |
| Assessment, labs and supporting documents | [AssessmentController](../../../backend/app/Http/Controllers/RND/AssessmentController.php), [LabFlagService](../../../backend/app/Services/LabFlagService.php), [ScreeningDocumentController](../../../backend/app/Http/Controllers/RND/ScreeningDocumentController.php) |
| Manual diagnoses and AI-assisted drafts | [DiagnosisController](../../../backend/app/Http/Controllers/RND/DiagnosisController.php), [Diagnosis services](../../../backend/app/Services/Diagnosis), [AI guide](ai.md) |
| Monitoring indicators and comparisons | [MonitoringPlanService](../../../backend/app/Services/MonitoringPlanService.php), [MonitoringSummaryService](../../../backend/app/Services/MonitoringSummaryService.php), [MonitoringController](../../../backend/app/Http/Controllers/RND/MonitoringController.php) |
| Clinical food library, recipes, recommendations and meal plans | [FoodItemController](../../../backend/app/Http/Controllers/RND/FoodItemController.php), [RecipeController](../../../backend/app/Http/Controllers/RND/RecipeController.php), [RecommendService](../../../backend/app/Services/RecommendService.php), [MealPlanService](../../../backend/app/Services/MealPlanService.php), [FoodRecallService](../../../backend/app/Services/FoodRecallService.php) |
| HTTP boundary | [RND controllers](../../../backend/app/Http/Controllers/RND), [RND requests](../../../backend/app/Http/Requests/RND) |

Trace formula changes through context, overrides/snapshots, saved intervention, frontend previews and reports. Preserve units, precision, age/stage boundaries and historical meaning. Characterize unclear behavior before editing; client previews do not override server calculations. AI wording is separate: [AI features](ai.md).

Food Library's Food Calculator reuses the Food Recall calculator in a full-width tab and keeps calculations in the current tab session. Assessment requires one Nutrition support selection. Oral keeps the weekly menu; Tube Feeding opens a formula collection without dates or automatic prescription scaling; NPO / TPN keeps prescription and guidance without a food menu. Existing missing selections are backfilled to Oral by a forward migration. Saved interventions retain their own mode.

[FormulaPlanService](../../../backend/app/Services/FormulaPlanService.php) validates library/template copies and plan-only formulas using Recipe's existing snapshot calculation. [TubeFormulaPlanner](../../../frontend/components/ncp/TubeFormulaPlanner.tsx) reuses [MenuItemCreateForm](../../../frontend/components/ncp/MenuItemCreateForm.tsx) for **Use in this plan** or **Save to Food Library and use**. Tube formulas only appear in tube pickers and library management. Templates use the existing CRUD with Oral/Tube collections; their profiles stay view-only. Patient plans can edit their own portions and save independent templates. See [data meaning](data.md) for snapshot, reset and census semantics.

FormulaCollection uses formula columns with **Create formula** / **Select formula** in the next empty slot and template controls outside. The Formula 1, 2, etc. selector reuses ButtonFilterGroup, MacroTrackerBar and MicronutrientTotals to show one formula's macros and prescription-selected micros. Formula and Food Recall cards reuse MenuEditorSlots: white, name-only, whole-card profile actions and a top-right X. Profiles contain measured portions; the selected-formula summary shows macros, fluid and chosen micros. Oral fluid totals share the macro card. Scaling belongs inside the editor. Tube reports print exact planned portion details followed by macros and every selected micro, marking missing values unrecorded; they omit original-recipe comparisons and multipliers.

Diagnosis uses compact NI family/direction/subject choices and grouped NC/NB terms in the existing PES builder. Historical text remains saved; clinician-only NO is an exclusive completed decision. AI returns up to five new drafts and reuses unchanged reviews; a separate paid retry is explicit. See [AI behavior](ai.md) and [saved-field meanings](data.md).

Focused tests: [NutritionPrescriptionServiceTest](../../../backend/tests/Unit/NutritionPrescriptionServiceTest.php), [NutritionPrescriptionV2Test](../../../backend/tests/Unit/NutritionPrescriptionV2Test.php), [ClinicalCompletenessTest](../../../backend/tests/Feature/ClinicalCompletenessTest.php), [InterventionCalculationV2Test](../../../backend/tests/Feature/InterventionCalculationV2Test.php). Follow [testing](../platform/testing.md); tests are not independent clinical certification.

Domain references: [intervention goals](../../logic/intervention-goals.md), [meal algorithm](../../logic/meal-algorithm.md), [recommend/avoid](../../logic/recommend-avoid.md). Revalidate prose against called code when making changes. Obtain clinical review before changing guideline-dependent behavior.
