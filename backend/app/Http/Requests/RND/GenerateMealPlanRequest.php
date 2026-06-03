<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class GenerateMealPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'week_start_date' => ['required', 'date'],
            'conditions'      => ['nullable', 'array'],
            'conditions.*'    => ['nullable', 'string'],
            'allergens'       => ['nullable', 'array'],
            'allergens.*'     => ['nullable', 'string'],
        ];
    }
}
