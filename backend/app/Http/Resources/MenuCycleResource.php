<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'rnd_user_id'             => $this->rnd_user_id,
            'name'                    => $this->name,
            'population'              => (int) $this->population,
            'budget_per_head_per_day' => $this->budget_per_head_per_day,
            'cycle_days'              => $this->cycle_days,
            'is_active'               => (bool) $this->is_active,
            'week_start_date'         => $this->week_start_date?->toDateString(),
            'status'                  => $this->status,
            'activation_date'         => $this->activation_date?->toDateString(),
            'days'                    => $this->whenLoaded('days', fn () => $this->days->map(fn ($d) => [
                'id'                => $d->id,
                'day_of_week'       => $d->day_of_week,
                'meal_type'         => $d->meal_type,
                'recipe_id'         => $d->recipe_id,
                'fs_item_id'        => $d->fs_item_id,
                'quantity'          => $d->quantity,
                'servings_override' => $d->servings_override,
                'estimate_population' => $d->estimate_population,
                'is_event'          => (bool) $d->is_event,
                'event_allocation'  => $d->event_allocation,
                'recipe'            => $d->relationLoaded('recipe') && $d->recipe ? [
                    'id' => $d->recipe->id, 'name' => $d->recipe->name, 'servings' => $d->recipe->servings, 'cost' => $d->recipe->cost,
                ] : null,
                'fs_item'           => $d->relationLoaded('fsItem') && $d->fsItem ? [
                    'id' => $d->fsItem->id, 'name' => $d->fsItem->name,
                ] : null,
            ])->values()),
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
        ];
    }
}
