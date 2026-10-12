<?php

namespace App\Services\Audit;

use App\Enums\AuditModule;
use App\Models\AiUsageLimit;
use App\Models\AuditActivity;
use App\Models\AuditSetting;
use App\Models\FoodServiceSetting;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditQuery
{
    /** @param array<string, mixed> $filters */
    public function build(array $filters): Builder
    {
        $query = AuditActivity::query()
            ->select([
                'id', 'public_id', 'log_name', 'event', 'category', 'domain', 'module',
                'subject_type', 'subject_id', 'subject_public_id', 'causer_type', 'causer_id',
                'context_type', 'context_id', 'context_public_id', 'patient_code_snapshot',
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
        $this->applyOversightVisibility($query);

        $this->wherePresentedAction($query, $filters['action'] ?? null);

        if ($module = AuditModule::tryFrom((string) ($filters['module'] ?? ''))) {
            $query->where('module', $module->value);
        }

        if (isset($filters['context'])) {
            $query->where('properties->report_type', $filters['context']);
        }

        $query
            ->when($filters['start'] ?? null, fn (Builder $query, string $value): Builder => $query->fromDate($value))
            ->when($filters['end'] ?? null, fn (Builder $query, string $value): Builder => $query->toDate($value));

        if (isset($filters['month'])) {
            $start = CarbonImmutable::createFromFormat('!Y-m', $filters['month'], 'Asia/Manila')->startOfMonth();
            $query->where('created_at', '>=', $start->utc())
                ->where('created_at', '<', $start->addMonth()->utc());
        }

        if (isset($filters['actor_id'])) {
            $actorId = User::withTrashed()->where('uuid', $filters['actor_id'])->value('id');
            $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $actorId ?? 0);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function applyOversightVisibility(Builder $query): Builder
    {
        $reportType = (new Report)->getMorphClass();
        $excludedSubjectTypes = array_map(
            fn (string $model): string => (new $model)->getMorphClass(),
            [AiUsageLimit::class, AuditSetting::class, FoodServiceSetting::class, ReportBranding::class, ReportTemplate::class],
        );

        return $query
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('event')
                ->orWhereNotIn('event', AuditLegacyEventNormalizer::removedEvents()))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('event')
                ->orWhere('event', '!=', 'viewed')
                ->orWhere('subject_type', $reportType))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('subject_type')
                ->orWhere('subject_type', '!=', (new User)->getMorphClass())
                ->orWhereNull('event')
                ->orWhere('event', '!=', 'updated')
                ->orWhereNull('properties->details->subject_label')
                ->orWhere('properties->details->subject_label', '!=', 'Profile'))
            ->where(function (Builder $query) use ($reportType, $excludedSubjectTypes): void {
                $query
                    ->where(fn (Builder $query): Builder => $query
                        ->where('subject_type', $reportType)
                        ->whereIn('event', AuditLegacyEventNormalizer::reportAccessActions()))
                    ->orWhere(fn (Builder $query): Builder => $query
                        ->where(fn (Builder $query): Builder => $query
                            ->whereNull('subject_type')
                            ->orWhereNotIn('subject_type', [$reportType, ...$excludedSubjectTypes]))
                        ->where(fn (Builder $query): Builder => $query
                            ->whereNull('domain')
                            ->orWhere('domain', '!=', 'reports')));
            });
    }

    private function wherePresentedAction(Builder $query, mixed $value): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $query->where('event', $value);
    }
}
