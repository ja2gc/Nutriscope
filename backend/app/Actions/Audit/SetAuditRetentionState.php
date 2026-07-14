<?php

namespace App\Actions\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Models\AuditSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class SetAuditRetentionState
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(bool $enabled, User $actor): AuditSetting
    {
        return DB::transaction(function () use ($enabled, $actor): AuditSetting {
            $setting = AuditSetting::query()
                ->where('key', AuditSetting::RETENTION_ENABLED)
                ->lockForUpdate()
                ->first();
            $old = $setting?->enabled ?? (bool) config('audit.features.retention', false);

            if ($setting === null) {
                $setting = AuditSetting::query()->create([
                    'key' => AuditSetting::RETENTION_ENABLED,
                    'enabled' => $enabled,
                ]);
            } elseif ($old !== $enabled) {
                $setting->update(['enabled' => $enabled]);
            }

            if ($old !== $enabled) {
                $this->auditLogger->record(
                    AuditAction::SettingsChanged,
                    AuditCategory::Operations,
                    AuditDomain::System,
                    details: [
                        'changed_fields' => ['retention_enabled'],
                        'old' => ['retention_enabled' => $old],
                        'attributes' => ['retention_enabled' => $enabled],
                    ],
                    actor: $actor,
                );
            }

            return $setting;
        }, 3);
    }
}
