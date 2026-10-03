<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoodItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isPost = $this->isMethod('POST');

        return [
            'name' => $isPost ? 'required|string|max:255' : 'sometimes|string|max:255',
            'calories' => $isPost ? 'required|numeric|between:0,10000|decimal:0,2' : 'sometimes|numeric|between:0,10000|decimal:0,2',
            'category' => 'sometimes|nullable|string|max:100',
            'ready_to_eat' => 'sometimes|nullable|boolean',
            'protein' => 'sometimes|nullable|numeric|between:0,1000|decimal:0,2',
            'carbs' => 'sometimes|nullable|numeric|between:0,1000|decimal:0,2',
            'fat' => 'sometimes|nullable|numeric|between:0,1000|decimal:0,2',
            'micronutrients' => 'sometimes|nullable|array',
            'allergens' => 'sometimes|nullable|array',
            'allergens.*' => 'string|max:100',
            'serving_unit' => 'sometimes|nullable|string|max:50',
            'serving_size' => 'sometimes|nullable|numeric|between:0.01,10000|decimal:0,2',
            'usda_fdc_id' => 'sometimes|nullable|integer',
        ];
    }
}
