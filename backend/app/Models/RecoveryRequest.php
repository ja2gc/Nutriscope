<?php

namespace App\Models;

use App\Enums\RecoveryIncidentType;
use App\Enums\RecoveryStatus;
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
        'safety_snapshot_backup_run_id', 'temporary_database', 'failure_message',
        'started_at', 'terminal_at', 'safety_snapshot_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'incident_type' => RecoveryIncidentType::class,
            'state' => RecoveryStatus::class,
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
            'started_at' => 'datetime',
            'terminal_at' => 'datetime',
            'safety_snapshot_expires_at' => 'datetime',
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

    public function safetySnapshot(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'safety_snapshot_backup_run_id');
    }

    public function transitionTo(RecoveryStatus $status): void
    {
        $allowed = match ($this->state) {
            RecoveryStatus::Requested => [RecoveryStatus::Preparing, RecoveryStatus::Cancelled],
            RecoveryStatus::Preparing => [RecoveryStatus::Checking, RecoveryStatus::Failed, RecoveryStatus::Cancelled],
            RecoveryStatus::Checking => [RecoveryStatus::Ready, RecoveryStatus::Failed, RecoveryStatus::Cancelled],
            RecoveryStatus::Ready => [RecoveryStatus::Switching, RecoveryStatus::Cancelled],
            RecoveryStatus::Switching => [RecoveryStatus::Completed, RecoveryStatus::RolledBack, RecoveryStatus::Failed],
            default => [],
        };
        if (! in_array($status, $allowed, true)) {
            throw new \RuntimeException("Invalid recovery transition from {$this->state->value} to {$status->value}.");
        }
        $values = ['state' => $status];
        if ($status->terminal()) {
            $values += ['terminal_at' => now(), 'resolved_at' => now(), 'safety_snapshot_expires_at' => now()->addHours(48)];
        }
        $this->update($values);
        if ($status->terminal() && $this->safetySnapshot !== null) {
            $this->safetySnapshot->update(['retention_expires_at' => $this->safety_snapshot_expires_at]);
        }
    }
}
