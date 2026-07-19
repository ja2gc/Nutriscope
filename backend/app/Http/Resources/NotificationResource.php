<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'source_module' => $this->source_module,
            'source_id' => $this->source_id,
            // Public uuid of the source record (announcement / PO / cycle), resolved by
            // the controller — deep-links address the target by uuid, not the raw FK.
            'source_uuid' => $this->source_uuid ?? null,
            'read' => (bool) $this->read,
            'read_at' => $this->read_at,
            'opened_at' => $this->opened_at,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
