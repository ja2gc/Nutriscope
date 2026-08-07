<?php

namespace Tests\Feature\Backup;

use App\Contracts\BackupArchiveRunner;
use App\Contracts\DatabaseRestoreManager;
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
