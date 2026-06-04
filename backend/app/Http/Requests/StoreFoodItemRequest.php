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
            'name'           => $isPost ? 'required|string|max:255' : 'sometimes|string|max:255',
            'calories'       => $isPost ? 'required|numeric|min:0' : 'sometimes|numeric|min:0',
            'category'       => 'sometimes|nullable|string|max:100',
            'protein'        => 'sometimes|nullable|numeric|min:0',
            'carbs'          => 'sometimes|nullable|numeric|min:0',
            'fat'            => 'sometimes|nullable|numeric|min:0',
            'micronutrients' => 'sometimes|nullable|array',
            'allergens'      => 'sometimes|nullable|array',
            'allergens.*'    => 'string|max:100',
            'serving_unit'   => 'sometimes|nullable|string|max:50',
            'serving_size'   => 'sometimes|nullable|numeric|min:0',
            'usda_fdc_id'    => 'sometimes|nullable|integer',
        ];
    }
}
