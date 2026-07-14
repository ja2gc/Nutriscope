<?php

namespace Database\Factories;

use App\Models\FsItem;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class FsItemFactory extends Factory
{
    protected $model = FsItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'kind' => 'ingredient',
            'category' => fake()->randomElement(['Vegetables', 'Meat', 'Dairy', 'Dry Goods']),
            'base_unit' => 'g',
            'purchase_unit' => 'kg',
            'purchase_price' => fake()->randomFloat(2, 50, 500),
            'units_per_purchase' => null,
            'default_supplier_id' => Supplier::factory(),
            'is_active' => true,
            'notes' => null,
        ];
    }
}
