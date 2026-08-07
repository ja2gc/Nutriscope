<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScreeningDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'patient_id' => $this->patient_id,
            'assessment_id' => $this->assessment_id,
            'type' => $this->type,
            'file_url' => '/api/rnd/screening-documents/'.$this->uuid.'/file',
            'original_name' => $this->original_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
