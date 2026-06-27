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

        // Food POs derive the fiscal year from the procurement span; supplies POs
        // have no span, so fall back to the completion date's year.
        $year = $sl?->period_start
            ? Carbon::parse($sl->period_start)->year
            : Carbon::parse($po->completed_at ?? now())->year;

        $budget = Budget::where('fiscal_year', $year)->first();

        if (! $budget) {
            Log::warning("BudgetLedgerListener: No Budget allocation for fiscal year {$year}. PO {$po->id} deduction skipped — set up the year to trigger deduction.");
            return;
        }

        // Idempotency: one po_deduction entry per PO
        if (BudgetLedger::where('purchase_order_id', $po->id)->where('type', 'po_deduction')->exists()) {
            return;
        }

        BudgetLedger::create([
            'fiscal_year'       => $year,
            'type'              => 'po_deduction',
            'source'            => 'system',
            'amount'            => (float) $po->total_amount,
            'reference'         => $po->po_number ?? "PO #{$po->id}",
            'purchase_order_id' => $po->id,
            'created_by'        => null,
        ]);
    }
}
