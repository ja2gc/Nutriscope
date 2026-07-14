<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestNcpRecord = $this->relationLoaded('ncpRecords')
            ? $this->ncpRecords->first()
            : null;

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'dob' => $this->dob,
            'sex' => $this->sex,
            'religion' => $this->religion,
            'address' => $this->address,
            'contact' => $this->contact,
            'physician' => $this->physician,
            'admission_date' => $this->admission_date,
            'medical_diagnosis' => $this->medical_diagnosis,
            'ward' => $this->ward,
            'status' => $this->status,
            'screening_type' => $this->screening_type,
            'hospital_number' => $this->hospital_number,
            'age_group_category' => $this->age_group_category,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'ncp_records' => $this->whenLoaded('ncpRecords'),
            // This reflects when the assessment was created, not when the NCP record was last changed.
            'last_assessment_date' => $latestNcpRecord?->assessment?->created_at,
            'next_followup_date' => $latestNcpRecord?->intervention?->next_followup_date,
            'risk_score' => $latestNcpRecord?->risk_score,
            'latest_ncp_id' => $latestNcpRecord?->uuid,
            'latest_ncp_created_by' => $this->resource->getAttribute('latest_ncp_created_by'),
            'last_clinical_action' => $this->resource->getAttribute('last_clinical_action'),
        ];
    }
}
