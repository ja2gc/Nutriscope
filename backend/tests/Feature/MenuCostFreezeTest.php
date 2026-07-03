<?php

namespace Tests\Feature;

use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\User;
use App\Services\MenuCycleCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec 6 #1 — a menu cycle's cost is frozen at activation, so PPA / Menu Calendar
 * reports for a past cycle keep their original cost even when catalog prices change.
 */
class MenuCostFreezeTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
    }

    /** A 1-day cycle costing ₱100 (100 g × ₱1/g, day population 1, servings 1). */
    private function makeCycle(): MenuCycle
    {
        $fsItem = FsItem::factory()->create([
            'kind' => 'ingredient', 'base_unit' => 'g', 'purchase_unit' => 'kg', 'purchase_price' => 1000,
        ]); // unit_cost = 1000 / 1000 g = ₱1.00/g

        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'is_active' => false, 'status' => 'draft', 'activation_date' => null,
        ]);

        $recipe = FoodServiceRecipe::create(['name' => 'R', 'rnd_user_id' => $this->rnd->id, 'servings' => 1]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id, 'fs_item_id' => $fsItem->id, 'quantity' => 100, 'unit' => 'g',
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch',
            'recipe_id' => $recipe->id, 'quantity' => 1, 'estimate_population' => 1,
        ]);

        // Activation requires every weekday to have a planned item (cycles always span the
        // full week — cycle_days is forced to 7). Fill Tue–Sun with a separate ZERO-cost
        // item so the cycle is activatable without changing its ₱100 cost. The ₱1000 item
        // above is created first, so FsItem::firstOrFail() in the callers still targets it.
        $filler = FsItem::factory()->create([
            'kind' => 'ingredient', 'base_unit' => 'g', 'purchase_unit' => 'g', 'purchase_price' => 0,
        ]);
        foreach (['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            MenuCycleDay::create([
                'menu_cycle_id' => $cycle->id, 'day_of_week' => $day, 'meal_type' => 'lunch',
                'fs_item_id' => $filler->id, 'quantity' => 1, 'estimate_population' => 1,
            ]);
        }

        return $cycle->fresh();
    }

    public function test_activation_freezes_cost_and_price_changes_do_not_affect_report(): void
    {
        $cycle  = $this->makeCycle();
        $fsItem = FsItem::firstOrFail();

        // Activate → snapshot taken at ₱100.
        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/activate")
            ->assertOk();

        $cycle->refresh();
        $this->assertNotNull($cycle->cost_snapshot);
        $this->assertEqualsWithDelta(100.0, $cycle->cost_snapshot['total_cost'], 1e-6);

        // Catalog price doubles AFTER activation.
        $fsItem->update(['purchase_price' => 2000]);
        $cycle->refresh();

        // Report path stays frozen; live path reflects the new price.
        $this->assertEqualsWithDelta(100.0, MenuCycleCostService::forReport($cycle)['total_cost'], 1e-6);
        $this->assertEqualsWithDelta(200.0, MenuCycleCostService::forCycle($cycle)['total_cost'], 1e-6);
    }

    public function test_reactivation_does_not_overwrite_the_frozen_snapshot(): void
    {
        $cycle  = $this->makeCycle();
        $fsItem = FsItem::firstOrFail();

        // First activation → snapshot frozen at ₱100.
        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/activate")
            ->assertOk();
        $cycle->refresh();
        $this->assertEqualsWithDelta(100.0, $cycle->cost_snapshot['total_cost'], 1e-6);
        $firstSnapshotAt = $cycle->cost_snapshot_at;

        // Price doubles, then the SAME cycle is activated again (re-promote after
        // another cycle took over, or a double-click). History must NOT re-price.
        $fsItem->update(['purchase_price' => 2000]);
        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/activate")
            ->assertOk();
        $cycle->refresh();

        $this->assertEqualsWithDelta(100.0, $cycle->cost_snapshot['total_cost'], 1e-6);
        $this->assertEquals($firstSnapshotAt, $cycle->cost_snapshot_at);
    }

    public function test_draft_cycle_without_snapshot_falls_back_to_live(): void
    {
        $cycle = $this->makeCycle(); // not activated → no snapshot

        $this->assertNull($cycle->cost_snapshot);
        $this->assertEqualsWithDelta(
            MenuCycleCostService::forCycle($cycle)['total_cost'],
            MenuCycleCostService::forReport($cycle)['total_cost'],
            1e-6,
        );
        $this->assertEqualsWithDelta(100.0, MenuCycleCostService::forReport($cycle)['total_cost'], 1e-6);
    }
}
