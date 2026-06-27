<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entries    = $this->ledgerEntries()->get();
        $additions  = (float) $entries->where('type', 'manual_addition')->sum('amount');
        $manDeduc   = (float) $entries->where('type', 'manual_deduction')->sum('amount');
        $poDeduc    = (float) $entries->where('type', 'po_deduction')->sum('amount');
        $remaining  = (float) $this->allocated_amount + $additions - $manDeduc - $poDeduc;

        return [
            'id'                       => $this->id,
            'fiscal_year'              => $this->fiscal_year,
            'allocated_amount'         => $this->allocated_amount,
            'per_head_day_limit'       => $this->per_head_day_limit,
            'total_po_deductions'      => round($poDeduc, 2),
            'total_manual_additions'   => round($additions, 2),
            'total_manual_deductions'  => round($manDeduc, 2),
            'remaining_balance'        => round($remaining, 2),
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}
