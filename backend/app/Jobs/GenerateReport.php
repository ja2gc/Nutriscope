<?php

namespace App\Jobs;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Report $report)
    {
    }

    public function handle(): void
    {
        $this->report->update([
            'status'       => 'completed',
            'generated_at' => now(),
            'expires_at'   => now()->addDays(7),
            'file_path'    => 'reports/' . $this->report->id . '.pdf',
        ]);
    }
}
