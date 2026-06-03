<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'fss_user_id' => $this->fss_user_id,
            'name'        => $this->name,
            'cycle_days'  => $this->cycle_days,
            'is_active'       => (bool)$this->is_active,
            'week_start_date' => $this->week_start_date?->toDateString(),
            'status'          => $this->status,
            'activation_date' => $this->activation_date?->toDateString(),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
