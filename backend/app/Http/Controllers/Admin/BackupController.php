<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\BackupRetentionTier;
use App\Enums\BackupSource;
use App\Enums\BackupState;
use App\Enums\RecoveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateBackupRequest;
use App\Http\Requests\Admin\DeleteBackupRequest;
use App\Http\Requests\Admin\KeepBackupRequest;
use App\Http\Requests\Admin\ListBackupsRequest;
use App\Http\Resources\BackupRunResource;
use App\Jobs\CreateDatabaseBackup;
use App\Models\BackupRun;
use App\Models\RecoveryRequest;
use App\Models\RecoveryTest;
use App\Services\Audit\AuditLogger;
use App\Services\Backup\BackupRetentionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BackupController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly BackupRetentionService $retention,
    ) {}

    public function index(ListBackupsRequest $request): AnonymousResourceCollection
    {
        $latestVerifiedId = BackupRun::verified()->latest('verified_at')->value('id');
        $states = $this->statesFor($request->section());
        $backups = $this->filterCategory(
            BackupRun::query()->whereIn('state', $states),
            $request->category(),
        )
            ->with(['manifest', 'latestRecoveryRequest', 'schedulePeriods'])
            ->withCount(['recoveryRequests as pending_recovery_requests_count' => fn (Builder $query) => $query->whereNotIn('state', ['completed', 'failed', 'rolled_back', 'cancelled'])])
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString()
            ->through(fn (BackupRun $backup) => $backup->setAttribute('is_latest_verified', $backup->id === $latestVerifiedId));

        return BackupRunResource::collection($backups)->additional([
            'summary' => $this->summary($latestVerifiedId, $states),
        ]);
    }

    public function store(CreateBackupRequest $request): JsonResponse
    {
        if (BackupRun::whereIn('state', [BackupState::Queued, BackupState::Running, BackupState::Verifying])->exists()) {
            return response()->json(['message' => 'A backup is already in progress.'], 409);
        }

        $backup = BackupRun::create([
            'state' => BackupState::Queued,
            'source' => BackupSource::Manual,
            'storage_disk' => config('nutriscope-backups.disk'),
            'requested_by' => $request->user()->id,
            'queued_at' => now(),
        ]);
        CreateDatabaseBackup::dispatch($backup->uuid);
        $this->audit($backup, AuditAction::Created, $request->user(), 'queued');

        return (new BackupRunResource($backup))->response()->setStatusCode(202);
    }

    public function destroy(DeleteBackupRequest $request, BackupRun $backupRun): JsonResponse
    {
        if (in_array($backupRun->state, [BackupState::Failed, BackupState::RecentlyDeleted], true)) {
            $outcome = $backupRun->state === BackupState::Failed ? 'failed_record_removed' : 'purged';
            $this->retention->purge($backupRun);
            $this->audit($backupRun, AuditAction::Deleted, $request->user(), $outcome);

            return (new BackupRunResource($backupRun))->response();
        }

        if ($backupRun->state !== BackupState::Completed || $backupRun->isProtectedFromDeletion()) {
            return response()->json(['message' => 'This backup is protected and cannot be deleted.'], 409);
        }

        $backupRun->transitionTo(BackupState::RecentlyDeleted);
        $backupRun->update([
            'deleted_at' => now(),
            'recoverable_until' => now()->addHours(config('nutriscope-backups.recoverable_hours')),
            'retention_tier' => null,
            'retention_expires_at' => null,
        ]);
        $this->audit($backupRun, AuditAction::Deleted, $request->user(), 'recently_deleted');

        return (new BackupRunResource($backupRun))->response();
    }

    public function keep(KeepBackupRequest $request, BackupRun $backupRun): JsonResponse
    {
        if ($backupRun->state !== BackupState::RecentlyDeleted || $backupRun->recoverable_until?->isPast() !== false) {
            return response()->json(['message' => 'This backup can no longer be recovered.'], 409);
        }

        $backupRun->transitionTo(BackupState::Completed);
        $backupRun->update([
            'deleted_at' => null,
            'recoverable_until' => null,
            'retention_expires_at' => match ($backupRun->source) {
                BackupSource::Manual => null,
                BackupSource::Safety => now()->addHours(48),
                BackupSource::Automatic => $backupRun->schedulePeriods()->max('expires_at'),
            },
        ]);
        $this->audit($backupRun, AuditAction::Updated, $request->user(), 'kept');

        return (new BackupRunResource($backupRun))->response();
    }

    /** @param array<int, BackupState> $states */
    private function summary(?int $latestVerifiedId, array $states): array
    {
        $latest = $latestVerifiedId === null ? null : BackupRun::find($latestVerifiedId);
        $latestFailure = BackupRun::where('state', BackupState::Failed)->latest('id')->first();
        $activeRecovery = RecoveryRequest::query()
            ->whereIn('state', [
                RecoveryStatus::Requested,
                RecoveryStatus::Preparing,
                RecoveryStatus::Checking,
                RecoveryStatus::Ready,
                RecoveryStatus::Switching,
            ])
            ->with('backupRun')
            ->latest('id')
            ->first();
        $healthy = $latest?->verified_at?->greaterThan(now()->subDays(2)) === true
            && ($latestFailure === null || $latestFailure->created_at->lt($latest->verified_at));
        $next = now(config('nutriscope-backups.timezone'))->setTime(1, 30);
        if ($next->isPast()) {
            $next->addDay();
        }

        return [
            'status' => $healthy ? 'healthy' : ($latestFailure !== null ? 'failed' : 'attention_needed'),
            'last_successful_at' => $latest?->verified_at?->toIso8601String(),
            'next_automatic_at' => $next->toIso8601String(),
            'scope' => 'Database records and protected uploaded files',
            'storage_bytes' => BackupRun::whereIn('state', [BackupState::Completed, BackupState::RecentlyDeleted])->sum('bytes'),
            'last_recovery_test_at' => RecoveryTest::query()->where('state', 'completed')->max('completed_at'),
            'active_recovery' => $activeRecovery === null ? null : [
                'id' => $activeRecovery->uuid,
                'backup_id' => $activeRecovery->backupRun->uuid,
                'state' => $activeRecovery->state->value,
                'requested_at' => $activeRecovery->requested_at?->toIso8601String(),
                'can_cancel' => in_array($activeRecovery->state, [
                    RecoveryStatus::Requested,
                    RecoveryStatus::Preparing,
                    RecoveryStatus::Checking,
                    RecoveryStatus::Ready,
                ], true),
            ],
            'counts' => [
                'available' => BackupRun::where('state', BackupState::Completed)->count(),
                'in_progress' => BackupRun::whereIn('state', [BackupState::Queued, BackupState::Running, BackupState::Verifying])->count(),
                'failed' => BackupRun::where('state', BackupState::Failed)->count(),
                'recently_deleted' => BackupRun::where('state', BackupState::RecentlyDeleted)->count(),
            ],
            'category_counts' => [
                'daily' => $this->filterCategory(BackupRun::query()->whereIn('state', $states), 'daily')->count(),
                'weekly' => $this->filterCategory(BackupRun::query()->whereIn('state', $states), 'weekly')->count(),
                'monthly' => $this->filterCategory(BackupRun::query()->whereIn('state', $states), 'monthly')->count(),
                'manual' => $this->filterCategory(BackupRun::query()->whereIn('state', $states), 'manual')->count(),
                'safety' => $this->filterCategory(BackupRun::query()->whereIn('state', $states), 'safety')->count(),
            ],
        ];
    }

    private function filterCategory(Builder $query, string $category): Builder
    {
        if ($category === 'all') {
            return $query;
        }

        if ($category === 'manual' || $category === 'safety') {
            return $query->where('source', BackupSource::from($category));
        }

        $tier = BackupRetentionTier::from($category);

        return $query->where('source', BackupSource::Automatic)->where(function (Builder $query) use ($tier): void {
            $query->whereHas('schedulePeriods', fn (Builder $periods): Builder => $periods->where('category', $tier))
                ->orWhere('retention_tier', $tier);
        });
    }

    /** @return array<int, BackupState> */
    private function statesFor(string $section): array
    {
        return match ($section) {
            'in_progress' => [BackupState::Queued, BackupState::Running, BackupState::Verifying],
            'failed' => [BackupState::Failed],
            'recently_deleted' => [BackupState::RecentlyDeleted],
            default => [BackupState::Completed],
        };
    }

    private function audit(BackupRun $backup, AuditAction $action, object $actor, string $outcome): void
    {
        $this->auditLogger->record(
            $action,
            AuditCategory::Operations,
            AuditDomain::System,
            subject: $backup,
            details: ['backup_public_id' => $backup->uuid, 'backup_outcome' => $outcome],
            actor: $actor,
        );
    }
}
