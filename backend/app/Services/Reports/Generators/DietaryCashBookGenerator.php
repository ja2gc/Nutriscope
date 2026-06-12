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
            $entries[] = [
                'date'           => $start->toDateString(),
                'ref'            => $params['replenishment_ref'] ?? '',
                'payee'          => $params['accountable_officer'] ?? 'Accountable Officer',
                'nature'         => 'Replenishment',
                'replenishment'  => (float) $params['replenishment'],
                'disbursement'   => 0.0,
            ];
        }

        PurchaseOrder::with('supplier')
            ->where('status', 'received')
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('order_date')
            ->get()
            ->each(function (PurchaseOrder $po) use (&$entries) {
                $entries[] = [
                    'date'          => optional($po->order_date)->toDateString() ?? '',
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
            ],
        );
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
