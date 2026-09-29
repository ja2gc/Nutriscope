<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterventionPlanSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'plan_date' => $this->created_at?->toISOString(),
            'goal_type' => $this->goal_type,
            'disease_stage' => $this->disease_stage,
            'source_monitoring_id' => $this->sourceMonitoring?->uuid,
            'source_monitoring_date' => $this->sourceMonitoring?->observed_at?->toDateString()
                ?? $this->sourceMonitoring?->created_at?->toDateString(),
            'has_meal_plan' => $this->mealPlan !== null,
            'meal_plan_id' => $this->mealPlan?->uuid,
        ];
    }
}
