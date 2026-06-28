<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dietary_intake'       => ['nullable', 'string'],
            'appetite_changes'     => ['nullable', 'string'],
            'dietary_restrictions' => ['nullable', 'string'],
            'supplements'          => ['nullable', 'string'],
            'knowledge_notes'      => ['nullable', 'string'],
            'weight'               => ['nullable', 'numeric', 'min:0'],
            'height'               => ['nullable', 'numeric', 'min:0'],
            'body_composition'     => ['nullable', 'string'],
            'medical_history'      => ['nullable', 'string'],
            'social_history'       => ['nullable', 'string'],
            'religion'             => ['nullable', 'string', 'max:100'],
            'lifestyle'            => ['nullable', 'string'],
            'allergies'            => ['nullable', 'array'],
            'food_dislikes'        => ['nullable', 'array'],
            'medications'          => ['nullable', 'array'],
            'rnd_summary'          => ['nullable', 'string'],
            'usual_weight'         => ['nullable', 'numeric', 'min:0'],
            'nutritional_status'   => ['nullable', 'string', 'in:Normal,Moderate Malnutrition,Severe Malnutrition'],
            'weight_loss_percentage'=> ['nullable', 'numeric', 'min:0', 'max:100'],
            'weight_loss_period'   => ['nullable', 'string'],
            'functional_assessment'=> ['nullable', 'string', 'in:Bed ridden,Needs assistance,Ambulatory'],
            'energy_intake_status' => ['nullable', 'string', 'in:No change,Mostly liquids,Sub-optimal,Starvation,Poor intake prior to admission'],
            'ibw_percentage'       => ['nullable', 'numeric', 'min:0'],
            'present_diet'         => ['nullable', 'string'],
            'physical_assessment'  => ['nullable', 'string'],
            'chewing_swallowing_difficulties' => ['nullable', 'string'],
            'constipation'         => ['nullable', 'string'],
            'diarrhea_notes'       => ['nullable', 'string'],
            'food_intolerance'     => ['nullable', 'string'],
            'nutrient_drug_interaction' => ['nullable', 'string'],
            'dietary_intake_method'=> ['nullable', 'string', 'in:24_hour_recall,food_frequency,3_day_record,other'],
            'dietary_record_file'  => ['nullable', 'string'],
            // Clinical measurement fields
            'physical_activity_level' => ['nullable', 'string', 'in:sedentary,light,moderate,very_active,extra_active'],
            'muac_mm'                 => ['nullable', 'numeric', 'min:0'],
            'waist_cm'                => ['nullable', 'numeric', 'min:0'],
            'hip_cm'                  => ['nullable', 'numeric', 'min:0'],
            // Phase 5 — engine inputs
            'stress_factor'               => ['nullable', 'numeric', 'min:0.5', 'max:3.0'],
            'edema_present'               => ['nullable', 'boolean'],
            'pregnancy_lactation_status'  => ['nullable', 'string', 'in:none,pregnant,lactating'],
            'biochemical_data'            => ['nullable', 'array'],
            'biochemical_data.albumin'    => ['nullable', 'numeric'],
            'biochemical_data.hematocrit' => ['nullable', 'numeric'],
            'biochemical_data.bun'        => ['nullable', 'numeric'],
            'biochemical_data.hemoglobin' => ['nullable', 'numeric'],
            'biochemical_data.calcium'    => ['nullable', 'numeric'],
            'biochemical_data.ldl'        => ['nullable', 'numeric'],
            'biochemical_data.cholesterol'=> ['nullable', 'numeric'],
            'biochemical_data.phosphate'  => ['nullable', 'numeric'],
            'biochemical_data.creatinine' => ['nullable', 'numeric'],
            'biochemical_data.potassium'  => ['nullable', 'numeric'],
            'biochemical_data.glucose'    => ['nullable', 'numeric'],
            'biochemical_data.sodium'     => ['nullable', 'numeric'],
            'biochemical_data.hba1c'      => ['nullable', 'numeric'],
            'biochemical_data.triglycerides'=> ['nullable', 'numeric'],
            'biochemical_data.hdl'        => ['nullable', 'numeric'],
            'biochemical_data.urr'        => ['nullable', 'numeric'],
            'biochemical_data.bp'         => ['nullable', 'string', 'max:255'],
            'biochemical_data.abg'        => ['nullable', 'string', 'max:255'],
            'biochemical_data.others'     => ['nullable', 'array'],
        ];
     }
}
