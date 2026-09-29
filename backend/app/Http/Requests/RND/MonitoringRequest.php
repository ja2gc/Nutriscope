<?php

namespace App\Http\Requests\RND;

use App\Support\MonitoringVisitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class MonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bounds = config('clinical.assessment_input_bounds');

        return [
            'observed_at' => ['required', 'date', 'before_or_equal:today'],
            'visit_type' => ['required', 'string', Rule::in(MonitoringVisitType::values())],
            'weight' => ['required', 'numeric', "between:{$bounds['weight']['min']},{$bounds['weight']['max']}"],
            'height' => ['required', 'numeric', "between:{$bounds['height']['min']},{$bounds['height']['max']}"],
            'edema_present' => ['required', 'boolean'],
            'dry_weight_kg' => ['nullable', 'numeric', "between:{$bounds['dry_weight_kg']['min']},{$bounds['dry_weight_kg']['max']}"],
            'physical_activity_level' => ['required', 'string', Rule::in(['sedentary', 'light', 'moderate', 'very_active', 'extra_active'])],
            'pregnancy_lactation_status' => ['required', 'string', Rule::in(['none', 'pregnant_t1', 'pregnant_t2', 'pregnant_t3', 'pregnant_unspecified', 'lactating'])],
            'allergies' => ['required', 'array'],
            'allergies.*' => ['string', 'max:160'],
            'dietary_restrictions' => ['present', 'nullable', 'string'],
            'food_dislikes' => ['required', 'array'],
            'food_dislikes.*' => ['string', 'max:160'],
            'lab_values' => ['nullable', 'array'],
            'intake_notes' => ['nullable', 'string'],
            'symptoms' => ['nullable', 'string'],
            'goal_achievement' => ['nullable', 'array'],
            'clinical_summary' => ['nullable', 'string'],
            'ai_decision' => ['nullable', 'string'],
            'next_monitoring_date' => ['nullable', 'date', 'after_or_equal:observed_at'],
            'intervention_revision' => ['prohibited'],
            'intervention_revision_id' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('edema_present') && blank($this->input('dry_weight_kg'))) {
                $validator->errors()->add('dry_weight_kg', 'Dry weight is required when edema is present.');
            }

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
