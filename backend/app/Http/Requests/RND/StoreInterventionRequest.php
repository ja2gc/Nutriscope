<?php

namespace App\Http\Requests\RND;

use App\Support\InterventionGoalCatalog;
use App\Support\InterventionGuidance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInterventionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goal_type' => ['nullable', 'string', Rule::in(InterventionGoalCatalog::goalTypes())],
            'disease_stage' => ['nullable', 'string', 'max:255'],
            'displayed_nutrients' => ['nullable', 'array'],
            'energy_kcal' => ['nullable', 'numeric', 'between:0,10000', 'decimal:0,2'],
            'protein_g' => ['nullable', 'numeric', 'between:0,1000', 'decimal:0,2'],
            'carbs_g' => ['nullable', 'numeric', 'between:0,1000', 'decimal:0,2'],
            'fat_g' => ['nullable', 'numeric', 'between:0,1000', 'decimal:0,2'],
            'fluid_ml' => ['nullable', 'numeric', 'between:0,10000', 'decimal:0,2'],
            'micronutrient_limits' => ['nullable', 'array'],
            'micronutrient_limits.*.target' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'micronutrient_limits.*.min' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'micronutrient_limits.*.max' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'nutrient_operators' => ['nullable', 'array:energy_kcal,protein_g,carbs_g,fat_g,fluid_ml'],
            'nutrient_operators.*' => ['required', Rule::in(['min', 'max'])],
            'weight_target' => ['nullable', 'array:target,min,max'],
            'weight_target.*' => ['required', 'numeric', 'between:1,400', 'decimal:0,1', 'regex:/^\d{1,3}(?:\.\d)?$/'],
            'education_notes' => ['nullable', 'string', 'max:'.InterventionGuidance::FIELD_MAX],
            'counseling_goals' => ['nullable', 'string', 'max:'.InterventionGuidance::FIELD_MAX],
            'barriers' => ['nullable', 'string', 'max:'.InterventionGuidance::FIELD_MAX],
            'strategies' => ['nullable', 'string', 'max:'.InterventionGuidance::FIELD_MAX],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $weight = $this->input('weight_target');
                if (is_array($weight) && (isset($weight['target'])
                    ? count($weight) !== 1 : ! isset($weight['min'], $weight['max']))) {
                    $validator->errors()->add('weight_target', 'Enter a single target weight or both ends of a range.');
                }
                if (is_array($weight) && isset($weight['min'], $weight['max']) && (float) $weight['min'] > (float) $weight['max']) {
                    $validator->errors()->add('weight_target', 'Target weight minimum must not exceed maximum.');
                }
                foreach ((array) $this->input('micronutrient_limits', []) as $key => $limit) {
                    if (! is_array($limit)) {
                        $validator->errors()->add("micronutrient_limits.{$key}", 'Enter a nutrient target or limit.');

                        continue;
                    }
                    if (isset($limit['target']) && (isset($limit['min']) || isset($limit['max']))) {
                        $validator->errors()->add("micronutrient_limits.{$key}", 'Choose a target or limits, not both.');
                    }
                    if (isset($limit['min'], $limit['max']) && (float) $limit['min'] > (float) $limit['max']) {
                        $validator->errors()->add("micronutrient_limits.{$key}", 'Minimum must not exceed maximum.');
                    }
                }
                if (InterventionGuidance::characterCount($this->all()) > InterventionGuidance::TOTAL_MAX) {
                    $validator->errors()->add(
                        'education_notes',
                        'Education and counseling guidance must not exceed '.number_format(InterventionGuidance::TOTAL_MAX).' characters in total.',
                    );
                }

                $goalType = $this->input('goal_type');
                $stage = $this->input('disease_stage');

                if ($goalType === null || $goalType === '') {
                    if ($stage !== null && $stage !== '') {
                        $validator->errors()->add('disease_stage', 'A disease stage can only be set with a valid intervention goal.');
                    }

                    return;
                }

                if (! InterventionGoalCatalog::stageIsValid($goalType, $stage)) {
                    $validator->errors()->add('disease_stage', 'The selected disease stage is invalid for the intervention goal.');
                }
            },
        ];
    }
}
