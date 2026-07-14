<?php

namespace App\Services\Audit;

use App\Models\AuditSetting;

class AuditRetentionState
{
    /** @return array{enabled: bool, source: 'config'|'database', periods: array<string, int>} */
    public function current(): array
    {
        $setting = AuditSetting::query()
            ->where('key', AuditSetting::RETENTION_ENABLED)
            ->first(['enabled']);

        return [
            'enabled' => $setting?->enabled ?? (bool) config('audit.features.retention', false),
            'source' => $setting === null ? 'config' : 'database',
            'periods' => $this->periods(),
        ];
    }

    public function enabled(): bool
    {
        return $this->current()['enabled'];
    }

    /** @return array<string, int> */
    private function periods(): array
    {
        return collect(config('audit.retention', []))
            ->mapWithKeys(fn (array $policy, string $category): array => [
                $category => (int) $policy['days'],
            ])
            ->all();
    }
}
