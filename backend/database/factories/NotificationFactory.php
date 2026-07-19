<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'message' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['alert', 'info', 'warning']),
            'source_module' => $this->faker->randomElement(['ncp', 'fss', null]),
            'source_id' => null,
            'read' => false,
            'read_at' => null,
            'opened_at' => null,
            'resolved_at' => null,
        ];
    }
}
