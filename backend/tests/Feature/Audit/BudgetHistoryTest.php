<?php

namespace Tests\Feature\Audit;

use App\Events\PurchaseOrderCompleted;
use App\Listeners\BudgetLedgerListener;
use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class BudgetHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_fiscal_year_create_and_manual_adjustment_store_complete_budget_versions(): void
    {
        $rnd = User::factory()->rnd()->create();
        $admin = User::factory()->admin()->create();
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($rnd)->postJson('/api/fss/budgets', [
            'fiscal_year' => 2026,
            'allocated_amount' => 100000,
        ])->assertCreated();
        $created = AuditActivity::query()->where('subject_type', Budget::class)->sole();
        $this->assertNull($created->revision->before);
        $this->assertSame(100000, $created->revision->after['allocated_amount']);
        $this->assertCount(0, $created->revision->after['ledger']);

        AuditFixture::delete(AuditActivity::query());
        $this->actingAs($rnd)->postJson('/api/fss/budgets/adjust', [
            'fiscal_year' => 2026,
            'type' => 'manual_deduction',
            'amount' => 5000,
            'reason' => 'Correction with supporting memo',
            'reference' => 'BUR-2026-02',
        ])->assertCreated();

        $adjusted = AuditActivity::query()->where('subject_type', Budget::class)->sole();
        $this->assertSame('updated', $adjusted->event);
        $this->assertCount(0, $adjusted->revision->before['ledger']);
        $this->assertSame('Correction with supporting memo', $adjusted->revision->after['ledger'][0]['reason']);
        $this->assertSame(95000, $adjusted->revision->after['totals']['remaining_balance']);
        $this->actingAs($admin)
            ->getJson("/api/admin/audit-logs/{$adjusted->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.version.serializer', 'budget')
            ->assertJsonPath('data.after.tables.0.rows.0.values.reason.value', 'Correction with supporting memo');
    }

    public function test_po_deduction_is_idempotent_without_system_audit_revision(): void
    {
        $budget = Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 200000]);
        $list = ShoppingList::factory()->create(['period_start' => '2026-06-01']);
        $po = PurchaseOrder::factory()->create([
            'shopping_list_id' => $list->id,
            'po_number' => 'PO-BUDGET-001',
            'total_amount' => 45000,
        ]);
        AuditFixture::delete(AuditActivity::query());

        $listener = app(BudgetLedgerListener::class);
        $listener->handle(new PurchaseOrderCompleted($po));
        $listener->handle(new PurchaseOrderCompleted($po));

        $this->assertDatabaseCount('budget_ledger', 1);
        $this->assertDatabaseCount('activity_log', 0);
        $this->assertDatabaseCount('audit_revisions', 0);
    }
}
