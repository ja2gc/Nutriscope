<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupManifestObject extends Model
{
    protected $fillable = ['stored_object_id', 'stored_object_uuid', 'protected_key', 'purpose', 'bytes', 'sha256'];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(BackupManifest::class, 'backup_manifest_id');
    }
}
