<?php

namespace App\Http\Requests\RND;

use App\Support\PrimaryDiagnosisCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'dietary_intake' => ['nullable', 'string', 'max:400'],
            'appetite_changes' => ['nullable', 'string', 'max:400'],
            'dietary_restrictions' => ['nullable', 'string', 'max:400'],
            'supplements' => ['nullable', 'string', 'max:400'],
            'knowledge_notes' => ['nullable', 'string', 'max:400'],
            'weight' => ['nullable', 'numeric', "between:{$bounds['weight']['min']},{$bounds['weight']['max']}", 'decimal:0,2'],
            'height' => ['nullable', 'numeric', "between:{$bounds['height']['min']},{$bounds['height']['max']}", 'decimal:0,2'],
            'body_composition' => ['nullable', 'string', 'max:400'],
            'medical_history' => ['nullable', 'string', 'max:400'],
            'social_history' => ['nullable', 'string', 'max:400'],
            'religion' => ['nullable', 'string', 'max:100'],
            'lifestyle' => ['nullable', 'string', 'max:400'],
            'allergies' => ['nullable', 'array'],
            'food_dislikes' => ['nullable', 'array'],
            'medications' => ['nullable', 'array'],
            'rnd_summary' => ['nullable', 'string', 'max:3000'],
            'usual_weight' => ['nullable', 'numeric', "between:{$bounds['usual_weight']['min']},{$bounds['usual_weight']['max']}", 'decimal:0,2'],
            'nutritional_status' => ['nullable', 'string', 'in:Normal,Moderate Malnutrition,Severe Malnutrition'],
            'weight_loss_percentage' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
            'weight_loss_period' => ['nullable', 'string', 'max:400'],
            'weight_change_period_value' => ['nullable', 'integer', 'between:1,104'],
            'weight_change_period_unit' => ['nullable', Rule::in(['weeks', 'months'])],
            'primary_diagnosis_category' => ['nullable', 'string', Rule::in(PrimaryDiagnosisCategory::values())],
            'primary_diagnosis_other' => ['nullable', 'string', 'max:160'],
            'functional_assessment' => ['nullable', 'string', 'in:Bed ridden,Needs assistance,Ambulatory'],
            'energy_intake_status' => ['nullable', 'string', 'in:No change,Mostly liquids,Sub-optimal,Starvation,Poor intake prior to admission'],
            'ibw_percentage' => ['nullable', 'numeric', 'between:0,300', 'decimal:0,2'],
            'present_diet' => ['nullable', 'string', 'max:400'],
            'physical_assessment' => ['nullable', 'string', 'max:400'],
            'chewing_swallowing_difficulties' => ['nullable', 'string', 'max:400'],
            'constipation' => ['nullable', 'string', 'max:400'],
            'diarrhea_notes' => ['nullable', 'string', 'max:400'],
            'food_intolerance' => ['nullable', 'string', 'max:400'],
            'nutrient_drug_interaction' => ['nullable', 'string', 'max:400'],
            'dietary_intake_method' => ['nullable', 'string', 'in:24_hour_recall,food_frequency,3_day_record,other'],
            'dietary_record_file' => ['nullable', 'string', 'max:400'],
            // Clinical measurement fields
            'physical_activity_level' => ['nullable', 'string', 'in:sedentary,light,moderate,very_active,extra_active'],
            'muac_mm' => ['nullable', 'numeric', 'between:50,600', 'decimal:0,2'],
            'waist_cm' => ['nullable', 'numeric', 'between:20,250', 'decimal:0,2'],
            'hip_cm' => ['nullable', 'numeric', 'between:20,250', 'decimal:0,2'],
            // Phase 5 — engine inputs
            'edema_present' => ['nullable', 'boolean'],
            'dry_weight_kg' => ['nullable', 'numeric', "between:{$bounds['dry_weight_kg']['min']},{$bounds['dry_weight_kg']['max']}", 'decimal:0,2'],
            'pregnancy_lactation_status' => ['nullable', 'string', Rule::in(['none', 'pregnant_t1', 'pregnant_t2', 'pregnant_t3', 'pregnant_unspecified', 'lactating'])],
            'biochemical_data' => ['nullable', 'array'],
            'biochemical_data.albumin' => ['nullable', 'numeric', 'between:0,10', 'decimal:0,2'],
            'biochemical_data.hematocrit' => ['nullable', 'numeric', 'between:10,75', 'decimal:0,2'],
            'biochemical_data.bun' => ['nullable', 'numeric', 'between:0,300', 'decimal:0,2'],
            'biochemical_data.hemoglobin' => ['nullable', 'numeric', 'between:2,25', 'decimal:0,2'],
            'biochemical_data.calcium' => ['nullable', 'numeric', 'between:0,30', 'decimal:0,2'],
            'biochemical_data.ldl' => ['nullable', 'numeric', 'between:10,5000', 'decimal:0,2'],
            'biochemical_data.cholesterol' => ['nullable', 'numeric', 'between:10,5000', 'decimal:0,2'],
            'biochemical_data.phosphate' => ['nullable', 'numeric', 'between:0,20', 'decimal:0,2'],
            'biochemical_data.magnesium' => ['nullable', 'numeric', 'between:0,15', 'decimal:0,2'],
            'biochemical_data.creatinine' => ['nullable', 'numeric', 'between:0,30', 'decimal:0,2'],
            'biochemical_data.potassium' => ['nullable', 'numeric', 'between:0,15', 'decimal:0,2'],
            'biochemical_data.glucose' => ['nullable', 'numeric', 'between:10,2000', 'decimal:0,2'],
            'biochemical_data.sodium' => ['nullable', 'numeric', 'between:50,200', 'decimal:0,2'],
            'biochemical_data.hba1c' => ['nullable', 'numeric', 'between:2,25', 'decimal:0,2'],
            'biochemical_data.triglycerides' => ['nullable', 'numeric', 'between:10,5000', 'decimal:0,2'],
            'biochemical_data.hdl' => ['nullable', 'numeric', 'between:10,5000', 'decimal:0,2'],
            'biochemical_data.urr' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
            'biochemical_data.bp' => ['nullable', 'string', 'max:20'],
            'biochemical_data.abg' => ['nullable', 'string', 'max:100'],
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

                $category = $this->exists('primary_diagnosis_category')
                    ? $this->input('primary_diagnosis_category')
                    : $assessment?->primary_diagnosis_category;
                if (blank($category)) {
                    $validator->errors()->add('primary_diagnosis_category', 'The nutrition care category field is required.');
                }

                $other = $this->exists('primary_diagnosis_other')
                    ? $this->input('primary_diagnosis_other')
                    : $assessment?->primary_diagnosis_other;
                if ($category === PrimaryDiagnosisCategory::OTHER && blank($other)) {
                    $validator->errors()->add('primary_diagnosis_other', 'The specified category field is required when nutrition care category is Other.');
                }

                $periodValue = $this->exists('weight_change_period_value')
                    ? $this->input('weight_change_period_value')
                    : $assessment?->weight_change_period_value;
                $periodUnit = $this->exists('weight_change_period_unit')
                    ? $this->input('weight_change_period_unit')
                    : $assessment?->weight_change_period_unit;
                if (blank($periodValue) xor blank($periodUnit)) {
                    $missingField = blank($periodValue) ? 'weight_change_period_value' : 'weight_change_period_unit';
                    $validator->errors()->add($missingField, 'Weight change duration requires both a value and unit.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return array_merge(self::PRESCRIPTION_INPUTS, [
            'weight_change_period_value' => 'weight change duration',
            'weight_change_period_unit' => 'weight change duration unit',
            'primary_diagnosis_category' => 'nutrition care category',
            'primary_diagnosis_other' => 'specified category',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $assessment = $this->route('ncpRecord')?->assessment;
        $category = $this->exists('primary_diagnosis_category')
            ? $this->input('primary_diagnosis_category')
            : $assessment?->primary_diagnosis_category;

        if ($category !== PrimaryDiagnosisCategory::OTHER) {
            $this->merge(['primary_diagnosis_other' => null]);
        }
    }
}
