<?php

namespace App\Services\Backup;

use App\Data\BackupArchiveResult;
use App\Exceptions\BackupVerificationFailed;
use Illuminate\Support\Facades\Storage;

class BackupVerifier
{
    public function verify(BackupArchiveResult $result): BackupArchiveResult
    {
        $disk = Storage::disk(config('nutriscope-backups.disk'));

        if (! $result->encrypted || ! $disk->exists($result->objectKey)) {
            throw new BackupVerificationFailed('Backup verification failed.');
        }

        $bytes = $disk->size($result->objectKey);
        if ($bytes < 1) {
            throw new BackupVerificationFailed('Backup verification failed.');
        }

        return $result->withBytes($bytes);
    }
}
