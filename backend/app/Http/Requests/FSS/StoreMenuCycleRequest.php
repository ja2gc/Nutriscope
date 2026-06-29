<?php

namespace App\Http\Requests\FSS;

use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMenuCycleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:255'],
            'cycle_days'              => ['nullable', 'integer', 'in:7'],
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
            'days.*.is_event'            => ['nullable', 'boolean'],
            'days.*.event_allocation'    => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $date = $this->input('week_start_date');
            if (! $date) {
                return;
            }

            try {
                if (Carbon::parse($date)->dayOfWeek !== Carbon::MONDAY) {
                    $validator->errors()->add('week_start_date', 'Week start date must be a Monday.');
                }
            } catch (\Throwable) {
                // The date rule reports invalid date formats.
            }
        });
    }
}
