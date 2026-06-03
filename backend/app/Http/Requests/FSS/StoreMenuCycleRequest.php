<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuCycleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'cycle_days'      => ['nullable', 'integer', 'min:1', 'max:28'],
            'is_active'       => ['nullable', 'boolean'],
            'week_start_date' => ['nullable', 'date'],
        ];
    }
}
