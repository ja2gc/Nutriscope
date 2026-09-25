<?php

namespace Database\Factories;

use App\Models\Intervention;
use App\Models\InterventionRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterventionRevisionFactory extends Factory
{
    protected $model = InterventionRevision::class;

    public function definition(): array
    {
        return [
            'intervention_id' => Intervention::factory(),
            'monitoring_id' => null,
            'version' => 1,
            'effective_at' => now(),
            'reason' => 'Initial intervention',
            'actor_user_id' => User::factory(),
            'source' => 'initial',
            'snapshot' => [],
        ];
    }
}
