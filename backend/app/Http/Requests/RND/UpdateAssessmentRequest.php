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
        ];
     }
}
