<?php

namespace App\Http\Requests\FSS;

use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreMenuCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cycle_days' => ['nullable', 'integer', 'in:7'],
            'week_start_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],

            'days' => ['nullable', 'array'],
            'days.*.day_of_week' => ['required_with:days', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'days.*.meal_type' => ['required_with:days', 'in:breakfast,am_snack,lunch,pm_snack,dinner'],
            'days.*.recipe_id' => ['nullable', 'string', 'exists:food_service_recipes,uuid'],
            'days.*.fs_item_id' => ['nullable', 'string', 'exists:fs_items,uuid'],
            'days.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'days.*.servings_override' => ['nullable', 'integer', 'min:1'],
            'days.*.estimate_population' => ['nullable', 'integer', 'min:0'],
            'days.*.is_event' => ['nullable', 'boolean'],
            'days.*.event_allocation' => ['nullable', 'numeric', 'min:0'],
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

    /** Frontend pickers send each day slot's recipe/fs_item public uuid — resolve to internal ids. */
    protected function passedValidation(): void
    {
        if ($this->has('days')) {
            $days = collect($this->input('days'))->map(function ($d) {
                if (! empty($d['recipe_id'])) {
                    $d['recipe_id'] = FoodServiceRecipe::idFromUuid($d['recipe_id']);
                }
                if (! empty($d['fs_item_id'])) {
                    $d['fs_item_id'] = FsItem::idFromUuid($d['fs_item_id']);
                }

                return $d;
            })->all();
            $this->merge(['days' => $days]);
        }
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data) && array_key_exists('days', $data)) {
            $data['days'] = $this->input('days');
        }

        return $data;
    }
}
