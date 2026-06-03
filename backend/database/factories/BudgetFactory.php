<?php

namespace Database\Factories;

use App\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fss_user_id'      => \App\Models\User::factory()->fss(),
            'allocated_amount' => $this->faker->randomFloat(2, 5000, 20000),
            'actual_amount'    => $this->faker->randomFloat(2, 4000, 19000),
            'period_start'     => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'period_end'       => $this->faker->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'cost_per_person'  => $this->faker->randomFloat(2, 50, 150),
        ];
    }
}
