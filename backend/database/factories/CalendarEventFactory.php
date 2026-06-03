<?php

namespace Database\Factories;

use App\Models\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'       => \App\Models\User::factory(),
            'title'         => $this->faker->sentence(3),
            'type'          => $this->faker->randomElement(['manual', 'system']),
            'source_module' => $this->faker->randomElement(['ncp', 'fss', null]),
            'source_id'     => null,
            'event_date'    => $this->faker->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'status'        => $this->faker->randomElement(['pending', 'completed']),
            'deletable'     => true,
        ];
    }
}
