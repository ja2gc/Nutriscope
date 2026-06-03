<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StoreShoppingListRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'list_date'    => ['required', 'date'],
            'list_type'    => ['nullable', 'string', 'in:manual,suggested'],
            'status'       => ['nullable', 'string', 'in:draft,finalized'],
            'period_start' => ['nullable', 'date'],
            'period_end'   => ['nullable', 'date', 'after_or_equal:period_start'],
        ];
    }
}
