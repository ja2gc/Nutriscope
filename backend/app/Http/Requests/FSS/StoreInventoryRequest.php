<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'food_item_id'             => ['required', 'exists:food_items,id'],
            'quantity_in_stock'        => ['required', 'numeric', 'min:0'],
            'unit'                     => ['required', 'string'],
            'expiry_date'              => ['nullable', 'date'],
            'usage_rate'               => ['nullable', 'numeric', 'min:0'],
            'minimum_stock_threshold'  => ['nullable', 'numeric', 'min:0'],
            'notes'                    => ['nullable', 'string'],
        ];
    }
}
