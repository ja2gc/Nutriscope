<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\AuditActivity;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AuditFilterMetadata
{
    public function __construct(private readonly AuditRetentionState $retentionState) {}

    /** @return array{filters: array<string, mixed>, capabilities: array<string, bool>} */
    public function for(User $user): array
    {
        return [
            'filters' => [
                'categories' => $this->options(AuditCategory::cases()),
                'domains' => $this->options(AuditDomain::cases()),
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
                'temporary_ip_block' => false,
            ],
            'retention' => $this->retentionState->current(),
        ];
    }

    /**
     * @param  array<int, AuditAction|AuditCategory|AuditDomain|AuditOutcome|AuditSeverity>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(
            fn (object $case): array => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );
    }
}
