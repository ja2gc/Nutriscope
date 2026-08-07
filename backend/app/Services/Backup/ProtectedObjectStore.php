<?php

namespace App\Services\Backup;

use App\Models\StoredObject;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProtectedObjectStore
{
    /** @return array{key:string,bytes:int,sha256:string} */
    public function ensureProtected(StoredObject $object): array
    {
        $source = Storage::disk($object->storage_disk);
        $backup = Storage::disk(config('nutriscope-backups.disk'));
        $key = 'protected-objects/'.$object->sha256.'.'.$object->extension;
        if (! $backup->exists($key)) {
            $stream = $source->readStream($object->object_key);
            if (! is_resource($stream) || ! $backup->writeStream($key, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException('Uploaded object could not be protected.');
            }
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($backup->size($key) !== $object->bytes || hash('sha256', $backup->get($key)) !== $object->sha256) {
            throw new RuntimeException('Protected object verification failed.');
        }

        return ['key' => $key, 'bytes' => $object->bytes, 'sha256' => $object->sha256];
    }
}
