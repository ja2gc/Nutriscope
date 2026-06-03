<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitoringResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'ncp_record_id'        => $this->ncp_record_id,
            'weight'               => $this->weight,
            'bmi'                  => $this->bmi,
            'lab_values'           => $this->lab_values,
            'intake_notes'         => $this->intake_notes,
            'symptoms'             => $this->symptoms,
            'goal_achievement'     => $this->goal_achievement,
            'clinical_summary'     => $this->clinical_summary,
            'ai_decision'          => $this->ai_decision,
            'next_monitoring_date' => $this->next_monitoring_date?->toDateString(),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
