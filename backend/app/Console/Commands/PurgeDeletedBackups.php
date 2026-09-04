<?php

namespace App\Console\Commands;

use App\Enums\BackupState;
use App\Models\BackupRun;
use App\Services\Backup\BackupRetentionService;
use Illuminate\Console\Command;
use Throwable;

class PurgeDeletedBackups extends Command
{
    protected $signature = 'backups:purge-deleted';

    protected $description = 'Permanently remove expired Recently Deleted backup objects';

    public function handle(BackupRetentionService $retention): int
    {
        $failed = false;

        BackupRun::query()
            ->where('state', BackupState::RecentlyDeleted)
            ->where('recoverable_until', '<=', now())
            ->orderBy('id')
            ->eachById(function (BackupRun $backup) use (&$failed, $retention): void {
                try {
                    $retention->purge($backup);
                } catch (Throwable $exception) {
                    $failed = true;
                    report($exception);
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
