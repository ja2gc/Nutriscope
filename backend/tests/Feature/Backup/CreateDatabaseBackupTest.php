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

        $job = new CreateDatabaseBackup($backup->uuid);
        $exception = new RuntimeException('secret=sensitive-production-value');

        try {
            app()->call([$job, 'handle']);
        } catch (RuntimeException) {
            $job->failed($exception);
        }

        $backup->refresh();
        $this->assertSame(BackupState::Failed, $backup->state);
        $this->assertStringNotContainsString('sensitive-production-value', (string) $backup->failure_message);
        $this->assertSame('backup_failed', $backup->failure_code);
    }

    #[Test]
    public function job_can_retry_after_a_transient_failure(): void
    {
        Storage::fake('backups');
        config(['backup.backup.password' => 'test-password']);
        Storage::disk('backups')->put('database.zip', $this->encryptedDatabaseZip());
        $this->app->instance(BackupArchiveRunner::class, new class implements BackupArchiveRunner
        {
            private int $attempts = 0;

            public function runDatabaseOnly(): BackupArchiveResult
            {
                if (++$this->attempts === 1) {
                    throw new RuntimeException('Temporary provider failure.');
                }

                return new BackupArchiveResult('database.zip', 0, 'etag', true);
            }
        });
        $backup = BackupRun::factory()->create();
        $job = new CreateDatabaseBackup($backup->uuid);

        try {
            app()->call([$job, 'handle']);
        } catch (RuntimeException) {
            // The queue retries the same job and backup record.
        }

        $this->assertSame(BackupState::Running, $backup->refresh()->state);

        app()->call([$job, 'handle']);

        $this->assertSame(BackupState::Completed, $backup->refresh()->state);
    }

    #[Test]
    public function verification_failure_is_terminal_instead_of_being_retried_from_an_invalid_state(): void
    {
        Storage::fake('backups');
        $this->app->instance(BackupArchiveRunner::class, new class implements BackupArchiveRunner
        {
            public function runDatabaseOnly(): BackupArchiveResult
            {
                return new BackupArchiveResult('missing.zip', 0, 'etag', true);
            }
        });
        $backup = BackupRun::factory()->create();
        $job = (new CreateDatabaseBackup($backup->uuid))->withFakeQueueInteractions();

        app()->call([$job, 'handle']);

        $job->assertFailed();
        $this->assertSame(BackupState::Failed, $backup->refresh()->state);
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
