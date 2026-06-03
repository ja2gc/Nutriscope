<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class StoreMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weight'               => ['nullable', 'numeric', 'min:0'],
            'bmi'                  => ['nullable', 'numeric', 'min:0'],
            'lab_values'           => ['nullable', 'array'],
            'intake_notes'         => ['nullable', 'string'],
            'symptoms'             => ['nullable', 'string'],
            'goal_achievement'     => ['nullable', 'array'],
            'clinical_summary'     => ['nullable', 'string'],
            'ai_decision'          => ['nullable', 'string'],
            'next_monitoring_date' => ['nullable', 'date'],
        ];
    }
}
