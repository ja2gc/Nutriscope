<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'shopping_list_id' => ['nullable', 'exists:shopping_lists,id'],
            'supplier_id'      => ['nullable', 'exists:suppliers,id'],
            'order_date'       => ['nullable', 'date'],
            'total_amount'     => ['nullable', 'numeric', 'min:0'],
            'status'           => ['nullable', 'string', 'in:draft,ordered,received'],
            'receipt_image'    => ['nullable', 'string'],
            'notes'            => ['nullable', 'string'],
        ];
    }
}
