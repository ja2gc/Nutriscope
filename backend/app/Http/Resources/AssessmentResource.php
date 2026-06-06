<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'ncp_record_id'        => $this->ncp_record_id,
            'dietary_intake'       => $this->dietary_intake,
            'appetite_changes'     => $this->appetite_changes,
            'dietary_restrictions' => $this->dietary_restrictions,
            'supplements'          => $this->supplements,
            'knowledge_notes'      => $this->knowledge_notes,
            'weight'               => $this->weight,
            'height'               => $this->height,
            'bmi'                  => $this->bmi,
            'body_composition'     => $this->body_composition,
            'medical_history'      => $this->medical_history,
            'social_history'       => $this->social_history,
            'lifestyle'            => $this->lifestyle,
            'allergies'            => $this->allergies,
            'food_dislikes'        => $this->food_dislikes,
            'medications'          => $this->medications,
            'rnd_summary'          => $this->rnd_summary,
            'usual_weight'         => $this->usual_weight,
            'nutritional_status'   => $this->nutritional_status,
            'weight_loss_percentage'=> $this->weight_loss_percentage,
            'weight_loss_period'   => $this->weight_loss_period,
            'functional_assessment'=> $this->functional_assessment,
            'energy_intake_status' => $this->energy_intake_status,
            'ibw_percentage'       => $this->ibw_percentage,
            'present_diet'         => $this->present_diet,
            'physical_assessment'  => $this->physical_assessment,
            'chewing_swallowing_difficulties' => $this->chewing_swallowing_difficulties,
            'constipation'         => $this->constipation,
            'diarrhea_notes'       => $this->diarrhea_notes,
            'food_intolerance'     => $this->food_intolerance,
            'nutrient_drug_interaction' => $this->nutrient_drug_interaction,
            'dietary_intake_method'=> $this->dietary_intake_method,
            'dietary_record_file'  => $this->dietary_record_file,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'biochemical_data'     => $this->whenLoaded('biochemicalData', function () {
                $bd = $this->biochemicalData;
                return [
                    'albumin'      => $bd->albumin,
                    'hba1c'        => $bd->hba1c,
                    'ldl'          => $bd->ldl,
                    'cholesterol'  => $bd->cholesterol,
                    'creatinine'   => $bd->creatinine,
                    'potassium'    => $bd->potassium,
                    'hemoglobin'   => $bd->hemoglobin,
                    'glucose'      => $bd->glucose,
                    'bp'           => $bd->bp,
                ];
            }),
        ];
    }
}
