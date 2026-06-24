<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAdjustmentTest extends TestCase
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

    private function budget(): Budget
    {
        return Budget::create([
            'rnd_user_id' => $this->rnd->id, 'scope' => 'yearly', 'name' => 'FY2026',
            'allocated_amount' => 100000, 'period_start' => '2026-01-01', 'period_end' => '2026-12-31',
        ]);
    }

    public function test_rnd_can_log_addition_and_deduction_and_effective_allocation_updates(): void
    {
        $budget = $this->budget();

        $this->actingAs($this->rnd)->postJson("/api/fss/budgets/{$budget->id}/adjustments", [
            'type' => 'addition', 'amount' => 25000, 'reason_category' => 'Request for additional funds',
        ])->assertCreated()->assertJsonPath('data.signed_amount', 25000);

        $this->actingAs($this->rnd)->postJson("/api/fss/budgets/{$budget->id}/adjustments", [
            'type' => 'deduction', 'amount' => 5000, 'reason_category' => 'Budget correction',
        ])->assertCreated()->assertJsonPath('data.signed_amount', -5000);

        // Base 100000 + 25000 − 5000 = 120000 effective.
        $res = $this->actingAs($this->rnd)
            ->getJson("/api/fss/budgets/{$budget->id}/summary?start=2026-06-01&end=2026-06-30")
            ->assertOk();

        $this->assertEquals(100000, $res->json('data.year.base_allocated'));
        $this->assertEquals(20000, $res->json('data.year.adjustments_total'));
        $this->assertEquals(120000, $res->json('data.year.allocated'));
        $this->assertCount(2, $res->json('data.adjustments'));
    }

    public function test_other_reason_requires_free_text(): void
    {
        $budget = $this->budget();

        $this->actingAs($this->rnd)->postJson("/api/fss/budgets/{$budget->id}/adjustments", [
            'type' => 'addition', 'amount' => 1000, 'reason_category' => 'Other',
        ])->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $this->actingAs($this->rnd)->postJson("/api/fss/budgets/{$budget->id}/adjustments", [
            'type' => 'addition', 'amount' => 1000, 'reason_category' => 'Other', 'reason' => 'Donation from LGU',
        ])->assertCreated();
    }

    public function test_fss_cannot_log_adjustment(): void
    {
        $budget = $this->budget();

        $this->actingAs($this->fss)->postJson("/api/fss/budgets/{$budget->id}/adjustments", [
            'type' => 'addition', 'amount' => 1000, 'reason_category' => 'Budget correction',
        ])->assertForbidden();
    }
}
