<?php

namespace App\Services\FSS;

use App\Actions\Reports\PrepareSavedReport;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ReportAuditReference;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccomplishmentReportArchiveService
{
    public function __construct(
        private readonly PrepareSavedReport $prepare,
        private readonly AuditLogger $auditLogger,
        private readonly ReportAuditReference $auditReference,
    ) {}

    public function preparePeriod(User $user, string $serviceDate): ?Report
    {
        if ($user->role !== 'FSS') {
            return null;
        }
        $this->auditLogger->assertAvailable();
        $date = Carbon::parse($serviceDate);
        $start = $date->copy()->startOfMonth()->addDays($date->day > 15 ? 15 : 0)->toDateString();
        $end = $date->day <= 15 ? $date->copy()->startOfMonth()->day(15)->toDateString() : $date->copy()->endOfMonth()->toDateString();
        $params = [
            'start' => $start,
            'end' => $end,
            'from' => $start,
            'to' => $end,
            'fss_user_id' => $user->id,
            'prepared_by_name' => $user->display_name,
            'auto_generated' => true,
        ];
        $reportPublicId = (string) Str::uuid();
        $report = null;
        $existing = Report::query()
            ->where('user_id', $user->id)
            ->where('type', 'accomplishment_report')
            ->where('parameters->start', $start)
            ->where('parameters->end', $end)
            ->first();
        try {
            $report = $this->prepare->execute($user, 'accomplishment_report', $params);
            $this->auditLogger->record(
                AuditAction::Generated,
                AuditCategory::Operations,
                AuditDomain::Reports,
                subject: $report,
                details: [
                    ...$this->auditReference->details($report->type, $params, $report, 200),
                    'generation_status' => 'completed',
                ],
                systemActor: 'accomplishment_report_preparation',
                includeRequestMetadata: false,
            );

            return $report->fresh();
        } catch (\Throwable $exception) {
            if ($existing === null && $report !== null) {
                if ($report->cache_path) {
                    Storage::disk('report_cache')->delete($report->cache_path);
                }
                $report->delete();
            }
            try {
                $this->auditLogger->record(
                    AuditAction::Generated,
                    AuditCategory::Operations,
                    AuditDomain::Reports,
                    outcome: AuditOutcome::Failure,
                    severity: AuditSeverity::Warning,
                    details: [
                        ...$this->auditReference->details('accomplishment_report', $params, null, 500, $reportPublicId),
                        'generation_status' => 'failed',
                    ],
                    systemActor: 'accomplishment_report_preparation',
                    includeRequestMetadata: false,
                );
            } catch (\Throwable $auditException) {
                report($auditException);
            }
            throw $exception;
        }
    }

    public function archiveCompletedWeek(User $user, string $serviceDate): ?Report
    {
        return $this->preparePeriod($user, $serviceDate);
    }
}
