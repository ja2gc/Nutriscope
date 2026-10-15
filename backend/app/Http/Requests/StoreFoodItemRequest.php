<?php

namespace App\Http\Requests;

use App\Models\FoodItem;
use App\Support\UnitConverter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'water_g' => 'sometimes|nullable|numeric|min:0|decimal:0,2',
            'micronutrients' => 'sometimes|nullable|array',
            'allergens' => 'sometimes|nullable|array',
            'allergens.*' => 'string|max:100',
            'serving_unit' => 'sometimes|required|string|max:50',
            'serving_size' => 'sometimes|required|numeric|between:0.000001,10000|decimal:0,6',
            'usda_fdc_id' => 'sometimes|nullable|integer',
            'recipe_corrections' => 'sometimes|array',
            'recipe_corrections.*.recipe_id' => 'required|string|exists:recipes,uuid',
            'recipe_corrections.*.line_index' => 'required|integer|min:0',
            'recipe_corrections.*.quantity' => 'required|numeric|between:0.000001,100000|decimal:0,6',
            'recipe_corrections.*.unit' => 'required|string|max:50',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('serving_unit'))) {
            $this->merge(['serving_unit' => UnitConverter::canonical($this->input('serving_unit'))]);
        }
        $size = $this->input('serving_size');
        if (is_float($size) && round($size, 6) === $size) {
            $this->merge(['serving_size' => number_format($size, 6, '.', '')]);
        }
        if (is_array($this->input('recipe_corrections'))) {
            $this->merge(['recipe_corrections' => array_map(function ($correction) {
                if (is_array($correction) && is_string($correction['unit'] ?? null)) {
                    $correction['unit'] = UnitConverter::canonical($correction['unit']);
                }
                if (is_array($correction) && is_float($correction['quantity'] ?? null) && round($correction['quantity'], 6) === $correction['quantity']) {
                    $correction['quantity'] = number_format($correction['quantity'], 6, '.', '');
                }

                return $correction;
            }, $this->input('recipe_corrections'))]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->has('serving_unit')) {
                return;
            }
            $food = $this->route('food_item');
            $unit = $this->input('serving_unit');
            $unchanged = $food instanceof FoodItem && UnitConverter::normalize((string) $food->serving_unit) === UnitConverter::normalize($unit);
            if (! UnitConverter::isKnown($unit) && $unit !== 'piece' && ! $unchanged) {
                $validator->errors()->add('serving_unit', 'Choose a mass, volume, or measured piece unit.');
            }
            if ($unit === 'piece' && ! $unchanged && (float) $this->input('serving_size') !== 1.0) {
                $validator->errors()->add('serving_size', 'Enter the measured nutrients for one piece and use a baseline of 1 piece.');
            }
        }];
    }
}
