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
            'id' => $this->id,
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'ncp_records' => $this->whenLoaded('ncpRecords'),
            // This reflects when the assessment was created, not when the NCP record was last changed.
            'last_assessment_date' => $latestNcpRecord?->assessment?->created_at,
            'next_followup_date' => $latestNcpRecord?->intervention?->next_followup_date,
            'risk_score' => $latestNcpRecord?->risk_score,
            'latest_ncp_id' => $latestNcpRecord?->id,
        ];
    }
}
