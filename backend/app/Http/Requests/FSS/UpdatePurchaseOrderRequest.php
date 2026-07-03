<?php

namespace App\Http\Requests\FSS;

use App\Http\Requests\Concerns\ResolvesUuidForeignKeys;
use App\Models\ShoppingList;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    use ResolvesUuidForeignKeys;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'shopping_list_id' => ['nullable', 'string', 'exists:shopping_lists,uuid'],
            'supplier_id'      => ['nullable', 'string', 'exists:suppliers,uuid'],
            'po_number'        => ['nullable', 'string'],
            'or_number'        => ['nullable', 'string'],
            'order_date'       => ['nullable', 'date'],
            'total_amount'     => ['nullable', 'numeric', 'min:0'],
            'status'           => ['nullable', 'string', 'in:draft,ordered,received'],
            'lifecycle_status' => ['nullable', 'string', 'in:archived'],
            'notes'            => ['nullable', 'string'],
        ];
    }

    protected function uuidForeignKeyMap(): array
    {
        return ['shopping_list_id' => ShoppingList::class, 'supplier_id' => Supplier::class];
    }
}
