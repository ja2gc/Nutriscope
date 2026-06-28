<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBudgetReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_budget_summary_ledger_and_fiscal_years(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);
        BudgetLedger::create([
            'fiscal_year' => 2026,
            'type' => 'manual_addition',
            'source' => 'manual',
            'amount' => 5000,
            'reason' => 'Supplemental allocation',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/budgets')
            ->assertOk()
            ->assertJsonPath('data.0.fiscal_year', 2026);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/budgets/summary?fiscal_year=2026')
            ->assertOk()
            ->assertJsonPath('data.fiscal_year', 2026);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/budgets/ledger?fiscal_year=2026')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'manual_addition');
    }

    public function test_admin_cannot_mutate_budget_from_admin_scope(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/budgets', [
                'fiscal_year' => 2027,
                'allocated_amount' => 100000,
            ])
            ->assertStatus(405);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/budgets/adjust', [
                'fiscal_year' => 2027,
                'type' => 'manual_addition',
                'amount' => 1000,
                'reason' => 'Not allowed',
            ])
            ->assertStatus(405);
    }
}
