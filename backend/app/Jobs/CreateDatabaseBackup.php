<?php

namespace App\Jobs;

use App\Contracts\BackupArchiveRunner;
use App\Enums\BackupState;
use App\Models\BackupRun;
use App\Notifications\BackupFailedNotification;
use App\Services\Backup\BackupVerifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as DispatchableQueueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CreateDatabaseBackup implements ShouldBeUnique, ShouldQueue
{
    use DispatchableQueueable;

    public int $tries = 3;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $backupUuid)
    {
        $this->onQueue(config('nutriscope-backups.queue'));
    }

    public function uniqueId(): string
    {
        return 'database-backup';
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('database-backup'))->expireAfter(960)->dontRelease()];
    }

    public function handle(BackupArchiveRunner $runner, BackupVerifier $verifier): void
    {
        $backup = BackupRun::where('uuid', $this->backupUuid)->firstOrFail();
        $backup->update([
            'state' => BackupState::Running,
            'started_at' => now(),
            'failure_code' => null,
            'failure_message' => null,
        ]);

        try {
            $result = $runner->runDatabaseOnly();
            $backup->update(['state' => BackupState::Verifying]);
            $result = $verifier->verify($result);
            $backup->update([
                'state' => BackupState::Completed,
                'object_key' => $result->objectKey,
                'bytes' => $result->bytes,
                'integrity_value' => $result->integrityValue,
                'encrypted' => $result->encrypted,
                'completed_at' => now(),
                'verified_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($backup);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $backup = BackupRun::where('uuid', $this->backupUuid)->first();
        if ($backup !== null) {
            $this->markFailed($backup);
        }

        $email = config('nutriscope-backups.alert_email');
        if (filled($email)) {
            Notification::route('mail', $email)->notify(new BackupFailedNotification($this->backupUuid));
        }
    }

    private function markFailed(BackupRun $backup): void
    {
        $backup->update([
            'state' => BackupState::Failed,
            'failure_code' => 'backup_failed',
            'failure_message' => 'Backup could not be completed. Check the storage and database connection.',
        ]);
    }
}
