<?php

namespace App\Services;

use App\Jobs\DeleteStoredObject;
use App\Models\StoredObject;
use App\Services\Uploads\ImageNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoredObjectStorage
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(private readonly ImageNormalizer $images) {}

    public function storeUpload(UploadedFile $file, string $purpose): StoredObject
    {
        $bytes = $file->get();
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        if (! isset(self::MIME_EXTENSIONS[$mime])) {
            $mime = $file->getClientMimeType() ?: $mime;
        }

        return $this->storeBytes($bytes, $mime, $file->guessExtension() ?: '', $purpose, $file->getClientOriginalName());
    }

    public function storeBytes(string $bytes, string $mime, string $extension, string $purpose, ?string $originalName): StoredObject
    {
        if (str_starts_with($mime, 'image/')) {
            $normalized = $this->images->normalizeBytes($bytes, $purpose);
            $bytes = $normalized['bytes'];
            $mime = $normalized['mime'];
        } elseif ($mime === 'application/pdf') {
            if (! str_starts_with($bytes, '%PDF-') || ! str_contains(substr($bytes, -1024), '%%EOF')) {
                throw new RuntimeException('PDF content is invalid.');
            }
        }

        $extension = self::MIME_EXTENSIONS[$mime] ?? null;
        if ($bytes === '' || $extension === null || preg_match('/^[a-z0-9_-]+$/D', $purpose) !== 1) {
            throw new RuntimeException('Stored object input is invalid.');
        }

        $diskName = config('filesystems.private_uploads');
        $key = $purpose.'/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk($diskName);

        try {
            if (! $disk->put($key, $bytes, ['visibility' => 'private'])) {
                throw new RuntimeException('Private object storage failed.');
            }
            if ($disk->size($key) !== strlen($bytes)) {
                throw new RuntimeException('Private object verification failed.');
            }

            return StoredObject::query()->create([
                'storage_disk' => $diskName,
                'object_key' => $key,
                'purpose' => $purpose,
                'mime_type' => $mime,
                'extension' => $extension,
                'bytes' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'original_name' => $originalName,
            ]);
        } catch (Throwable $exception) {
            $disk->delete($key);
            throw $exception;
        }
    }

    /** @return resource */
    public function readStream(StoredObject $object)
    {
        $stream = Storage::disk($object->storage_disk)->readStream($object->object_key);
        if (! is_resource($stream)) {
            throw new RuntimeException('Private object is unavailable.');
        }

        return $stream;
    }

    public function delete(StoredObject $object): void
    {
        $disk = Storage::disk($object->storage_disk);
        if ($disk->exists($object->object_key) && ! $disk->delete($object->object_key)) {
            throw new RuntimeException('Private object could not be deleted.');
        }
        $object->delete();
    }

    public function deleteOrQueue(StoredObject $object): void
    {
        try {
            $this->delete($object);
        } catch (Throwable $exception) {
            report($exception);
            DeleteStoredObject::dispatch($object->id, $object->storage_disk, $object->object_key);
        }
    }
}
