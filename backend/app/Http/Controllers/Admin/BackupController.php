<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\BackupSource;
use App\Enums\BackupState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateBackupRequest;
use App\Http\Requests\Admin\DeleteBackupRequest;
use App\Http\Requests\Admin\KeepBackupRequest;
use App\Http\Resources\BackupRunResource;
use App\Jobs\CreateDatabaseBackup;
use App\Models\BackupRun;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BackupController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): AnonymousResourceCollection
    {
        $latestVerifiedId = BackupRun::verified()->latest('verified_at')->value('id');
        $backups = BackupRun::query()
            ->withCount(['recoveryRequests as pending_recovery_requests_count' => fn (Builder $query) => $query->where('state', 'requested')])
            ->latest('id')
            ->limit(100)
            ->get()
            ->each(fn (BackupRun $backup) => $backup->setAttribute('is_latest_verified', $backup->id === $latestVerifiedId));

        return BackupRunResource::collection($backups)->additional([
            'meta' => $this->summary($latestVerifiedId),
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
        if ($backupRun->state !== BackupState::Completed || $backupRun->isProtectedFromDeletion()) {
            return response()->json(['message' => 'This backup is protected and cannot be deleted.'], 409);
        }

        $backupRun->update([
            'state' => BackupState::RecentlyDeleted,
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

        $backupRun->update([
            'state' => BackupState::Completed,
            'deleted_at' => null,
            'recoverable_until' => null,
        ]);
        $this->audit($backupRun, AuditAction::Updated, $request->user(), 'kept');

        return (new BackupRunResource($backupRun))->response();
    }

    private function summary(?int $latestVerifiedId): array
    {
        $latest = $latestVerifiedId === null ? null : BackupRun::find($latestVerifiedId);
        $latestFailure = BackupRun::where('state', BackupState::Failed)->latest('id')->first();
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
            'scope' => 'Database records',
            'storage_bytes' => BackupRun::whereIn('state', [BackupState::Completed, BackupState::RecentlyDeleted])->sum('bytes'),
            'last_recovery_test_at' => null,
        ];
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
