<?php

namespace App\Http\Requests\FSS;

use App\Http\Requests\Concerns\ResolvesUuidForeignKeys;
use App\Models\MenuCycle;
use Illuminate\Foundation\Http\FormRequest;

class StoreDietListCountRequest extends FormRequest
{
    use ResolvesUuidForeignKeys;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_date' => ['required', 'date'],
            'ward' => ['required', 'string', 'max:255'],
            'population' => ['required', 'integer', 'min:0'],
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

    protected function uuidForeignKeyMap(): array
    {
        return ['menu_cycle_id' => MenuCycle::class];
    }
}
