<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuCycleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'       => ['nullable', 'string', 'max:255'],
            'cycle_days' => ['nullable', 'integer', 'min:1', 'max:28'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }
}
