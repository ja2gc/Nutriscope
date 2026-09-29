<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitoringResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'observed_at' => $this->observed_at?->toDateString(),
            'visit_type' => $this->visit_type,
            'weight' => $this->weight,
            'height' => $this->height,
            'edema_present' => $this->edema_present,
            'dry_weight_kg' => $this->dry_weight_kg,
            'physical_activity_level' => $this->physical_activity_level,
            'pregnancy_lactation_status' => $this->pregnancy_lactation_status,
            'allergies' => $this->allergies,
            'dietary_restrictions' => $this->dietary_restrictions,
            'food_dislikes' => $this->food_dislikes,
            'bmi' => $this->bmi,
            'lab_values' => $this->lab_values,
            'intake_notes' => $this->intake_notes,
            'symptoms' => $this->symptoms,
            'goal_achievement' => $this->goal_achievement,
            'clinical_summary' => $this->clinical_summary,
            'ai_decision' => $this->ai_decision,
            'next_monitoring_date' => $this->next_monitoring_date?->toDateString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
