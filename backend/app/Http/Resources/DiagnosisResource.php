<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiagnosisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->uuid,
            'ncp_record_id'  => $this->ncp_record_id,
            'domain'         => $this->domain,
            'problem'        => $this->problem,
            'label'          => $this->label ?? $this->problem,
            'etiology'       => $this->etiology,
            'signs_symptoms' => $this->signs_symptoms,
            'pes_statement'  => $this->pes_statement,
            'extra_notes'    => $this->extra_notes,
            'ai_generated'   => $this->ai_generated,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
