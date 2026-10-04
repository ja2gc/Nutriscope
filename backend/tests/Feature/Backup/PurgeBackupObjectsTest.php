<?php

namespace Tests\Feature\Backup;

use App\Jobs\PurgeBackupObjects;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PurgeBackupObjectsTest extends TestCase
{
    #[Test]
    public function a_transient_delete_failure_retries_the_same_objects(): void
    {
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->twice()->with(['private-object'])->andReturn(false, true);
        Storage::shouldReceive('disk')->twice()->with('backups')->andReturn($disk);
        $job = new PurgeBackupObjects(['backups' => ['private-object', 'private-object']]);

        try {
            $job->handle();
            $this->fail('First delete attempt should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Backup objects could not be removed.', $exception->getMessage());
        }

        $job->handle();

        $this->assertSame(5, $job->tries);
        $this->assertSame([30, 120, 300, 600], $job->backoff());
    }

    #[Test]
    public function exhausted_attempts_log_only_a_safe_summary(): void
    {
        Log::spy();
        $job = new PurgeBackupObjects(['backups' => ['private-object']]);

        $job->failed(new RuntimeException('secret=private-object'));

        Log::shouldHaveReceived('error')->once()->with(
            'Backup object purge failed after retries.',
            ['disk_count' => 1],
        );
    }
}
