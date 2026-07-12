<?php

namespace App\Console\Commands;

use App\Jobs\ProcessReportFileOperation;
use App\Models\ReportFileOperation;
use App\Services\Reports\BrandingAssetStorage;
use App\Services\Reports\ReportArchiveStorage;
use Illuminate\Console\Command;

class ProcessReportFileOperations extends Command
{
    protected $signature = 'reports:process-file-operations';

    protected $description = 'Retry pending path-safe report archive file operations';

    public function handle(ReportArchiveStorage $reports, BrandingAssetStorage $branding): int
    {
        ReportFileOperation::query()
            ->whereIn('phase', [ReportFileOperation::PHASE_FINALIZED, ReportFileOperation::PHASE_ACQUISITION])
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->pluck('id')
            ->each(function (int $id) use ($reports, $branding): void {
                try {
                    (new ProcessReportFileOperation($id))->handle($reports, $branding);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

        return self::SUCCESS;
    }
}
