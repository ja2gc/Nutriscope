<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * FSS insights routes were removed in R2 scope enforcement (§6 — insights are
 * off-scope for FSS backend surface; re-homing to budget/reporting is a separate task).
 * These tests assert the three endpoints are gone.
 */
class InsightsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
    }

    public function test_spend_by_supplier_route_is_gone(): void
    {
        $this->actingAs($this->fss)
            ->getJson('/api/fss/insights/spend-by-supplier')
            ->assertNotFound();
    }

    public function test_cost_per_head_route_is_gone(): void
    {
        $this->actingAs($this->fss)
            ->getJson('/api/fss/insights/cost-per-head')
            ->assertNotFound();
    }

    public function test_consumption_route_is_gone(): void
    {
        $this->actingAs($this->fss)
            ->getJson('/api/fss/insights/consumption')
            ->assertNotFound();
    }
}
