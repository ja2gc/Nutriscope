<?php

namespace App\Http\Requests\RND;

use App\Support\InterventionGoalCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMonitoringRequest extends MonitoringRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'intervention_revision' => ['sometimes', 'array:effective_date,reason,snapshot'],
            'intervention_revision.effective_date' => ['required_with:intervention_revision', 'date'],
            'intervention_revision.reason' => ['required_with:intervention_revision', 'string', 'max:500'],
            'intervention_revision.snapshot' => [
                'required_with:intervention_revision',
                'array:goal_type,disease_stage,displayed_nutrients,energy_kcal,protein_g,carbs_g,fat_g,fluid_ml,micronutrient_limits,education_notes,counseling_goals,barriers,strategies,session_type,next_followup_date',
            ],
            'intervention_revision.snapshot.goal_type' => [
                'required_with:intervention_revision',
                'string',
                Rule::in(InterventionGoalCatalog::goalTypes()),
            ],
            'intervention_revision.snapshot.disease_stage' => ['nullable', 'string', 'max:255'],
            'intervention_revision.snapshot.displayed_nutrients' => ['nullable', 'array'],
            'intervention_revision.snapshot.displayed_nutrients.*' => ['string', 'max:100'],
            'intervention_revision.snapshot.energy_kcal' => ['required_with:intervention_revision', 'numeric', 'gt:0'],
            'intervention_revision.snapshot.protein_g' => ['required_with:intervention_revision', 'numeric', 'gt:0'],
            'intervention_revision.snapshot.carbs_g' => ['required_with:intervention_revision', 'numeric', 'gt:0'],
            'intervention_revision.snapshot.fat_g' => ['required_with:intervention_revision', 'numeric', 'gt:0'],
            'intervention_revision.snapshot.fluid_ml' => ['nullable', 'numeric', 'min:0'],
            'intervention_revision.snapshot.micronutrient_limits' => ['nullable', 'array'],
            'intervention_revision.snapshot.education_notes' => ['nullable', 'string'],
            'intervention_revision.snapshot.counseling_goals' => ['nullable', 'string'],
            'intervention_revision.snapshot.barriers' => ['nullable', 'string'],
            'intervention_revision.snapshot.strategies' => ['nullable', 'string'],
            'intervention_revision.snapshot.session_type' => ['nullable', 'string', 'max:100'],
            'intervention_revision.snapshot.next_followup_date' => ['nullable', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if (! $this->has('intervention_revision')) {
                    return;
                }

                $goalType = $this->input('intervention_revision.snapshot.goal_type');
                $stage = $this->input('intervention_revision.snapshot.disease_stage');
                if (is_string($goalType) && ! InterventionGoalCatalog::stageIsValid($goalType, $stage)) {
                    $validator->errors()->add(
                        'intervention_revision.snapshot.disease_stage',
                        'The selected disease stage is invalid for the intervention goal.',
                    );
                }
            },
        ];
    }
}
