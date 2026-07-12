<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $additions = (float) ($this->manual_additions_sum ?? 0);
        $manDeduc = (float) ($this->manual_deductions_sum ?? 0);
        $poDeduc = (float) ($this->po_deductions_sum ?? 0);
        // Three-card model: Allocated, Total Deductions, Remaining.
        $totalDeductions = $manDeduc + $poDeduc;
        $remaining = (float) $this->allocated_amount + $additions - $totalDeductions;

        return [
            'id' => $this->uuid,
            'fiscal_year' => $this->fiscal_year,
            'allocated_amount' => $this->allocated_amount,
            'total_deductions' => round($totalDeductions, 2),
            'remaining' => round($remaining, 2),
            'creator' => $this->creator ? [
                'id' => $this->creator->uuid,
                'name' => $this->creator->name,
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
