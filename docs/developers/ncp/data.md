# NCP data meaning

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: explain important clinical data families and route maintainers to their authoritative writers/consumers. No patient data or independently certified clinical guidance is included.

| Fields / family | Meaning and authority |
|---|---|
| NcpRecord.patient_id, rnd_user_id, type, status | Cycle context and ownership; [NcpRecord](../../../backend/app/Models/NcpRecord.php), [NcpRecordController](../../../backend/app/Http/Controllers/RND/NcpRecordController.php), [NcpAppointmentWorkflow](../../../backend/app/Services/NcpAppointmentWorkflow.php). Check transitions/permissions, not only a status label |
| risk_score, risk_score_manual_override, risk_score_manual_factors | Calculated score and explicit override metadata; [RiskScoreCalculator](../../../backend/app/Services/RiskScoreCalculator.php), cycle model and assessment/save routes. Score has a decimal:2 model cast; do not turn it into an AI-generated diagnosis |
| Assessment.weight, height, dry_weight_kg, edema_present | Measurement/context inputs; [Assessment](../../../backend/app/Models/Assessment.php), [InterventionCalculationContextService](../../../backend/app/Services/InterventionCalculationContextService.php) maps weightKg/heightCm and chooses the measured/dry weight basis. Trace field_sources/measurement date rather than assuming the assessment is always the newest source |
| muac_mm, waist_cm, hip_cm | Different explicit units in Assessment. Preserve request limits and conversions; do not apply one shared unit by appearance |
| food_recall, intake_nutrients, allergies, food_dislikes | Structured clinical inputs in Assessment; [FoodRecallService](../../../backend/app/Services/FoodRecallService.php), [RecommendService](../../../backend/app/Services/RecommendService.php) and [MealPlanService](../../../backend/app/Services/MealPlanService.php) consume relevant food/intake context. Read the selected calculation path before changing the JSON shape |
| Intervention.energy_kcal, protein_g, carbs_g, fat_g, fluid_ml | Saved prescription quantities with decimal:2 casts; [Intervention](../../../backend/app/Models/Intervention.php), [NutritionPrescriptionService](../../../backend/app/Services/NutritionPrescriptionService.php), [InterventionPlanService](../../../backend/app/Services/InterventionPlanService.php). Display rounding is not permission to change stored precision |
| goal_type, disease_stage, calculation_options, calculation_snapshot | Goal and calculation context/history; follow the plan service and [calculation context](../../../backend/app/Services/InterventionCalculationContextService.php). The snapshot is not a fresh recomputation against today's measurements |
| source_monitoring_id and latestIntervention | Links follow-up input to intervention history; NcpRecord selects latest intervention by created_at/id, while Intervention's update hook protects historical plans. Preserve those meanings across API/report changes |
| Monitoring indicators/comparisons | [MonitoringPlanService](../../../backend/app/Services/MonitoringPlanService.php), [MonitoringSummaryService](../../../backend/app/Services/MonitoringSummaryService.php), [Monitoring](../../../backend/app/Models/Monitoring.php). Baselines, targets and comparisons are different values; AI narrates bounded evidence |
| FoodItem.serving_size, serving_unit, micronutrients, water_g | Clinical food quantities and structured nutrient data; [FoodItem](../../../backend/app/Models/FoodItem.php), [UsdaService](../../../backend/app/Services/UsdaService.php). USDA nutrient keys/mapping must stay compatible with recipes and clinical rules |

Use [NCP maintenance](README.md) for HTTP owners, tests and domain explanations, and [general data tracing](../platform/data-semantics.md) before editing. Clinical recipes/FoodItem and operational FoodServiceRecipe/FsItem are distinct models; do not merge their units or calculations by field-name similarity.

Before changing clinical data, establish the relevant record shapes, migration history and clinical requirements. This selected map does not enumerate every clinical field; locate additional fields through models/requests and document only the behavior inspected.
