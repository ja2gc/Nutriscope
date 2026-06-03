<?php

namespace Database\Factories;

use App\Models\MealPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealPlan>
 */
class MealPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'intervention_id' => \App\Models\Intervention::factory(),
            'patient_id'      => \App\Models\Patient::factory(),
            'week_start_date' => $this->faker->dateTimeBetween('now', '+1 week')->format('Y-m-d'),
            'generation_type' => $this->faker->randomElement(['manual', 'auto']),
            'status'          => $this->faker->randomElement(['draft', 'active']),
        ];
    }
}
