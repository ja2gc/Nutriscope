<?php

namespace Database\Factories;

use App\Models\FoodItem;
use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'food_item_id'            => FoodItem::factory(),
            'quantity_in_stock'       => fake()->randomFloat(2, 0, 500),
            'unit'                    => fake()->randomElement(['kg', 'g', 'L', 'ml', 'piece']),
            'usage_rate'              => fake()->randomFloat(2, 0.1, 10),
            'minimum_stock_threshold' => fake()->randomFloat(2, 5, 50),
            'notes'                   => null,
        ];
    }
}
