<?php

namespace App\Services\Backup;

use App\Models\BackupManifest;
use App\Models\BackupRun;
use App\Models\StoredObject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BackupManifestService
{
    public function __construct(private readonly ProtectedObjectStore $protectedObjects) {}

    public function create(BackupRun $run): BackupManifest
    {
        $objects = StoredObject::query()->orderBy('uuid')->get()->map(function (StoredObject $object): array {
            $protected = $this->protectedObjects->ensureProtected($object);

            return [
                'stored_object_id' => $object->id,
                'stored_object_uuid' => $object->uuid,
                'protected_key' => $protected['key'],
                'purpose' => $object->purpose,
                'bytes' => $protected['bytes'],
                'sha256' => $protected['sha256'],
            ];
        })->values();
        $document = json_encode([
            'version' => 1,
            'backup_uuid' => $run->uuid,
            'objects' => $objects->map(fn (array $object): array => collect($object)->except('stored_object_id')->all())->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $diskName = config('nutriscope-backups.disk');
        $key = 'manifests/'.$run->uuid.'.json';
        if (! Storage::disk($diskName)->put($key, $document, ['visibility' => 'private'])) {
            throw new RuntimeException('Backup manifest could not be stored.');
        }
        $checksum = hash('sha256', $document);

        try {
            try {
                return DB::transaction(function () use ($run, $diskName, $key, $checksum, $objects): BackupManifest {
                    $manifest = BackupManifest::query()->create([
                        'backup_run_id' => $run->id,
                        'storage_disk' => $diskName,
                        'object_key' => $key,
                        'sha256' => $checksum,
                        'object_count' => $objects->count(),
                        'total_bytes' => $objects->sum('bytes'),
                    ]);
                    $manifest->objects()->createMany($objects->all());

                    return $manifest->load('objects');
                });
            } catch (Throwable $exception) {
                Storage::disk($diskName)->delete($key);
                throw $exception;
            }
        } catch (Throwable $exception) {
            Storage::disk($diskName)->delete($key);
            throw $exception;
        }
    }
}
