<?php

namespace App\Http\Requests\FSS;

use App\Http\Requests\Concerns\ResolvesUuidForeignKeys;
use App\Models\MenuCycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDietListCountRequest extends FormRequest
{
    use ResolvesUuidForeignKeys;

    public function authorize(): bool
    {
        return $this->user()?->isFss() === true;
    }

    public function rules(): array
    {
        return [
            'service_date' => ['required', 'date_format:Y-m-d'],
            'ward' => ['nullable', 'string', 'max:255'],
            'population' => ['nullable', 'integer', 'min:0'],
            'collected_ward_diet_lists' => ['nullable', 'integer', 'min:0'],
            'apportioned_distributed_meals' => ['nullable', 'integer', 'min:0'],
            'menu_cycle_id' => ['nullable', 'string', 'exists:menu_cycles,uuid'],
            'helped_food_prep' => ['sometimes', 'boolean'],
            'stored_supplies' => ['sometimes', 'boolean'],
            'collected_diet_list' => ['sometimes', 'boolean'],
            'apportioned_food' => ['sometimes', 'boolean'],
            'cleaned_utensils' => ['sometimes', 'boolean'],
            'assistant_cook' => ['sometimes', 'boolean'],
            'maintained_cleanliness' => ['sometimes', 'boolean'],
            'off_duty' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('service_date')) {
                return;
            }

            if ($this->input('ward') === null && ! $this->hasAny(['collected_ward_diet_lists', 'apportioned_distributed_meals'])) {
                $validator->errors()->add('ward', 'Ward is required for legacy entries.');
            }
            if ($this->input('ward') !== null && ! $this->hasAny(['collected_ward_diet_lists', 'apportioned_distributed_meals']) && $this->input('population') === null) {
                $validator->errors()->add('population', 'Population is required for legacy entries.');
            }

            $serviceDate = (string) $this->input('service_date');
            $today = CarbonImmutable::now('Asia/Manila')->toDateString();
            if ($serviceDate > $today) {
                $validator->errors()->add('service_date', 'The service date cannot be in the future.');
            }

            if (! $this->boolean('off_duty')) {
                return;
            }

            if ((int) $this->input('population', 0) !== 0 || (int) $this->input('collected_ward_diet_lists', 0) !== 0 || (int) $this->input('apportioned_distributed_meals', 0) !== 0) {
                $validator->errors()->add('population', 'Population must be zero when off duty.');
            }

            foreach ($this->taskFields() as $field) {
                if ($this->boolean($field)) {
                    $validator->errors()->add($field, 'Completed duties cannot be selected when off duty.');
                }
            }
        }];
    }

    /** @return list<string> */
    private function taskFields(): array
    {
        return [
            'helped_food_prep',
            'stored_supplies',
            'collected_diet_list',
            'apportioned_food',
            'cleaned_utensils',
            'assistant_cook',
            'maintained_cleanliness',
        ];
    }

    protected function uuidForeignKeyMap(): array
    {
        return ['menu_cycle_id' => MenuCycle::class];
    }
}
