<?php

namespace Database\Factories;

use App\Models\MealPlan;
use App\Models\MealPlanDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealPlanDay>
 */
class MealPlanDayFactory extends Factory
{
    protected $model = MealPlanDay::class;

    public function definition(): array
    {
        return [
            'meal_plan_id' => MealPlan::factory(),
            'day_of_week' => fake()->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']),
            'meal_type' => fake()->randomElement(['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner']),
            'flagged' => false,
        ];
    }
}
