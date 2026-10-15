<?php

namespace App\Http\Requests\RND;

use App\Http\Requests\Concerns\ResolvesUuidForeignKeys;
use App\Models\Recipe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'food_item_id' => 'prohibited',
            'fdc_id' => 'prohibited',
            'recipe_id' => ['required', 'string', Rule::exists('recipes', 'uuid')->whereIn('category', ['meal', 'carb', 'snack'])],
            'quantity' => 'required|numeric|between:0.01,999999.99|decimal:0,2',
            'unit' => 'required|string|in:serving',
        ];
    }

    protected function uuidForeignKeyMap(): array
    {
        return ['recipe_id' => Recipe::class];
    }
}
