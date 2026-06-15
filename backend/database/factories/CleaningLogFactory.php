<?php

namespace Database\Factories;

use App\Models\CleaningLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CleaningLog>
 */
class CleaningLogFactory extends Factory
{
    protected $model = CleaningLog::class;

    public function definition(): array
    {
        return [
            'fss_user_id' => User::factory()->state(['role' => 'FSS']),
            'item_name'   => $this->faker->randomElement(['Walk-in chiller', 'Prep table', 'Stock pot', 'Floor drains']),
            'category'    => $this->faker->randomElement(['equipment', 'surface', 'storage']),
            'status'      => $this->faker->randomElement(['pending', 'done']),
            'notes'       => $this->faker->optional()->sentence(),
            'cleaned_at'  => $this->faker->optional()->dateTimeThisMonth(),
        ];
    }
}
