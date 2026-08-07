<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\DatabaseRestoreManager;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\BackupState;
use App\Enums\RecoveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelRecoveryRequest;
use App\Http\Requests\Admin\CreateRecoveryRequest;
use App\Http\Resources\RecoveryRequestResource;
use App\Jobs\PrepareSystemRecovery;
use App\Models\BackupRun;
use App\Models\RecoveryRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

class BackupRecoveryController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(CreateRecoveryRequest $request, BackupRun $backupRun): JsonResponse
    {
        if ($backupRun->state !== BackupState::Completed
            || $backupRun->manifest === null
            || $backupRun->recoveryRequests()->whereNotIn('state', ['completed', 'failed', 'rolled_back', 'cancelled'])->exists()) {
            return response()->json(['message' => 'Recovery cannot be requested for this backup.'], 409);
        }

        $recovery = RecoveryRequest::create([
            ...$request->safe()->only(['incident_type', 'note']),
            'backup_run_id' => $backupRun->id,
            'requested_by' => $request->user()->id,
            'state' => RecoveryStatus::Requested,
            'requested_at' => now(),
        ]);
        $this->auditLogger->record(
            AuditAction::Created,
            AuditCategory::Operations,
            AuditDomain::System,
            subject: $recovery,
            details: [
                'backup_public_id' => $backupRun->uuid,
                'recovery_request_public_id' => $recovery->uuid,
                'backup_outcome' => 'recovery_requested',
            ],
            actor: $request->user(),
        );

        PrepareSystemRecovery::dispatch($recovery->uuid)->onQueue(config('nutriscope-backups.queue'));

        return (new RecoveryRequestResource($recovery->load('backupRun')))->response()->setStatusCode(201);
    }

    public function cancel(CancelRecoveryRequest $request, RecoveryRequest $recoveryRequest, DatabaseRestoreManager $databases): RecoveryRequestResource|JsonResponse
    {
        if (! in_array($recoveryRequest->state, [RecoveryStatus::Requested, RecoveryStatus::Preparing, RecoveryStatus::Checking, RecoveryStatus::Ready], true)) {
            return response()->json(['message' => 'Recovery can no longer be cancelled.'], 409);
        }
        $recoveryRequest->transitionTo(RecoveryStatus::Cancelled);
        if (filled($recoveryRequest->temporary_database)) {
            $databases->dropTemporary($recoveryRequest->temporary_database);
            $recoveryRequest->update(['temporary_database' => null]);
        }
        $this->auditLogger->record(
            AuditAction::Updated,
            AuditCategory::Operations,
            AuditDomain::System,
            subject: $recoveryRequest,
            details: ['recovery_request_public_id' => $recoveryRequest->uuid, 'backup_outcome' => 'recovery_cancelled'],
            actor: $request->user(),
        );

        return new RecoveryRequestResource($recoveryRequest->load('backupRun'));
    }
}
