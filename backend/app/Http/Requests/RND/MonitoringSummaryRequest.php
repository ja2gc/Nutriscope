<?php

namespace App\Http\Requests\RND;

use App\Services\FoodRecallService;
use Illuminate\Validation\Rule;

class MonitoringSummaryRequest extends MonitoringRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            ...FoodRecallService::intakeRules(),
            'clinical_summary' => ['prohibited'],
            'monitoring_id' => ['nullable', 'uuid'],
            'entry_key' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/D'],
            'draft_id' => ['required', 'uuid'],
            'goal_achievement' => ['nullable', 'array:compliance,gi_tolerance,continuation_decision'],
            'goal_achievement.compliance' => ['sometimes', Rule::in(['compliant', 'partial', 'non_compliant'])],
            'goal_achievement.gi_tolerance' => ['sometimes', Rule::in(['tolerating', 'not_tolerating'])],
            'goal_achievement.continuation_decision' => ['sometimes', Rule::in(['continue', 'modify', 'discontinue'])],
        ]);
    }
}
