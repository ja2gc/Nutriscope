<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'week_start_date' => ['required', 'date'],
            'generation_type' => ['nullable', 'string', 'in:manual,auto'],
            'status'          => ['nullable', 'string', 'in:draft,active,completed'],
        ];
    }
}
