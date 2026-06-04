<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'rnd_user_id'    => $this->rnd_user_id,
            'name'           => $this->name,
            'category'       => $this->category,
            'prep_notes'     => $this->prep_notes,
            'servings'       => $this->servings,
            'total_calories' => $this->total_calories,
            'total_protein'  => $this->total_protein,
            'total_carbs'    => $this->total_carbs,
            'total_fat'      => $this->total_fat,
            'cost'           => $this->cost,
            'micronutrients' => $this->micronutrients ?? [],
            'ingredients'    => $this->whenLoaded('ingredients', fn() =>
                $this->ingredients->map(fn($ing) => [
                    'id'           => $ing->id,
                    'food_item_id' => $ing->food_item_id,
                    'quantity'     => $ing->quantity,
                    'unit'         => $ing->unit,
                    'food_item'    => $ing->relationLoaded('foodItem') ? [
                        'id'           => $ing->foodItem->id,
                        'name'         => $ing->foodItem->name,
                        'calories'     => $ing->foodItem->calories,
                        'protein'      => $ing->foodItem->protein,
                        'carbs'        => $ing->foodItem->carbs,
                        'fat'          => $ing->foodItem->fat,
                        'serving_size' => $ing->foodItem->serving_size,
                        'serving_unit' => $ing->foodItem->serving_unit,
                    ] : null,
                ])
            ),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
