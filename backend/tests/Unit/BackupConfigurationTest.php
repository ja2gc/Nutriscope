<?php

namespace Tests\Unit;

use App\Jobs\RunBackupRecoveryTest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupConfigurationTest extends TestCase
{
    #[Test]
    public function backup_configuration_is_private_provider_neutral_and_database_only(): void
    {
        config()->set('backup.backup.password', 'test-archive-password');

        $this->assertSame('backups', config('nutriscope-backups.disk'));
        $this->assertSame('public', config('filesystems.uploads'));
        $this->assertSame('s3', config('filesystems.disks.backups.driver'));
        $this->assertSame('private', config('filesystems.disks.backups.visibility'));
        $this->assertTrue(config('filesystems.disks.backups.throw'));
        $this->assertSame([], config('backup.backup.source.files.include'));
        $this->assertSame(['mysql'], config('backup.backup.source.databases'));
        $this->assertSame(['backups'], config('backup.backup.destination.disks'));
        $this->assertSame('aes256', config('backup.backup.encryption'));
        $this->assertNotEmpty(config('backup.backup.password'));
        $this->assertSame(3, config('nutriscope-backups.retention.daily'));
        $this->assertSame(2, config('nutriscope-backups.retention.weekly'));
        $this->assertSame(3, config('nutriscope-backups.retention.monthly'));
        $this->assertSame(48, config('nutriscope-backups.recoverable_hours'));
    }

    #[Test]
    public function environment_examples_name_backup_secrets_without_values(): void
    {
        foreach (['.env.example', '.env.production.example'] as $file) {
            $contents = file_get_contents(base_path($file));

            $this->assertIsString($contents);
            $this->assertMatchesRegularExpression('/^BACKUP_SECRET_ACCESS_KEY=$/m', $contents);
            $this->assertMatchesRegularExpression('/^BACKUP_ARCHIVE_PASSWORD=$/m', $contents);
        }
    }

    #[Test]
    public function production_queue_retry_window_exceeds_the_longest_backup_job_timeout(): void
    {
        $env = file_get_contents(base_path('.env.production.example'));

        $this->assertIsString($env);
        $this->assertSame(1, preg_match('/^REDIS_QUEUE_RETRY_AFTER=(\d+)$/m', $env, $matches));
        $this->assertGreaterThan((new RunBackupRecoveryTest('test'))->timeout, (int) $matches[1]);
        $this->assertGreaterThan(
            (new RunBackupRecoveryTest('test'))->timeout,
            config('queue.connections.redis.retry_after'),
        );
    }
}
