<?php

namespace App\Jobs;

use App\Models\StoredObject;
use App\Services\StoredObjectStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class DeleteStoredObject implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly int $storedObjectId,
        public readonly string $storageDisk,
        public readonly string $objectKey,
    ) {}

    public function handle(StoredObjectStorage $storage): void
    {
        $object = StoredObject::query()->find($this->storedObjectId);
        if ($object !== null) {
            $storage->delete($object);

            return;
        }
        Storage::disk($this->storageDisk)->delete($this->objectKey);
    }
}
