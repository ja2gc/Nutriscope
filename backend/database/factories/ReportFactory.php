<?php

namespace Database\Factories;

use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'      => \App\Models\User::factory(),
            'title'        => $this->faker->sentence(3),
            'type'         => $this->faker->randomElement([
                'adime_individual', 'adime_aggregate', 'ncp_census', 'inventory',
                'budget', 'procurement', 'menu_cycle', 'patient_menu_plan',
                'inspection_report', 'marketing_statement'
            ]),
            'filters'      => [],
            'parameters'   => [],
            'file_path'    => null,
            'status'       => 'queued',
            'generated_at' => null,
            'expires_at'    => null,
        ];
    }
}
