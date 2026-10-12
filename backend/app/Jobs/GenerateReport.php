<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\Reports\ReportArchiveStorage;
use App\Services\Reports\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public Report $report) {}

    public function handle(ReportService $reports, ReportArchiveStorage $archiveStorage): void
    {
        $shouldGenerate = DB::transaction(function (): bool {
            $report = Report::query()->lockForUpdate()->findOrFail($this->report->getKey());
            if (in_array($report->status, ['generating', 'completed', 'archived', 'failed'], true)) {
                return false;
            }

            $report->update(['status' => 'generating']);
            $this->report = $report;

            return true;
        });
        if (! $shouldGenerate) {
            return;
        }

        $path = null;
        try {
            $path = $reports->generate($this->report);

            DB::transaction(function () use ($path): void {
                $this->report = Report::query()->lockForUpdate()->findOrFail($this->report->getKey());
                if ($this->report->status !== 'generating') {
                    return;
                }
                $this->report->update([
                    'status' => 'completed',
                    'file_path' => $path,
                    'generated_at' => now(),
                    'expires_at' => now()->addDays(7),
                ]);
            });
        } catch (\Throwable $e) {
            try {
                $archiveStorage->cleanupGenerated($path);
            } catch (\Throwable $cleanup) {
                report($cleanup);
            }
            Log::error('Report generation failed', [
                'report_public_id' => $this->report->uuid,
                'type' => $this->report->type,
                'exception' => $e::class,
            ]);
            DB::transaction(function (): void {
                $this->report = Report::query()->lockForUpdate()->findOrFail($this->report->getKey());
                if ($this->report->status === 'failed') {
                    return;
                }
                $this->report->update(['status' => 'failed']);
            });
            throw $e;
        }
    }
}
