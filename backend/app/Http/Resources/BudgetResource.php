<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'rnd_user_id'           => $this->rnd_user_id,
            'menu_cycle_id'         => $this->menu_cycle_id,
            'scope'                 => $this->scope,
            'name'                  => $this->name,
            'allocated_amount'      => $this->allocated_amount,
            'actual_amount'         => $this->actual_amount,
            'period_start'          => $this->period_start?->toDateString(),
            'period_end'            => $this->period_end?->toDateString(),
            'cost_per_person'       => $this->cost_per_person,
            'population'            => $this->population,
            'budget_per_head_day'   => $this->budget_per_head_day,
            'budget_per_head_month' => $this->budget_per_head_month,
            'budget_per_head_year'  => $this->budget_per_head_year,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }
}
