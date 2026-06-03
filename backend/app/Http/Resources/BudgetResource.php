<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'fss_user_id'      => $this->fss_user_id,
            'allocated_amount' => $this->allocated_amount,
            'actual_amount'    => $this->actual_amount,
            'period_start'     => $this->period_start?->toDateString(),
            'period_end'       => $this->period_end?->toDateString(),
            'cost_per_person'  => $this->cost_per_person,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
