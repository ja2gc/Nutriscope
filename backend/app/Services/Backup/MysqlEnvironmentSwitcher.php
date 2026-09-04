<?php

namespace App\Services\Backup;

use App\Contracts\EnvironmentSwitcher;
use App\Models\BackupRun;
use App\Models\RecoveryRequest;
use RuntimeException;
use Throwable;

class MysqlEnvironmentSwitcher implements EnvironmentSwitcher
{
    public function __construct(
        private readonly MysqlDatabaseRestoreManager $databases,
        private readonly ProtectedUploadRestorer $uploads,
        private readonly RecoveryVerifier $recoveryVerifier,
        private readonly BackupVerifier $backupVerifier,
    ) {}

    public function available(): bool
    {
        return config('nutriscope-backups.restore_enabled') === true
            && config('database.default') === 'mysql'
            && config('app.maintenance.driver') === 'cache'
            && $this->uploads->available();
    }

    public function switch(array $candidate): array
    {
        if (! $this->available()) {
            return ['successful' => false, 'message' => 'Production recovery prerequisites are not configured.'];
        }

        $recovery = RecoveryRequest::query()->where('uuid', $candidate['recovery_uuid'] ?? null)->firstOrFail();
        if (! $this->databases->candidateContainsUser($candidate['name'], $recovery->requested_by)) {
            throw new RuntimeException('The requesting administrator is absent from the selected restore point.');
        }
        $backup = BackupRun::query()->where('uuid', $candidate['backup_uuid'] ?? null)->firstOrFail();
        $token = $this->token($recovery->uuid);
        $this->uploads->stage($backup->load('manifest.objects'), $candidate['connection'], $token);
        $this->databases->promoteTemporary($candidate);
        $this->uploads->activate($token);

        return ['successful' => true, 'message' => 'Production data and protected uploads were restored.'];
    }

    public function finalize(array $candidate): void
    {
        $this->uploads->finalize($this->token((string) $candidate['recovery_uuid']));
    }

    public function rollback(string $safetySnapshotUuid): array
    {
        $safety = BackupRun::query()->where('uuid', $safetySnapshotUuid)->with('manifest.objects')->firstOrFail();
        $recovery = RecoveryRequest::query()->where('safety_snapshot_backup_run_id', $safety->id)->latest('id')->firstOrFail();
        $name = 'nutriscope_recovery_'.substr(hash('sha256', $safety->uuid), 0, 12);
        $candidate = null;

        try {
            $this->databases->dropTemporary($name);
            $candidate = $this->databases->restoreToTemporary($safety, $name);
            $checks = $this->recoveryVerifier->verify($candidate);
            $this->backupVerifier->verifyManifest($safety->manifest);
            if (! $checks['passed']) {
                throw new RuntimeException('Safety snapshot recovery checks failed.');
            }
            $this->databases->promoteTemporary($candidate);
            $this->uploads->rollback($this->token($recovery->uuid));

            return ['successful' => true, 'message' => 'The pre-restore safety snapshot was restored.'];
        } catch (Throwable $exception) {
            report($exception);

            return ['successful' => false, 'message' => 'Automatic rollback failed.'];
        } finally {
            if ($candidate !== null) {
                $this->databases->dropTemporary($candidate['name']);
            }
        }
    }

    private function token(string $recoveryUuid): string
    {
        return substr(hash('sha256', $recoveryUuid), 0, 32);
    }
}
