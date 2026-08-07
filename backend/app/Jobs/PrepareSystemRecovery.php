<?php

namespace App\Jobs;

use App\Contracts\DatabaseRestoreManager;
use App\Contracts\EnvironmentSwitcher;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\BackupSource;
use App\Enums\BackupState;
use App\Enums\RecoveryStatus;
use App\Models\BackupRun;
use App\Models\RecoveryRequest;
use App\Notifications\RecoveryInterventionRequired;
use App\Services\Audit\AuditLogger;
use App\Services\Backup\BackupVerifier;
use App\Services\Backup\RecoveryVerifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class PrepareSystemRecovery implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public readonly string $recoveryUuid) {}

    public function handle(
        DatabaseRestoreManager $databases,
        RecoveryVerifier $verifier,
        BackupVerifier $backups,
        EnvironmentSwitcher $switcher,
        AuditLogger $audit,
    ): void {
        $recovery = RecoveryRequest::query()->where('uuid', $this->recoveryUuid)->firstOrFail();
        if ($recovery->state !== RecoveryStatus::Requested) {
            return;
        }
        $candidate = null;
        $maintenance = false;
        try {
            $recovery->transitionTo(RecoveryStatus::Preparing);
            $this->audit($audit, $recovery);
            $recovery->update(['started_at' => now()]);
            $safety = BackupRun::query()->create([
                'state' => BackupState::Queued,
                'source' => BackupSource::Safety,
                'storage_disk' => config('nutriscope-backups.disk'),
                'queued_at' => now(),
                'requested_by' => $recovery->requested_by,
            ]);
            CreateDatabaseBackup::dispatchSync($safety->uuid);
            $safety->refresh();
            if ($safety->state !== BackupState::Completed) {
                throw new \RuntimeException('Safety snapshot verification failed.');
            }
            $recovery->update(['safety_snapshot_backup_run_id' => $safety->id]);
            if ($this->stopIfCancelled($recovery, $databases, $candidate)) {
                return;
            }
            $recovery->transitionTo(RecoveryStatus::Checking);
            $this->audit($audit, $recovery);
            $name = 'nutriscope_recovery_'.substr(str_replace('-', '', $recovery->uuid), 0, 12);
            $candidate = $databases->restoreToTemporary($recovery->backupRun, $name);
            $recovery->update(['temporary_database' => $name]);
            if ($this->stopIfCancelled($recovery, $databases, $candidate)) {
                return;
            }
            if ($recovery->backupRun->manifest === null) {
                throw new \RuntimeException('The selected restore point has no uploaded-file manifest.');
            }
            $backups->verifyManifest($recovery->backupRun->manifest->load('objects'));
            $checks = $verifier->verify($candidate);
            if (! $checks['passed']) {
                throw new \RuntimeException('Candidate recovery checks failed.');
            }
            $recovery->transitionTo(RecoveryStatus::Ready);
            $this->audit($audit, $recovery);
            if (! $switcher->available()) {
                Notification::send($recovery->requestedBy, new RecoveryInterventionRequired($recovery->uuid, 'Production switching must be configured during Phase 2.'));

                return;
            }

            Artisan::call('down', ['--secret' => Str::random(40), '--render' => 'errors::503']);
            $maintenance = true;
            $recovery->transitionTo(RecoveryStatus::Switching);
            $this->audit($audit, $recovery);
            $switched = $switcher->switch($candidate);
            if (! $switched['successful']) {
                throw new \RuntimeException('Production switch failed.');
            }
            DB::selectOne('SELECT 1 AS healthy');
            $backups->verifyManifest($recovery->backupRun->manifest->load('objects'));
            $recovery->transitionTo(RecoveryStatus::Completed);
            $this->audit($audit, $recovery);
        } catch (Throwable $exception) {
            $recovery->refresh();
            if ($recovery->state === RecoveryStatus::Cancelled) {
                if ($candidate !== null) {
                    $databases->dropTemporary($candidate['name']);
                    $recovery->update(['temporary_database' => null]);
                }

                return;
            }
            if ($recovery->state === RecoveryStatus::Switching && $recovery->safetySnapshot !== null) {
                $rollback = $switcher->rollback($recovery->safetySnapshot->uuid);
                $recovery->transitionTo($rollback['successful'] ? RecoveryStatus::RolledBack : RecoveryStatus::Failed);
            } elseif (! $recovery->state->terminal()) {
                $recovery->transitionTo(RecoveryStatus::Failed);
            }
            $recovery->update(['failure_message' => 'Recovery could not be completed safely.']);
            $this->audit($audit, $recovery);
            Notification::send($recovery->requestedBy, new RecoveryInterventionRequired($recovery->uuid, 'Recovery failed before completion. Production was left unchanged or rolled back.'));
            if ($candidate !== null && $recovery->state !== RecoveryStatus::Completed) {
                $databases->dropTemporary($candidate['name']);
                $recovery->update(['temporary_database' => null]);
            }
            throw $exception;
        } finally {
            if ($maintenance) {
                Artisan::call('up');
            }
        }
    }

    /** @param array{name:string}|null $candidate */
    private function stopIfCancelled(RecoveryRequest $recovery, DatabaseRestoreManager $databases, ?array $candidate): bool
    {
        $recovery->refresh();
        if ($recovery->state !== RecoveryStatus::Cancelled) {
            return false;
        }
        if ($recovery->safetySnapshot !== null) {
            $recovery->safetySnapshot->update(['retention_expires_at' => $recovery->safety_snapshot_expires_at ?? now()->addHours(48)]);
        }
        if ($candidate !== null) {
            $databases->dropTemporary($candidate['name']);
            $recovery->update(['temporary_database' => null]);
        }

        return true;
    }

    private function audit(AuditLogger $audit, RecoveryRequest $recovery): void
    {
        $audit->record(
            AuditAction::Updated,
            AuditCategory::Operations,
            AuditDomain::System,
            subject: $recovery,
            details: [
                'recovery_request_public_id' => $recovery->uuid,
                'backup_public_id' => $recovery->backupRun->uuid,
                'recovery_status' => $recovery->state->value,
            ],
            actor: $recovery->requestedBy,
        );
    }
}
