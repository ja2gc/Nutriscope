<?php

namespace App\Jobs;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\Report;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ReportArchiveStorage;
use App\Services\Reports\ReportAuditReference;
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

    public function handle(ReportService $reports, AuditLogger $auditLogger, ReportArchiveStorage $archiveStorage, ReportAuditReference $auditReference): void
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

            DB::transaction(function () use ($auditLogger, $auditReference, $path): void {
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
                $this->recordLifecycle($auditLogger, $auditReference, AuditOutcome::Success);
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
            DB::transaction(function () use ($auditLogger, $auditReference): void {
                $this->report = Report::query()->lockForUpdate()->findOrFail($this->report->getKey());
                if ($this->report->status === 'failed') {
                    return;
                }
                $this->report->update(['status' => 'failed']);
                $this->recordLifecycle($auditLogger, $auditReference, AuditOutcome::Failure);
            });
            throw $e;
        }
    }

    private function recordLifecycle(AuditLogger $auditLogger, ReportAuditReference $auditReference, AuditOutcome $outcome): void
    {
        $auditLogger->record(
            AuditAction::Generated,
            AuditCategory::Operations,
            AuditDomain::Reports,
            subject: $this->report,
            outcome: $outcome,
            severity: $outcome === AuditOutcome::Failure ? AuditSeverity::Warning : AuditSeverity::Info,
            details: [
                ...$auditReference->details(
                    $this->report->type,
                    $this->report->parameters ?? [],
                    $this->report,
                    $outcome === AuditOutcome::Success ? 200 : 500,
                ),
                'generation_status' => $outcome === AuditOutcome::Success ? 'completed' : 'failed',
            ],
            systemActor: 'report_generation',
            includeRequestMetadata: false,
        );
    }
}
