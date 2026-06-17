<?php

namespace Database\Factories;

use App\Models\ShoppingList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingList>
 */
class ShoppingListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rnd_user_id'  => \App\Models\User::factory()->fss(),
            'name'         => $this->faker->words(3, true) . ' List',
            'list_date'    => $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d'),
            'period_start' => $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d'),
            'period_end'   => $this->faker->dateTimeBetween('now', '+1 week')->format('Y-m-d'),
            'list_type'    => $this->faker->randomElement(['manual', 'suggested']),
            'status'       => $this->faker->randomElement(['draft', 'finalized']),
        ];
    }
}
