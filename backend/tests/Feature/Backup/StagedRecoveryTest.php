<?php

namespace Tests\Feature\Backup;

use App\Contracts\BackupArchiveRunner;
use App\Contracts\DatabaseRestoreManager;
use App\Contracts\EnvironmentSwitcher;
use App\Data\BackupArchiveResult;
use App\Enums\BackupState;
use App\Enums\RecoveryStatus;
use App\Jobs\PrepareSystemRecovery;
use App\Models\BackupRun;
use App\Models\RecoveryRequest;
use App\Models\User;
use App\Notifications\RecoveryInterventionRequired;
use App\Services\Backup\BackupVerifier;
use App\Services\Backup\RecoveryVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StagedRecoveryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_must_reauthenticate_and_type_the_exact_restore_phrase(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $backup = $this->restorePoint();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/backups/{$backup->uuid}/recovery-requests", [
            'incident_type' => 'damaged_database',
            'note' => 'The database cannot be opened safely.',
        ])->assertUnprocessable();

        $this->postJson("/api/admin/backups/{$backup->uuid}/recovery-requests", [
            'incident_type' => 'damaged_database',
            'note' => 'The database cannot be opened safely.',
            'current_password' => 'password',
            'confirmation' => 'RESTORE '.$backup->uuid,
        ])->assertCreated()->assertJsonPath('data.state', 'requested');

        Queue::assertPushed(PrepareSystemRecovery::class);
    }

    #[Test]
    public function preparation_creates_one_safety_snapshot_and_stops_at_ready_without_a_switcher(): void
    {
        Storage::fake('backups');
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $backup = $this->restorePoint();
        $recovery = RecoveryRequest::query()->create([
            'backup_run_id' => $backup->id,
            'requested_by' => $admin->id,
            'incident_type' => 'damaged_database',
            'note' => 'The database cannot be opened safely.',
            'state' => RecoveryStatus::Requested,
            'requested_at' => now(),
        ]);
        $this->app->instance(BackupArchiveRunner::class, new class implements BackupArchiveRunner
        {
            public function runDatabaseOnly(): BackupArchiveResult
            {
                return new BackupArchiveResult('safety.zip', 100, str_repeat('c', 64), true);
            }
        });
        $verifier = $this->createMock(BackupVerifier::class);
        $verifier->method('verify')->willReturn(new BackupArchiveResult('safety.zip', 100, str_repeat('c', 64), true));
        $verifier->method('verifyManifest');
        $this->app->instance(BackupVerifier::class, $verifier);
        $databases = new class implements DatabaseRestoreManager
        {
            public int $restores = 0;

            public function restoreToTemporary(BackupRun $run, string $databaseName): array
            {
                $this->restores++;

                return ['name' => $databaseName, 'disposable' => true, 'promotable' => true, 'connection' => 'test'];
            }

            public function dropTemporary(string $databaseName): void {}
        };
        $this->app->instance(DatabaseRestoreManager::class, $databases);
        $checks = $this->createMock(RecoveryVerifier::class);
        $checks->method('verify')->willReturn(['passed' => true, 'checks' => ['schema' => true]]);
        $this->app->instance(RecoveryVerifier::class, $checks);

        app()->call([new PrepareSystemRecovery($recovery->uuid), 'handle']);

        $recovery->refresh();
        $this->assertSame(RecoveryStatus::Ready, $recovery->state);
        $this->assertNotNull($recovery->safety_snapshot_backup_run_id);
        $this->assertSame(1, $databases->restores);
        $this->assertDatabaseHas('backup_runs', ['id' => $recovery->safety_snapshot_backup_run_id, 'state' => BackupState::Completed->value, 'source' => 'safety']);
        Notification::assertSentTo($admin, RecoveryInterventionRequired::class);
    }

    #[Test]
    public function cancellation_during_candidate_preparation_stops_without_marking_recovery_failed(): void
    {
        Storage::fake('backups');
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $backup = $this->restorePoint();
        $recovery = RecoveryRequest::query()->create([
            'backup_run_id' => $backup->id,
            'requested_by' => $admin->id,
            'incident_type' => 'damaged_database',
            'note' => 'The database cannot be opened safely.',
            'state' => RecoveryStatus::Requested,
            'requested_at' => now(),
        ]);
        $this->app->instance(BackupArchiveRunner::class, new class implements BackupArchiveRunner
        {
            public function runDatabaseOnly(): BackupArchiveResult
            {
                return new BackupArchiveResult('safety.zip', 100, str_repeat('c', 64), true);
            }
        });
        $verifier = $this->createMock(BackupVerifier::class);
        $verifier->method('verify')->willReturn(new BackupArchiveResult('safety.zip', 100, str_repeat('c', 64), true));
        $verifier->method('verifyManifest');
        $this->app->instance(BackupVerifier::class, $verifier);
        $databases = new class($recovery) implements DatabaseRestoreManager
        {
            public bool $dropped = false;

            public function __construct(private readonly RecoveryRequest $recovery) {}

            public function restoreToTemporary(BackupRun $run, string $databaseName): array
            {
                $this->recovery->refresh()->transitionTo(RecoveryStatus::Cancelled);

                return ['name' => $databaseName, 'disposable' => true, 'promotable' => true, 'connection' => 'test'];
            }

            public function dropTemporary(string $databaseName): void
            {
                $this->dropped = true;
            }
        };
        $this->app->instance(DatabaseRestoreManager::class, $databases);

        app()->call([new PrepareSystemRecovery($recovery->uuid), 'handle']);

        $recovery->refresh();
        $this->assertSame(RecoveryStatus::Cancelled, $recovery->state);
        $this->assertNull($recovery->failure_message);
        $this->assertNull($recovery->temporary_database);
        $this->assertTrue($databases->dropped);
        $this->assertNotNull($recovery->safety_snapshot_expires_at);
    }

    #[Test]
    public function supported_recovery_switches_then_cleans_up_the_temporary_database(): void
    {
        Storage::fake('backups');
        $admin = User::factory()->admin()->create();
        $backup = $this->restorePoint();
        $recovery = RecoveryRequest::factory()->for($backup)->for($admin, 'requestedBy')->create();
        $this->fakeSafetyBackupPipeline();
        $databases = $this->fakeDatabases();
        $this->app->instance(DatabaseRestoreManager::class, $databases);
        $checks = $this->createMock(RecoveryVerifier::class);
        $checks->method('verify')->willReturn(['passed' => true, 'checks' => ['schema' => true]]);
        $this->app->instance(RecoveryVerifier::class, $checks);
        $switcher = new class implements EnvironmentSwitcher
        {
            public bool $switched = false;

            public bool $finalized = false;

            public function available(): bool
            {
                return true;
            }

            public function switch(array $candidate): array
            {
                $this->switched = isset($candidate['recovery_uuid'], $candidate['backup_uuid']);

                return ['successful' => true, 'message' => 'switched'];
            }

            public function finalize(array $candidate): void
            {
                $this->finalized = true;
            }

            public function rollback(string $safetySnapshotUuid): array
            {
                return ['successful' => true, 'message' => 'rolled back'];
            }
        };
        $this->app->instance(EnvironmentSwitcher::class, $switcher);

        app()->call([new PrepareSystemRecovery($recovery->uuid), 'handle']);

        $recovery->refresh();
        $this->assertSame(RecoveryStatus::Completed, $recovery->state);
        $this->assertNull($recovery->temporary_database);
        $this->assertTrue($switcher->switched);
        $this->assertTrue($switcher->finalized);
        $this->assertCount(1, $databases->dropped);
    }

    #[Test]
    public function a_failure_after_switch_automatically_rolls_back_to_the_safety_snapshot(): void
    {
        Storage::fake('backups');
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $backup = $this->restorePoint();
        $recovery = RecoveryRequest::factory()->for($backup)->for($admin, 'requestedBy')->create();
        $this->fakeSafetyBackupPipeline();
        $databases = $this->fakeDatabases();
        $this->app->instance(DatabaseRestoreManager::class, $databases);
        $checks = $this->createMock(RecoveryVerifier::class);
        $checks->method('verify')->willReturn(['passed' => true, 'checks' => ['schema' => true]]);
        $this->app->instance(RecoveryVerifier::class, $checks);
        $verifier = $this->createMock(BackupVerifier::class);
        $verifier->expects($this->exactly(3))->method('verifyManifest')
            ->willReturnOnConsecutiveCalls(null, null, $this->throwException(new \RuntimeException('post-switch failure')));
        $verifier->method('verify')->willReturn(new BackupArchiveResult('safety.zip', 100, str_repeat('c', 64), true));
        $this->app->instance(BackupVerifier::class, $verifier);
        $switcher = new class implements EnvironmentSwitcher
        {
            public bool $rolledBack = false;

            public function available(): bool
            {
                return true;
            }

            public function switch(array $candidate): array
            {
                return ['successful' => true, 'message' => 'switched'];
            }

            public function finalize(array $candidate): void {}

            public function rollback(string $safetySnapshotUuid): array
            {
                $this->rolledBack = true;

                return ['successful' => true, 'message' => 'rolled back'];
            }
        };
        $this->app->instance(EnvironmentSwitcher::class, $switcher);

        try {
            app()->call([new PrepareSystemRecovery($recovery->uuid), 'handle']);
            $this->fail('The post-switch verification failure was not raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('post-switch failure', $exception->getMessage());
        }

        $recovery->refresh();
        $this->assertNotNull($recovery->safety_snapshot_backup_run_id);
        $this->assertTrue($switcher->rolledBack);
        $this->assertSame(RecoveryStatus::RolledBack, $recovery->state);
        $this->assertNull($recovery->temporary_database);
    }

    #[Test]
    public function a_retried_job_rolls_back_an_interrupted_switch_before_doing_any_new_work(): void
    {
        $admin = User::factory()->admin()->create();
        $backup = $this->restorePoint();
        $safety = BackupRun::factory()->completed()->create(['source' => 'safety']);
        $recovery = RecoveryRequest::factory()->for($backup)->for($admin, 'requestedBy')->create([
            'state' => RecoveryStatus::Switching,
            'safety_snapshot_backup_run_id' => $safety->id,
            'temporary_database' => 'nutriscope_recovery_0123456789ab',
        ]);
        $databases = $this->fakeDatabases();
        $this->app->instance(DatabaseRestoreManager::class, $databases);
        $switcher = new class implements EnvironmentSwitcher
        {
            public bool $rolledBack = false;

            public function available(): bool
            {
                return true;
            }

            public function switch(array $candidate): array
            {
                throw new \RuntimeException('A retry must not switch again.');
            }

            public function finalize(array $candidate): void {}

            public function rollback(string $safetySnapshotUuid): array
            {
                $this->rolledBack = true;

                return ['successful' => true, 'message' => 'rolled back'];
            }
        };
        $this->app->instance(EnvironmentSwitcher::class, $switcher);

        app()->call([new PrepareSystemRecovery($recovery->uuid), 'handle']);

        $this->assertSame(RecoveryStatus::RolledBack, $recovery->refresh()->state);
        $this->assertTrue($switcher->rolledBack);
        $this->assertNull($recovery->temporary_database);
        $this->assertSame(['nutriscope_recovery_0123456789ab'], $databases->dropped);
    }

    private function fakeSafetyBackupPipeline(): void
    {
        $this->app->instance(BackupArchiveRunner::class, new class implements BackupArchiveRunner
        {
            public function runDatabaseOnly(): BackupArchiveResult
            {
                return new BackupArchiveResult('safety.zip', 100, str_repeat('c', 64), true);
            }
        });
        $verifier = $this->createMock(BackupVerifier::class);
        $verifier->method('verify')->willReturn(new BackupArchiveResult('safety.zip', 100, str_repeat('c', 64), true));
        $verifier->method('verifyManifest');
        $this->app->instance(BackupVerifier::class, $verifier);
    }

    private function fakeDatabases(): DatabaseRestoreManager
    {
        return new class implements DatabaseRestoreManager
        {
            /** @var list<string> */
            public array $dropped = [];

            public function restoreToTemporary(BackupRun $run, string $databaseName): array
            {
                return ['name' => $databaseName, 'disposable' => true, 'promotable' => true, 'connection' => 'test'];
            }

            public function dropTemporary(string $databaseName): void
            {
                $this->dropped[] = $databaseName;
            }
        };
    }

    private function restorePoint(): BackupRun
    {
        $backup = BackupRun::factory()->completed()->create();
        $backup->manifest()->create([
            'storage_disk' => 'backups',
            'object_key' => 'manifests/'.$backup->uuid.'.json',
            'sha256' => str_repeat('a', 64),
            'object_count' => 0,
            'total_bytes' => 0,
        ]);

        return $backup;
    }
}
