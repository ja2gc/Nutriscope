<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'rnd_user_id' => $this->rnd_user_id,
            'name' => $this->name,
            'cycle_days' => $this->cycle_days,
            'is_active' => (bool) $this->is_active,
            'week_start_date' => $this->week_start_date?->toDateString(),
            'status' => $this->status,
            'activation_date' => $this->activation_date?->toDateString(),
            'days_count' => $this->whenCounted('days'),
            'plan_days' => $this->whenLoaded('days', fn () => collect(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])
                ->mapWithKeys(fn ($day) => [$day => $this->days
                    ->where('day_of_week', $day)
                    ->contains(fn ($slot) => $slot->recipe_id || $slot->fs_item_id)])
                ->all()),
            'days' => $this->whenLoaded('days', fn () => $this->days->map(fn ($d) => [
                'id' => $d->id,
                'day_of_week' => $d->day_of_week,
                'meal_type' => $d->meal_type,
                // Expose the public uuids (matching the nested recipe/fs_item embeds
                // below) — the flat FK columns are the raw internal ints, and clients
                // feed these values back into uuid-bound routes (recipe profile fetch,
                // menu-cycle save). Null when the relation isn't loaded, mirroring the
                // nested embeds' own availability.
                'recipe_id' => $d->relationLoaded('recipe') && $d->recipe ? $d->recipe->uuid : null,
                'fs_item_id' => $d->relationLoaded('fsItem') && $d->fsItem ? $d->fsItem->uuid : null,
                'quantity' => $d->quantity,
                'servings_override' => $d->servings_override,
                'estimate_population' => $d->estimate_population,
                'estimate_population_updated_at' => $d->estimate_population_updated_at?->toISOString(),
                'is_event' => (bool) $d->is_event,
                'event_allocation' => $d->event_allocation,
                // Frozen scaled snapshot from the food PO conversion (null until converted).
                'po_snapshot' => $d->po_snapshot,
                'po_snapshot_at' => $d->po_snapshot_at?->toISOString(),
                'po_snapshot_locked' => (bool) $d->po_snapshot_locked,
                'snapshot_purchase_order_id' => $d->snapshot_purchase_order_id,
                'recipe' => $d->relationLoaded('recipe') && $d->recipe ? [
                    'id' => $d->recipe->uuid, 'name' => $d->recipe->name, 'servings' => $d->recipe->servings, 'cost' => $d->recipe->cost,
                ] : null,
                'fs_item' => $d->relationLoaded('fsItem') && $d->fsItem ? [
                    'id' => $d->fsItem->uuid, 'name' => $d->fsItem->name,
                ] : null,
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
