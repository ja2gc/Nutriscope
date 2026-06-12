<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\Reports\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Report $report)
    {
    }

    public function handle(ReportService $reports): void
    {
        $this->report->update(['status' => 'generating']);

        try {
            $path = $reports->generate($this->report);

            $this->report->update([
                'status'       => 'completed',
                'file_path'    => $path,
                'generated_at' => now(),
                'expires_at'   => now()->addDays(7),
            ]);
        } catch (\Throwable $e) {
            Log::error('Report generation failed', [
                'report_id' => $this->report->id,
                'type'      => $this->report->type,
                'error'     => $e->getMessage(),
            ]);
            $this->report->update(['status' => 'failed']);
            throw $e;
        }
    }
}
