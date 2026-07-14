<?php

namespace Database\Factories;

use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NcpRecordFactory extends Factory
{
    protected $model = NcpRecord::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'rnd_user_id' => User::factory()->rnd(),
            'type' => fake()->randomElement(['new', 'followup', 'reassessment']),
            'status' => fake()->randomElement(['draft', 'active', 'completed']),
            'risk_score' => fake()->randomFloat(2, 0, 10),
        ];
    }
}
