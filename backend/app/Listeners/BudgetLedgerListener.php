<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Events\PurchaseOrderCompleted;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\PurchaseOrder;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use App\Services\Audit\Revisions\AuditRevisionWriter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BudgetLedgerListener
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AuditRevisionRegistry $revisionRegistry,
        private readonly AuditRevisionWriter $revisionWriter,
    ) {}

    public function handle(PurchaseOrderCompleted $event): void
    {
        $this->auditLogger->assertAvailable();
        DB::transaction(function () use ($event): void {
            $po = PurchaseOrder::query()
                ->whereKey($event->purchaseOrder->getKey())
                ->lockForUpdate()
                ->with('shoppingList')
                ->firstOrFail();
            $sl = $po->shoppingList;

            // Food POs derive the fiscal year from the procurement span; supplies POs
            // have no span, so fall back to the completion date's year.
            $year = $sl?->period_start
                ? Carbon::parse($sl->period_start)->year
                : Carbon::parse($po->completed_at ?? now())->year;

            $budget = Budget::query()
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->with('ledgerEntries.purchaseOrder', 'ledgerEntries.creator')
                ->first();

            if (! $budget) {
                Log::warning("BudgetLedgerListener: No Budget allocation for fiscal year {$year}. PO {$po->id} deduction skipped — set up the year to trigger deduction.");

                return;
            }

            // Idempotency: one po_deduction entry per PO
            if (BudgetLedger::where('purchase_order_id', $po->id)->where('type', 'po_deduction')->exists()) {
                return;
            }
            $before = $this->revisionRegistry->capture($budget);

            $entry = BudgetLedger::create([
                'fiscal_year' => $year,
                'type' => 'po_deduction',
                'source' => 'system',
                'amount' => (float) $po->total_amount,
                'reference' => $po->po_number ?? "PO #{$po->id}",
                'purchase_order_id' => $po->id,
                'po_deduction_guard' => $po->id,
                'created_by' => null,
            ]);

            $activity = $this->auditLogger->record(
                AuditAction::Adjusted,
                AuditCategory::Operations,
                AuditDomain::Budget,
                subject: $budget,
                context: $po,
                details: [
                    'fiscal_year' => $year,
                    'type' => 'po_deduction',
                    'source' => 'system',
                    'amount' => (float) $po->total_amount,
                    'purchase_order_id' => $po->id,
                    'purchase_order_public_id' => $po->uuid,
                ],
                systemActor: 'budget-ledger-listener',
            );
            $this->revisionWriter->write(
                $activity,
                $before,
                $this->revisionRegistry->capture($budget->fresh(['ledgerEntries.purchaseOrder', 'ledgerEntries.creator'])),
            );
        });
    }
}
