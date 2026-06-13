<?php

namespace App\Services\Reports\Generators;

use App\Models\Budget;
use App\Models\Report;
use App\Services\BudgetService;
use App\Services\Reports\Contracts\ReportGenerator;
use Carbon\Carbon;

/**
 * Budget report — planned-vs-actual over a period, with variance, reusing
 * {@see BudgetService} (the same rollup the budget page uses). No recompute.
 */
class BudgetReportGenerator implements ReportGenerator
{
    public function type(): string
    {
        return 'budget_report';
    }

    public function view(): string
    {
        return 'reports.budget';
    }

    public function paper(): array
    {
        return ['a4', 'portrait'];
    }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];
        $budget = Budget::findOrFail($params['budget_id']);

        $granularity = $params['granularity'] ?? 'month';
        $start = Carbon::parse($params['start'] ?? $budget->period_start ?? now()->startOfMonth());
        $end   = Carbon::parse($params['end'] ?? $budget->period_end ?? now()->endOfMonth());

        $series    = \App\Services\BudgetActualService::dailySeries($budget, $start, $end);
        $summary   = BudgetService::summarize($series['days'], $granularity);
        $allocated = (float) $budget->allocated_amount;

        return [
            'budget'    => $budget,
            'summary'   => $summary,
            'source'    => $series['source'],
            'cash_flow' => $series['cash_flow'],
            'days_served' => $series['days_served'],
            'allocated' => $allocated,
            // Remaining is a CASH question: allocation minus money disbursed (POs),
            // not allocation minus food-served value (different axis — see §5-D).
            'remaining' => round($allocated - $series['cash_flow'], 2),
            'period_label' => $budget->period_start
                ? Carbon::parse($budget->period_start)->format('M j, Y') . ' – ' .
                  optional($budget->period_end ? Carbon::parse($budget->period_end) : null)?->format('M j, Y')
                : ($budget->name ?? 'Budget'),
        ];
    }
}
