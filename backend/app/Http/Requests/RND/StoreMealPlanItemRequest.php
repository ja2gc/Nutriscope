<?php

namespace App\Http\Requests\RND;

use App\Http\Requests\Concerns\ResolvesUuidForeignKeys;
use App\Models\FoodItem;
use App\Models\Recipe;
use Illuminate\Foundation\Http\FormRequest;

class StoreMealPlanItemRequest extends FormRequest
{
    use ResolvesUuidForeignKeys;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'food_item_id' => 'sometimes|nullable|string|exists:food_items,uuid',
            'fdc_id' => 'sometimes|nullable|string|regex:/^\d{1,10}$/',
            'recipe_id' => 'sometimes|nullable|string|exists:recipes,uuid',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50',
        ];
    }

    protected function uuidForeignKeyMap(): array
    {
        return ['food_item_id' => FoodItem::class, 'recipe_id' => Recipe::class];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $sources = array_filter([
                $this->input('food_item_id'),
                $this->input('fdc_id'),
                $this->input('recipe_id'),
            ], fn ($val) => filled($val));

            if (count($sources) > 1) {
                $v->errors()->add('food_item_id', 'Provide exactly one of: food_item_id, fdc_id, or recipe_id.');
            }
            if (count($sources) === 0) {
                $v->errors()->add('food_item_id', 'One of food_item_id, fdc_id, or recipe_id is required.');
            }
        });
    }
}
