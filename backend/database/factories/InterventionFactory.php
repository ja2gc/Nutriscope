<?php

namespace Database\Factories;

use App\Models\Intervention;
use App\Models\NcpRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterventionFactory extends Factory
{
    protected $model = Intervention::class;

    public function definition(): array
    {
        return [
            'ncp_record_id'      => \App\Models\NcpRecord::factory(),
            'goal_type'          => fake()->randomElement(['weight_management', 'glycemic_control', 'renal_diet']),
            'disease_stage'      => fake()->randomElement(['stage_3', 'stage_4', 'stage_5', null]),
            'displayed_nutrients' => ['energy', 'protein', 'carbs', 'fat'],
            'energy_kcal'        => fake()->randomFloat(2, 1200, 2500),
            'protein_g'          => fake()->randomFloat(2, 40, 120),
            'carbs_g'            => fake()->randomFloat(2, 100, 350),
            'fat_g'              => fake()->randomFloat(2, 30, 100),
            'fluid_ml'           => fake()->randomFloat(2, 1000, 3000),
            'micronutrient_limits' => [],
            'education_notes'    => fake()->sentence(),
            'counseling_goals'   => fake()->sentence(),
            'session_type'       => fake()->randomElement(['initial', 'follow-up']),
            'next_followup_date' => fake()->dateTimeBetween('+1 week', '+3 months')->format('Y-m-d'),
        ];
    }
}
