<?php

namespace App\Services\FSS;

use App\Actions\Reports\PrepareSavedReport;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\DietListCount;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ReportAuditReference;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccomplishmentReportArchiveService
{
    public function __construct(
        private readonly PrepareSavedReport $prepare,
        private readonly AuditLogger $auditLogger,
        private readonly ReportAuditReference $auditReference,
    ) {}

    public function archiveCompletedWeek(User $user, string $serviceDate): ?Report
    {
        if ($user->role !== 'FSS') {
            return null;
        }
        $this->auditLogger->assertAvailable();
        $start = Carbon::parse($serviceDate)->startOfWeek(Carbon::MONDAY)->toDateString();
        $end = Carbon::parse($serviceDate)->endOfWeek(Carbon::SUNDAY)->toDateString();
        if (! $this->hasEntryForEveryDay($user, $start, $end)) {
            return null;
        }
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

    private function hasEntryForEveryDay(User $user, string $start, string $end): bool
    {
        $dates = DietListCount::query()
            ->where('fss_user_id', $user->id)
            ->whereBetween('service_date', [$start, $end])
            ->pluck('service_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();
        $expected = collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $date) => $date->toDateString())
            ->values();

        return $expected->diff($dates)->isEmpty();
    }
}
