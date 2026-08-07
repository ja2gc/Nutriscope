<?php

namespace App\Console\Commands;

use App\Enums\BackupState;
use App\Models\BackupManifestObject;
use App\Models\BackupRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeDeletedBackups extends Command
{
    protected $signature = 'backups:purge-deleted';

    protected $description = 'Permanently remove expired Recently Deleted backup objects';

    public function handle(): int
    {
        $failed = false;

        BackupRun::query()
            ->where('state', BackupState::RecentlyDeleted)
            ->where('recoverable_until', '<=', now())
            ->orderBy('id')
            ->eachById(function (BackupRun $backup) use (&$failed): void {
                try {
                    if (filled($backup->object_key)) {
                        $deleted = Storage::disk($backup->storage_disk)->delete($backup->object_key);
                        if (! $deleted) {
                            throw new \RuntimeException('Backup object could not be removed.');
                        }
                    }

                    $protectedKeys = $backup->manifest?->objects()->pluck('protected_key')->all() ?? [];
                    if ($backup->manifest !== null) {
                        Storage::disk($backup->manifest->storage_disk)->delete($backup->manifest->object_key);
                        $backup->manifest->delete();
                    }
                    foreach ($protectedKeys as $key) {
                        if (! BackupManifestObject::query()->where('protected_key', $key)->exists()) {
                            Storage::disk(config('nutriscope-backups.disk'))->delete($key);
                        }
                    }

                    $backup->transitionTo(BackupState::Purged);
                    $backup->update([
                        'purged_at' => now(),
                        'object_key' => null,
                        'integrity_value' => null,
                    ]);
                } catch (Throwable $exception) {
                    $failed = true;
                    report($exception);
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
