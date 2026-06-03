<?php

namespace Database\Factories;

use App\Models\MenuCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCycle>
 */
class MenuCycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fss_user_id'     => \App\Models\User::factory()->fss(),
            'name'            => $this->faker->words(3, true),
            'cycle_days'      => 7,
            'is_active'       => false,
            'week_start_date' => $this->faker->dateTimeBetween('now', '+1 week')->format('Y-m-d'),
            'status'          => 'draft',
            'activation_date' => null,
        ];
    }
}
