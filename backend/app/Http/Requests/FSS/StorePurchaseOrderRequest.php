<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'shopping_list_id'          => ['nullable', 'exists:shopping_lists,id'],
            'supplier_id'               => ['required', 'exists:suppliers,id'],
            'po_number'                 => ['nullable', 'string', 'unique:purchase_orders,po_number'],
            'order_date'                => ['nullable', 'date'],
            'total_amount'              => ['nullable', 'numeric', 'min:0'],
            'status'                    => ['nullable', 'string', 'in:draft,ordered,received'],
            'receipt_image'             => ['nullable', 'string'],
            'notes'                     => ['nullable', 'string'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.food_item_id'      => ['required', 'exists:food_items,id'],
            'items.*.quantity'          => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'        => ['required', 'numeric', 'min:0'],
            'items.*.unit'              => ['nullable', 'string'],
            'items.*.description'       => ['nullable', 'string'],
        ];
    }
}
