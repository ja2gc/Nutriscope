<?php

namespace Tests\Feature;

use App\Events\PurchaseOrderCompleted;
use App\Listeners\BudgetLedgerListener;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BudgetAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_setup_and_manual_adjustment_write_audit_events(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        $this->actingAs($rnd, 'sanctum')
            ->postJson('/api/fss/budgets', [
                'fiscal_year' => 2026,
                'allocated_amount' => 100000,
            ])
            ->assertCreated();

        $budget = Budget::where('fiscal_year', 2026)->firstOrFail();
        $this->assertNotNull(Activity::where('subject_type', Budget::class)
            ->where('subject_id', $budget->id)
            ->where('event', 'created')
            ->where('causer_id', $rnd->id)
            ->first());

        $this->actingAs($rnd, 'sanctum')
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2026,
                'type' => 'manual_addition',
                'amount' => 5000,
                'reason' => 'Supplemental allocation',
            ])
            ->assertCreated();

        $entry = BudgetLedger::where('type', 'manual_addition')->firstOrFail();
        $this->assertNotNull(Activity::where('subject_type', BudgetLedger::class)
            ->where('subject_id', $entry->id)
            ->where('event', 'created')
            ->where('causer_id', $rnd->id)
            ->first());
    }

    public function test_po_deduction_ledger_creation_writes_system_audit_event(): void
    {
        $sl = ShoppingList::factory()->create(['period_start' => '2026-06-01']);
        $po = PurchaseOrder::factory()->create([
            'shopping_list_id' => $sl->id,
            'total_amount' => 45000,
        ]);
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 200000]);

        app(BudgetLedgerListener::class)->handle(new PurchaseOrderCompleted($po));

        $entry = BudgetLedger::where('purchase_order_id', $po->id)->firstOrFail();
        $activity = Activity::where('subject_type', BudgetLedger::class)
            ->where('subject_id', $entry->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('system', $activity->properties['source']);
        $this->assertSame($po->id, $activity->properties['purchase_order_id']);
    }
}
