<?php

namespace App\Http\Requests\FSS;

use App\Models\FsItem;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuSlotRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reference_servings' => ['required', 'integer', 'min:1'],
            'planned_servings' => ['required', 'integer', 'min:1'],
            'prep_notes' => ['nullable', 'string', 'max:5000'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.fs_item_id' => ['required', 'string', 'distinct', 'exists:fs_items,uuid'],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0'],
            'ingredients.*.unit' => ['required', 'string', 'max:30'],
        ];
    }

    protected function passedValidation(): void
    {
        $ingredients = collect($this->input('ingredients'))->map(function (array $ingredient): array {
            $ingredient['fs_item_id'] = FsItem::idFromUuid($ingredient['fs_item_id']);

            return $ingredient;
        })->all();

        $this->merge(['ingredients' => $ingredients]);
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data)) {
            $data['ingredients'] = $this->input('ingredients');
        }

        return $data;
    }
}
