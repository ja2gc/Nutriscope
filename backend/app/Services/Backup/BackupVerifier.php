<?php

namespace App\Services\Backup;

use App\Data\BackupArchiveResult;
use App\Exceptions\BackupVerificationFailed;
use App\Models\BackupManifest;
use Illuminate\Support\Facades\Storage;

class BackupVerifier
{
    public function __construct(private readonly BackupArchiveInspector $archives) {}

    public function verify(BackupArchiveResult $result): BackupArchiveResult
    {
        $disk = Storage::disk(config('nutriscope-backups.disk'));

        if (! $result->encrypted || ! $disk->exists($result->objectKey)) {
            throw new BackupVerificationFailed('Backup verification failed.');
        }

        $inspection = $this->archives->inspect(
            config('nutriscope-backups.disk'),
            $result->objectKey,
            (string) config('backup.backup.password'),
        );
        if ($inspection['bytes'] < 1) {
            throw new BackupVerificationFailed('Backup verification failed.');
        }

        return $result->withIntegrity($inspection['bytes'], $inspection['sha256']);
    }

    public function verifyManifest(BackupManifest $manifest): void
    {
        $disk = Storage::disk($manifest->storage_disk);
        if (! $disk->exists($manifest->object_key)) {
            throw new BackupVerificationFailed('Backup manifest verification failed.');
        }
        $document = $disk->get($manifest->object_key);
        $decoded = json_decode($document, true);
        if (hash('sha256', $document) !== $manifest->sha256
            || ! is_array($decoded)
            || ($decoded['version'] ?? null) !== 1
            || count($decoded['objects'] ?? []) !== $manifest->object_count) {
            throw new BackupVerificationFailed('Backup manifest verification failed.');
        }
        foreach ($manifest->objects as $object) {
            if (! $disk->exists($object->protected_key)
                || $disk->size($object->protected_key) !== $object->bytes
                || hash('sha256', $disk->get($object->protected_key)) !== $object->sha256) {
                throw new BackupVerificationFailed('Backup uploaded-object verification failed.');
            }
        }
    }
}
