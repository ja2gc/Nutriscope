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
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

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
        'archive_sha256',
        'encrypted',
        'requested_by',
        'queued_at',
        'started_at',
        'completed_at',
        'verified_at',
        'recovery_tested_at',
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
            'recovery_tested_at' => 'datetime',
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

    public function latestRecoveryRequest(): HasOne
    {
        return $this->hasOne(RecoveryRequest::class)->latestOfMany();
    }

    public function schedulePeriods(): HasMany
    {
        return $this->hasMany(BackupSchedulePeriod::class);
    }

    public function manifest(): HasOne
    {
        return $this->hasOne(BackupManifest::class);
    }

    public function transitionTo(BackupState $state): void
    {
        $allowed = match ($this->state) {
            BackupState::Queued => [BackupState::Running, BackupState::Failed],
            BackupState::Running => [BackupState::Verifying, BackupState::Failed],
            BackupState::Verifying => [BackupState::Completed, BackupState::Failed],
            BackupState::Completed => [BackupState::RecentlyDeleted],
            BackupState::RecentlyDeleted => [BackupState::Completed, BackupState::Purged],
            BackupState::Failed => [BackupState::Purged],
            BackupState::Purged => [],
        };
        if (! in_array($state, $allowed, true)) {
            throw new RuntimeException("Invalid backup transition from {$this->state->value} to {$state->value}.");
        }
        $this->update(['state' => $state]);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query
            ->where('state', BackupState::Completed)
            ->whereNotNull('verified_at');
    }

    public function isProtectedFromDeletion(): bool
    {
        if ($this->source === BackupSource::Safety && $this->retention_expires_at?->isFuture()) {
            return true;
        }

        if ($this->recoveryRequests()->whereNotIn('state', ['completed', 'failed', 'rolled_back', 'cancelled'])->exists()) {
            return true;
        }

        return static::verified()->latest('verified_at')->value('id') === $this->id;
    }
}
