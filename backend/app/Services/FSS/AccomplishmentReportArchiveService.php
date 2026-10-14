<?php

namespace App\Services\FSS;

use App\Actions\Reports\PrepareSavedReport;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class AccomplishmentReportArchiveService
{
    public function __construct(
        private readonly PrepareSavedReport $prepare,
    ) {}

    /** @return array{0: string, 1: string} */
    public static function periodBounds(string $serviceDate): array
    {
        $date = Carbon::parse($serviceDate);
        $start = $date->copy()->startOfMonth()->addDays($date->day > 15 ? 15 : 0)->toDateString();
        $end = $date->day <= 15 ? $date->copy()->startOfMonth()->day(15)->toDateString() : $date->copy()->endOfMonth()->toDateString();

        return [$start, $end];
    }

    public function preparePeriod(User $user, string $serviceDate, bool $freeze = false): ?Report
    {
        if ($user->role !== 'FSS') {
            return null;
        }
        [$start, $end] = self::periodBounds($serviceDate);
        $params = [
            'start' => $start,
            'end' => $end,
            'from' => $start,
            'to' => $end,
            'fss_user_id' => $user->id,
            'prepared_by_name' => $user->display_name,
            'auto_generated' => true,
            'review_workflow' => 1,
        ];
        $report = null;
        $existing = Report::query()
            ->where('user_id', $user->id)
            ->where('type', 'accomplishment_report')
            ->where('parameters->start', $start)
            ->where('parameters->end', $end)
            ->whereNotNull('review_state')
            ->first();
        if (in_array($existing?->review_state, ['pending', 'approved'], true)) {
            return $existing;
        }
        try {
            // Current-period auto copies stay refreshable as each daily entry is saved.
            // Explicitly prepared/filed reports use the action's immutable default.
            $report = $this->prepare->execute($user, 'accomplishment_report', $params, freeze: $freeze);

            return $report->fresh();
        } catch (\Throwable $exception) {
            if ($existing === null && $report !== null) {
                if ($report->cache_path) {
                    Storage::disk('report_cache')->delete($report->cache_path);
                }
                $report->delete();
            }
            throw $exception;
        }
    }

    public function archiveCompletedWeek(User $user, string $serviceDate): ?Report
    {
        return $this->preparePeriod($user, $serviceDate);
    }
}
