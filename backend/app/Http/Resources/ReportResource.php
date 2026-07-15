<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'user_id' => $this->user_id,
            'created_by' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->uuid,
                'name' => $this->user->display_name,
            ]),
            'title' => $this->title,
            'type' => $this->type,
            'filters' => $this->filters,
            'parameters' => $this->parameters,
            'snapshot' => $this->snapshot,
            'file_path' => $this->file_path,
            'status' => $this->status,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
