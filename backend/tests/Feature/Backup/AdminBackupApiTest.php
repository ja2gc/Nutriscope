<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupRetentionTier;
use App\Enums\BackupSource;
use App\Enums\BackupState;
use App\Jobs\CreateDatabaseBackup;
use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
    public function backup_list_is_paginated_ten_at_a_time_with_summary_kept_separate(): void
    {
        $admin = User::factory()->admin()->create();
        BackupRun::factory()->count(12)->completed()->create();
        BackupRun::factory()->count(2)->create(['state' => BackupState::Failed]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/backups?section=available&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('summary.counts.available', 12)
            ->assertJsonPath('summary.counts.failed', 2)
            ->assertJsonStructure(['summary' => ['status', 'scope', 'storage_bytes', 'counts']]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/backups?section=failed')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/backups?section=unknown')
            ->assertUnprocessable();
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
    public function admin_can_remove_a_failed_backup_record_without_affecting_available_backups(): void
    {
        $admin = User::factory()->admin()->create();
        $available = BackupRun::factory()->completed()->create(['verified_at' => now()]);
        $failed = BackupRun::factory()->create([
            'state' => BackupState::Failed,
            'failure_code' => 'archive_failed',
            'failure_message' => 'Backup could not be completed.',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/backups/{$failed->uuid}")
            ->assertOk()
            ->assertJsonPath('data.state', 'purged');

        $this->assertSame(BackupState::Purged, $failed->refresh()->state);
        $this->assertNotNull($failed->purged_at);
        $this->assertSame(BackupState::Completed, $available->refresh()->state);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/backups')
            ->assertOk()
            ->assertJsonMissing(['id' => $failed->uuid])
            ->assertJsonFragment(['id' => $available->uuid]);
    }

    #[Test]
    public function admin_can_permanently_delete_a_recently_deleted_backup(): void
    {
        Storage::fake('backups');
        $admin = User::factory()->admin()->create();
        $available = BackupRun::factory()->completed()->create(['verified_at' => now()]);
        Storage::disk('backups')->put('deleted.zip', 'backup');
        $deleted = BackupRun::factory()->create([
            'state' => BackupState::RecentlyDeleted,
            'storage_disk' => 'backups',
            'object_key' => 'deleted.zip',
            'recoverable_until' => now()->addHours(48),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/backups/{$deleted->uuid}")
            ->assertOk()
            ->assertJsonPath('data.state', 'purged');

        Storage::disk('backups')->assertMissing('deleted.zip');
        $this->assertSame(BackupState::Purged, $deleted->refresh()->state);
        $this->assertSame(BackupState::Completed, $available->refresh()->state);
    }

    #[Test]
    public function backup_list_filters_by_category_and_exposes_all_categories_for_shared_archives(): void
    {
        $admin = User::factory()->admin()->create();
        $shared = BackupRun::factory()->completed()->create(['source' => BackupSource::Automatic]);
        $shared->schedulePeriods()->createMany([
            ['category' => BackupRetentionTier::Daily, 'period_key' => '2026-09-04', 'expires_at' => now()->addDays(3)],
            ['category' => BackupRetentionTier::Weekly, 'period_key' => '2026-W36', 'expires_at' => now()->addWeeks(2)],
        ]);
        BackupRun::factory()->completed()->create(['source' => BackupSource::Manual]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/backups?section=available&category=weekly')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $shared->uuid)
            ->assertJsonPath('data.0.categories', ['daily', 'weekly'])
            ->assertJsonPath('summary.category_counts.daily', 1)
            ->assertJsonPath('summary.category_counts.weekly', 1)
            ->assertJsonPath('summary.category_counts.monthly', 0)
            ->assertJsonPath('summary.category_counts.manual', 1);
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

    #[Test]
    public function keeping_an_automatic_backup_restores_its_schedule_expiration(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->admin()->create();
        $backup = BackupRun::factory()->create([
            'source' => BackupSource::Automatic,
            'state' => BackupState::RecentlyDeleted,
            'recoverable_until' => now()->addHour(),
            'retention_expires_at' => null,
        ]);
        $backup->schedulePeriods()->create([
            'category' => 'weekly',
            'period_key' => now()->format('o-\WW'),
            'expires_at' => now()->addWeek(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/backups/{$backup->uuid}/keep")
            ->assertOk();

        $this->assertTrue($backup->refresh()->retention_expires_at->equalTo(now()->addWeek()));
    }
}
