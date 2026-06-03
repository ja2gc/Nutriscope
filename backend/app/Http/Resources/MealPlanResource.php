<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'intervention_id' => $this->intervention_id,
            'patient_id'      => $this->patient_id,
            'week_start_date' => $this->week_start_date?->toDateString(),
            'generation_type' => $this->generation_type,
            'status'          => $this->status,
            'days'            => $this->whenLoaded('days', fn() => $this->days->map(fn($d) => [
                'id'            => $d->id,
                'day_number'    => $d->day_number ?? null,
                'meal_date'     => $d->meal_date ?? null,
                'items'         => $d->items ?? [],
            ])),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
