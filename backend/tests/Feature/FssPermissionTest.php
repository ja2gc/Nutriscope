<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\MenuCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FssPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
        $this->rnd = User::factory()->create(['role' => 'RND', 'password' => Hash::make('password')]);
    }

    /** FSS may READ planning resources. */
    public function test_fss_can_read_menu_cycles_budgets_recipes(): void
    {
        $this->actingAs($this->fss)->getJson('/api/fss/menu-cycles')->assertOk();
        $this->actingAs($this->fss)->getJson('/api/fss/budgets')->assertOk();
        $this->actingAs($this->fss)->getJson('/api/fss/food-service-recipes')->assertOk();
    }

    /** FSS may NOT write planning resources (RND-owned). */
    public function test_fss_forbidden_from_planning_writes(): void
    {
        $this->actingAs($this->fss)->postJson('/api/fss/menu-cycles', [])->assertForbidden();
        $this->actingAs($this->fss)->postJson('/api/fss/budgets', [])->assertForbidden();
        $this->actingAs($this->fss)->postJson('/api/fss/food-service-recipes', [])->assertForbidden();
        $this->actingAs($this->fss)->postJson('/api/fss/menu-cycle-templates', [])->assertForbidden();
    }

    public function test_fss_forbidden_from_menu_cycle_mutations(): void
    {
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id]);

        $this->actingAs($this->fss)->putJson("/api/fss/menu-cycles/{$cycle->uuid}", [])->assertForbidden();
        $this->actingAs($this->fss)->deleteJson("/api/fss/menu-cycles/{$cycle->uuid}")->assertForbidden();
        $this->actingAs($this->fss)->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/activate")->assertForbidden();
    }

    public function test_fss_forbidden_from_budget_mutations(): void
    {
        $this->actingAs($this->fss)
            ->postJson('/api/fss/budgets', ['fiscal_year' => 2026, 'allocated_amount' => 10000])
            ->assertForbidden();

        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 10000]);
        $this->actingAs($this->fss)
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2026, 'type' => 'manual_addition', 'amount' => 1000, 'reason' => 'test',
            ])
            ->assertForbidden();
    }

    /** RND retains write access (split did not lock them out). */
    public function test_rnd_not_forbidden_from_planning_writes(): void
    {
        $this->actingAs($this->rnd)->postJson('/api/fss/menu-cycles', ['cycle_days' => 0])->assertStatus(422);
        $this->actingAs($this->rnd)->postJson('/api/fss/food-service-recipes', [])->assertStatus(422);
    }
}
