<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'item_type'               => ['required', 'in:ingredient,supply,recipe'],
            'fs_item_id'              => ['nullable', 'exists:fs_items,id', 'required_unless:item_type,recipe'],
            'recipe_id'               => ['nullable', 'exists:food_service_recipes,id', 'required_if:item_type,recipe'],
            'quantity_in_stock'       => ['required', 'numeric', 'min:0'],
            'unit'                    => ['required', 'string'],
            'expiry_date'             => ['nullable', 'date'],
            'usage_rate'              => ['nullable', 'numeric', 'min:0'],
            'minimum_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'unit_price'              => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string'],
        ];
    }
}
