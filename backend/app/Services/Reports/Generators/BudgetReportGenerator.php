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
        $budget = Budget::with('dailyLogs')->findOrFail($params['budget_id']);

        $granularity = $params['granularity'] ?? 'month';
        $days = $budget->dailyLogs->map(fn ($log) => [
            'date'    => optional($log->date ?? $log->log_date)->toDateString() ?? '',
            'planned' => (float) ($log->planned ?? 0),
            'actual'  => (float) ($log->actual ?? $log->spent ?? 0),
        ])->filter(fn ($d) => $d['date'] !== '')->values()->all();

        $summary   = BudgetService::summarize($days, $granularity);
        $allocated = (float) $budget->allocated_amount;

        return [
            'budget'    => $budget,
            'summary'   => $summary,
            'allocated' => $allocated,
            'remaining' => round($allocated - $summary['actual'], 2),
            'period_label' => $budget->period_start
                ? Carbon::parse($budget->period_start)->format('M j, Y') . ' – ' .
                  optional($budget->period_end ? Carbon::parse($budget->period_end) : null)?->format('M j, Y')
                : ($budget->name ?? 'Budget'),
        ];
    }
}
