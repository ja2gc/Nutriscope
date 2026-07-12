<?php

namespace App\Models;

use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;

class AuditActivity extends Activity
{
    private const MAX_UNSIGNED_BIGINT = '18446744073709551615';

    protected $casts = [
        'properties' => 'collection',
        'category' => AuditCategory::class,
        'domain' => AuditDomain::class,
        'severity' => AuditSeverity::class,
        'outcome' => AuditOutcome::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (AuditActivity $activity): void {
            $activity->public_id ??= (string) Str::uuid();
        });
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
