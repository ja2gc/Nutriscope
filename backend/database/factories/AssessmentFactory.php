<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\NcpRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return [
            'ncp_record_id' => NcpRecord::factory(),
            'height' => fake()->randomFloat(1, 145, 190),
            'weight' => fake()->randomFloat(1, 40, 120),
            'usual_weight' => fake()->randomFloat(1, 40, 120),
            'nutritional_status' => fake()->randomElement(['Normal', 'Moderate Malnutrition', 'Severe Malnutrition']),
            'weight_loss_percentage' => fake()->randomFloat(2, 0, 20),
            'weight_loss_period' => fake()->randomElement(['1 month', '3 months', '6 months', null]),
            'energy_intake_status' => fake()->randomElement(['No change', 'Mostly liquids', 'Sub-optimal', 'Starvation', 'Poor intake prior to admission']),
            'ibw_percentage' => fake()->randomFloat(2, 60, 120),
            'dietary_intake_method' => fake()->randomElement(['24_hour_recall', 'food_frequency', '3_day_record', 'other']),
            'functional_assessment' => fake()->randomElement(['Bed ridden', 'Needs assistance', 'Ambulatory']),
            'physical_assessment' => fake()->sentence(),
            'present_diet' => fake()->randomElement(['regular', 'soft', 'liquid', 'NPO']),
        ];
    }
}
