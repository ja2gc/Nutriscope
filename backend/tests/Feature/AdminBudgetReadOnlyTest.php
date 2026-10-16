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

    public function test_admin_can_set_up_fiscal_years_and_add_adjustments_from_admin_scope(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $budget = Budget::factory()->create(['fiscal_year' => 2026]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/budgets', [
                'fiscal_year' => 2027,
                'allocated_amount' => 100000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.fiscal_year', 2027)
            ->assertJsonPath('data.creator.id', $admin->uuid);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/budgets/adjust', [
                'fiscal_year' => 2027,
                'type' => 'manual_addition',
                'amount' => 1000,
                'reason' => 'Supplemental allocation',
            ])
            ->assertCreated()
            ->assertJsonPath('data.actor.id', $admin->uuid)
            ->assertJsonPath('data.signed_amount', 1000);

        $this->putJson("/api/admin/budgets/{$budget->uuid}", ['allocated_amount' => 1])->assertStatus(405);
        $this->deleteJson("/api/admin/budgets/{$budget->uuid}")->assertStatus(405);
        foreach (['approve', 'reject', 'flag', 'reverse'] as $workflow) {
            $this->postJson("/api/admin/budgets/{$budget->uuid}/{$workflow}")->assertNotFound();
        }
    }

    public function test_fss_and_rnd_cannot_use_admin_budget_mutation_routes(): void
    {
        foreach (['FSS', 'RND'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user, 'sanctum')->postJson('/api/admin/budgets', [
                'fiscal_year' => 2027, 'allocated_amount' => 100000,
            ])->assertForbidden();
            $this->postJson('/api/admin/budgets/adjust', [
                'fiscal_year' => 2027, 'type' => 'manual_addition', 'amount' => 1000, 'reason' => 'Test',
            ])->assertForbidden();
        }
    }
}
