<?php

namespace App\Services\FSS;

use App\Models\DietListCount;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\Generators\AccomplishmentReportGenerator;
use App\Services\Reports\ReportService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AccomplishmentReportArchiveService
{
    public function archiveCompletedWeek(User $user, string $serviceDate): ?Report
    {
        if ($user->role !== 'FSS') {
            return null;
        }

        $start = Carbon::parse($serviceDate)->startOfWeek(Carbon::MONDAY)->toDateString();
        $end = Carbon::parse($serviceDate)->endOfWeek(Carbon::SUNDAY)->toDateString();

        if (! $this->hasEntryForEveryDay($user, $start, $end)) {
            return null;
        }

        $existing = Report::query()
            ->where('user_id', $user->id)
            ->where('type', 'accomplishment_report')
            ->where('parameters->start', $start)
            ->where('parameters->end', $end)
            ->first();

        if ($existing) {
            return $existing;
        }

        $params = [
            'start' => $start,
            'end' => $end,
            'from' => $start,
            'to' => $end,
            'fss_user_id' => $user->id,
            'prepared_by_name' => $user->name,
            'auto_generated' => true,
        ];

        $report = Report::create([
            'user_id' => $user->id,
            'title' => $user->name . ' — Accomplishment Report ' . Carbon::parse($start)->format('M j') . '–' . Carbon::parse($end)->format('j, Y'),
            'type' => 'accomplishment_report',
            'parameters' => $params,
            'status' => 'archived',
        ]);

        $report->snapshot = [
            'accomplishment' => $this->snapshotData($report),
            'params' => $params,
            'archived_at' => now()->toIso8601String(),
        ];
        $report->save();

        $path = app(ReportService::class)->generate($report);

        $report->update([
            'file_path' => $path,
            'generated_at' => now(),
            'status' => 'archived',
        ]);

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
                    'name' => $sheet['user']?->name,
                    'role' => $sheet['user']?->role,
                ],
                'task_rows' => $sheet['task_rows'],
            ])->values()->all(),
        ];
    }
}
