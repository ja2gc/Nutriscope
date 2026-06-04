<?php

namespace App\Http\Requests;

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
            'ingredients.*.food_item_id'    => 'required_with:ingredients.*|integer|exists:food_items,id',
            'ingredients.*.quantity'        => 'required_with:ingredients.*|numeric|min:0.01',
            'ingredients.*.unit'            => 'required_with:ingredients.*|string|max:50',
        ];
    }
}
