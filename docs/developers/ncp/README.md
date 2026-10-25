# Nutrition care process

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Classification paths additionally verified against `79ee23b53a0ba047faa791cdb8e5b7fcb1211429` plus this working tree (2026-10-25): `Assessment::classifyBmi`, its derived API field, diagnosis/monitoring evidence and current census/NCP report presentation. See [data meaning](data.md) and [AI maintenance](ai.md).

Purpose: locate nutrition calculations and NCP workflow authority.

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

Focused tests: [NutritionPrescriptionServiceTest](../../../backend/tests/Unit/NutritionPrescriptionServiceTest.php), [NutritionPrescriptionV2Test](../../../backend/tests/Unit/NutritionPrescriptionV2Test.php), [ClinicalCompletenessTest](../../../backend/tests/Feature/ClinicalCompletenessTest.php), [InterventionCalculationV2Test](../../../backend/tests/Feature/InterventionCalculationV2Test.php). Follow [testing](../platform/testing.md); tests are not independent clinical certification.

Domain references: [intervention goals](../../logic/intervention-goals.md), [meal algorithm](../../logic/meal-algorithm.md), [recommend/avoid](../../logic/recommend-avoid.md). Revalidate prose against called code when making changes. Obtain clinical review before changing guideline-dependent behavior.
