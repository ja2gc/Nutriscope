<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCleaningLogRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'item_name'  => ['sometimes', 'required', 'string', 'max:255'],
            'category'   => ['nullable', 'string', 'max:255'],
            'status'     => ['nullable', 'string', 'in:pending,done'],
            'notes'      => ['nullable', 'string'],
            'cleaned_at' => ['nullable', 'date'],
        ];
    }
}
