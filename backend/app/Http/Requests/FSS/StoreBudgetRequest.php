<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'scope'                 => ['nullable', 'in:monthly,quarterly,yearly,custom'],
            'menu_cycle_id'         => ['nullable', 'integer', 'exists:menu_cycles,id'],
            'name'                  => ['nullable', 'string', 'max:255'],
            'allocated_amount'      => ['required', 'numeric', 'min:0'],
            'actual_amount'         => ['nullable', 'numeric', 'min:0'],
            'period_start'          => ['nullable', 'date'],
            'period_end'            => ['nullable', 'date', 'after_or_equal:period_start'],
            'cost_per_person'       => ['nullable', 'numeric', 'min:0'],
            'population'             => ['nullable', 'integer', 'min:0'],
            'budget_per_head_day'   => ['nullable', 'numeric', 'min:0'],
            'budget_per_head_month' => ['nullable', 'numeric', 'min:0'],
            'budget_per_head_year'  => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
