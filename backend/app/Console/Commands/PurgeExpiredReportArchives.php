<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Models\ReportArchiveSetting;
use App\Services\Reports\ReportSourceKey;
use App\Services\StoredObjectStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeExpiredReportArchives extends Command
{
    protected $signature = 'reports:purge-expired-archives';

    protected $description = 'Purge operational report archives after the enabled five-year retention period';

    public function handle(StoredObjectStorage $storedObjects): int
    {
        if (! ReportArchiveSetting::enabled()) {
            return self::SUCCESS;
        }

        $failed = false;
        Report::query()->where('status', 'archived')->whereNotNull('retention_expires_at')
            ->where('retention_expires_at', '<=', now())->orderBy('id')
            ->eachById(function (Report $report) use ($storedObjects, &$failed): void {
                $key = ReportSourceKey::for($report->type, $report->parameters ?? []);
                $file = $report->officialFile;
                if ($key === null || ($file !== null && Report::query()
                    ->where('official_file_stored_object_id', $file->id)->where('id', '!=', $report->id)->exists())) {
                    $failed = true;

                    return;
                }

                try {
                    DB::transaction(function () use ($report, $key): void {
                        DB::table('report_retired_sources')->updateOrInsert(
                            ['source_key' => $key],
                            ['type' => $report->type, 'retired_at' => now()],
                        );
                        $report->delete();
                    });
                    if ($file !== null) {
                        $storedObjects->deleteOrQueue($file);
                    }
                    if ($report->cache_path && ! Report::query()->where('cache_path', $report->cache_path)->exists()) {
                        Storage::disk('report_cache')->delete($report->cache_path);
                    }
                } catch (Throwable $exception) {
                    $failed = true;
                    Log::error('Report archive retention purge failed.', ['exception_class' => $exception::class]);
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
