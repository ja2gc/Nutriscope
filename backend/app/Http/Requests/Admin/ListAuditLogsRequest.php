<?php

namespace App\Http\Requests\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\AuditActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', AuditActivity::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'module' => ['nullable', Rule::in([
                AuditModule::SecurityAdministration->value,
                AuditModule::NutritionCare->value,
                AuditModule::FoodServiceOperations->value,
            ])],
            'subfilter' => ['prohibited'],
            'category' => ['prohibited'],
            'domain' => ['prohibited'],
            'event' => ['prohibited'],
            'causer_id' => ['prohibited'],
            'subject_id' => ['prohibited'],
            'context_id' => ['prohibited'],
            'context' => ['nullable', Rule::in([
                'accomplishment_report', 'program_project_activity', 'procurement_pack', 'menu_calendar',
                'demographic_census', 'ncp_summary',
            ])],
            'action' => ['nullable', Rule::in($this->visibleActions())],
            'severity' => ['prohibited'],
            'outcome' => ['prohibited'],
            'actor_id' => ['nullable', 'uuid', 'exists:users,uuid'],
            'month' => ['nullable', 'date_format:Y-m'],
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $validated = $this->validated();
        unset($validated['page'], $validated['per_page']);

        return array_filter($validated, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @return list<string> */
    private function visibleActions(): array
    {
        return [
            AuditAction::Created->value,
            AuditAction::Updated->value,
            AuditAction::Deleted->value,
            AuditAction::Viewed->value,
            AuditAction::Downloaded->value,
            AuditAction::Archived->value,
            AuditAction::Failed->value,
            AuditAction::Suspended->value,
            AuditAction::Unsuspended->value,
            AuditAction::Discontinued->value,
        ];
    }
}
