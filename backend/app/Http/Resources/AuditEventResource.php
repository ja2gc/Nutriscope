<?php

namespace App\Http\Resources;

use App\Data\AuditEventDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AuditEventDto $event */
        $event = $this->resource;

        return $event->toArray();
    }
}
