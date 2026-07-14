<?php

namespace Database\Factories;

use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuCycleDayFactory extends Factory
{
    protected $model = MenuCycleDay::class;

    public function definition(): array
    {
        return [
            'menu_cycle_id' => MenuCycle::factory(),
            'day_of_week' => fake()->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']),
            'meal_type' => fake()->randomElement(['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner']),
            'recipe_id' => null,
            'fs_item_id' => null,
            'quantity' => 1,
            'servings_override' => null,
            'estimate_population' => fake()->numberBetween(20, 100),
            'estimate_population_updated_at' => null,
            'is_event' => false,
            'event_allocation' => null,
        ];
    }
}
