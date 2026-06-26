<?php

namespace App\Services\Reports\Generators;

use App\Models\BudgetLedger;
use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Services\Reports\Contracts\ReportGenerator;
use Carbon\Carbon;

/**
 * Dietary Cash Book — the Cash Disbursement Record (accounting ledger):
 * Date · Ref/OR No · Payee · Nature of Payment · Cash Advance/Replenishment ·
 * Disbursements · running Cash Advance Balance.
 *
 * Replenishments come from manual_addition ledger entries.
 * Disbursements come from completed purchase orders.
 */
class DietaryCashBookGenerator implements ReportGenerator
{
    public function type(): string
    {
        return 'dietary_cash_book';
    }

    public function view(): string
    {
        return 'reports.dietary-cash-book';
    }

    public function paper(): array
    {
        return ['a4', 'landscape'];
    }

    public function data(Report $report): array
    {
        $params    = $report->parameters ?? [];
        $start     = ! empty($params['start']) ? Carbon::parse($params['start']) : Carbon::now()->startOfMonth();
        $end       = ! empty($params['end']) ? Carbon::parse($params['end']) : Carbon::now()->endOfMonth();
        $beginning = (float) ($params['beginning_balance'] ?? 0);

        $entries = [];

        if (! empty($params['replenishment'])) {
            // Explicit param still wins (manual override).
            $entries[] = [
                'date'          => $start->toDateString(),
                'ref'           => $params['replenishment_ref'] ?? '',
                'payee'         => $params['accountable_officer'] ?? 'Accountable Officer',
                'nature'        => 'Replenishment',
                'replenishment' => (float) $params['replenishment'],
                'disbursement'  => 0.0,
            ];
        } else {
            $officer = $params['accountable_officer'] ?? 'Accountable Officer';
            foreach (self::replenishmentsFromLedger($start, $end, $officer) as $entry) {
                $entries[] = $entry;
            }
        }

        // Disbursements: completed POs in the period (completed_at falls in range).
        PurchaseOrder::with('supplier')
            ->whereIn('lifecycle_status', ['completed', 'archived'])
            ->whereBetween('completed_at', [$start->startOfDay(), $end->endOfDay()])
            ->orderBy('completed_at')
            ->get()
            ->each(function (PurchaseOrder $po) use (&$entries) {
                $entries[] = [
                    'date'          => optional($po->completed_at)->toDateString() ?? '',
                    'ref'           => $po->or_number ?: $po->po_number,
                    'payee'         => $po->supplier?->name ?? '',
                    'nature'        => $po->notes ?: 'Food / supplies',
                    'replenishment' => 0.0,
                    'disbursement'  => (float) $po->total_amount,
                ];
            });

        return array_merge(
            self::ledger($entries, $beginning),
            [
                'inclusive_start' => $start->toDateString(),
                'inclusive_end'   => $end->toDateString(),
                'period_label'    => $start->format('F j, Y') . ' – ' . $end->format('F j, Y'),
                'annual_budget'   => self::annualBudget($start->year),
            ],
        );
    }

    /**
     * Replenishments from manual_addition budget ledger entries in the period.
     */
    private static function replenishmentsFromLedger(Carbon $start, Carbon $end, string $officer): array
    {
        $year = $start->year;

        return BudgetLedger::where('fiscal_year', $year)
            ->where('type', 'manual_addition')
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
            ->with('creator:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (BudgetLedger $e) => [
                'date'          => $e->created_at->toDateString(),
                'ref'           => $e->reference ?? '',
                'payee'         => $officer,
                'nature'        => $e->reason ?? 'Budget replenishment',
                'replenishment' => (float) $e->amount,
                'disbursement'  => 0.0,
            ])
            ->all();
    }

    /**
     * Resolve fiscal year budget for the cash book header context.
     */
    private static function annualBudget(int $year): ?array
    {
        $budget = \App\Models\Budget::where('fiscal_year', $year)->first();
        if (! $budget) {
            return null;
        }

        return [
            'label'              => 'FY ' . $year,
            'allocated'          => (float) $budget->allocated_amount,
            'per_head_day_limit' => $budget->per_head_day_limit ? (float) $budget->per_head_day_limit : null,
        ];
    }

    /**
     * Compute the running cash-advance balance across ledger entries.
     *
     * @param  array<int,array{date:string,ref:string,payee:string,nature:string,replenishment:float,disbursement:float}> $entries
     * @return array{beginning_balance:float,rows:array,total_replenishment:float,total_disbursement:float,ending_balance:float}
     */
    public static function ledger(array $entries, float $beginningBalance): array
    {
        $balance  = $beginningBalance;
        $totalRep = 0.0;
        $totalDis = 0.0;
        $rows     = [];

        foreach ($entries as $e) {
            $rep = (float) ($e['replenishment'] ?? 0);
            $dis = (float) ($e['disbursement'] ?? 0);
            $balance += $rep - $dis;
            $totalRep += $rep;
            $totalDis += $dis;

            $rows[] = array_merge($e, [
                'replenishment' => round($rep, 2),
                'disbursement'  => round($dis, 2),
                'balance'       => round($balance, 2),
            ]);
        }

        return [
            'beginning_balance'   => round($beginningBalance, 2),
            'rows'                => $rows,
            'total_replenishment' => round($totalRep, 2),
            'total_disbursement'  => round($totalDis, 2),
            'ending_balance'      => round($balance, 2),
        ];
    }
}
