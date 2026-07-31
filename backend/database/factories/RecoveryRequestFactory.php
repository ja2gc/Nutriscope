<?php

namespace Database\Factories;

use App\Enums\RecoveryIncidentType;
use App\Models\BackupRun;
use App\Models\RecoveryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecoveryRequest> */
class RecoveryRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'backup_run_id' => BackupRun::factory()->completed(),
            'requested_by' => User::factory()->admin(),
            'incident_type' => RecoveryIncidentType::DamagedDatabase,
            'note' => $this->faker->sentence(),
            'state' => 'requested',
            'requested_at' => now(),
        ];
    }
}
