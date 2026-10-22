<?php

namespace App\Http\Requests\RND;

use App\Models\Intervention;
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
        $existing = $this->route('intervention');
        $goals = InterventionGoalCatalog::goalTypes();
        if ($existing instanceof Intervention && $existing->goal_type) {
            $goals[] = $existing->goal_type;
        }
        $guidanceMax = fn (string $field): string => 'max:'.($existing instanceof Intervention && $this->input($field) === $existing->getAttribute($field)
            ? max(InterventionGuidance::FIELD_MAX, mb_strlen((string) $existing->getAttribute($field))) : InterventionGuidance::FIELD_MAX);

        return [
            ...self::calculationRules(),
            'recalculate' => ['nullable', 'boolean'],
            'goal_type' => ['nullable', 'string', Rule::in($goals)],
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
            'education_notes' => ['nullable', 'string', $guidanceMax('education_notes')],
            'counseling_goals' => ['nullable', 'string', $guidanceMax('counseling_goals')],
            'barriers' => ['nullable', 'string', $guidanceMax('barriers')],
            'strategies' => ['nullable', 'string', $guidanceMax('strategies')],
        ];
    }

    public static function calculationRules(): array
    {
        return [
            'energy_adjustment_mode' => ['nullable', Rule::in(['none', 'surplus', 'deficit'])],
            'energy_adjustment_kcal' => ['nullable', 'integer', 'between:0,10000', 'required_if:energy_adjustment_mode,surplus,deficit'],
            'fluid_mode' => ['nullable', Rule::in(['estimated', 'restricted'])],
            'calculation_options' => ['nullable', 'array:energy_per_kg,protein_per_kg,clinical_weight_kg,protein_weight_kg,weight_basis,nutrition_status,maternal_confirmed,refeeding_risk,malnutrition_note,fat_fraction'],
            'calculation_options.energy_per_kg' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'calculation_options.protein_per_kg' => ['nullable', 'numeric', 'gt:0', 'max:5'],
            'calculation_options.clinical_weight_kg' => ['nullable', 'numeric', 'between:1,400'],
            'calculation_options.protein_weight_kg' => ['nullable', 'numeric', 'between:1,400'],
            'calculation_options.weight_basis' => ['nullable', Rule::in(['measured', 'dry', 'clinician_selected'])],
            'calculation_options.nutrition_status' => ['nullable', Rule::in(['adequate', 'malnutrition_or_muscle_loss', 'unconfirmed'])],
            'calculation_options.maternal_confirmed' => ['nullable', 'boolean'],
            'calculation_options.refeeding_risk' => ['nullable', 'boolean'],
            'calculation_options.malnutrition_note' => ['nullable', 'boolean'],
            'calculation_options.fat_fraction' => ['nullable', 'numeric', 'gt:0', 'lt:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                if (in_array($this->input('energy_adjustment_mode'), ['surplus', 'deficit'], true) && (float) $this->input('energy_adjustment_kcal', 0) <= 0) {
                    $validator->errors()->add('energy_adjustment_kcal', 'Enter a positive whole-number kcal adjustment.');
                }
                if ($this->input('fluid_mode') === 'restricted' && (float) $this->input('fluid_ml', 0) <= 0) {
                    $validator->errors()->add('fluid_ml', 'Enter a positive prescribed fluid limit.');
                }
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
                $existing = $this->route('intervention');
                $guidanceChanged = ! ($existing instanceof Intervention) || collect(InterventionGuidance::FIELDS)->contains(fn (string $field): bool => $this->exists($field) && $this->input($field) !== $existing->getAttribute($field));
                $guidanceValues = array_replace($existing instanceof Intervention ? $existing->only(InterventionGuidance::FIELDS) : [], $this->all());
                if ($guidanceChanged && InterventionGuidance::characterCount($guidanceValues) > InterventionGuidance::TOTAL_MAX) {
                    $validator->errors()->add(
                        'education_notes',
                        'Education and counseling guidance must not exceed '.number_format(InterventionGuidance::TOTAL_MAX).' characters in total.',
                    );
                }

                $goalType = $this->input('goal_type');
                $stage = $this->input('disease_stage');
                if ($this->acceptsSavedGoalSelection()) {
                    return;
                }

                if ($goalType === null || $goalType === '') {
                    if ($stage !== null && $stage !== '') {
                        $validator->errors()->add('disease_stage', 'A disease stage can only be set with a valid intervention goal.');
                    }

                    return;
                }

                if (! InterventionGoalCatalog::stageIsValid($goalType, $stage)) {
                    $validator->errors()->add('disease_stage', 'Choose a current goal and type before calculating. Existing saved targets can still be edited without recalculation.');
                }
            },
        ];
    }

    protected function acceptsSavedGoalSelection(): bool
    {
        $existing = $this->route('intervention');

        return $existing instanceof Intervention && $this->input('goal_type') === $existing->goal_type
            && $this->input('disease_stage') === $existing->disease_stage;
    }
}
