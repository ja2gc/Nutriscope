<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->uuid,
            // The day's public uuid — clients key their day lookup by MealPlanResource's
            // uuid 'id', so the raw internal FK here would never match. Falls back to the
            // raw column only if the relation somehow isn't resolvable.
            'meal_plan_day_id'  => $this->mealPlanDay?->uuid ?? $this->meal_plan_day_id,
            'food_item_id'      => $this->food_item_id,
            'fdc_id'            => $this->fdc_id,
            'recipe_id'         => $this->recipe_id,
            'quantity'          => $this->quantity,
            'unit'              => $this->unit,
            'nutrient_snapshot' => $this->nutrient_snapshot,
            'ai_suggested'      => $this->ai_suggested,
            'source'            => $this->fdc_id ? 'usda' : ($this->food_item_id ? 'library' : 'recipe'),
        ];
    }
}
