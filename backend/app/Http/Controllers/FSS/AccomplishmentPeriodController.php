<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Models\DietListCount;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\FSS\AccomplishmentReportArchiveService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccomplishmentPeriodController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $user = $request->user();
        $dates = DietListCount::query()->where('fss_user_id', $user->id)
            ->pluck('service_date');
        $starts = $dates->map(fn ($date) => AccomplishmentReportArchiveService::periodBounds($date->toDateString())[0]);
        $todayStart = AccomplishmentReportArchiveService::periodBounds(CarbonImmutable::now('Asia/Manila')->toDateString())[0];
        Report::query()->where('type', 'accomplishment_report')->where('user_id', $user->id)
            ->whereNotNull('review_state')
            ->pluck('report_covered_from')
            ->each(fn ($date) => $starts->push(CarbonImmutable::parse($date)->toDateString()));

        $period = CarbonImmutable::parse($starts->min() ?? $todayStart);
        $last = CarbonImmutable::parse($todayStart);
        $starts->push($todayStart);
        while ($period <= $last) {
            $starts->push($period->toDateString());
            $period = $period->day === 1 ? $period->addDays(15) : $period->addMonthNoOverflow()->startOfMonth();
        }

        return response()->json(['data' => $starts->unique()->sortDesc()->values()
            ->when(isset($data['year']), fn ($periods) => $periods->filter(fn (string $start) => (int) substr($start, 0, 4) === (int) $data['year'])->values())
            ->map(fn (string $start) => $this->summary($user, $start))->all()]);
    }

    public function show(Request $request, string $start): JsonResponse
    {
        $this->validStart($start);

        return response()->json(['data' => $this->summary($request->user(), $start)]);
    }

    public function submit(Request $request, string $start, AccomplishmentReportArchiveService $archives): JsonResponse
    {
        $this->validStart($start);
        $user = $request->user();
        $summary = $this->audited(function () use ($user, $start, $archives): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $current = $this->summary($user, $start);
            abort_if($current['saved_days'] !== $current['expected_days'], 422, 'Save a daily log for every date before submitting.');
            abort_if(in_array($current['status'], ['pending', 'approved'], true), 409, 'This period is already submitted.');

            $report = $archives->preparePeriod($user, $start, freeze: true);
            abort_unless($report?->official_file_stored_object_id, 409, 'The report PDF could not be prepared.');
            $report->update([
                'review_state' => 'pending',
                'review_version' => $report->review_version + 1,
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
                'rejection_reason' => null,
            ]);
            $this->auditReviewChange($report, $current['status']);

            return $this->summary($user, $start);
        });

        return response()->json(['data' => $summary]);
    }

    public function withdraw(Request $request, string $start): JsonResponse
    {
        $this->validStart($start);
        $user = $request->user();
        $summary = $this->audited(function () use ($user, $start): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $report = $this->periodReport($user, $start, lock: true);
            abort_unless($report?->review_state === 'pending', 409, 'Only a pending submission can be withdrawn.');
            $report->update(['review_state' => 'draft', 'submitted_at' => null]);
            $this->auditReviewChange($report, 'pending');

            return $this->summary($user, $start);
        });

        return response()->json(['data' => $summary]);
    }

    public function staff(PaginatedRequest $request): JsonResponse
    {
        $users = User::withTrashed()->where('role', 'FSS')
            ->when($request->filled('search'), fn ($query) => $query->where(function ($match) use ($request): void {
                foreach (explode(' ', Str::squish($request->string('search')->toString())) as $part) {
                    $match->where(function ($names) use ($part): void {
                        $search = '%'.$part.'%';
                        $names->where('name', 'like', $search)->orWhere('first_name', 'like', $search)->orWhere('last_name', 'like', $search);
                    });
                }
            }))
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->paginate($request->perPage());

        return response()->json([
            'data' => $users->getCollection()->map(fn (User $user): array => ['id' => $user->uuid, 'display_name' => $user->display_name])->all(),
            'meta' => ['current_page' => $users->currentPage(), 'per_page' => $users->perPage(), 'total' => $users->total(), 'last_page' => $users->lastPage()],
        ]);
    }

    public function toReview(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'fss_user_id' => ['nullable', 'uuid'],
            'covered_month' => ['nullable', 'date_format:Y-m'],
        ]);
        $userId = isset($filters['fss_user_id']) ? User::withTrashed()->where('uuid', $filters['fss_user_id'])->value('id') ?? 0 : null;
        $monthStart = isset($filters['covered_month']) ? $filters['covered_month'].'-01' : null;
        $monthEnd = $monthStart ? CarbonImmutable::parse($monthStart)->endOfMonth()->toDateString() : null;
        $reports = Report::query()->with('user:id,uuid,name,first_name,last_name')
            ->where('type', 'accomplishment_report')->where('review_state', 'pending')
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when($monthStart, fn ($query) => $query->whereDate('report_covered_from', '<=', $monthEnd)
                ->whereDate('report_covered_until', '>=', $monthStart))
            ->orderBy('submitted_at')->get();

        return response()->json(['data' => $reports->map(fn (Report $report): array => $this->reviewData($report))->all()]);
    }

    public function approve(Request $request, Report $report): JsonResponse
    {
        $data = $request->validate(['submission_version' => ['required', 'integer', 'min:1']]);

        return $this->decide($request, $report, $data['submission_version'], 'approved');
    }

    public function reject(Request $request, Report $report): JsonResponse
    {
        $data = $request->validate([
            'submission_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->decide($request, $report, $data['submission_version'], 'rejected', trim($data['reason']));
    }

    private function decide(Request $request, Report $report, int $version, string $state, ?string $reason = null): JsonResponse
    {
        $updated = $this->audited(function () use ($request, $report, $version, $state, $reason): Report {
            User::query()->whereKey($report->user_id)->lockForUpdate()->firstOrFail();
            $locked = Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->type === 'accomplishment_report' && $locked->review_state === 'pending'
                && $locked->review_version === $version, 409, 'This submission has changed. Refresh the review.');
            if ($state === 'approved') {
                $file = $locked->officialFile;
                abort_unless($file !== null && Storage::disk($file->storage_disk)->exists($file->object_key), 409, 'The submitted PDF is unavailable.');
            }
            $locked->update([
                'review_state' => $state,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'rejection_reason' => $reason,
            ]);
            $this->auditReviewChange($locked, 'pending');

            return $locked->fresh('user:id,uuid,name,first_name,last_name');
        });

        return response()->json(['data' => $this->reviewData($updated)]);
    }

    private function validStart(string $start): void
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        abort_unless($date && $date->format('Y-m-d') === $start && in_array((int) $date->format('j'), [1, 16], true), 404);
    }

    private function auditReviewChange(Report $report, string $before): void
    {
        $this->auditLogger->recordMutation(
            AuditAction::Updated,
            AuditDomain::Reports,
            $report,
            ['review_state', 'review_version'],
            oldValues: ['review_state' => $before],
            newValues: ['review_state' => $report->review_state],
        );
    }

    private function periodReport(User $user, string $start, bool $lock = false): ?Report
    {
        return Report::query()->where('type', 'accomplishment_report')->where('user_id', $user->id)
            ->whereNotNull('review_state')->where('parameters->start', $start)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->orderByDesc('id')->first();
    }

    private function summary(User $user, string $start): array
    {
        [, $end] = AccomplishmentReportArchiveService::periodBounds($start);
        $logs = DietListCount::query()->where('fss_user_id', $user->id)
            ->whereBetween('service_date', [$start, $end])->get()->groupBy(fn (DietListCount $log) => $log->service_date->toDateString());
        $days = collect(CarbonPeriod::create($start, $end))->map(fn ($date): array => [
            'date' => $date->toDateString(),
            'logged' => $logs->has($date->toDateString()),
            'off_duty' => $logs->get($date->toDateString())?->contains(fn (DietListCount $log) => $log->off_duty) ?? false,
        ])->all();
        $report = $this->periodReport($user, $start);

        return [
            'start' => $start,
            'end' => $end,
            'expected_days' => count($days),
            'saved_days' => $logs->count(),
            'days' => $days,
            'status' => $report?->review_state ?? 'draft',
            'report_id' => $report?->uuid,
            'submission_version' => $report?->review_version ?? 0,
            'rejection_reason' => $report?->rejection_reason,
        ];
    }

    private function reviewData(Report $report): array
    {
        return [
            'report_id' => $report->uuid,
            'start' => $report->report_covered_from?->toDateString(),
            'end' => $report->report_covered_until?->toDateString(),
            'status' => $report->review_state,
            'submission_version' => $report->review_version,
            'submitted_at' => $report->submitted_at?->toIso8601String(),
            'rejection_reason' => $report->rejection_reason,
            'fss_user' => [
                'id' => $report->user?->uuid,
                'display_name' => $report->user?->display_name,
            ],
        ];
    }
}
