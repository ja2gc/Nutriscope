<?php

namespace App\Actions\Audit;

use App\Models\AuditSetting;
use Illuminate\Support\Facades\DB;

class SetAuditRetentionState
{
    public function execute(bool $enabled): AuditSetting
    {
        return DB::transaction(function () use ($enabled): AuditSetting {
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

            return $setting;
        }, 3);
    }
}
