<?php

namespace Database\Factories;

use App\Enums\BackupRetentionTier;
use App\Enums\BackupSource;
use App\Enums\BackupState;
use App\Models\BackupRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BackupRun> */
class BackupRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'state' => BackupState::Queued,
            'source' => BackupSource::Automatic,
            'storage_disk' => 'backups',
            'object_key' => null,
            'bytes' => null,
            'integrity_value' => null,
            'encrypted' => false,
            'queued_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'state' => BackupState::Completed,
            'object_key' => 'nutriscope-database/database-'.$this->faker->uuid().'.zip',
            'bytes' => 1024,
            'integrity_value' => hash('sha256', $this->faker->uuid()),
            'encrypted' => true,
            'completed_at' => now(),
            'verified_at' => now(),
            'retention_tier' => BackupRetentionTier::Daily,
        ]);
    }
}
