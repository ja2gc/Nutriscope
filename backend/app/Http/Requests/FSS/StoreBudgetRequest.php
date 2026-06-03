<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'allocated_amount' => ['required', 'numeric', 'min:0'],
            'actual_amount'    => ['nullable', 'numeric', 'min:0'],
            'period_start'     => ['required', 'date'],
            'period_end'       => ['required', 'date', 'after_or_equal:period_start'],
            'cost_per_person'  => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
