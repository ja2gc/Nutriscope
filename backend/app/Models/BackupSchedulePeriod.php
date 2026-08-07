<?php

namespace App\Models;

use App\Enums\BackupRetentionTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupSchedulePeriod extends Model
{
    protected $fillable = ['backup_run_id', 'category', 'period_key', 'expires_at'];

    protected function casts(): array
    {
        return [
            'category' => BackupRetentionTier::class,
            'expires_at' => 'datetime',
        ];
    }

    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class);
    }
}
