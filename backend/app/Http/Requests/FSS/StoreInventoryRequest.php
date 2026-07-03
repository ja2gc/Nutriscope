<?php

namespace App\Http\Requests\FSS;

use App\Http\Requests\Concerns\ResolvesUuidForeignKeys;
use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
{
    use ResolvesUuidForeignKeys;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'item_type'               => ['required', 'in:ingredient,supply,recipe'],
            'fs_item_id'              => ['nullable', 'string', 'exists:fs_items,uuid', 'required_unless:item_type,recipe'],
            'recipe_id'               => ['nullable', 'string', 'exists:food_service_recipes,uuid', 'required_if:item_type,recipe'],
            'quantity_in_stock'       => ['nullable', 'numeric', 'min:0'],
            'unit'                    => ['nullable', 'string'],
            'unit_price'              => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string'],
        ];
    }

    protected function uuidForeignKeyMap(): array
    {
        return ['fs_item_id' => FsItem::class, 'recipe_id' => FoodServiceRecipe::class];
    }
}
