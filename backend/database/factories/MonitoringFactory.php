<?php

namespace Database\Factories;

use App\Models\Monitoring;
use App\Models\NcpRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonitoringFactory extends Factory
{
    protected $model = Monitoring::class;

    public function definition(): array
    {
        return [
            'ncp_record_id' => NcpRecord::factory(),
            'weight' => fake()->randomFloat(2, 40, 120),
            'bmi' => fake()->randomFloat(2, 16, 35),
            'lab_values' => [
                'albumin' => fake()->randomFloat(1, 2.5, 5.0),
                'creatinine' => fake()->randomFloat(1, 0.5, 3.0),
            ],
            'intake_notes' => fake()->sentence(),
            'symptoms' => fake()->sentence(),
            'goal_achievement' => [
                'energy' => fake()->randomElement(['met', 'partial', 'not_met']),
                'protein' => fake()->randomElement(['met', 'partial', 'not_met']),
            ],
            'clinical_summary' => fake()->paragraph(),
            'next_monitoring_date' => fake()->dateTimeBetween('+1 week', '+3 months')->format('Y-m-d'),
        ];
    }
}
