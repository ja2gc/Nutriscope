<?php

namespace Tests\Feature\Backup;

use App\Contracts\BackupArchiveRunner;
use App\Data\BackupArchiveResult;
use App\Enums\BackupSource;
use App\Enums\BackupState;
use App\Jobs\CreateDatabaseBackup;
use App\Models\BackupRun;
use App\Models\User;
use App\Services\Backup\BackupRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManualBackupRetentionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function manual_backup_uses_the_existing_pipeline_and_receives_seven_days(): void
    {
        Carbon::setTestNow('2026-08-03 09:00:00');
        Storage::fake('backups');
        config(['backup.backup.password' => 'test-password']);
        Storage::disk('backups')->put('manual.zip', $this->encryptedDatabaseZip());
        $this->app->instance(BackupArchiveRunner::class, new class implements BackupArchiveRunner
        {
            public function runDatabaseOnly(): BackupArchiveResult
            {
                return new BackupArchiveResult('manual.zip', 0, 'etag', true);
            }
        });
        $backup = BackupRun::factory()->create(['source' => BackupSource::Manual]);

        app()->call([new CreateDatabaseBackup($backup->uuid), 'handle']);

        $backup->refresh();
        $this->assertSame(BackupState::Completed, $backup->state);
        $this->assertTrue($backup->retention_expires_at->equalTo(now()->addDays(7)));
        $this->assertDatabaseMissing('backup_schedule_periods', ['backup_run_id' => $backup->id]);
    }

    #[Test]
    public function expired_manual_backup_enters_recently_deleted_for_48_hours(): void
    {
        $this->freezeSecond();

        $backup = BackupRun::factory()->completed()->create([
            'source' => BackupSource::Manual,
            'retention_tier' => null,
            'retention_expires_at' => now()->subMinute(),
        ]);

        app(BackupRetentionService::class)->apply();

        $backup->refresh();
        $this->assertSame(BackupState::RecentlyDeleted, $backup->state);
        $this->assertTrue($backup->recoverable_until->between(now()->addHours(48)->subSecond(), now()->addHours(48)->addSecond()));
    }

    #[Test]
    public function keeping_a_manual_backup_grants_a_new_seven_day_window(): void
    {
        $this->freezeSecond();

        $admin = User::factory()->admin()->create();
        $backup = BackupRun::factory()->create([
            'source' => BackupSource::Manual,
            'state' => BackupState::RecentlyDeleted,
            'recoverable_until' => now()->addHour(),
            'retention_expires_at' => null,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/backups/{$backup->uuid}/keep")
            ->assertOk();

        $expiresAt = $backup->refresh()->retention_expires_at;
        $this->assertTrue($expiresAt->between(now()->addDays(7)->subSecond(), now()->addDays(7)->addSecond()));
    }

    private function encryptedDatabaseZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'manual-backup-test-');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->setPassword('test-password');
        $zip->addFromString('db-dumps/mysql-database.sql', 'CREATE TABLE users (id bigint);');
        $zip->setEncryptionName('db-dumps/mysql-database.sql', \ZipArchive::EM_AES_256);
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }
}
