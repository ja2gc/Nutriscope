<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string'],
            'contact' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'payment_terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
