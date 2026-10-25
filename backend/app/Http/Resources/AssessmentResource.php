<?php

namespace App\Http\Resources;

use App\Models\Assessment;
use App\Services\RiskScoreCalculator;
use App\Support\WeightChangePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $riskResult = resolve(RiskScoreCalculator::class)->calculate($this->resource);
        $ncpRecord = $this->ncpRecord;
        $ageYears = $ncpRecord?->patient?->dob?->diffInYears($ncpRecord->created_at);
        $ageMonths = $ncpRecord?->patient?->dob ? (int) $ncpRecord->patient->dob->diffInMonths($ncpRecord->created_at) : null;
        $manualOverride = (bool) ($ncpRecord?->risk_score_manual_override ?? false);
        $manualFactors = $ncpRecord?->risk_score_manual_factors;
        $checkedFactors = $manualOverride ? ($manualFactors ?? []) : $riskResult['checked_factors'];

        return [
            'id' => $this->id,
            'ncp_record_id' => $this->ncp_record_id,
            'dietary_intake' => $this->dietary_intake,
            'food_recall' => $this->food_recall,
            'intake_nutrients' => $this->intake_nutrients,
            'appetite_changes' => $this->appetite_changes,
            'dietary_restrictions' => $this->dietary_restrictions,
            'supplements' => $this->supplements,
            'knowledge_notes' => $this->knowledge_notes,
            'weight' => $this->weight,
            'height' => $this->height,
            'bmi' => $ageYears !== null && $ageYears < 5 ? null : $this->bmi,
            'bmi_reference_date' => $ncpRecord?->created_at?->toDateString(),
            'bmi_classification' => Assessment::classifyBmi($this->calculateBmi($ageYears), $ageYears, (bool) $this->edema_present, $this->pregnancy_lactation_status, $ncpRecord?->patient?->sex, $ageMonths),
            'body_composition' => $this->body_composition,
            'medical_history' => $this->medical_history,
            'social_history' => $this->social_history,
            'lifestyle' => $this->lifestyle,
            'allergies' => $this->allergies,
            'food_dislikes' => $this->food_dislikes,
            'medications' => $this->medications,
            'rnd_summary' => $this->rnd_summary,
            'usual_weight' => $this->usual_weight,
            'nutritional_status' => $this->nutritional_status,
            'weight_loss_percentage' => $this->weight_loss_percentage,
            'weight_loss_period' => $this->weight_loss_period,
            'weight_change_period_value' => $this->weight_change_period_value,
            'weight_change_period_unit' => $this->weight_change_period_unit,
            'weight_change_period' => WeightChangePeriod::format(
                $this->weight_change_period_value,
                $this->weight_change_period_unit,
            ),
            'primary_diagnosis_category' => $this->primary_diagnosis_category,
            'primary_diagnosis_other' => $this->primary_diagnosis_other,
            'functional_assessment' => $this->functional_assessment,
            'energy_intake_status' => $this->energy_intake_status,
            'ibw_percentage' => $ageMonths !== null && $ageMonths > 228 ? $this->ibw_percentage : null,
            'present_diet' => $this->present_diet,
            'physical_assessment' => $this->physical_assessment,
            'chewing_swallowing_difficulties' => $this->chewing_swallowing_difficulties,
            'chewing_swallowing_details' => $this->chewing_swallowing_details,
            'constipation' => $this->constipation,
            'constipation_details' => $this->constipation_details,
            'diarrhea_notes' => $this->diarrhea_notes,
            'diarrhea_details' => $this->diarrhea_details,
            'food_intolerance' => $this->food_intolerance,
            'nutrient_drug_interaction' => $this->nutrient_drug_interaction,
            'dietary_intake_method' => $this->dietary_intake_method,
            'dietary_record_file' => $this->dietary_record_file,
            // Phase 5 — clinical measurements + engine inputs (fillable + validated;
            // omitting these left Anthropometrics/Client-History tabs blank on edit reload).
            'religion' => $this->religion,
            'physical_activity_level' => $this->physical_activity_level,
            'muac_mm' => $this->muac_mm,
            'waist_cm' => $this->waist_cm,
            'hip_cm' => $this->hip_cm,
            'edema_present' => $this->edema_present,
            'dry_weight_kg' => $this->dry_weight_kg,
            'pregnancy_lactation_status' => $this->pregnancy_lactation_status,
            'risk_score' => $ncpRecord?->risk_score,
            'risk_score_manual_override' => $manualOverride,
            'risk_score_manual_factors' => $manualFactors,
            'computed_risk_score' => $riskResult['score'],
            'computed_checked_factors' => $riskResult['checked_factors'],
            'checked_factors' => $checkedFactors,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'biochemical_data' => $this->whenLoaded('biochemicalData', function () {
                $bd = $this->biochemicalData;

                return [
                    'albumin' => $bd->albumin,
                    'hematocrit' => $bd->hematocrit,
                    'bun' => $bd->bun,
                    'hemoglobin' => $bd->hemoglobin,
                    'calcium' => $bd->calcium,
                    'ldl' => $bd->ldl,
                    'cholesterol' => $bd->cholesterol,
                    'phosphate' => $bd->phosphate,
                    'magnesium' => $bd->magnesium,
                    'creatinine' => $bd->creatinine,
                    'potassium' => $bd->potassium,
                    'glucose' => $bd->glucose,
                    'sodium' => $bd->sodium,
                    'hba1c' => $bd->hba1c,
                    'triglycerides' => $bd->triglycerides,
                    'hdl' => $bd->hdl,
                    'urr' => $bd->urr,
                    'bp' => $bd->bp,
                    'abg' => $bd->abg,
                    'others' => $bd->others,
                ];
            }),
        ];
    }
}
