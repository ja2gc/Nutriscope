<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'allocated_amount' => ['nullable', 'numeric', 'min:0'],
            'actual_amount'    => ['nullable', 'numeric', 'min:0'],
            'period_start'     => ['nullable', 'date'],
            'period_end'       => ['nullable', 'date', 'after_or_equal:period_start'],
            'cost_per_person'  => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
