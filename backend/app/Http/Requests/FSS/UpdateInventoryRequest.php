<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'quantity_in_stock'        => ['nullable', 'numeric', 'min:0'],
            'unit'                     => ['nullable', 'string'],
            'unit_price'               => ['nullable', 'numeric', 'min:0'],
            'notes'                    => ['nullable', 'string'],
        ];
    }
}
