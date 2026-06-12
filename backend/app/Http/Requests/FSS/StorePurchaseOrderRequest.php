<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'shopping_list_id'   => ['nullable', 'exists:shopping_lists,id'],
            'supplier_id'        => ['nullable', 'exists:suppliers,id'],
            'po_number'          => ['nullable', 'string', 'unique:purchase_orders,po_number'],
            'or_number'          => ['nullable', 'string'],
            'order_date'         => ['nullable', 'date'],
            'total_amount'       => ['nullable', 'numeric', 'min:0'],
            'status'             => ['nullable', 'string', 'in:draft,ordered,received'],
            'notes'              => ['nullable', 'string'],
            'items'              => ['nullable', 'array'],
            'items.*.fs_item_id'  => ['nullable', 'integer', 'exists:fs_items,id'],
            'items.*.qty'         => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_price'  => ['required_with:items', 'numeric', 'min:0'],
            'items.*.unit'        => ['nullable', 'string'],
            'items.*.description' => ['nullable', 'string'],
        ];
    }
}
