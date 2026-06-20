<?php

namespace Database\Factories;

use App\Models\DietListCount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DietListCount>
 */
class DietListCountFactory extends Factory
{
    protected $model = DietListCount::class;

    public function definition(): array
    {
        return [
            'service_date'          => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'menu_cycle_id'         => null,
            'ward'                  => $this->faker->randomElement(['Ward A', 'Ward B', 'Ward C', 'ICU']),
            'fss_user_id'           => User::factory()->state(['role' => 'FSS']),
            'population'            => $this->faker->numberBetween(10, 60),
            'helped_food_prep'      => false,
            'stored_supplies'       => false,
            'collected_diet_list'   => false,
            'apportioned_food'      => false,
            'cleaned_utensils'      => false,
            'assistant_cook'        => false,
            'maintained_cleanliness'=> false,
            'off_duty'              => false,
        ];
    }
}
