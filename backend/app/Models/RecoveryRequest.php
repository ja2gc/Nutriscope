<?php

namespace App\Models;

use App\Enums\RecoveryIncidentType;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryRequest extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'backup_run_id',
        'requested_by',
        'incident_type',
        'note',
        'state',
        'requested_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'incident_type' => RecoveryIncidentType::class,
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }
}
