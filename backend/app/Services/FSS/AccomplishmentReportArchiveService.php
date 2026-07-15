<?php

namespace App\Services\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\DietListCount;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\Generators\AccomplishmentReportGenerator;
use App\Services\Reports\ReportArchiveStorage;
use App\Services\Reports\ReportAuditReference;
use App\Services\Reports\ReportService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccomplishmentReportArchiveService
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly AuditLogger $auditLogger,
        private readonly ReportArchiveStorage $archiveStorage,
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

        $path = null;
        $reportPublicId = (string) Str::uuid();
        $archiveIdentity = 'accomplishment:'.$user->uuid.':'.$start;
        try {
            $report = DB::transaction(function () use ($user, $params, $start, $end, $reportPublicId, $archiveIdentity, &$path): Report {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = Report::query()
                    ->where('user_id', $user->id)
                    ->where('type', 'accomplishment_report')
                    ->where(function ($query) use ($archiveIdentity, $start, $end): void {
                        $query->where('archive_identity', $archiveIdentity)
                            ->orWhere(function ($legacy) use ($start, $end): void {
                                $legacy->whereNull('archive_identity')
                                    ->where('parameters->start', $start)
                                    ->where('parameters->end', $end);
                            });
                    })->first();
                if ($existing !== null) {
                    return $existing;
                }
                $report = new Report([
                    'user_id' => $user->id,
                    'title' => $user->display_name.' — Accomplishment Report '.Carbon::parse($start)->format('M j').'–'.Carbon::parse($end)->format('j, Y'),
                    'type' => 'accomplishment_report',
                    'archive_identity' => $archiveIdentity,
                    'parameters' => $params,
                    'status' => 'pending',
                ]);
                $report->forceFill(['uuid' => $reportPublicId])->save();

                $snapshot = [
                    'accomplishment' => $this->snapshotData($report),
                    'params' => $params,
                    'archived_at' => now()->toIso8601String(),
                ];
                $path = $this->reports->generate($report);

                $report->update([
                    'file_path' => $path,
                    'generated_at' => now(),
                    'status' => 'archived',
                    'snapshot' => $snapshot,
                ]);
                $this->auditLogger->record(
                    AuditAction::Generated,
                    AuditCategory::Operations,
                    AuditDomain::Reports,
                    subject: $report,
                    details: [
                        ...$this->auditReference->details($report->type, $params, $report, 200),
                        'generation_status' => 'completed',
                    ],
                    systemActor: 'accomplishment_report_archive',
                    includeRequestMetadata: false,
                );

                return $report;
            });
        } catch (\Throwable $exception) {
            try {
                $this->archiveStorage->cleanupGenerated($path);
            } catch (\Throwable $cleanupException) {
                report($cleanupException);
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
                    systemActor: 'accomplishment_report_archive',
                    includeRequestMetadata: false,
                );
            } catch (\Throwable $auditException) {
                report($auditException);
            }
            throw $exception;
        }

        return $report->fresh();
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

    private function snapshotData(Report $report): array
    {
        $data = app(AccomplishmentReportGenerator::class)->data($report);

        return [
            'from' => $data['from']->toDateString(),
            'to' => $data['to']->toDateString(),
            'period_label' => $data['period_label'],
            'days' => $data['days'],
            'tasks' => $data['tasks'],
            'numeric_task' => $data['numeric_task'],
            'daily_population' => $data['daily_population'],
            'staff_sheets' => collect($data['staff_sheets'])->map(fn (array $sheet) => [
                'user' => [
                    'id' => $sheet['user']?->id,
                    'name' => $sheet['user']?->display_name,
                    'role' => $sheet['user']?->role,
                ],
                'task_rows' => $sheet['task_rows'],
            ])->values()->all(),
        ];
    }
}
