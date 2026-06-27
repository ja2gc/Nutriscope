<?php

namespace Database\Factories;

use App\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'fiscal_year'        => $this->faker->unique()->numberBetween(2020, 2099),
            'allocated_amount'   => $this->faker->randomFloat(2, 500000, 2000000),
            'per_head_day_limit' => $this->faker->randomFloat(2, 100, 500),
        ];
    }
}
