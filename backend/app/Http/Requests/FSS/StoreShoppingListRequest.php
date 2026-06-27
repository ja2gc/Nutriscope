<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StoreShoppingListRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'list_date'     => ['nullable', 'date'],
            'list_type'     => ['nullable', 'string', 'in:manual,suggested'],
            'status'        => ['nullable', 'string', 'in:draft,converted'],
            'period_start'  => ['nullable', 'date'],
            'period_end'    => ['nullable', 'date', 'after_or_equal:period_start'],
            'days_span'     => ['nullable', 'integer', 'min:1', 'max:60'],
            'estimate_population' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
