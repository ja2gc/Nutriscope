<?php

namespace App\Http\Requests\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
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
            'category' => ['nullable', Rule::enum(AuditCategory::class)],
            'domain' => ['nullable', Rule::enum(AuditDomain::class)],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'event' => ['nullable', Rule::enum(AuditAction::class)],
            'severity' => ['nullable', Rule::enum(AuditSeverity::class)],
            'outcome' => ['nullable', Rule::enum(AuditOutcome::class)],
            'actor_id' => ['nullable', 'uuid', 'exists:users,uuid'],
            'causer_id' => ['nullable', 'uuid', 'exists:users,uuid'],
            'subject_id' => ['nullable', 'uuid'],
            'context_id' => ['nullable', 'uuid'],
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $validated = $this->validated();
        $validated['action'] ??= $validated['event'] ?? null;
        $validated['actor_id'] ??= $validated['causer_id'] ?? null;

        unset($validated['event'], $validated['causer_id'], $validated['page'], $validated['per_page']);

        return array_filter($validated, fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
