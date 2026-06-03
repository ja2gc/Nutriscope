<?php

namespace Database\Factories;

use App\Models\ClinicalRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalRule>
 */
class ClinicalRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'condition'            => $this->faker->randomElement(['CKD', 'DM', 'HTN']),
            'stage'                => 'all',
            'nutrient_or_food_tag' => $this->faker->randomElement(['sodium', 'potassium', 'sugar', 'phosphorus']),
            'rule_type'            => $this->faker->randomElement(['recommend', 'avoid', 'limit']),
            'threshold'            => $this->faker->randomFloat(2, 0, 2000),
            'unit'                 => $this->faker->randomElement(['mg', 'g', '']),
            'reason'               => $this->faker->sentence(),
        ];
    }
}
