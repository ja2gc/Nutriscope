<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->uuid,
            'intervention_id' => $this->intervention_id,
            'patient_id'      => $this->patient_id,
            'week_start_date' => $this->week_start_date?->toDateString(),
            'generation_type' => $this->generation_type,
            'status'          => $this->status,
            'days'            => $this->whenLoaded('days', fn() => $this->days->map(fn($d) => [
                'id'          => $d->uuid,
                'day_of_week' => $d->day_of_week,
                'meal_type'   => $d->meal_type,
                'flagged'     => (bool) ($d->flagged ?? false),
            ])),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
