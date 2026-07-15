<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\AuditActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class AuditFilterMetadata
{
    public function __construct(
        private readonly AuditRetentionState $retentionState,
        private readonly AuditContextualFilters $contextualFilters,
    ) {}

    /** @return array{filters: array<string, mixed>, capabilities: array<string, bool>} */
    public function for(User $user): array
    {
        return [
            'filters' => [
                'categories' => $this->options(AuditCategory::cases()),
                'domains' => $this->options(AuditDomain::cases()),
                'modules' => $this->options(AuditModule::cases()),
                'module_subfilters' => $this->contextualFilters->options(),
                'module_actions' => collect(config('audit.taxonomy.module_actions', []))
                    ->map(fn (array $actions): array => array_values($actions))
                    ->all(),
                'module_counts' => $this->moduleCounts(),
                'actions' => $this->options(AuditAction::cases()),
                'outcomes' => $this->options(AuditOutcome::cases()),
                'severities' => $this->options(AuditSeverity::cases()),
                'category_actions' => collect(config('audit.taxonomy.category_actions', []))
                    ->map(fn (array $actions): array => array_values($actions))
                    ->all(),
            ],
            'capabilities' => [
                'export' => (bool) config('audit.features.export')
                    && Gate::forUser($user)->allows('export', AuditActivity::class),
            ],
            'retention' => $this->retentionState->current(),
        ];
    }

    /**
     * @param  array<int, AuditAction|AuditCategory|AuditDomain|AuditModule|AuditOutcome|AuditSeverity>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(
            fn (object $case): array => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );
    }

    /** @return array{all: int, security_administration: int, nutrition_care: int, food_service_operations: int, reports: int} */
    private function moduleCounts(): array
    {
        $row = AuditActivity::query()
            ->auditOnly()
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('causer_type')->orWhere('causer_type', (new User)->getMorphClass()))
            ->selectRaw("COUNT(*) AS all_count,
                SUM(module = 'security_administration') AS security_administration_count,
                SUM(module = 'nutrition_care') AS nutrition_care_count,
                SUM(module = 'food_service_operations') AS food_service_operations_count,
                SUM(module = 'reports') AS reports_count")
            ->first();

        return [
            'all' => (int) ($row?->all_count ?? 0),
            'security_administration' => (int) ($row?->security_administration_count ?? 0),
            'nutrition_care' => (int) ($row?->nutrition_care_count ?? 0),
            'food_service_operations' => (int) ($row?->food_service_operations_count ?? 0),
            'reports' => (int) ($row?->reports_count ?? 0),
        ];
    }
}
