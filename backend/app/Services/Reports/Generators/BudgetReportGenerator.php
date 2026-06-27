<?php

namespace App\Services\Reports\Generators;

use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\Report;
use App\Services\Reports\Contracts\ReportGenerator;

class BudgetReportGenerator implements ReportGenerator
{
    public function type(): string { return 'budget_report'; }
    public function view(): string { return 'reports.budget'; }
    public function paper(): array { return ['a4', 'portrait']; }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];
        $year   = (int) ($params['fiscal_year'] ?? now()->year);

        $budget  = Budget::where('fiscal_year', $year)->first();
        $entries = BudgetLedger::where('fiscal_year', $year)
            ->with(['purchaseOrder:id,po_number,completed_at', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->get();

        $allocated  = $budget ? (float) $budget->allocated_amount : 0.0;
        $poDeduc    = (float) $entries->where('type', 'po_deduction')->sum('amount');
        $manAdd     = (float) $entries->where('type', 'manual_addition')->sum('amount');
        $manDeduc   = (float) $entries->where('type', 'manual_deduction')->sum('amount');
        $remaining  = $allocated + $manAdd - $manDeduc - $poDeduc;

        return [
            'fiscal_year'             => $year,
            'budget'                  => $budget,
            'allocated_amount'        => $allocated,
            'per_head_day_limit'      => $budget ? (float) $budget->per_head_day_limit : null,
            'total_po_deductions'     => round($poDeduc, 2),
            'total_manual_additions'  => round($manAdd, 2),
            'total_manual_deductions' => round($manDeduc, 2),
            'remaining_balance'       => round($remaining, 2),
            'entries'                 => $entries->map(fn ($e) => [
                'type'             => $e->type,
                'amount'           => (float) $e->amount,
                'signed_amount'    => $e->signedAmount(),
                'reason'           => $e->reason,
                'reference'        => $e->reference,
                'po_number'        => $e->purchaseOrder?->po_number,
                'procurement_span' => $e->procurement_span,
                'created_by'       => $e->creator?->name,
                'created_at'       => $e->created_at?->toDateTimeString(),
            ])->all(),
        ];
    }
}
