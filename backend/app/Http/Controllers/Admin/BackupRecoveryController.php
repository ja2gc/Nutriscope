<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\BackupState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateRecoveryRequest;
use App\Models\BackupRun;
use App\Models\RecoveryRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

class BackupRecoveryController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function __invoke(CreateRecoveryRequest $request, BackupRun $backupRun): JsonResponse
    {
        if ($backupRun->state !== BackupState::Completed
            || $backupRun->recoveryRequests()->where('state', 'requested')->exists()) {
            return response()->json(['message' => 'Recovery cannot be requested for this backup.'], 409);
        }

        $recovery = RecoveryRequest::create([
            ...$request->validated(),
            'backup_run_id' => $backupRun->id,
            'requested_by' => $request->user()->id,
            'state' => 'requested',
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

        return response()->json(['data' => [
            'id' => $recovery->uuid,
            'backup_id' => $backupRun->uuid,
            'incident_type' => $recovery->incident_type->value,
            'state' => $recovery->state,
            'requested_at' => $recovery->requested_at->toIso8601String(),
        ]], 201);
    }
}
