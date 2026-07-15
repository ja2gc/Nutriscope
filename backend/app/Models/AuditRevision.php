<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class AuditRevision extends Model
{
    public const MAX_SNAPSHOT_BYTES = 1_048_576;

    public const SUPPORTED_SUBJECT_TYPES = [
        Budget::class,
        FoodServiceRecipe::class,
        MenuCycle::class,
        PurchaseOrder::class,
        Recipe::class,
        ShoppingList::class,
    ];

    public const SNAPSHOT_BYTE_LIMITS = [
        Budget::class => 262_144,
        FoodServiceRecipe::class => 262_144,
        MenuCycle::class => 524_288,
        PurchaseOrder::class => self::MAX_SNAPSHOT_BYTES,
        Recipe::class => 262_144,
        ShoppingList::class => 524_288,
    ];

    public const UPDATED_AT = null;

    protected $fillable = [
        'public_id',
        'activity_id',
        'module',
        'domain',
        'subject_type',
        'subject_public_id',
        'action',
        'schema_version',
        'before',
        'after',
        'occurred_at',
    ];

    protected $casts = [
        'module' => AuditModule::class,
        'domain' => AuditDomain::class,
        'action' => AuditAction::class,
        'schema_version' => 'integer',
        'before' => 'array',
        'after' => 'array',
        'occurred_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AuditRevision $revision): void {
            $revision->public_id ??= (string) Str::uuid();
            $revision->assertValid();
        });
        static::updating(function (): never {
            throw new RuntimeException('Audit revisions are immutable.');
        });
        static::deleting(function (): never {
            throw new RuntimeException('Audit revisions may only be deleted with their audit event.');
        });
    }

    public function auditEvent(): BelongsTo
    {
        return $this->belongsTo(AuditActivity::class, 'activity_id');
    }

    public function getConnectionName(): ?string
    {
        return config('activitylog.database_connection') ?? parent::getConnectionName();
    }

    private function assertValid(): void
    {
        if (! in_array($this->subject_type, self::SUPPORTED_SUBJECT_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported audit revision subject type.');
        }
        $activity = AuditActivity::query()->find($this->activity_id);
        if ($activity === null) {
            throw new InvalidArgumentException('Audit revision requires an existing audit event.');
        }
        if ($activity->category === AuditCategory::Clinical
            || $activity->root_patient_id !== null
            || $activity->ncp_record_id !== null
            || $activity->patient_display_name_snapshot !== null) {
            throw new InvalidArgumentException('Patient-linked audit events cannot have revisions.');
        }
        if ($activity->module !== $this->module
            || $activity->domain !== $this->domain
            || $activity->subject_type !== $this->subject_type
            || strtolower((string) $activity->subject_public_id) !== strtolower($this->subject_public_id)
            || $activity->event !== $this->action->value) {
            throw new InvalidArgumentException('Audit revision must match its parent event.');
        }
        $this->occurred_at = $activity->created_at;
        if (! is_string($this->subject_public_id) || ! Str::isUuid($this->subject_public_id)) {
            throw new InvalidArgumentException('Audit revision subject reference must be a UUID.');
        }
        if ((int) $this->schema_version < 1) {
            throw new InvalidArgumentException('Audit revision schema version must be positive.');
        }
        if ($this->before === null && $this->after === null) {
            throw new InvalidArgumentException('Audit revision requires a before or after snapshot.');
        }

        foreach ([$this->before, $this->after] as $snapshot) {
            if ($snapshot === null) {
                continue;
            }
            try {
                $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('Audit revision snapshot must be valid JSON.', previous: $exception);
            }
            if (strlen($encoded) > self::SNAPSHOT_BYTE_LIMITS[$this->subject_type]) {
                throw new InvalidArgumentException('Audit revision snapshot exceeds its size limit.');
            }
        }
    }
}
