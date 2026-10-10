<?php

namespace App\Http\Requests\RND;

use App\Support\MonitoringVisitType;
use Carbon\CarbonImmutable;
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
            'observed_at' => ['required', 'date', 'before_or_equal:'.CarbonImmutable::now('Asia/Manila')->toDateString()],
            'visit_type' => ['required', 'string', Rule::in(MonitoringVisitType::values())],
            'weight' => ['required', 'numeric', "between:{$bounds['weight']['min']},{$bounds['weight']['max']}"],
            'height' => ['required', 'numeric', "between:{$bounds['height']['min']},{$bounds['height']['max']}"],
            'edema_present' => ['required', 'boolean'],
            'dry_weight_kg' => ['nullable', 'numeric', "between:{$bounds['dry_weight_kg']['min']},{$bounds['dry_weight_kg']['max']}"],
            'physical_activity_level' => ['required', 'string', Rule::in(['sedentary', 'light', 'moderate', 'very_active', 'extra_active'])],
            'pregnancy_lactation_status' => ['required', 'string', Rule::in(['none', 'pregnant_t1', 'pregnant_t2', 'pregnant_t3', 'pregnant_unspecified', 'lactating'])],
            'allergies' => ['present', 'array'],
            'allergies.*' => ['string', 'max:160'],
            'dietary_restrictions' => ['present', 'nullable', 'string'],
            'food_dislikes' => ['present', 'array'],
            'food_dislikes.*' => ['string', 'max:160'],
            'lab_values' => ['nullable', 'array', 'max:40'],
            'intake_notes' => ['nullable', 'string', 'max:400'],
            'symptoms' => ['nullable', 'string', 'max:400'],
            'goal_achievement' => ['nullable', 'array'],
            'clinical_summary' => ['nullable', 'string', 'max:3000'],
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

                $limits = [
                    'albumin' => [0, 10], 'calcium' => [0, 30], 'potassium' => [0, 15],
                    'sodium' => [50, 200], 'magnesium' => [0, 15], 'phosphate' => [0, 20],
                    'bun' => [0, 300], 'creatinine' => [0, 30], 'hba1c' => [2, 25],
                    'hematocrit' => [10, 75], 'hemoglobin' => [2, 25], 'urr' => [0, 100],
                    'glucose' => [10, 2000], 'triglycerides' => [10, 5000],
                    'cholesterol' => [10, 5000], 'ldl' => [10, 5000], 'hdl' => [10, 5000],
                    'energy_kcal' => [0, 10000], 'fluid_ml' => [0, 10000],
                    'protein_g' => [0, 1000], 'carbs_g' => [0, 1000], 'fat_g' => [0, 1000],
                ];
                $valid = match ($key) {
                    'bp' => is_string($value) && mb_strlen($value) <= 20,
                    'abg' => is_string($value) && mb_strlen($value) <= 100,
                    default => (isset($limits[$key]) || preg_match('/^micro_[a-z0-9_]{1,80}$/D', (string) $key) === 1)
                        && is_numeric($value)
                        && (isset($limits[$key])
                            ? ((float) $value >= $limits[$key][0] && (float) $value <= $limits[$key][1])
                            : ((float) $value >= 0 && (float) $value <= 100000))
                        && preg_match('/^-?\d+(?:\.\d{1,2})?$/D', (string) $value) === 1,
                };

                if (! $valid) {
                    $validator->errors()->add("lab_values.{$key}", 'The lab value must be numeric.');
                }
            }
        }];
    }
}
