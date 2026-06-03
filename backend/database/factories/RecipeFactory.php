<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    public function definition(): array
    {
        return [
            'rnd_user_id'    => \App\Models\User::factory()->rnd(),
            'name'           => fake()->unique()->words(3, true),
            'category'       => fake()->randomElement(['breakfast', 'lunch', 'dinner', 'snack']),
            'prep_notes'     => fake()->sentence(),
            'total_calories' => fake()->randomFloat(2, 200, 900),
            'total_protein'  => fake()->randomFloat(2, 10, 60),
            'total_carbs'    => fake()->randomFloat(2, 20, 120),
            'total_fat'      => fake()->randomFloat(2, 5, 40),
            'micronutrients' => [],
            'servings'       => fake()->numberBetween(1, 8),
            'cost'           => fake()->randomFloat(2, 20, 500),
        ];
    }
}
