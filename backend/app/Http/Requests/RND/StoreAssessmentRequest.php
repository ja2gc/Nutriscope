<?php

namespace App\Http\Requests\RND;

use App\Services\FoodRecallService;
use App\Support\NutritionSupport;
use App\Support\PrimaryDiagnosisCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssessmentRequest extends FormRequest
{
    private const PRESCRIPTION_INPUTS = [
        'weight',
        'usual_weight',
        'height',
        'physical_activity_level',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bounds = config('clinical.assessment_input_bounds');

        return [
            'nutrition_support' => [Rule::requiredIf(! $this->route('ncpRecord')?->nutrition_support), Rule::in(NutritionSupport::VALUES)],
            ...FoodRecallService::rules(),
            ...FoodRecallService::intakeRules(),
            'dietary_intake' => ['nullable', 'string', 'max:250'],
            'appetite_changes' => ['nullable', 'string', 'max:400'],
            'dietary_restrictions' => ['nullable', 'string', 'max:100'],
            'supplements' => ['nullable', 'string', 'max:100'],
            'knowledge_notes' => ['nullable', 'string', 'max:250'],
            'weight' => ['required', 'numeric', "between:{$bounds['weight']['min']},{$bounds['weight']['max']}", 'decimal:0,2'],
            'height' => ['required', 'numeric', "between:{$bounds['height']['min']},{$bounds['height']['max']}", 'decimal:0,2'],
            'body_composition' => ['nullable', 'string', 'max:400'],
            'medical_history' => ['nullable', 'string', 'max:250'],
            'social_history' => ['nullable', 'string', 'max:250'],
            'religion' => ['nullable', 'string', 'max:100'],
            'lifestyle' => ['nullable', 'string', 'max:400'],
            'allergies' => ['nullable', 'array'],
            'food_dislikes' => ['nullable', 'array', 'max:5'],
            'food_dislikes.*' => ['string', 'max:50'],
            'medications' => ['nullable', 'array', 'max:1'],
            'medications.*' => ['string', 'max:100'],
            'rnd_summary' => ['nullable', 'string', 'max:3500'],
            'usual_weight' => ['required', 'numeric', "between:{$bounds['usual_weight']['min']},{$bounds['usual_weight']['max']}", 'decimal:0,2'],
            'nutritional_status' => ['nullable', 'string', 'in:Normal,Moderate Malnutrition,Severe Malnutrition'],
            'weight_loss_percentage' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
            'weight_loss_period' => ['nullable', 'string', 'max:400'],
            'weight_change_period_value' => ['nullable', 'required_with:weight_change_period_unit', 'integer', 'between:1,104'],
            'weight_change_period_unit' => ['nullable', 'required_with:weight_change_period_value', Rule::in(['weeks', 'months'])],
            'primary_diagnosis_category' => ['required', 'string', Rule::in(PrimaryDiagnosisCategory::values())],
            'primary_diagnosis_other' => ['nullable', 'required_if:primary_diagnosis_category,'.PrimaryDiagnosisCategory::OTHER, 'string', 'max:150'],
            'functional_assessment' => ['nullable', 'string', Rule::in($this->functionalAssessmentOptions())],
            'energy_intake_status' => ['nullable', 'string', 'in:No change,Mostly liquids,Sub-optimal,Starvation,Poor intake prior to admission'],
            'ibw_percentage' => ['nullable', 'numeric', 'between:0,300', 'decimal:0,2'],
            'present_diet' => ['nullable', 'string', 'max:250'],
            'physical_assessment' => ['nullable', 'string', 'max:400'],
            'chewing_swallowing_difficulties' => ['nullable', Rule::in($this->symptomOptions('chewing_swallowing_difficulties'))],
            'chewing_swallowing_details' => ['nullable', 'string', 'max:150'],
            'constipation' => ['nullable', Rule::in($this->symptomOptions('constipation'))],
            'constipation_details' => ['nullable', 'string', 'max:150'],
            'diarrhea_notes' => ['nullable', Rule::in($this->symptomOptions('diarrhea_notes'))],
            'diarrhea_details' => ['nullable', 'string', 'max:150'],
            'food_intolerance' => ['nullable', 'string', 'max:100'],
            'nutrient_drug_interaction' => ['nullable', 'string', 'max:100'],
            'dietary_intake_method' => ['nullable', 'string', 'in:24_hour_recall,food_frequency,3_day_record,other'],
            'dietary_record_file' => ['nullable', 'string', 'max:400'],
            // Clinical measurement fields
            // Note: physical_activity_level accepts canonical AND legacy strings;
            // Assessment::normalizedActivityLevel() canonicalises before engine use.
            'physical_activity_level' => ['required', 'string', 'in:sedentary,light,moderate,very_active,extra_active'],
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
            'biochemical_data.abg' => ['nullable', 'string', 'max:6', 'regex:/^[+-]?(?:\d+(?:\.\d{0,2})?|\.\d{1,2})$/'],
            'biochemical_data.others' => ['nullable', 'array'],
            'risk_score_manual_override' => ['nullable', 'boolean'],
            'risk_score_manual_factors' => ['nullable', 'array'],
            'risk_score_manual_factors.*' => ['string', 'in:screening_criteria,ibw_limit,unintentional_weight_loss,mechanical_digestive_problem,low_albumin,significant_lab_result,others'],
        ];
    }

    public function messages(): array
    {
        $bounds = config('clinical.assessment_input_bounds');

        return array_merge(
            collect(self::PRESCRIPTION_INPUTS)
                ->mapWithKeys(fn (string $field) => [
                    "{$field}.required" => 'This field is required before nutrition prescription calculation.',
                ])
                ->all(),
            [
                'weight.between' => "Body weight must be between {$bounds['weight']['min']} and {$bounds['weight']['max']} kg. Check the entry for a typo.",
                'usual_weight.between' => "Usual body weight must be between {$bounds['usual_weight']['min']} and {$bounds['usual_weight']['max']} kg. Check the entry for a typo.",
                'dry_weight_kg.between' => "Dry weight must be between {$bounds['dry_weight_kg']['min']} and {$bounds['dry_weight_kg']['max']} kg. Check the entry for a typo.",
                'dry_weight_kg.required' => 'Dry weight is required when edema is present.',
                'height.between' => "Height must be between {$bounds['height']['min']} and {$bounds['height']['max']} cm. Check the entry for a typo.",
            ],
        );
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('edema_present') && blank($this->input('dry_weight_kg'))) {
                    $validator->errors()->add('dry_weight_kg', 'Dry weight is required when edema is present.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'weight' => 'body weight',
            'usual_weight' => 'usual body weight',
            'dry_weight_kg' => 'dry weight',
            'height' => 'height',
            'physical_activity_level' => 'physical activity level',
            'weight_change_period_value' => 'weight change duration',
            'weight_change_period_unit' => 'weight change duration unit',
            'primary_diagnosis_category' => 'nutrition care category',
            'primary_diagnosis_other' => 'specified category',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('primary_diagnosis_category') !== PrimaryDiagnosisCategory::OTHER) {
            $this->merge(['primary_diagnosis_other' => null]);
        }
    }

    private function functionalAssessmentOptions(): array
    {
        $options = ['Independent', 'Needs assistance', 'Bedridden'];
        $existing = $this->route('ncpRecord')?->assessment?->functional_assessment;

        return $existing && ! in_array($existing, $options, true)
            ? [...$options, $existing]
            : $options;
    }

    private function symptomOptions(string $field): array
    {
        $options = ['Yes', 'No'];
        $existing = $this->route('ncpRecord')?->assessment?->{$field};

        return $existing && ! in_array($existing, $options, true)
            ? [...$options, $existing]
            : $options;
    }
}
