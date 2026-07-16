<?php

namespace App\Services\Audit;

use App\Models\FoodItem;

final class FoodItemAuditValues
{
    /** @return array<string, string|int|float|bool|null> */
    public function values(FoodItem $food): array
    {
        return [
            'name' => (string) $food->name,
            'category' => $food->category,
            'ready_to_eat' => $food->ready_to_eat,
            'usda_fdc_id' => $food->usda_fdc_id,
            'serving_size' => $food->serving_size !== null ? (float) $food->serving_size : null,
            'serving_unit' => $food->serving_unit,
            'calories' => (float) $food->calories,
            'protein' => $food->protein !== null ? (float) $food->protein : null,
            'carbs' => $food->carbs !== null ? (float) $food->carbs : null,
            'fat' => $food->fat !== null ? (float) $food->fat : null,
            'water_g' => $food->water_g,
            'unit_price' => $food->unit_price !== null ? (float) $food->unit_price : null,
        ];
    }

    public function source(FoodItem $food): string
    {
        return $food->usda_fdc_id === null ? 'custom' : 'usda';
    }
}
