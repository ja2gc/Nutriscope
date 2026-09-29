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
            'energy_kcal' => ['nullable', 'numeric', 'min:0'],
            'protein_g' => ['nullable', 'numeric', 'min:0'],
            'carbs_g' => ['nullable', 'numeric', 'min:0'],
            'fat_g' => ['nullable', 'numeric', 'min:0'],
            'fluid_ml' => ['nullable', 'numeric', 'min:0'],
            'micronutrient_limits' => ['nullable', 'array'],
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
