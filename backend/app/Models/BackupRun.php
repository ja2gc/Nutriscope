<?php

namespace App\Models;

use App\Enums\BackupRetentionTier;
use App\Enums\BackupSource;
use App\Enums\BackupState;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupRun extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'state',
        'source',
        'storage_disk',
        'object_key',
        'bytes',
        'integrity_value',
        'encrypted',
        'requested_by',
        'queued_at',
        'started_at',
        'completed_at',
        'verified_at',
        'deleted_at',
        'recoverable_until',
        'purged_at',
        'retention_tier',
        'retention_expires_at',
        'failure_code',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'state' => BackupState::class,
            'source' => BackupSource::class,
            'retention_tier' => BackupRetentionTier::class,
            'encrypted' => 'boolean',
            'bytes' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'deleted_at' => 'datetime',
            'recoverable_until' => 'datetime',
            'purged_at' => 'datetime',
            'retention_expires_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function recoveryRequests(): HasMany
    {
        return $this->hasMany(RecoveryRequest::class);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query
            ->where('state', BackupState::Completed)
            ->whereNotNull('verified_at');
    }

    public function isProtectedFromDeletion(): bool
    {
        if ($this->recoveryRequests()->where('state', 'requested')->exists()) {
            return true;
        }

        return static::verified()->latest('verified_at')->value('id') === $this->id;
    }
}
