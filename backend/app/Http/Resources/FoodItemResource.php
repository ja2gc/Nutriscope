<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'category' => $this->category,
            'ready_to_eat' => $this->ready_to_eat,
            'usda_fdc_id' => $this->usda_fdc_id,
            'calories' => $this->calories,
            'protein' => $this->protein,
            'carbs' => $this->carbs,
            'fat' => $this->fat,
            'water_g' => $this->water_g,
            'micronutrients' => $this->micronutrients ?? [],
            'allergens' => $this->allergens ?? [],
            'serving_unit' => $this->serving_unit,
            'serving_size' => $this->serving_size,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
