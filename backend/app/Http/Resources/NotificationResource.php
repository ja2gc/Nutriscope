<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->uuid,
            'user_id'       => $this->user_id,
            'title'         => $this->title,
            'message'       => $this->message,
            'type'          => $this->type,
            'source_module' => $this->source_module,
            'source_id'     => $this->source_id,
            'read'          => (bool) $this->read,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
