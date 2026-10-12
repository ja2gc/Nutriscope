<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportArchiveSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportArchiveSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['enabled' => ReportArchiveSetting::enabled(), 'years' => 5]);
    }

    public function update(Request $request): JsonResponse
    {
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        DB::transaction(function () use ($enabled): void {
            $setting = ReportArchiveSetting::query()->where('key', ReportArchiveSetting::RETENTION)->lockForUpdate()->first();
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
        });

        return $this->show();
    }
}
