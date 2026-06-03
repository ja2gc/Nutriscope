<?php

namespace Database\Factories;

use App\Models\Diagnosis;
use App\Models\NcpRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiagnosisFactory extends Factory
{
    protected $model = Diagnosis::class;

    public function definition(): array
    {
        return [
            'ncp_record_id' => \App\Models\NcpRecord::factory(),
            'domain'        => fake()->randomElement(['NI', 'NC', 'NB']),
            'label'         => fake()->sentence(4),
            'etiology'      => 'related to ' . fake()->sentence(4),
            'signs'         => 'evidenced by ' . fake()->sentence(4),
            'priority'      => fake()->numberBetween(1, 5),
        ];
    }
}
