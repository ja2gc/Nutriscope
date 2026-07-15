<?php

namespace App\Models;

use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Exceptions\AuditLoggingUnavailable;
use App\Models\Builders\AuditActivityBuilder;
use App\Services\Audit\AuditHealthMonitor;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;
use Throwable;

class AuditActivity extends Activity
{
    private const MAX_UNSIGNED_BIGINT = '18446744073709551615';

    protected $hidden = [
        'subject_id',
        'subject_public_id',
        'context_id',
        'context_public_id',
        'root_patient_id',
        'ncp_record_id',
        'audit_owner_id',
        'patient_display_name_snapshot',
    ];

    protected $casts = [
        'properties' => 'collection',
        'category' => AuditCategory::class,
        'domain' => AuditDomain::class,
        'module' => AuditModule::class,
        'patient_display_name_snapshot' => 'encrypted',
        'severity' => AuditSeverity::class,
        'outcome' => AuditOutcome::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (AuditActivity $activity): void {
            $activity->public_id ??= (string) Str::uuid();
        });
        static::updating(function (): never {
            app(AuditHealthMonitor::class)->unauthorizedRowMutation('update');

            throw new \RuntimeException('Audit events are immutable.');
        });
        static::deleting(function (): never {
            app(AuditHealthMonitor::class)->unauthorizedRowMutation('delete');

            throw new \RuntimeException('Audit events may only be deleted by the retention service.');
        });
    }

    public function newEloquentBuilder($query): AuditActivityBuilder
    {
        /** @var QueryBuilder $query */
        return new AuditActivityBuilder($query);
    }

    public function revision(): HasOne
    {
        return $this->hasOne(AuditRevision::class, 'activity_id');
    }

    protected function performInsert(Builder $query): bool
    {
        try {
            return parent::performInsert($query);
        } catch (Throwable) {
            $exception = new AuditLoggingUnavailable('The audit event could not be persisted.');
            try {
                app(AuditHealthMonitor::class)->writerFailure($exception);
            } catch (Throwable) {
            }

            throw $exception;
        }
    }

    #[Scope]
    protected function auditOnly(Builder $query): void
    {
        $query->where('log_name', config('audit.log_name'));
    }

    #[Scope]
    protected function forCategory(Builder $query, AuditCategory|string $category): void
    {
        $query->where('category', $category instanceof AuditCategory ? $category->value : $category);
    }

    #[Scope]
    protected function forContext(Builder $query, Model|string $context, int|string|null $contextId = null): void
    {
        if ($context instanceof Model) {
            if ($contextId !== null) {
                throw new InvalidArgumentException('Do not pass a context identifier with a context model.');
            }

            if (! $context->exists || ! $this->isValidContextId($context->getKey())) {
                throw new InvalidArgumentException('The context model must be persisted with a positive numeric key.');
            }

            $contextType = $context->getMorphClass();
            $key = $context->getKey();
        } else {
            if (trim($context) === '') {
                throw new InvalidArgumentException('The context type must not be empty.');
            }

            if (! $this->isValidContextId($contextId)) {
                throw new InvalidArgumentException('The context identifier must be a positive unsigned bigint.');
            }

            $contextType = $context;
            $key = $contextId;
        }

        $query->where('context_type', $contextType)->where('context_id', $key);
    }

    private function isValidContextId(mixed $contextId): bool
    {
        if (is_int($contextId)) {
            return $contextId > 0;
        }

        if (! is_string($contextId) || preg_match('/^[1-9][0-9]*$/D', $contextId) !== 1) {
            return false;
        }

        return strlen($contextId) < strlen(self::MAX_UNSIGNED_BIGINT)
            || (strlen($contextId) === strlen(self::MAX_UNSIGNED_BIGINT)
                && strcmp($contextId, self::MAX_UNSIGNED_BIGINT) <= 0);
    }

    #[Scope]
    protected function fromDate(Builder $query, DateTimeInterface|string $date): void
    {
        $query->where('created_at', '>=', CarbonImmutable::parse($date)->startOfDay());
    }

    #[Scope]
    protected function toDate(Builder $query, DateTimeInterface|string $date): void
    {
        $query->where('created_at', '<=', CarbonImmutable::parse($date)->endOfDay());
    }

    #[Scope]
    protected function betweenDates(
        Builder $query,
        DateTimeInterface|string $start,
        DateTimeInterface|string $end,
    ): void {
        $query
            ->where('created_at', '>=', CarbonImmutable::parse($start)->startOfDay())
            ->where('created_at', '<=', CarbonImmutable::parse($end)->endOfDay());
    }
}
