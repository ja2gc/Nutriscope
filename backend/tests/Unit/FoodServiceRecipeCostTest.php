<?php

namespace Tests\Unit;

use App\Models\FsItem;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
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

    private function makeFsItem(string $name, float $purchasePrice, string $purchaseUnit, string $baseUnit, ?float $unitsPerPurchase = null): FsItem
    {
        return FsItem::create([
            'name'               => $name,
            'purchase_price'     => $purchasePrice,
            'purchase_unit'      => $purchaseUnit,
            'base_unit'          => $baseUnit,
            'units_per_purchase' => $unitsPerPurchase,
            'kind'               => 'ingredient',
        ]);
    }

    public function test_recalculate_cost_uses_fs_item_unit_cost(): void
    {
        $rnd = $this->rnd();
        $recipe = $this->makeRecipe($rnd);
        // Carrots: ₱80 per 1 kg (purchase_unit), base_unit is g.
        // basePerPurchase is 1000. unit_cost is 80/1000 = ₱0.08 per g.
        $item = $this->makeFsItem('Carrots', 80.00, 'kg', 'g');

        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id'             => $item->id,
            'quantity'               => 120,
            'unit'                   => 'g',
        ]);

        $recipe->recalculateCost();

        // cost = 120 * 0.08 = 9.60
        $this->assertEquals('9.60', $recipe->fresh()->cost);
    }

    public function test_recalculate_cost_converts_units(): void
    {
        $rnd = $this->rnd();
        $recipe = $this->makeRecipe($rnd);
        // Chicken: ₱300 per kg, base is g. unit_cost = ₱0.30 per g.
        $item = $this->makeFsItem('Chicken', 300.00, 'kg', 'g');

        // Ingredient in kg: 0.5 kg (converts to 500 g)
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id'             => $item->id,
            'quantity'               => 0.5,
            'unit'                   => 'kg',
        ]);

        $recipe->recalculateCost();

        // cost = 500 * 0.30 = 150.00
        $this->assertEquals('150.00', $recipe->fresh()->cost);
    }
}
