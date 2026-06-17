<?php

namespace Database\Factories;

use App\Models\FsItem;
use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'item_type'               => 'ingredient',
            'fs_item_id'              => FsItem::factory(),
            'quantity_in_stock'       => fake()->randomFloat(2, 0, 500),
            'unit'                    => fake()->randomElement(['kg', 'g', 'L', 'ml', 'piece']),
            'notes'                   => null,
        ];
    }
}
