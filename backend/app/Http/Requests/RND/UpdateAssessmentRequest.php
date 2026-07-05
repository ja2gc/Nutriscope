<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAssessmentRequest extends FormRequest
{
    private const PRESCRIPTION_INPUTS = [
        'weight' => 'body weight',
        'usual_weight' => 'usual body weight',
        'height' => 'height',
        'physical_activity_level' => 'physical activity level',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bounds = config('clinical.assessment_input_bounds');

        return [
            'dietary_intake' => ['nullable', 'string'],
            'appetite_changes' => ['nullable', 'string'],
            'dietary_restrictions' => ['nullable', 'string'],
            'supplements' => ['nullable', 'string'],
            'knowledge_notes' => ['nullable', 'string'],
            'weight' => ['nullable', 'numeric', "between:{$bounds['weight']['min']},{$bounds['weight']['max']}"],
            'height' => ['nullable', 'numeric', "between:{$bounds['height']['min']},{$bounds['height']['max']}"],
            'body_composition' => ['nullable', 'string'],
            'medical_history' => ['nullable', 'string'],
            'social_history' => ['nullable', 'string'],
            'religion' => ['nullable', 'string', 'max:100'],
            'lifestyle' => ['nullable', 'string'],
            'allergies' => ['nullable', 'array'],
            'food_dislikes' => ['nullable', 'array'],
            'medications' => ['nullable', 'array'],
            'rnd_summary' => ['nullable', 'string'],
            'usual_weight' => ['nullable', 'numeric', "between:{$bounds['usual_weight']['min']},{$bounds['usual_weight']['max']}"],
            'nutritional_status' => ['nullable', 'string', 'in:Normal,Moderate Malnutrition,Severe Malnutrition'],
            'weight_loss_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'weight_loss_period' => ['nullable', 'string'],
            'functional_assessment' => ['nullable', 'string', 'in:Bed ridden,Needs assistance,Ambulatory'],
            'energy_intake_status' => ['nullable', 'string', 'in:No change,Mostly liquids,Sub-optimal,Starvation,Poor intake prior to admission'],
            'ibw_percentage' => ['nullable', 'numeric', 'min:0'],
            'present_diet' => ['nullable', 'string'],
            'physical_assessment' => ['nullable', 'string'],
            'chewing_swallowing_difficulties' => ['nullable', 'string'],
            'constipation' => ['nullable', 'string'],
            'diarrhea_notes' => ['nullable', 'string'],
            'food_intolerance' => ['nullable', 'string'],
            'nutrient_drug_interaction' => ['nullable', 'string'],
            'dietary_intake_method' => ['nullable', 'string', 'in:24_hour_recall,food_frequency,3_day_record,other'],
            'dietary_record_file' => ['nullable', 'string'],
            // Clinical measurement fields
            'physical_activity_level' => ['nullable', 'string', 'in:sedentary,light,moderate,very_active,extra_active'],
            'muac_mm' => ['nullable', 'numeric', 'min:0'],
            'waist_cm' => ['nullable', 'numeric', 'min:0'],
            'hip_cm' => ['nullable', 'numeric', 'min:0'],
            // Phase 5 — engine inputs
            'stress_factor' => ['nullable', 'numeric', 'min:0.5', 'max:3.0'],
            'edema_present' => ['nullable', 'boolean'],
            'dry_weight_kg' => ['nullable', 'numeric', "between:{$bounds['dry_weight_kg']['min']},{$bounds['dry_weight_kg']['max']}"],
            'pregnancy_lactation_status' => ['nullable', 'string', 'in:none,pregnant,lactating'],
            'biochemical_data' => ['nullable', 'array'],
            'biochemical_data.albumin' => ['nullable', 'numeric'],
            'biochemical_data.hematocrit' => ['nullable', 'numeric'],
            'biochemical_data.bun' => ['nullable', 'numeric'],
            'biochemical_data.hemoglobin' => ['nullable', 'numeric'],
            'biochemical_data.calcium' => ['nullable', 'numeric'],
            'biochemical_data.ldl' => ['nullable', 'numeric'],
            'biochemical_data.cholesterol' => ['nullable', 'numeric'],
            'biochemical_data.phosphate' => ['nullable', 'numeric'],
            'biochemical_data.magnesium' => ['nullable', 'numeric'],
            'biochemical_data.creatinine' => ['nullable', 'numeric'],
            'biochemical_data.potassium' => ['nullable', 'numeric'],
            'biochemical_data.glucose' => ['nullable', 'numeric'],
            'biochemical_data.sodium' => ['nullable', 'numeric'],
            'biochemical_data.hba1c' => ['nullable', 'numeric'],
            'biochemical_data.triglycerides' => ['nullable', 'numeric'],
            'biochemical_data.hdl' => ['nullable', 'numeric'],
            'biochemical_data.urr' => ['nullable', 'numeric'],
            'biochemical_data.bp' => ['nullable', 'string', 'max:255'],
            'biochemical_data.abg' => ['nullable', 'string', 'max:255'],
            'biochemical_data.others' => ['nullable', 'array'],
            'risk_score_manual_override' => ['nullable', 'boolean'],
            'risk_score_manual_factors' => ['nullable', 'array'],
            'risk_score_manual_factors.*' => ['string', 'in:screening_criteria,ibw_limit,unintentional_weight_loss,mechanical_digestive_problem,low_albumin,significant_lab_result,others'],
        ];
    }

    public function messages(): array
    {
        $bounds = config('clinical.assessment_input_bounds');

        return [
            'weight.between' => "Body weight must be between {$bounds['weight']['min']} and {$bounds['weight']['max']} kg. Check the entry for a typo.",
            'usual_weight.between' => "Usual body weight must be between {$bounds['usual_weight']['min']} and {$bounds['usual_weight']['max']} kg. Check the entry for a typo.",
            'dry_weight_kg.between' => "Dry weight must be between {$bounds['dry_weight_kg']['min']} and {$bounds['dry_weight_kg']['max']} kg. Check the entry for a typo.",
            'height.between' => "Height must be between {$bounds['height']['min']} and {$bounds['height']['max']} cm. Check the entry for a typo.",
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ncpRecord = $this->route('ncpRecord');
                $assessment = $ncpRecord?->assessment;

                foreach (self::PRESCRIPTION_INPUTS as $field => $label) {
                    $value = $this->has($field) ? $this->input($field) : $assessment?->{$field};
                    if ($value === null || $value === '') {
                        $validator->errors()->add(
                            $field,
                            "The {$label} field is required before nutrition prescription calculation."
                        );
                    }
                }

                $edemaPresent = $this->has('edema_present')
                    ? $this->boolean('edema_present')
                    : (bool) $assessment?->edema_present;
                $dryWeight = $this->has('dry_weight_kg')
                    ? $this->input('dry_weight_kg')
                    : $assessment?->dry_weight_kg;

                if ($edemaPresent && blank($dryWeight)) {
                    $validator->errors()->add('dry_weight_kg', 'Dry weight is required when edema is present.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return self::PRESCRIPTION_INPUTS;
    }
}
