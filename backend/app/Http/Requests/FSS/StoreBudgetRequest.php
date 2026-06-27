<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'fiscal_year'        => ['required', 'integer', 'min:2000', 'max:2100', 'unique:budgets,fiscal_year'],
            'allocated_amount'   => ['required', 'numeric', 'min:0'],
            'per_head_day_limit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
