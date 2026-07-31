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
        Storage::disk('backups')->put('database.zip', 'encrypted-content');
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
        $this->assertSame(17, $backup->bytes);
        $this->assertNotNull($backup->verified_at);
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
}
