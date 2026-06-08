<?php

namespace Tests\Unit;

use App\Models\FoodItem;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FoodServiceRecipeCostTest extends TestCase
{
    use RefreshDatabase;

    private function rnd(): User
    {
        return User::forceCreate([
            'name'      => 'RND',
            'email'     => 'rnd' . uniqid() . '@example.com',
            'password'  => Hash::make('password'),
            'role'      => 'RND',
            'is_active' => true,
        ]);
    }

    private function makeRecipe(User $rnd, string $name = 'Test Recipe'): FoodServiceRecipe
    {
        return FoodServiceRecipe::create([
            'rnd_user_id' => $rnd->id,
            'name'        => $name,
            'servings'    => 1,
        ]);
    }

    private function makeInventoryItem(string $itemName, float $unitPrice): Inventory
    {
        $food = FoodItem::forceCreate([
            'name'         => $itemName,
            'calories'     => 100,
            'protein'      => 10,
            'carbs'        => 10,
            'fat'          => 5,
            'serving_size' => 100,
        ]);
        return Inventory::forceCreate([
            'item_type'               => 'food_item',
            'food_item_id'            => $food->id,
            'quantity_in_stock'       => 10,
            'unit'                    => 'kg',
            'minimum_stock_threshold' => 1,
            'usage_rate'              => 1,
            'unit_price'              => $unitPrice,
        ]);
    }

    public function test_recalculate_cost_uses_inventory_unit_price(): void
    {
        $rnd = $this->rnd();
        $recipe = $this->makeRecipe($rnd);
        $inv = $this->makeInventoryItem('Chicken Breast', 28.00);

        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'inventory_id'           => $inv->id,
            'quantity'               => 120,
            'unit'                   => 'g',
        ]);

        $recipe->recalculateCost();

        // cost = (120 / 100) × 28.00 = 33.60
        $this->assertEquals('33.60', $recipe->fresh()->cost);
    }

    public function test_cost_is_zero_when_inventory_has_no_price(): void
    {
        $rnd = $this->rnd();
        $recipe = $this->makeRecipe($rnd);
        $inv = $this->makeInventoryItem('Unknown Item', 0);

        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'inventory_id'           => $inv->id,
            'quantity'               => 100,
            'unit'                   => 'g',
        ]);

        $recipe->recalculateCost();

        $this->assertEquals('0.00', $recipe->fresh()->cost);
    }

    public function test_food_service_recipe_does_not_use_food_item_price(): void
    {
        $rnd = $this->rnd();
        $recipe = $this->makeRecipe($rnd);

        // food_item has a large unit_price — but FSS only reads from inventory.unit_price
        $food = FoodItem::forceCreate([
            'name'         => 'Pork Loin',
            'calories'     => 200,
            'protein'      => 20,
            'carbs'        => 0,
            'fat'          => 12,
            'serving_size' => 100,
            'unit_price'   => 999,
        ]);
        $inv = Inventory::forceCreate([
            'item_type'               => 'food_item',
            'food_item_id'            => $food->id,
            'quantity_in_stock'       => 10,
            'unit'                    => 'kg',
            'minimum_stock_threshold' => 1,
            'usage_rate'              => 1,
            'unit_price'              => 28.00,
        ]);

        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'inventory_id'           => $inv->id,
            'quantity'               => 100,
            'unit'                   => 'g',
        ]);

        $recipe->recalculateCost();

        // Must be 28.00 (from inventory), not 999.00 (from food_item.unit_price)
        $this->assertEquals('28.00', $recipe->fresh()->cost);
        $this->assertNotEquals('999.00', $recipe->fresh()->cost);
    }
}
