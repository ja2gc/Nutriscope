<?php

namespace App\Http\Resources;

use App\Data\AuditEventDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class AuditEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof AuditEventDto) {
            throw new LogicException('AuditEventResource requires a typed audit event.');
        }

        return $this->resource->toArray();
    }
}
