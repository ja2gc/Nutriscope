<?php

namespace Database\Factories;

use App\Models\MealPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealPlanItem>
 */
class MealPlanItemFactory extends Factory
{
    protected $model = MealPlanItem::class;

    public function definition(): array
    {
        return [
            'meal_plan_day_id'  => \App\Models\MealPlanDay::factory(),
            'food_item_id'      => null,
            'recipe_id'         => null,
            'fdc_id'            => null,
            'quantity'          => fake()->randomFloat(2, 50, 300),
            'unit'              => fake()->randomElement(['g', 'ml', 'serving']),
            'nutrient_snapshot' => [
                'calories' => fake()->randomFloat(1, 100, 500),
                'protein'  => fake()->randomFloat(1, 5, 40),
                'carbs'    => fake()->randomFloat(1, 10, 80),
                'fat'      => fake()->randomFloat(1, 2, 30),
            ],
            'ai_suggested'      => false,
        ];
    }
}
