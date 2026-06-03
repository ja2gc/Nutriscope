<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'user_id'    => $this->user_id,
            'title'      => $this->title,
            'message'    => $this->message,
            'read'       => (bool) $this->read,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
