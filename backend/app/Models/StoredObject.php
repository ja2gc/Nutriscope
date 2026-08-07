<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class StoredObject extends Model
{
    use HasPublicId;

    protected $fillable = [
        'storage_disk', 'object_key', 'purpose', 'mime_type', 'extension',
        'bytes', 'sha256', 'original_name',
    ];

    protected function casts(): array
    {
        return ['bytes' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $object): void {
            if ($object->isDirty($object->getFillable())) {
                throw new RuntimeException('Stored object metadata is immutable.');
            }
        });
    }
}
