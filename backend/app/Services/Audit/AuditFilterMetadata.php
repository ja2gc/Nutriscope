<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\AuditActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class AuditFilterMetadata
{
    public function __construct(private readonly AuditRetentionState $retentionState) {}

    /** @return array{filters: array<string, mixed>, capabilities: array<string, bool>|object, retention: array<string, mixed>} */
    public function for(User $user): array
    {
        return [
            'filters' => [
                'modules' => $this->options([
                    AuditModule::SecurityAdministration,
                    AuditModule::NutritionCare,
                    AuditModule::FoodServiceOperations,
                ]),
                'module_counts' => $this->moduleCounts(),
                'actions' => $this->options([
                    AuditAction::Created,
                    AuditAction::Updated,
                    AuditAction::Deleted,
                    AuditAction::Viewed,
                    AuditAction::Downloaded,
                    AuditAction::Archived,
                    AuditAction::Failed,
                    AuditAction::Suspended,
                    AuditAction::Unsuspended,
                    AuditAction::Discontinued,
                ]),
                'report_contexts' => [
                    'nutrition_care' => $this->reportContextOptions([
                        'demographic_census' => 'Demographic Census',
                        'ncp_summary' => 'NCP Summary',
                        'adime_individual' => 'Nutrition Intervention Plan',
                        'adime_aggregate' => 'ADIME Aggregate',
                        'ncp_census' => 'NCP Census',
                        'patient_menu_plan' => 'Patient Menu Plan',
                    ]),
                    'food_service_operations' => $this->reportContextOptions([
                        'accomplishment_report' => 'Accomplishment Report',
                        'program_project_activity' => 'PPA',
                        'procurement_pack' => 'Procurement Pack',
                        'menu_calendar' => 'Menu Calendar',
                    ]),
                ],
            ],
            'capabilities' => (bool) config('audit.features.export')
                && Gate::forUser($user)->allows('export', AuditActivity::class)
                    ? ['export' => true]
                    : (object) [],
            'retention' => $this->retentionState->current(),
        ];
    }

    /**
     * @param  array<int, AuditAction|AuditModule>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(
            fn (object $case): array => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );
    }

    /** @param array<string, string> $types
     * @return array<int, array{value: string, label: string}>
     */
    private function reportContextOptions(array $types): array
    {
        return array_map(
            fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys($types),
            array_values($types),
        );
    }

    /** @return array{all: int, security_administration: int, nutrition_care: int, food_service_operations: int} */
    private function moduleCounts(): array
    {
        $row = AuditActivity::query()
            ->auditOnly()
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('causer_type')->orWhere('causer_type', (new User)->getMorphClass()))
            ->selectRaw("COUNT(*) AS all_count,
                SUM(module = 'security_administration') AS security_administration_count,
                SUM(module = 'nutrition_care') AS nutrition_care_count,
                SUM(module = 'food_service_operations') AS food_service_operations_count")
            ->first();

        return [
            'all' => (int) ($row?->all_count ?? 0),
            'security_administration' => (int) ($row?->security_administration_count ?? 0),
            'nutrition_care' => (int) ($row?->nutrition_care_count ?? 0),
            'food_service_operations' => (int) ($row?->food_service_operations_count ?? 0),
        ];
    }
}
