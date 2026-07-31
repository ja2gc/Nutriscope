<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupState;
use App\Models\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PurgeDeletedBackupsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_purges_only_expired_recently_deleted_objects(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('expired.zip', 'backup');
        Storage::disk('backups')->put('recoverable.zip', 'backup');
        $expired = BackupRun::factory()->create([
            'state' => BackupState::RecentlyDeleted,
            'object_key' => 'expired.zip',
            'recoverable_until' => now()->subMinute(),
        ]);
        $recoverable = BackupRun::factory()->create([
            'state' => BackupState::RecentlyDeleted,
            'object_key' => 'recoverable.zip',
            'recoverable_until' => now()->addHour(),
        ]);

        $this->artisan('backups:purge-deleted')->assertSuccessful();

        Storage::disk('backups')->assertMissing('expired.zip');
        Storage::disk('backups')->assertExists('recoverable.zip');
        $this->assertSame(BackupState::Purged, $expired->refresh()->state);
        $this->assertSame(BackupState::RecentlyDeleted, $recoverable->refresh()->state);
    }
}
