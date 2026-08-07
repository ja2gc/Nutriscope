<?php

namespace Tests\Feature\Backup;

use App\Contracts\BackupArchiveRunner;
use App\Data\BackupArchiveResult;
use App\Enums\BackupState;
use App\Jobs\CreateDatabaseBackup;
use App\Models\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CreateDatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function job_moves_a_backup_through_verification_to_completed(): void
    {
        Storage::fake('backups');
        config(['backup.backup.password' => 'test-password']);
        Storage::disk('backups')->put('database.zip', $this->encryptedDatabaseZip());
        $this->app->instance(BackupArchiveRunner::class, new class implements BackupArchiveRunner
        {
            public function runDatabaseOnly(): BackupArchiveResult
            {
                return new BackupArchiveResult('database.zip', 0, 'etag', true);
            }
        });
        $backup = BackupRun::factory()->create();

        app()->call([new CreateDatabaseBackup($backup->uuid), 'handle']);

        $backup->refresh();
        $this->assertSame(BackupState::Completed, $backup->state);
        $this->assertGreaterThan(0, $backup->bytes);
        $this->assertNotNull($backup->verified_at);
        $this->assertNotNull($backup->manifest);
        $this->assertNull($backup->failure_message);
    }

    #[Test]
    public function job_records_only_a_safe_failure_message(): void
    {
        $this->app->instance(BackupArchiveRunner::class, new class implements BackupArchiveRunner
        {
            public function runDatabaseOnly(): BackupArchiveResult
            {
                throw new RuntimeException('secret=sensitive-production-value');
            }
        });
        $backup = BackupRun::factory()->create();

        try {
            app()->call([new CreateDatabaseBackup($backup->uuid), 'handle']);
        } catch (RuntimeException) {
            // The queue may retry; metadata must already be safe.
        }

        $backup->refresh();
        $this->assertSame(BackupState::Failed, $backup->state);
        $this->assertStringNotContainsString('sensitive-production-value', (string) $backup->failure_message);
        $this->assertSame('backup_failed', $backup->failure_code);
    }

    private function encryptedDatabaseZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'backup-test-');
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
