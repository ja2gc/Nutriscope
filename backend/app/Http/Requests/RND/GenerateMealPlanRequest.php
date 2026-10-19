<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateMealPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'intervention_plan_id' => ['required', 'uuid'],
            'week_start_date' => ['required', 'date'],
            'conditions' => ['nullable', 'array'],
            'conditions.*' => ['nullable', 'string'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => ['nullable', 'string'],
            'exclude_snacks' => ['sometimes', 'boolean'],
            'replace' => ['sometimes', 'boolean'],
            'exclude_carbs' => ['sometimes', 'boolean'],
            'carb_menu_item_id' => [
                Rule::requiredIf(fn (): bool => ! $this->boolean('exclude_carbs')),
                Rule::prohibitedIf(fn (): bool => $this->boolean('exclude_carbs')),
                'nullable', 'uuid', Rule::exists('recipes', 'uuid')->where('category', 'carb'),
            ],
            'use_rice_as_carb' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'carb_menu_item_id.required' => 'Choose a valid Carb Menu Item or select Exclude carbs.',
            'carb_menu_item_id.uuid' => 'Choose a valid Carb Menu Item or select Exclude carbs.',
            'carb_menu_item_id.exists' => 'Choose a valid Carb Menu Item or select Exclude carbs.',
            'carb_menu_item_id.prohibited' => 'Clear the Carb selection when Exclude carbs is selected.',
            'use_rice_as_carb.prohibited' => 'Choose a Carb Menu Item or select Exclude carbs instead of the retired rice option.',
        ];
    }
}
