<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StoreShoppingListRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $isSupplies = $this->input('procurement_track') === 'supplies';

        return [
            'name'              => ['required', 'string', 'max:255'],
            'list_date'         => ['nullable', 'date'],
            'list_type'         => ['nullable', 'string', 'in:manual,suggested'],
            'procurement_track' => ['nullable', 'string', 'in:food,supplies'],
            'status'            => ['nullable', 'string', 'in:draft,converted'],
            // Supplies lists are fully manual — no date span, no menu cycle, no population.
            'period_start'  => [$isSupplies ? 'prohibited' : 'nullable', 'date'],
            'period_end'    => [$isSupplies ? 'prohibited' : 'nullable', 'date', 'after_or_equal:period_start'],
            'days_span'     => [$isSupplies ? 'prohibited' : 'nullable', 'integer', 'min:1', 'max:60'],
            'estimate_population' => [$isSupplies ? 'prohibited' : 'nullable', 'integer', 'min:0'],
        ];
    }
}
