<?php

namespace App\Http\Requests;

use App\Models\FoodItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isPost = $this->isMethod('POST');

        return [
            'name'                          => $isPost ? 'required|string|max:255' : 'sometimes|string|max:255',
            'category'                      => 'sometimes|nullable|string|max:100',
            'prep_notes'                    => 'sometimes|nullable|string',
            'servings'                      => 'sometimes|nullable|integer|min:1',
            'ingredients'                   => 'sometimes|array',
            'ingredients.*.food_item_id'    => 'required_with:ingredients.*|string|exists:food_items,uuid',
            'ingredients.*.quantity'        => 'required_with:ingredients.*|numeric|min:0.01',
            'ingredients.*.unit'            => 'required_with:ingredients.*|string|max:50',
        ];
    }

    /** Frontend picker sends the food item's public uuid — resolve to the internal id. */
    protected function passedValidation(): void
    {
        if ($this->has('ingredients')) {
            $ingredients = collect($this->input('ingredients'))->map(function ($ing) {
                if (! empty($ing['food_item_id'])) {
                    $ing['food_item_id'] = FoodItem::idFromUuid($ing['food_item_id']);
                }
                return $ing;
            })->all();
            $this->merge(['ingredients' => $ingredients]);
        }
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data) && array_key_exists('ingredients', $data)) {
            $data['ingredients'] = $this->input('ingredients');
        }
        return $data;
    }
}
