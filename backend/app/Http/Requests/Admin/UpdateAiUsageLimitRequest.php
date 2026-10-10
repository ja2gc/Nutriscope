<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiUsageLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'daily_token_limit' => ['nullable', 'integer', 'between:0,100000000'],
            'monthly_token_limit' => ['nullable', 'integer', 'between:0,100000000'],
            'input_cost_per_1m_tokens_usd' => ['sometimes', 'numeric', 'between:0,1000', 'decimal:0,4'],
            'output_cost_per_1m_tokens_usd' => ['sometimes', 'numeric', 'between:0,1000', 'decimal:0,4'],
        ];
    }
}
