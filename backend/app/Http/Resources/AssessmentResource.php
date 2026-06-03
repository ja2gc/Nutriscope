<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'ncp_record_id'        => $this->ncp_record_id,
            'dietary_intake'       => $this->dietary_intake,
            'appetite_changes'     => $this->appetite_changes,
            'dietary_restrictions' => $this->dietary_restrictions,
            'supplements'          => $this->supplements,
            'knowledge_notes'      => $this->knowledge_notes,
            'weight'               => $this->weight,
            'height'               => $this->height,
            'bmi'                  => $this->bmi,
            'body_composition'     => $this->body_composition,
            'medical_history'      => $this->medical_history,
            'social_history'       => $this->social_history,
            'lifestyle'            => $this->lifestyle,
            'allergies'            => $this->allergies,
            'food_dislikes'        => $this->food_dislikes,
            'medications'          => $this->medications,
            'rnd_summary'          => $this->rnd_summary,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
