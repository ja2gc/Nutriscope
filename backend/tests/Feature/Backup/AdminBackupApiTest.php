<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupSource;
use App\Enums\BackupState;
use App\Jobs\CreateDatabaseBackup;
use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminBackupApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function only_active_admins_can_access_backup_administration(): void
    {
        $rnd = User::factory()->rnd()->create();
        $inactiveAdmin = User::factory()->admin()->create(['is_active' => false]);

        $this->actingAs($rnd, 'sanctum')->getJson('/api/admin/backups')->assertForbidden();
        $this->actingAs($inactiveAdmin, 'sanctum')->getJson('/api/admin/backups')->assertForbidden();
    }

    #[Test]
    public function admin_can_list_privacy_safe_backup_status_and_queue_a_manual_backup(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $backup = BackupRun::factory()->completed()->create([
            'object_key' => 'private/provider/path/database.zip',
            'integrity_value' => 'secret-etag',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/backups')
            ->assertOk()
            ->assertJsonPath('data.0.id', $backup->uuid)
            ->assertJsonMissing(['object_key' => 'private/provider/path/database.zip'])
            ->assertJsonMissing(['integrity_value' => 'secret-etag']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/backups')
            ->assertAccepted()
            ->assertJsonPath('data.state', 'queued');

        Queue::assertPushed(CreateDatabaseBackup::class);
        $this->assertDatabaseHas('backup_runs', [
            'uuid' => $response->json('data.id'),
            'requested_by' => $admin->id,
            'state' => BackupState::Queued->value,
        ]);
    }

    #[Test]
    public function newest_verified_backup_cannot_be_deleted_and_duplicate_work_is_rejected(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $newest = BackupRun::factory()->completed()->create(['verified_at' => now()]);
        $newest->manifest()->create([
            'storage_disk' => 'backups',
            'object_key' => 'manifests/'.$newest->uuid.'.json',
            'sha256' => str_repeat('a', 64),
            'object_count' => 0,
            'total_bytes' => 0,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/backups/{$newest->uuid}")
            ->assertConflict();

        BackupRun::factory()->create(['state' => BackupState::Running]);
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/backups')
            ->assertConflict();
    }

    #[Test]
    public function an_unexpired_safety_snapshot_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        BackupRun::factory()->completed()->create(['verified_at' => now()]);
        $safety = BackupRun::factory()->completed()->create([
            'source' => BackupSource::Safety,
            'verified_at' => now()->subMinute(),
            'retention_expires_at' => now()->addHours(48),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/backups/{$safety->uuid}")
            ->assertConflict();
    }

    #[Test]
    public function admin_can_recover_a_deleted_backup_and_request_operator_recovery(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $newest = BackupRun::factory()->completed()->create(['verified_at' => now()]);
        $newest->manifest()->create([
            'storage_disk' => 'backups',
            'object_key' => 'manifests/'.$newest->uuid.'.json',
            'sha256' => str_repeat('b', 64),
            'object_count' => 0,
            'total_bytes' => 0,
        ]);
        $older = BackupRun::factory()->completed()->create(['verified_at' => now()->subDay()]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/backups/{$older->uuid}")
            ->assertOk()
            ->assertJsonPath('data.state', 'recently_deleted');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/backups/{$older->uuid}/keep")
            ->assertOk()
            ->assertJsonPath('data.state', 'completed');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/backups/{$newest->uuid}/recovery-requests", [
                'incident_type' => 'damaged_database',
                'note' => 'Records cannot be opened after the latest deployment.',
                'current_password' => 'password',
                'confirmation' => 'RESTORE '.$newest->uuid,
            ])
            ->assertCreated()
            ->assertJsonPath('data.state', 'requested');

        $this->assertTrue($newest->isProtectedFromDeletion());
    }
}
