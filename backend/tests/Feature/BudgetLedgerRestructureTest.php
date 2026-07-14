<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Budget ledger restructure: source classifier (system|manual), no procurement_span
 * column, and the slimmed three-figure summary (allocated, total_deductions, remaining).
 */
class BudgetLedgerRestructureTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create(['role' => 'FSS']);
        $this->rnd = User::factory()->create(['role' => 'RND']);
    }

    public function test_procurement_span_column_is_dropped(): void
    {
        $this->assertFalse(Schema::hasColumn('budget_ledger', 'procurement_span'));
        $this->assertTrue(Schema::hasColumn('budget_ledger', 'source'));
    }

    public function test_ledger_response_omits_procurement_span_and_includes_source(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 50000]);
        BudgetLedger::create([
            'fiscal_year' => 2026, 'type' => 'po_deduction', 'source' => 'system',
            'amount' => 1000, 'reason' => 'PO',
        ]);

        $row = $this->actingAs($this->fss)
            ->getJson('/api/fss/budgets/ledger?fiscal_year=2026')
            ->assertOk()
            ->json('data.0');

        $this->assertArrayNotHasKey('procurement_span', $row);
        $this->assertArrayHasKey('source', $row);
        $this->assertArrayHasKey('created_by', $row);
    }

    public function test_summary_returns_three_figures(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'po_deduction', 'source' => 'system', 'amount' => 20000]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_addition', 'source' => 'manual', 'amount' => 5000]);

        $data = $this->actingAs($this->fss)
            ->getJson('/api/fss/budgets/summary?fiscal_year=2026')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('allocated_amount', $data);
        $this->assertArrayHasKey('total_deductions', $data);
        $this->assertArrayHasKey('remaining', $data);
        $this->assertArrayNotHasKey('total_manual_additions', $data);
        $this->assertEqualsWithDelta(20000, (float) $data['total_deductions'], 0.01);
        $this->assertEqualsWithDelta(85000, (float) $data['remaining'], 0.01); // 100k + 5k − 20k
    }

    public function test_manual_adjustment_is_tagged_manual_source(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2026, 'type' => 'manual_deduction',
                'amount' => 500, 'reason' => 'Correction',
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'manual');
    }
}
