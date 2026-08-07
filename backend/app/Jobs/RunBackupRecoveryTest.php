<?php

namespace App\Jobs;

use App\Contracts\DatabaseRestoreManager;
use App\Models\BackupRun;
use App\Models\RecoveryTest;
use App\Services\Backup\BackupVerifier;
use App\Services\Backup\RecoveryVerifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunBackupRecoveryTest implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public function __construct(public readonly string $backupUuid) {}

    public function uniqueId(): string
    {
        return 'backup-recovery-test';
    }

    public function handle(DatabaseRestoreManager $databases, RecoveryVerifier $verifier, BackupVerifier $backups): void
    {
        $backup = BackupRun::query()->where('uuid', $this->backupUuid)->verified()->firstOrFail();
        $test = RecoveryTest::query()->create(['backup_run_id' => $backup->id, 'state' => 'running', 'started_at' => now()]);
        $name = 'nutriscope_recovery_'.substr(str_replace('-', '', $test->getKey().$backup->uuid), -12);
        try {
            $candidate = $databases->restoreToTemporary($backup, $name);
            if ($backup->manifest !== null) {
                $backups->verifyManifest($backup->manifest->load('objects'));
            }
            $result = $verifier->verify([...$candidate, 'promotable' => false]);
            if (! $result['passed']) {
                throw new \RuntimeException('Recovery checks did not pass.');
            }
            $test->update(['state' => 'completed', 'checks' => $result['checks'], 'completed_at' => now()]);
            $backup->update(['recovery_tested_at' => now()]);
        } catch (Throwable $exception) {
            $test->update(['state' => 'failed', 'failure_message' => 'Recovery test failed.', 'completed_at' => now()]);
            throw $exception;
        } finally {
            $databases->dropTemporary($name);
        }
    }
}
