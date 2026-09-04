<?php

namespace Tests\Feature\Backup;

use App\Models\BackupRun;
use App\Models\StoredObject;
use App\Services\Backup\ProtectedUploadRestorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ProtectedUploadRestorerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function staged_uploads_can_be_activated_and_rolled_back_without_losing_current_files(): void
    {
        Storage::fake('private_uploads');
        Storage::fake('backups');
        Config::set('filesystems.private_uploads', 'private_uploads');
        Config::set('filesystems.disks.private_uploads.driver', 'local');
        Storage::disk('private_uploads')->put('profile/current.jpg', 'current');
        $backup = $this->backupWithProtectedObject('recovered');
        $token = str_repeat('a', 32);
        $restorer = app(ProtectedUploadRestorer::class);

        $restorer->stage($backup, 'mysql', $token);
        $restorer->activate($token);

        Storage::disk('private_uploads')->assertMissing('profile/current.jpg');
        Storage::disk('private_uploads')->assertExists('profile/11111111-1111-1111-1111-111111111111.jpg');

        $restorer->rollback($token);

        $this->assertSame('current', Storage::disk('private_uploads')->get('profile/current.jpg'));
        Storage::disk('private_uploads')->assertMissing('profile/11111111-1111-1111-1111-111111111111.jpg');
        $this->assertSame([], Storage::disk('private_uploads')->allFiles('.recovery-state'));
    }

    #[Test]
    public function invalid_protected_bytes_never_replace_current_uploads(): void
    {
        Storage::fake('private_uploads');
        Storage::fake('backups');
        Config::set('filesystems.private_uploads', 'private_uploads');
        Config::set('filesystems.disks.private_uploads.driver', 'local');
        Storage::disk('private_uploads')->put('profile/current.jpg', 'current');
        $backup = $this->backupWithProtectedObject('recovered');
        Storage::disk('backups')->put($backup->manifest->objects->first()->protected_key, 'tampered');

        $this->expectException(RuntimeException::class);

        try {
            app(ProtectedUploadRestorer::class)->stage($backup, 'mysql', str_repeat('b', 32));
        } finally {
            $this->assertSame('current', Storage::disk('private_uploads')->get('profile/current.jpg'));
            Storage::disk('private_uploads')->assertMissing('profile/11111111-1111-1111-1111-111111111111.jpg');
        }
    }

    private function backupWithProtectedObject(string $bytes): BackupRun
    {
        $stored = StoredObject::query()->create([
            'storage_disk' => 'private_uploads',
            'object_key' => 'profile/11111111-1111-1111-1111-111111111111.jpg',
            'purpose' => 'profile',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'original_name' => 'recovered.jpg',
        ]);
        $backup = BackupRun::factory()->completed()->create(['storage_disk' => 'backups']);
        $manifest = $backup->manifest()->create([
            'storage_disk' => 'backups',
            'object_key' => 'manifests/'.$backup->uuid.'.json',
            'sha256' => str_repeat('a', 64),
            'object_count' => 1,
            'total_bytes' => strlen($bytes),
        ]);
        $manifest->objects()->create([
            'stored_object_id' => $stored->id,
            'stored_object_uuid' => $stored->uuid,
            'protected_key' => 'protected/'.hash('sha256', $bytes).'.jpg',
            'purpose' => 'profile',
            'bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
        ]);
        Storage::disk('backups')->put($manifest->objects()->first()->protected_key, $bytes);

        return $backup->load('manifest.objects');
    }
}
