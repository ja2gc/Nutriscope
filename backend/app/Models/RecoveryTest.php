<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryTest extends Model
{
    protected $fillable = ['backup_run_id', 'state', 'checks', 'started_at', 'completed_at', 'failure_message'];

    protected function casts(): array
    {
        return ['checks' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class);
    }
}
