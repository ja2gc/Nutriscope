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
        $problem        = fake()->sentence(3);
        $etiology       = fake()->sentence(4);
        $signsSymptoms  = fake()->sentence(4);

        return [
            'ncp_record_id'  => \App\Models\NcpRecord::factory(),
            'domain'         => fake()->randomElement(['NI', 'NC', 'NB']),
            'problem'        => $problem,
            'label'          => fake()->sentence(4),
            'etiology'       => $etiology,
            'signs_symptoms' => $signsSymptoms,
            'pes_statement'  => Diagnosis::buildPes($problem, $etiology, $signsSymptoms),
            'extra_notes'    => fake()->optional()->sentence(),
            'ai_generated'   => fake()->boolean(),
        ];
    }
}
