<?php

namespace Tests\Feature;

use App\Events\PurchaseOrderCompleted;
use App\Listeners\BudgetLedgerListener;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\FSS\PurchaseOrderLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BudgetLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
        $this->fss = User::factory()->create(['role' => 'FSS']);
    }

    // ── Append-only ledger ────────────────────────────────────────────────────

    public function test_completed_po_event_creates_po_deduction_entry(): void
    {
        $sl = ShoppingList::factory()->create(['period_start' => '2026-06-01']);
        $po = PurchaseOrder::factory()->create([
            'shopping_list_id' => $sl->id,
            'total_amount' => 45000,
        ]);
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 200000]);

        $listener = app(BudgetLedgerListener::class);
        $listener->handle(new PurchaseOrderCompleted($po));

        $this->assertDatabaseHas('budget_ledger', [
            'fiscal_year' => 2026,
            'type' => 'po_deduction',
            'purchase_order_id' => $po->id,
            'amount' => 45000,
        ]);
    }

    public function test_listener_is_idempotent_for_same_po(): void
    {
        $sl = ShoppingList::factory()->create(['period_start' => '2026-06-01']);
        $po = PurchaseOrder::factory()->create(['shopping_list_id' => $sl->id, 'total_amount' => 10000]);
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        $listener = app(BudgetLedgerListener::class);
        $listener->handle(new PurchaseOrderCompleted($po));
        $listener->handle(new PurchaseOrderCompleted($po)); // second fire, same PO

        $this->assertSame(1, BudgetLedger::where('purchase_order_id', $po->id)->count());
    }

    public function test_listener_skips_when_no_fiscal_year_budget(): void
    {
        $sl = ShoppingList::factory()->create(['period_start' => '2099-01-01']);
        $po = PurchaseOrder::factory()->create(['shopping_list_id' => $sl->id, 'total_amount' => 5000]);

        // No budget for 2099 — listener should log + return silently.
        $listener = app(BudgetLedgerListener::class);
        $listener->handle(new PurchaseOrderCompleted($po));

        $this->assertDatabaseEmpty('budget_ledger');
    }

    // ── remaining_balance ────────────────────────────────────────────────────

    public function test_remaining_balance_reflects_all_ledger_types(): void
    {
        $budget = Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'po_deduction',    'amount' => 30000]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_addition', 'amount' => 5000]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_deduction', 'amount' => 2000]);

        // remaining = 100000 + 5000 - 30000 - 2000 = 73000
        $this->assertEqualsWithDelta(73000, $budget->remainingBalance(), 0.01);
    }

    // ── Manual adjustment endpoint ───────────────────────────────────────────

    public function test_rnd_can_add_manual_addition(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 80000]);

        $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2026,
                'type' => 'manual_addition',
                'amount' => 10000,
                'reason' => 'Supplemental from treasury',
                'reference' => 'BUR-2026-03',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('budget_ledger', [
            'fiscal_year' => 2026,
            'type' => 'manual_addition',
            'reason' => 'Supplemental from treasury',
            'reference' => 'BUR-2026-03',
        ]);
        $entry = BudgetLedger::where('fiscal_year', 2026)->where('type', 'manual_addition')->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(10000, (float) $entry->amount, 0.01);
    }

    public function test_manual_adjust_requires_reason(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 80000]);

        $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2026,
                'type' => 'manual_addition',
                'amount' => 5000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_manual_adjust_requires_existing_fiscal_year(): void
    {
        // No Budget for 2099 — should fail.
        $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2099,
                'type' => 'manual_addition',
                'amount' => 5000,
                'reason' => 'test',
            ])
            ->assertUnprocessable();
    }

    public function test_fss_cannot_adjust(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 80000]);

        $this->actingAs($this->fss)
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2026,
                'type' => 'manual_addition',
                'amount' => 5000,
                'reason' => 'test',
            ])
            ->assertForbidden();
    }

    // ── Ledger query endpoint ────────────────────────────────────────────────

    public function test_ledger_endpoint_filters_by_fiscal_year(): void
    {
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_addition', 'amount' => 5000]);
        BudgetLedger::create(['fiscal_year' => 2025, 'type' => 'po_deduction',    'amount' => 3000]);

        $res = $this->actingAs($this->fss)
            ->getJson('/api/fss/budgets/ledger?fiscal_year=2026')
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('manual_addition', $res->json('data.0.type'));
    }

    public function test_ledger_endpoint_filters_by_system_source(): void
    {
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_addition', 'source' => 'manual', 'amount' => 5000]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'po_deduction',    'source' => 'system', 'amount' => 3000]);

        $res = $this->actingAs($this->fss)
            ->getJson('/api/fss/budgets/ledger?fiscal_year=2026&source=system')
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('po_deduction', $res->json('data.0.type'));
    }

    public function test_ledger_endpoint_manual_source_filter(): void
    {
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_addition',  'source' => 'manual', 'amount' => 5000]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_deduction', 'source' => 'manual', 'amount' => 1000]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'po_deduction',     'source' => 'system', 'amount' => 3000]);

        $manual = $this->actingAs($this->fss)
            ->getJson('/api/fss/budgets/ledger?fiscal_year=2026&source=manual')
            ->assertOk();
        $this->assertCount(2, $manual->json('data'));

        $system = $this->actingAs($this->fss)
            ->getJson('/api/fss/budgets/ledger?fiscal_year=2026&source=system')
            ->assertOk();
        $this->assertCount(1, $system->json('data'));
    }

    // ── PO lifecycle guard ───────────────────────────────────────────────────

    public function test_po_stays_open_execution_when_no_fiscal_year_budget_exists(): void
    {
        Event::fake([PurchaseOrderCompleted::class]);

        $sl = ShoppingList::factory()->create(['period_start' => '2099-06-01']);
        $po = PurchaseOrder::factory()->create([
            'shopping_list_id' => $sl->id,
            'lifecycle_status' => 'open_execution',
        ]);

        // Trigger refresh — no FY 2099 budget, so PO must NOT complete.
        app(PurchaseOrderLifecycleService::class)->refresh($po);

        $this->assertSame('open_execution', $po->fresh()->lifecycle_status);
        Event::assertNotDispatched(PurchaseOrderCompleted::class);
    }

    // ── signed amount helper ─────────────────────────────────────────────────

    public function test_signed_amount_is_positive_for_addition_negative_for_deductions(): void
    {
        $add = BudgetLedger::make(['type' => 'manual_addition',  'amount' => 1000]);
        $ded = BudgetLedger::make(['type' => 'po_deduction',     'amount' => 500]);
        $man = BudgetLedger::make(['type' => 'manual_deduction', 'amount' => 200]);

        $this->assertEqualsWithDelta(1000, $add->signedAmount(), 0.01);
        $this->assertEqualsWithDelta(-500, $ded->signedAmount(), 0.01);
        $this->assertEqualsWithDelta(-200, $man->signedAmount(), 0.01);
    }
}
