<?php

namespace Database\Factories;

use App\Models\NcpAppointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NcpAppointment>
 */
class NcpAppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'rnd_user_id' => User::factory()->rnd(),
            'source' => 'scheduled',
            'status' => 'scheduled',
            'purpose' => fake()->sentence(4),
            'scheduled_at' => fake()->dateTimeBetween('now', '+30 days'),
        ];
    }
}
