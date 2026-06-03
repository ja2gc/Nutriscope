<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScreeningDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'assessment_id' => $this->assessment_id,
            'type' => $this->type,
            'file_path' => $this->file_path,
            'extracted_data' => $this->extracted_data,
            'mapped_fields' => $this->mapped_fields,
            'status' => $this->status,
            'confidence_score' => $this->confidence_score,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
