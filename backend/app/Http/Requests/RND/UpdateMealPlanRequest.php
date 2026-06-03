<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMealPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'          => ['nullable', 'string', 'in:draft,active,completed'],
            'week_start_date' => ['nullable', 'date'],
        ];
    }
}
