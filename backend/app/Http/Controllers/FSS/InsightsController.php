<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderVendorGroup;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    private function fiscalYear(Request $request): int
    {
        return (int) ($request->input('fiscal_year') ?? now()->year);
    }

    /**
     * Budget burn: Jan–Dec cumulative ledger deductions vs flat allocation.
     * PO deductions stamped on completed_at; manual entries stamped on created_at.
     */
    public function budgetBurn(Request $request): JsonResponse
    {
        $year   = $this->fiscalYear($request);
        $budget = Budget::where('fiscal_year', $year)->first();

        $allocated = $budget ? (float) $budget->allocated_amount : 0.0;

        $entries = BudgetLedger::where('fiscal_year', $year)
            ->with('purchaseOrder:id,completed_at')
            ->get();

        // Build monthly buckets Jan–Dec
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[sprintf('%04d-%02d', $year, $m)] = 0.0;
        }

        foreach ($entries as $entry) {
            if ($entry->type === 'po_deduction') {
                $date = optional($entry->purchaseOrder?->completed_at)->format('Y-m');
            } else {
                $date = Carbon::parse($entry->created_at)->format('Y-m');
            }

            if ($date && isset($months[$date])) {
                $months[$date] += $entry->type === 'manual_addition'
                    ? -(float) $entry->amount
                    : (float) $entry->amount;
            }
        }

        $cumulative = 0.0;
        $points     = [];
        foreach ($months as $month => $net) {
            $cumulative += $net;
            $points[] = [
                'month'            => $month,
                'cumulative_spent' => round($cumulative, 2),
                'allocated'        => $allocated,
                'remaining'        => round($allocated - $cumulative, 2),
            ];
        }

        return response()->json(['data' => [
            'points'  => $points,
            'summary' => [
                'fiscal_year'    => $year,
                'allocated'      => $allocated,
                'total_deducted' => round($cumulative, 2),
                'remaining'      => round($allocated - $cumulative, 2),
            ],
        ]]);
    }

    /**
     * Per-head actual vs limit: one point per PO in fiscal year.
     * Phase 2 POs (open_execution) show pending markers.
     */
    public function perHeadActualVsLimit(Request $request): JsonResponse
    {
        $year   = $this->fiscalYear($request);
        $budget = Budget::where('fiscal_year', $year)->first();
        $limit  = $budget ? (float) $budget->per_head_day_limit : null;

        $pos = PurchaseOrder::with(['shoppingList:id,period_start,period_end', 'programProjectActivity'])
            ->whereIn('lifecycle_status', ['open_execution', 'completed', 'archived'])
            ->whereHas('shoppingList', fn ($q) => $q->whereYear('period_start', $year))
            ->orderBy('completed_at')
            ->get();

        $points = $pos->map(function (PurchaseOrder $po) use ($limit) {
            $sl   = $po->shoppingList;
            $span = $sl
                ? (optional($sl->period_start)->format('M j') . '–' . optional($sl->period_end)->format('M j'))
                : null;

            // Estimated per-head/day from the frozen PPA planning snapshot:
            // estimated_total_cost / estimated_output_patients (sum of estimate_population across planned days).
            $ppa = $po->programProjectActivity;
            $estimatedPerHead = ($ppa && (int) $ppa->estimated_output_patients > 0)
                ? round((float) $ppa->estimated_total_cost / (int) $ppa->estimated_output_patients, 2)
                : null;

            return [
                'po_id'             => $po->id,
                'span'              => $span,
                'period_start'      => optional($sl?->period_start)->toDateString(),
                'lifecycle_status'  => $po->lifecycle_status,
                'actual_per_head'   => $po->lifecycle_status === 'open_execution'
                    ? null
                    : (float) ($po->actual_budget_per_head_per_day ?? 0),
                'estimated_per_head' => $estimatedPerHead,
                'pending'           => $po->lifecycle_status === 'open_execution',
                'limit_per_head'    => $limit,
            ];
        });

        $completed = $points->where('pending', false);

        return response()->json(['data' => [
            'points'  => $points->values(),
            'summary' => [
                'fiscal_year'    => $year,
                'limit_per_head' => $limit,
                'avg_actual'     => $completed->count() > 0
                    ? round($completed->avg('actual_per_head'), 2)
                    : null,
            ],
        ]]);
    }

    /**
     * Procurement deduction timeline: completed POs + manual ledger entries for fiscal year.
     */
    public function procurementDeductionTimeline(Request $request): JsonResponse
    {
        $year = $this->fiscalYear($request);

        $pos = PurchaseOrder::with(['shoppingList:id,period_start,period_end', 'programProjectActivity'])
            ->whereIn('lifecycle_status', ['completed', 'archived'])
            ->whereHas('shoppingList', fn ($q) => $q->whereYear('period_start', $year))
            ->orderBy('completed_at')
            ->get()
            ->map(function (PurchaseOrder $po) {
                $ppa = $po->programProjectActivity;
                $estimatedPerHead = ($ppa && (int) $ppa->estimated_output_patients > 0)
                    ? round((float) $ppa->estimated_total_cost / (int) $ppa->estimated_output_patients, 2)
                    : null;

                return [
                    'type'              => 'po',
                    'date'              => optional($po->completed_at)->toDateString(),
                    'po_id'             => $po->id,
                    'reference'         => $po->po_number ?? "PO #{$po->id}",
                    'procurement_span'  => $po->shoppingList
                        ? (optional($po->shoppingList->period_start)->format('m/d/Y')
                            . ' - ' . optional($po->shoppingList->period_end)->format('m/d/Y'))
                        : null,
                    'total_cost'        => (float) $po->total_amount,
                    'actual_per_head'   => (float) ($po->actual_budget_per_head_per_day ?? 0),
                    'estimated_per_head' => $estimatedPerHead,
                ];
            });

        $manuals = BudgetLedger::where('fiscal_year', $year)
            ->whereIn('type', ['manual_addition', 'manual_deduction'])
            ->with('creator:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (BudgetLedger $e) => [
                'type'       => $e->type,
                'date'       => optional($e->created_at)->toDateString(),
                'amount'     => (float) $e->amount,
                'reason'     => $e->reason,
                'created_by' => $e->creator?->name,
            ]);

        $timeline = $pos->merge($manuals)->sortBy('date')->values();

        return response()->json(['data' => [
            'timeline'    => $timeline,
            'fiscal_year' => $year,
        ]]);
    }

    /**
     * Spend by supplier for fiscal year using PurchaseOrderVendorGroup (completed POs only).
     */
    public function spendBySupplier(Request $request): JsonResponse
    {
        $year = $this->fiscalYear($request);

        $groups = PurchaseOrderVendorGroup::with('supplier:id,name')
            ->whereHas('purchaseOrder', fn ($q) => $q
                ->whereIn('lifecycle_status', ['completed', 'archived'])
                ->whereHas('shoppingList', fn ($q2) => $q2->whereYear('period_start', $year)))
            ->get(['supplier_id', 'total_amount']);

        $points = $groups
            ->groupBy('supplier_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'supplier_id' => $first->supplier_id,
                    'supplier'    => $first->supplier?->name ?? 'Unassigned',
                    'total'       => round((float) $group->sum('total_amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return response()->json(['data' => [
            'points'      => $points,
            'fiscal_year' => $year,
            'total'       => round((float) $points->sum('total'), 2),
        ]]);
    }
}
