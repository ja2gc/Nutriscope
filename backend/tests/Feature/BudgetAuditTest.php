<?php

namespace Tests\Feature;

use App\Events\PurchaseOrderCompleted;
use App\Listeners\BudgetLedgerListener;
use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_setup_and_manual_adjustment_write_audit_events(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $admin = User::factory()->create(['role' => 'Admin']);
        $shoppingList = ShoppingList::factory()->create(['period_start' => '2026-06-01']);
        PurchaseOrder::factory()->create([
            'shopping_list_id' => $shoppingList->id,
            'lifecycle_status' => 'open_execution',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->postJson('/api/fss/budgets', [
                'fiscal_year' => 2026,
                'allocated_amount' => 100000,
            ])
            ->assertCreated();

        $budget = Budget::where('fiscal_year', 2026)->firstOrFail();
        $created = AuditActivity::where('subject_type', Budget::class)
            ->where('subject_id', $budget->id)
            ->where('event', 'created')
            ->where('causer_id', $rnd->id)
            ->firstOrFail();
        $createdEvent = app(AuditEventPresenter::class)
            ->present($created->load(['causer', 'revision']), $admin)
            ->toArray();
        $createdDetails = collect($createdEvent['details'])->keyBy('key');
        $this->assertSame(2026, $createdDetails['fiscal_year']['value']);
        $this->assertSame('currency', $createdDetails['allocated_amount']['kind']);
        $this->assertEqualsWithDelta(100000, $createdDetails['allocated_amount']['value'], 0.01);
        $this->assertEqualsWithDelta(100000, $createdDetails['balance_after']['value'], 0.01);
        $this->assertSame(1, $createdDetails['open_purchase_orders_re_evaluated_count']['value']);
        $this->assertSame('history', $createdEvent['detail_mode']);

        $this->actingAs($rnd, 'sanctum')
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2026,
                'type' => 'manual_addition',
                'amount' => 5000,
                'reason' => 'Supplemental allocation',
            ])
            ->assertCreated();

        $adjusted = AuditActivity::where('subject_type', Budget::class)
            ->where('subject_id', $budget->id)
            ->where('event', 'adjusted')
            ->where('causer_id', $rnd->id)
            ->firstOrFail();
        $adjustedEvent = app(AuditEventPresenter::class)
            ->present($adjusted->load(['causer', 'revision']), $admin)
            ->toArray();
        $adjustedDetails = collect($adjustedEvent['details'])->keyBy('key');
        $this->assertSame('Supplemental allocation', $adjustedEvent['reason']);
        $this->assertSame('manual_addition', $adjustedDetails['type']['value']);
        $this->assertSame('manual', $adjustedDetails['source']['value']);
        $this->assertEqualsWithDelta(5000, $adjustedDetails['amount']['value'], 0.01);
        $this->assertEqualsWithDelta(5000, $adjustedDetails['signed_amount']['value'], 0.01);
        $this->assertEqualsWithDelta(100000, $adjustedDetails['balance_before']['value'], 0.01);
        $this->assertEqualsWithDelta(105000, $adjustedDetails['balance_after']['value'], 0.01);
    }

    public function test_po_deduction_ledger_creation_writes_system_audit_event(): void
    {
        $sl = ShoppingList::factory()->create(['period_start' => '2026-06-01']);
        $po = PurchaseOrder::factory()->create([
            'shopping_list_id' => $sl->id,
            'total_amount' => 45000,
        ]);
        $budget = Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 200000]);

        app(BudgetLedgerListener::class)->handle(new PurchaseOrderCompleted($po));

        BudgetLedger::where('purchase_order_id', $po->id)->firstOrFail();
        $activity = AuditActivity::where('subject_type', Budget::class)
            ->where('subject_id', $budget->id)
            ->where('event', 'adjusted')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('system', $activity->properties['source']);
        $this->assertSame($po->id, $activity->properties['purchase_order_id']);
        $this->assertNotNull($activity->revision);

        $admin = User::factory()->create(['role' => 'Admin']);
        $event = app(AuditEventPresenter::class)
            ->present($activity->load(['causer', 'revision']), $admin)
            ->toArray();
        $details = collect($event['details'])->keyBy('key');
        $this->assertSame('system', $event['actor']['kind']);
        $this->assertSame('Purchase order deduction', $event['reason']);
        $this->assertEqualsWithDelta(45000, $details['amount']['value'], 0.01);
        $this->assertEqualsWithDelta(-45000, $details['signed_amount']['value'], 0.01);
        $this->assertEqualsWithDelta(200000, $details['balance_before']['value'], 0.01);
        $this->assertEqualsWithDelta(155000, $details['balance_after']['value'], 0.01);
        $this->assertSame('reference', $details['purchase_order_public_id']['kind']);
        $this->assertSame($po->uuid, $details['purchase_order_public_id']['value']);
        $this->assertSame($po->po_number, $details['reference']['value']);
    }
}
