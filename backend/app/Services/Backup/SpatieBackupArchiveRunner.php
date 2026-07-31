<?php

namespace App\Services\Backup;

use App\Contracts\BackupArchiveRunner;
use App\Data\BackupArchiveResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SpatieBackupArchiveRunner implements BackupArchiveRunner
{
    public function runDatabaseOnly(): BackupArchiveResult
    {
        if (blank(config('backup.backup.password'))) {
            throw new RuntimeException('Backup archive encryption is not configured.');
        }

        $diskName = config('nutriscope-backups.disk');
        $disk = Storage::disk($diskName);
        $before = $disk->allFiles(config('backup.backup.name'));

        $exitCode = Artisan::call('backup:run', [
            '--only-db' => true,
            '--only-to-disk' => $diskName,
            '--disable-notifications' => true,
            '--timeout' => 840,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Database backup command failed.');
        }

        $objectKey = collect($disk->allFiles(config('backup.backup.name')))
            ->diff($before)
            ->sortByDesc(fn (string $path): int => $disk->lastModified($path))
            ->first();

        if (! is_string($objectKey)) {
            throw new RuntimeException('Database backup object was not created.');
        }

        return new BackupArchiveResult(
            objectKey: $objectKey,
            bytes: $disk->size($objectKey),
            integrityValue: null,
            encrypted: true,
        );
    }
}
