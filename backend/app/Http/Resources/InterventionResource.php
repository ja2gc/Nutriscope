<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterventionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'goal_type' => $this->goal_type,
            'disease_stage' => $this->disease_stage,
            'displayed_nutrients' => $this->displayed_nutrients,
            'energy_kcal' => $this->energy_kcal,
            'protein_g' => $this->protein_g,
            'carbs_g' => $this->carbs_g,
            'fat_g' => $this->fat_g,
            'fluid_ml' => $this->fluid_ml,
            'micronutrient_limits' => $this->micronutrient_limits,
            'education_notes' => $this->education_notes,
            'counseling_goals' => $this->counseling_goals,
            'barriers' => $this->barriers,
            'strategies' => $this->strategies,
            'session_type' => $this->session_type,
            'next_followup_date' => $this->next_followup_date?->toDateString(),
            'source_monitoring_id' => $this->sourceMonitoring?->uuid,
            'source_monitoring_date' => $this->sourceMonitoring?->observed_at?->toDateString()
                ?? $this->sourceMonitoring?->created_at?->toDateString(),
            'has_meal_plan' => $this->mealPlan !== null,
            'meal_plan_id' => $this->mealPlan?->uuid,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
