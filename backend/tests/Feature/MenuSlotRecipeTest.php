<?php

namespace Tests\Feature;

use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\User;
use App\Services\MenuCycleCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuSlotRecipeTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_override_is_scaled_instead_of_master_recipe(): void
    {
        $rnd = User::factory()->rnd()->create();
        $rice = FsItem::factory()->create([
            'name' => 'Rice',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'purchase_price' => 50,
            'units_per_purchase' => 1,
        ]);
        $master = FoodServiceRecipe::create([
            'rnd_user_id' => $rnd->id,
            'name' => 'Master Rice',
            'servings' => 10,
        ]);
        $day = new MenuCycleDay([
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'servings_override' => 100,
            'recipe_override' => [
                'name' => 'Slot Rice',
                'reference_servings' => 20,
                'prep_notes' => 'Slot only',
                'ingredients' => [[
                    'fs_item_id' => $rice->id,
                    'name' => 'Rice',
                    'quantity' => 2,
                    'unit' => 'kg',
                ]],
            ],
        ]);
        $day->setRelation('recipe', $master);
        $day->setRelation('fsItem', null);

        $entry = MenuCycleCostService::entryForDay($day);
        $result = MenuCycleCostService::aggregate([$entry]);

        $this->assertSame(20, $entry['recipe']['servings']);
        $this->assertSame('Slot Rice', $entry['recipe']['name']);
        $this->assertEqualsWithDelta(10, $result['ingredient_usage'][0]['quantity'], 0.001);
        $this->assertEqualsWithDelta(500, $result['total_cost'], 0.001);
    }

    public function test_cycle_grid_save_preserves_override_only_for_same_source(): void
    {
        $rnd = User::factory()->rnd()->create();
        $first = FoodServiceRecipe::create(['rnd_user_id' => $rnd->id, 'name' => 'First', 'servings' => 10]);
        $second = FoodServiceRecipe::create(['rnd_user_id' => $rnd->id, 'name' => 'Second', 'servings' => 10]);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id, 'is_active' => false]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'recipe_id' => $first->id,
            'recipe_override' => ['name' => 'Customized', 'reference_servings' => 10, 'ingredients' => []],
        ]);

        $payload = fn (FoodServiceRecipe $recipe) => ['days' => [[
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'recipe_id' => $recipe->uuid,
            'quantity' => 1,
        ]]];

        $this->actingAs($rnd)->patchJson("/api/fss/menu-cycles/{$cycle->uuid}", $payload($first))->assertOk();
        $this->assertDatabaseHas('menu_cycle_days', ['menu_cycle_id' => $cycle->id, 'recipe_id' => $first->id]);
        $this->assertSame('Customized', $cycle->days()->first()->recipe_override['name']);

        $this->actingAs($rnd)->patchJson("/api/fss/menu-cycles/{$cycle->uuid}", $payload($second))->assertOk();
        $this->assertNull($cycle->days()->first()->recipe_override);
    }
}
