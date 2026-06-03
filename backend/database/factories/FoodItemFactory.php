<?php

namespace Database\Factories;

use App\Models\FoodItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class FoodItemFactory extends Factory
{
    protected $model = FoodItem::class;

    public function definition(): array
    {
        return [
            'name'         => fake()->unique()->words(2, true),
            'category'     => fake()->randomElement(['protein', 'carbs', 'vegetable', 'fat', 'dairy', 'fruit']),
            'usda_fdc_id'  => null,
            'calories'     => fake()->randomFloat(2, 50, 400),
            'protein'      => fake()->randomFloat(2, 0, 40),
            'carbs'        => fake()->randomFloat(2, 0, 80),
            'fat'          => fake()->randomFloat(2, 0, 30),
            'micronutrients' => [],
            'allergens'    => [],
            'unit_price'   => fake()->randomFloat(2, 5, 200),
            'serving_unit' => fake()->randomElement(['g', 'ml', 'piece']),
            'serving_size' => 100,
        ];
    }
}
