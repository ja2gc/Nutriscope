<?php

namespace App\Listeners;

use App\Events\PurchaseOrderCompleted;
use App\Models\Budget;
use App\Models\BudgetLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BudgetLedgerListener
{
    public function handle(PurchaseOrderCompleted $event): void
    {
        $po = $event->purchaseOrder;
        $sl = $po->shoppingList;

        if (! $sl?->period_start) {
            Log::warning("BudgetLedgerListener: PO {$po->id} has no shopping list period_start — skipping deduction.");
            return;
        }

        $year   = Carbon::parse($sl->period_start)->year;
        $budget = Budget::where('fiscal_year', $year)->first();

        if (! $budget) {
            Log::warning("BudgetLedgerListener: No Budget allocation for fiscal year {$year}. PO {$po->id} deduction skipped — set up the year to trigger deduction.");
            return;
        }

        // Idempotency: one po_deduction entry per PO
        if (BudgetLedger::where('purchase_order_id', $po->id)->where('type', 'po_deduction')->exists()) {
            return;
        }

        $span = $sl->period_start->format('m/d/Y') . ' - ' . $sl->period_end->format('m/d/Y');

        BudgetLedger::create([
            'fiscal_year'       => $year,
            'type'              => 'po_deduction',
            'amount'            => (float) $po->total_amount,
            'reference'         => $po->po_number ?? "PO #{$po->id}",
            'purchase_order_id' => $po->id,
            'procurement_span'  => $span,
            'created_by'        => null,
        ]);
    }
}
