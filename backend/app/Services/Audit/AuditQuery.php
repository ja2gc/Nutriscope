<?php

namespace App\Services\Audit;

use App\Enums\AuditModule;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\AuditActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditQuery
{
    public function __construct(private readonly AuditContextualFilters $contextualFilters) {}

    /** @param array<string, mixed> $filters */
    public function build(array $filters): Builder
    {
        $query = AuditActivity::query()
            ->select([
                'id', 'public_id', 'log_name', 'event', 'category', 'domain', 'module', 'severity', 'outcome',
                'subject_type', 'subject_id', 'subject_public_id', 'causer_type', 'causer_id',
                'context_type', 'context_id', 'context_public_id', 'patient_display_name_snapshot',
                'properties', 'created_at',
            ])
            ->auditOnly()
            ->where(function (Builder $query): void {
                $query->whereNull('causer_type')->orWhere('causer_type', (new User)->getMorphClass());
            })
            ->with(['causer' => function (MorphTo $relation): void {
                $relation->constrain([
                    User::class => fn (Builder $query): Builder => $query
                        ->withTrashed()
                        ->select('id', 'uuid', 'name', 'first_name', 'last_name', 'role'),
                ]);
            }, 'revision:id,activity_id,public_id,action']);

        $this->wherePresentedDefault($query, 'severity', $filters['severity'] ?? null, AuditSeverity::Info->value);
        $this->wherePresentedDefault($query, 'outcome', $filters['outcome'] ?? null, AuditOutcome::Success->value);
        $this->wherePresentedAction($query, $filters['action'] ?? null);

        if ($module = AuditModule::tryFrom((string) ($filters['module'] ?? ''))) {
            $query->where('module', $module->value);
            if (is_string($filters['subfilter'] ?? null)) {
                $this->contextualFilters->apply($query, $module, $filters['subfilter']);
            }
        }

        $query
            ->when($filters['start'] ?? null, fn (Builder $query, string $value): Builder => $query->fromDate($value))
            ->when($filters['end'] ?? null, fn (Builder $query, string $value): Builder => $query->toDate($value));

        if (isset($filters['actor_id'])) {
            $actorId = User::withTrashed()->where('uuid', $filters['actor_id'])->value('id');
            $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $actorId ?? 0);
        }

        $query
            ->when($filters['subject_id'] ?? null, fn (Builder $query, string $value): Builder => $query->where('subject_public_id', $value))
            ->when($filters['context_id'] ?? null, fn (Builder $query, string $value): Builder => $query->where('context_public_id', $value));

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    private function wherePresentedDefault(Builder $query, string $column, mixed $value, string $default): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if ($value === $default) {
            $query->where(fn (Builder $scope): Builder => $scope->where($column, $value)->orWhereNull($column));

            return;
        }

        $query->where($column, $value);
    }

    private function wherePresentedAction(Builder $query, mixed $value): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $aliases = collect(config('audit.legacy.action_aliases', []))
            ->filter(fn (mixed $canonical): bool => $canonical === $value)
            ->keys()
            ->filter(fn (mixed $alias): bool => is_string($alias))
            ->values()
            ->all();
        $events = [$value, ...$aliases];

        $query->whereIn('event', $events);
    }
}
