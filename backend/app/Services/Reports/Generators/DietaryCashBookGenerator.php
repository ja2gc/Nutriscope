<?php

namespace App\Services\Reports\Generators;

use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Services\Reports\Contracts\ReportGenerator;
use Carbon\Carbon;

/**
 * Dietary Cash Book — the Cash Disbursement Record (accounting ledger):
 * Date · Ref/OR No · Payee · Nature of Payment · Cash Advance/Replenishment ·
 * Disbursements · running Cash Advance Balance.
 *
 * Disbursements come from received purchase orders (payee = supplier, amount = PO
 * total); replenishments come from the budget's allocations. The running-balance
 * math is pure (unit-tested); data() loads the entries for the period.
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
        $params = $report->parameters ?? [];
        $start  = ! empty($params['start']) ? Carbon::parse($params['start']) : Carbon::now()->startOfMonth();
        $end    = ! empty($params['end']) ? Carbon::parse($params['end']) : Carbon::now()->endOfMonth();
        $beginning = (float) ($params['beginning_balance'] ?? 0);

        $entries = [];

        if (! empty($params['replenishment'])) {
            // Explicit param still wins (back-compat / manual override).
            $entries[] = [
                'date'           => $start->toDateString(),
                'ref'            => $params['replenishment_ref'] ?? '',
                'payee'          => $params['accountable_officer'] ?? 'Accountable Officer',
                'nature'         => 'Replenishment',
                'replenishment'  => (float) $params['replenishment'],
                'disbursement'   => 0.0,
            ];
        } else {
            // A5: derive replenishments from Budget allocations overlapping the period,
            // so the cash book is reproducible from stored records (no report-only params).
            $officer = $params['accountable_officer'] ?? 'Accountable Officer';
            foreach (self::replenishmentsFromBudgets($start, $end, $officer) as $entry) {
                $entries[] = $entry;
            }
        }

        // Cash is disbursed on RECEIPT — use received_date (fall back to order_date) so the
        // cashbook agrees with BudgetActualService on which period a PO lands in (F4).
        PurchaseOrder::with('supplier')
            ->where('status', 'received')
            ->whereRaw('COALESCE(received_date, order_date) BETWEEN ? AND ?', [$start->toDateString(), $end->toDateString()])
            ->orderByRaw('COALESCE(received_date, order_date)')
            ->get()
            ->each(function (PurchaseOrder $po) use (&$entries) {
                $entries[] = [
                    'date'          => optional($po->received_date ?? $po->order_date)->toDateString() ?? '',
                    'ref'           => $po->or_number ?: $po->po_number,
                    'payee'         => $po->supplier?->name ?? '',
                    'nature'        => $po->notes ?: 'Food / supplies',
                    'replenishment' => 0.0,
                    'disbursement'  => (float) $po->total_amount,
                ];
            });

        foreach (self::manualDisbursementsFromBudgetLogs($start, $end) as $entry) {
            $entries[] = $entry;
        }

        return array_merge(
            self::ledger($entries, $beginning),
            [
                'inclusive_start' => $start->toDateString(),
                'inclusive_end'   => $end->toDateString(),
                'period_label'    => $start->format('F j, Y') . ' – ' . $end->format('F j, Y'),
                // A7: surface the year's allocation as context (the cashbook is read against
                // an annual subsistence budget). Null when no yearly budget covers the period.
                'annual_budget'   => self::annualBudget($start->year),
            ],
        );
    }

    /**
     * Derive replenishment ledger entries from Budget allocations whose period overlaps
     * the cash-book window (A5). Each budget's allocation is one replenishment row, dated
     * at the later of its period start and the window start.
     *
     * @return array<int,array{date:string,ref:string,payee:string,nature:string,replenishment:float,disbursement:float}>
     */
    private static function replenishmentsFromBudgets(Carbon $start, Carbon $end, string $officer): array
    {
        $budgets = \App\Models\Budget::query()
            ->where('allocated_amount', '>', 0)
            ->whereDate('period_start', '<=', $end->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->orderBy('period_start')
            ->get();

        return $budgets->map(function (\App\Models\Budget $b) use ($start, $officer) {
            $date = $b->period_start && Carbon::parse($b->period_start)->gt($start)
                ? Carbon::parse($b->period_start)
                : $start;

            return [
                'date'          => $date->toDateString(),
                'ref'           => $b->name ?: ucfirst((string) $b->scope) . ' budget',
                'payee'         => $officer,
                'nature'        => 'Budget allocation / replenishment',
                'replenishment' => (float) $b->allocated_amount,
                'disbursement'  => 0.0,
            ];
        })->all();
    }

    /**
     * Manual budget daily logs represent non-PO cash spending (petty cash/direct buys).
     *
     * @return array<int,array{date:string,ref:string,payee:string,nature:string,replenishment:float,disbursement:float}>
     */
    private static function manualDisbursementsFromBudgetLogs(Carbon $start, Carbon $end): array
    {
        return \App\Models\BudgetDailyLog::query()
            ->with('budget')
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->where('spent', '>', 0)
            ->orderBy('log_date')
            ->get()
            ->map(fn (\App\Models\BudgetDailyLog $log) => [
                'date'          => $log->log_date?->toDateString() ?? '',
                'ref'           => $log->budget?->name ?: 'Manual spend',
                'payee'         => '',
                'nature'        => $log->notes ?: 'Manual non-PO spend',
                'replenishment' => 0.0,
                'disbursement'  => (float) $log->spent,
            ])->all();
    }

    /**
     * Resolve the yearly subsistence budget overlapping a calendar year, as context
     * for the cashbook header. Returns null when no yearly budget covers the year.
     *
     * @return array{label:string,allocated:float,per_head_year:?float}|null
     */
    private static function annualBudget(int $year): ?array
    {
        $budget = \App\Models\Budget::query()
            ->where('scope', 'yearly')
            ->where(fn ($q) => $q->whereYear('period_start', $year)->orWhereYear('period_end', $year))
            ->orderByDesc('period_start')
            ->first();

        if (! $budget) {
            return null;
        }

        return [
            'label'         => $budget->name ?: ('FY ' . $year),
            'allocated'     => (float) $budget->allocated_amount,
            'per_head_year' => $budget->budget_per_head_year ? (float) $budget->budget_per_head_year : null,
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
        $balance     = $beginningBalance;
        $totalRep    = 0.0;
        $totalDis    = 0.0;
        $rows        = [];

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
