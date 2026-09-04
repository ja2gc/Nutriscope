<?php

namespace App\Services\Backup;

use App\Contracts\EnvironmentSwitcher;

class UnsupportedEnvironmentSwitcher implements EnvironmentSwitcher
{
    public function available(): bool
    {
        return false;
    }

    public function switch(array $candidate): array
    {
        return ['successful' => false, 'message' => 'Production switching is not configured.'];
    }

    public function finalize(array $candidate): void {}

    public function rollback(string $safetySnapshotUuid): array
    {
        return ['successful' => false, 'message' => 'Production switching is not configured.'];
    }
}
