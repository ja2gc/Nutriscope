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
            'daily_token_limit' => ['nullable', 'integer', 'min:0'],
            'monthly_token_limit' => ['nullable', 'integer', 'min:0'],
            'input_cost_per_1m_tokens_usd' => ['sometimes', 'numeric', 'min:0'],
            'output_cost_per_1m_tokens_usd' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
