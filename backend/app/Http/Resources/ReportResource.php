<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'title'        => $this->title,
            'type'         => $this->type,
            'filters'      => $this->filters,
            'parameters'   => $this->parameters,
            'snapshot'     => $this->snapshot,
            'file_path'    => $this->file_path,
            'status'       => $this->status,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'expires_at'   => $this->expires_at?->toIso8601String(),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
