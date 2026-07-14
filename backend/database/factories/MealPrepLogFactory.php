<?php

namespace Database\Factories;

use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MealPrepLogFactory extends Factory
{
    protected $model = MealPrepLog::class;

    public function definition(): array
    {
        return [
            'menu_cycle_id' => MenuCycle::factory(),
            'service_date' => now()->toDateString(),
            'population' => fake()->numberBetween(20, 100),
            'served_population' => null,
            'population_variance' => null,
            'status' => 'completed',
            'completed_by' => User::factory()->fss(),
            'completed_at' => now(),
            'total_value' => fake()->randomFloat(2, 100, 5000),
            'has_shortfall' => false,
        ];
    }
}
