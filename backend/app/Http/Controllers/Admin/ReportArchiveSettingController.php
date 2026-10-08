<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportArchiveSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportArchiveSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['enabled' => ReportArchiveSetting::enabled(), 'years' => 5]);
    }

    public function update(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        $auditLogger->assertAvailable();
        DB::transaction(function () use ($enabled, $request, $auditLogger): void {
            $setting = ReportArchiveSetting::query()->where('key', ReportArchiveSetting::RETENTION)->lockForUpdate()->first();
            $old = $setting?->enabled ?? false;
            if ($setting === null) {
                ReportArchiveSetting::query()->create(['key' => ReportArchiveSetting::RETENTION, 'enabled' => $enabled]);
            } else {
                $setting->update(['enabled' => $enabled]);
            }
            if ($enabled) {
                Report::query()->where('status', 'archived')->whereNull('archived_at')
                    ->orderBy('id')->chunkById(100, static function ($reports): void {
                        foreach ($reports as $report) {
                            $report->update(['archived_at' => $report->updated_at ?? $report->created_at ?? now()]);
                        }
                    });
                Report::query()->where('status', 'archived')->whereNotNull('archived_at')
                    ->whereNull('retention_expires_at')->orderBy('id')->chunkById(100, static function ($reports): void {
                        foreach ($reports as $report) {
                            $report->update(['retention_expires_at' => $report->archived_at->copy()->addYears(5)]);
                        }
                    });
            }
            if ($old !== $enabled) {
                $auditLogger->record(
                    AuditAction::SettingsChanged,
                    AuditCategory::Operations,
                    AuditDomain::Reports,
                    details: ['changed_fields' => ['report_archive_retention_enabled'], 'old' => ['enabled' => $old], 'attributes' => ['enabled' => $enabled]],
                    actor: $request->user(),
                );
            }
        });

        return $this->show();
    }
}
