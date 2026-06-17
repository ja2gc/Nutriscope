<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuCycleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                    => ['sometimes', 'string', 'max:255'],
            'population'              => ['nullable', 'integer', 'min:0'],
            'budget_per_head_per_day' => ['nullable', 'numeric', 'min:0'],
            'cycle_days'              => ['nullable', 'integer', 'min:1', 'max:28'],
            'week_start_date'         => ['nullable', 'date'],
            'is_active'               => ['nullable', 'boolean'],

            'days'                       => ['nullable', 'array'],
            'days.*.day_of_week'         => ['required_with:days', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'days.*.meal_type'           => ['required_with:days', 'in:breakfast,am_snack,lunch,pm_snack,dinner'],
            'days.*.recipe_id'           => ['nullable', 'integer', 'exists:food_service_recipes,id'],
            'days.*.fs_item_id'          => ['nullable', 'integer', 'exists:fs_items,id'],
            'days.*.quantity'            => ['nullable', 'numeric', 'min:0'],
            'days.*.servings_override'   => ['nullable', 'integer', 'min:1'],
            'days.*.estimate_population' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
