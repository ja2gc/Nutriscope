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
            'ncp_record_id'   => \App\Models\NcpRecord::factory(),
            'monitoring_date' => fake()->date('Y-m-d'),
            'weight_kg'       => fake()->randomFloat(2, 40, 120),
            'energy_actual'   => fake()->randomFloat(2, 1000, 2500),
            'protein_actual'  => fake()->randomFloat(2, 30, 120),
            'notes'           => fake()->sentence(),
            'goal_met'        => fake()->randomElement(['yes', 'partial', 'no']),
        ];
    }
}
