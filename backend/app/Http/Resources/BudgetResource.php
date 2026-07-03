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
        // Three-card model: Allocated, Total Deductions, Remaining.
        $totalDeductions = $manDeduc + $poDeduc;
        $remaining  = (float) $this->allocated_amount + $additions - $totalDeductions;

        return [
            'id'                 => $this->uuid,
            'fiscal_year'        => $this->fiscal_year,
            'allocated_amount'   => $this->allocated_amount,
            'total_deductions'   => round($totalDeductions, 2),
            'remaining'          => round($remaining, 2),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
