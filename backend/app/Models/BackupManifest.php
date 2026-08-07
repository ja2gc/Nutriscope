<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class BackupManifest extends Model
{
    protected $fillable = ['backup_run_id', 'storage_disk', 'object_key', 'sha256', 'object_count', 'total_bytes'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Backup manifests are immutable.'));
    }

    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class);
    }

    public function objects(): HasMany
    {
        return $this->hasMany(BackupManifestObject::class);
    }
}
