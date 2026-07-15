<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Models\AuditSetting;
use App\Models\DietListCount;
use App\Models\FoodServiceSetting;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleTemplate;
use App\Services\Reports\ReportService;
use Illuminate\Database\Eloquent\Builder;

class AuditContextualFilters
{
    /** @return array<string, list<array{value: string, label: string}>> */
    public function options(): array
    {
        return [
            AuditModule::SecurityAdministration->value => [
                ['value' => 'authentication', 'label' => 'Authentication'],
                ['value' => 'accounts', 'label' => 'Accounts'],
                ['value' => 'audit_oversight', 'label' => 'Audit Oversight'],
                ['value' => 'settings', 'label' => 'Settings'],
            ],
            AuditModule::NutritionCare->value => [
                ['value' => 'food_library', 'label' => 'Food Library'],
                ['value' => 'patients_ncp', 'label' => 'Patients/NCP'],
            ],
            AuditModule::FoodServiceOperations->value => [
                ['value' => 'catalog', 'label' => 'Catalog'],
                ['value' => 'menus', 'label' => 'Menus'],
                ['value' => 'procurement', 'label' => 'Procurement'],
                ['value' => 'budget', 'label' => 'Budget'],
            ],
            AuditModule::Reports->value => collect(ReportService::types())
                ->map(fn (string $type): array => ['value' => $type, 'label' => $this->reportLabel($type)])
                ->all(),
        ];
    }

    /** @return list<string> */
    public function valuesFor(?string $module): array
    {
        return collect($this->options()[$module] ?? [])->pluck('value')->all();
    }

    public function apply(Builder $query, AuditModule $module, string $subfilter): void
    {
        match ($module) {
            AuditModule::SecurityAdministration => $this->security($query, $subfilter),
            AuditModule::NutritionCare => $this->nutritionCare($query, $subfilter),
            AuditModule::FoodServiceOperations => $this->foodService($query, $subfilter),
            AuditModule::Reports => $query->where('properties->details->report_type', $subfilter),
        };
    }

    private function security(Builder $query, string $subfilter): void
    {
        $authentication = [
            AuditAction::LoginSucceeded->value,
            AuditAction::LoginFailed->value,
            AuditAction::AuthenticationFailed->value,
            AuditAction::Logout->value,
            AuditAction::PasswordChanged->value,
            AuditAction::PasswordReset->value,
            AuditAction::RecoveryEmailChanged->value,
            AuditAction::RecoveryEmailVerified->value,
            AuditAction::RateLimitExceeded->value,
            AuditAction::AuthorizationDenied->value,
        ];
        $oversight = function (Builder $scope): void {
            $scope->where('event', AuditAction::AuditLogViewed->value)
                ->orWhere('subject_type', (new AuditSetting)->getMorphClass())
                ->orWhere('properties->actor->name', 'Audit retention');
        };

        match ($subfilter) {
            'authentication' => $query->whereIn('event', $authentication),
            'accounts' => $query->where('domain', AuditDomain::Accounts->value)
                ->where(fn (Builder $scope): Builder => $scope
                    ->whereNull('event')->orWhereNotIn('event', $authentication)),
            'audit_oversight' => $query->where($oversight),
            'settings' => $query->where('domain', AuditDomain::System->value)
                ->where(fn (Builder $scope): Builder => $scope
                    ->whereNull('event')->orWhere('event', '!=', AuditAction::AuditLogViewed->value))
                ->where(fn (Builder $scope): Builder => $scope
                    ->whereNull('subject_type')->orWhere('subject_type', '!=', (new AuditSetting)->getMorphClass()))
                ->where(fn (Builder $scope): Builder => $scope
                    ->whereNull('properties->actor->name')->orWhere('properties->actor->name', '!=', 'Audit retention')),
            default => null,
        };
    }

    private function nutritionCare(Builder $query, string $subfilter): void
    {
        match ($subfilter) {
            'food_library' => $query->where('domain', AuditDomain::NutritionLibrary->value),
            'patients_ncp' => $query->whereIn('domain', [AuditDomain::Patients->value, AuditDomain::Ncp->value]),
            default => null,
        };
    }

    private function foodService(Builder $query, string $subfilter): void
    {
        $menuTypes = $this->morphTypes([
            MenuCycle::class,
            MenuCycleTemplate::class,
            MealPrepLog::class,
            DietListCount::class,
        ]);
        $budgetTypes = $this->morphTypes([FoodServiceSetting::class]);

        match ($subfilter) {
            'catalog' => $query->where('domain', AuditDomain::FoodService->value)
                ->where(fn (Builder $scope): Builder => $scope
                    ->whereNull('subject_type')->orWhereNotIn('subject_type', [...$menuTypes, ...$budgetTypes])),
            'menus' => $query->whereIn('subject_type', $menuTypes),
            'procurement' => $query->where('domain', AuditDomain::Procurement->value),
            'budget' => $query->where(function (Builder $scope) use ($budgetTypes): void {
                $scope->where('domain', AuditDomain::Budget->value)
                    ->orWhereIn('subject_type', $budgetTypes);
            }),
            default => null,
        };
    }

    /** @param list<class-string> $models
     * @return list<string>
     */
    private function morphTypes(array $models): array
    {
        return array_map(fn (string $model): string => (new $model)->getMorphClass(), $models);
    }

    private function reportLabel(string $type): string
    {
        return match ($type) {
            'demographic_census' => 'Demographic Census',
            'program_project_activity' => 'Program Project Activity',
            'menu_calendar' => 'Menu Calendar',
            'procurement_pack' => 'Procurement Pack',
            'accomplishment_report' => 'Accomplishment Report',
            'patient_menu_plan' => 'Patient Menu Plan',
            'ncp_summary' => 'NCP Summary',
            default => str($type)->replace('_', ' ')->title()->toString(),
        };
    }
}
