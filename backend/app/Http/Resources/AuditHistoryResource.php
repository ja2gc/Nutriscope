<?php

namespace App\Http\Resources;

use App\Data\AuditHistoryDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class AuditHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof AuditHistoryDto) {
            throw new LogicException('AuditHistoryResource requires a typed audit history record.');
        }

        return $this->resource->toArray();
    }
}
