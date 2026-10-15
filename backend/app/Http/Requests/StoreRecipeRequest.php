<?php

namespace App\Http\Requests;

use App\Models\FoodItem;
use App\Support\UnitConverter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

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
            'name' => $isPost ? 'required|string|max:255' : 'sometimes|string|max:255',
            'category' => $isPost
                ? 'required|string|in:meal,carb,snack'
                : 'sometimes|string|in:meal,carb,snack',
            'meal_types' => 'sometimes|nullable|array',
            'meal_types.*' => 'string|in:breakfast,am_snack,lunch,pm_snack,dinner,snack,any',
            'prep_notes' => 'sometimes|nullable|string|max:2000',
            'servings' => $isPost
                ? 'required|integer|between:1,10000'
                : 'sometimes|integer|between:1,10000',
            'ingredients' => $isPost
                ? 'required|array|min:1'
                : 'sometimes|array|min:1',
            'ingredients.*.food_item_id' => 'required_with:ingredients.*|string|exists:food_items,uuid',
            'ingredients.*.quantity' => 'required_with:ingredients.*|numeric|between:0.000001,100000|decimal:0,6',
            'ingredients.*.unit' => 'required_with:ingredients.*|string|max:50',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('ingredients'))) {
            $this->merge(['ingredients' => array_map(function ($ingredient) {
                if (is_array($ingredient) && is_string($ingredient['unit'] ?? null)) {
                    $ingredient['unit'] = UnitConverter::canonical($ingredient['unit']);
                }
                if (is_array($ingredient) && is_float($ingredient['quantity'] ?? null) && round($ingredient['quantity'], 6) === $ingredient['quantity']) {
                    $ingredient['quantity'] = number_format($ingredient['quantity'], 6, '.', '');
                }

                return $ingredient;
            }, $this->input('ingredients'))]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->has('ingredients')) {
                return;
            }
            $foods = FoodItem::query()->whereIn('uuid', array_column($this->input('ingredients'), 'food_item_id'))->get()->keyBy('uuid');
            foreach ($this->input('ingredients') as $index => $ingredient) {
                $food = $foods->get($ingredient['food_item_id']);
                try {
                    UnitConverter::scalingFactor((float) $ingredient['quantity'], $ingredient['unit'], (float) $food->serving_size, (string) $food->serving_unit);
                } catch (InvalidArgumentException $exception) {
                    $validator->errors()->add("ingredients.{$index}.unit", $exception->getMessage());
                }
            }
        }];
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
