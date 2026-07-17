<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class MonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weight' => ['nullable', 'numeric', 'min:0'],
            'bmi' => ['nullable', 'numeric', 'min:0'],
            'lab_values' => ['nullable', 'array'],
            'intake_notes' => ['nullable', 'string'],
            'symptoms' => ['nullable', 'string'],
            'goal_achievement' => ['nullable', 'array'],
            'clinical_summary' => ['nullable', 'string'],
            'ai_decision' => ['nullable', 'string'],
            'next_monitoring_date' => ['nullable', 'date'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('lab_values', []) as $key => $value) {
                if ($value === null) {
                    continue;
                }

                $valid = $key === 'bp'
                    ? is_string($value) && mb_strlen($value) <= 20
                    : is_numeric($value);

                if (! $valid) {
                    $validator->errors()->add("lab_values.{$key}", 'The lab value must be numeric.');
                }
            }
        }];
    }
}
