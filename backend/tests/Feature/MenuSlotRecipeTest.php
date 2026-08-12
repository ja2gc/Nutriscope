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

class MenuSlotRecipeTest extends TestCase
{
    use RefreshDatabase;

    private function recipeSlot(User $rnd, bool $locked = false): array
    {
        $item = FsItem::factory()->create([
            'name' => 'Chicken',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'purchase_price' => 100,
            'units_per_purchase' => 1,
        ]);
        $recipe = FoodServiceRecipe::create([
            'rnd_user_id' => $rnd->id,
            'name' => 'Master Adobo',
            'prep_notes' => 'Master notes',
            'servings' => 20,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id' => $item->id,
            'quantity' => 2,
            'unit' => 'kg',
        ]);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id, 'is_active' => false]);
        $day = MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'recipe_id' => $recipe->id,
            'estimate_population' => 100,
            'po_snapshot_locked' => $locked,
        ]);

        return compact('item', 'recipe', 'cycle', 'day');
    }

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

    public function test_fss_can_view_master_slot_details_but_cannot_update_them(): void
    {
        $rnd = User::factory()->rnd()->create();
        $fss = User::factory()->fss()->create();
        ['cycle' => $cycle] = $this->recipeSlot($rnd);
        $url = "/api/fss/menu-cycles/{$cycle->uuid}/slots/Monday/lunch";

        $this->actingAs($fss)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.name', 'Master Adobo')
            ->assertJsonPath('data.source', 'master')
            ->assertJsonPath('data.reference_servings', 20)
            ->assertJsonPath('data.planned_servings', 100)
            ->assertJsonPath('data.ingredients.0.quantity', 2);

        $this->actingAs($fss)->patchJson($url, [])->assertForbidden();
    }

    public function test_rnd_can_customize_and_restore_one_slot_without_changing_master(): void
    {
        $rnd = User::factory()->rnd()->create();
        ['item' => $item, 'recipe' => $recipe, 'cycle' => $cycle, 'day' => $day] = $this->recipeSlot($rnd);
        $url = "/api/fss/menu-cycles/{$cycle->uuid}/slots/Monday/lunch";
        $payload = [
            'name' => 'Ward Adobo',
            'reference_servings' => 25,
            'planned_servings' => 100,
            'prep_notes' => 'Less salty',
            'ingredients' => [[
                'fs_item_id' => $item->uuid,
                'quantity' => 3,
                'unit' => 'kg',
            ]],
        ];

        $this->actingAs($rnd)->patchJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('data.source', 'custom')
            ->assertJsonPath('data.total_cost', 1200);

        $this->assertSame('Master Adobo', $recipe->fresh()->name);
        $this->assertSame('Ward Adobo', $day->fresh()->recipe_override['name']);
        $this->assertSame(100, $day->fresh()->servings_override);

        $this->actingAs($rnd)->deleteJson($url)
            ->assertOk()
            ->assertJsonPath('data.source', 'master');
        $this->assertNull($day->fresh()->recipe_override);
    }

    public function test_slot_update_validates_ingredients_and_rejects_locked_slots(): void
    {
        $rnd = User::factory()->rnd()->create();
        ['item' => $item, 'cycle' => $cycle] = $this->recipeSlot($rnd, locked: true);
        $url = "/api/fss/menu-cycles/{$cycle->uuid}/slots/Monday/lunch";
        $payload = [
            'name' => 'Locked',
            'reference_servings' => 20,
            'planned_servings' => 100,
            'ingredients' => [
                ['fs_item_id' => $item->uuid, 'quantity' => 2, 'unit' => 'kg'],
                ['fs_item_id' => $item->uuid, 'quantity' => 3, 'unit' => 'kg'],
            ],
        ];

        $this->actingAs($rnd)->patchJson($url, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ingredients.1.fs_item_id');

        $payload['ingredients'] = [$payload['ingredients'][0]];
        $this->actingAs($rnd)->patchJson($url, $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'This menu item is locked to a purchase order.');
    }
}
