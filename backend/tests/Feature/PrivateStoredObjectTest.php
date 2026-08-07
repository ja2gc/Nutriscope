<?php

namespace Tests\Feature;

use App\Jobs\DeleteStoredObject;
use App\Models\ReportBranding;
use App\Models\User;
use App\Services\StoredObjectStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivateStoredObjectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function upload_is_stored_privately_with_immutable_generated_key_and_integrity_metadata(): void
    {
        Storage::fake('private_uploads');
        $file = UploadedFile::fake()->createWithContent(
            'portrait.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );

        $object = app(StoredObjectStorage::class)->storeUpload($file, 'profile');

        Storage::disk('private_uploads')->assertExists($object->object_key);
        $this->assertStringStartsWith('profile/', $object->object_key);
        $this->assertStringNotContainsString('portrait', $object->object_key);
        $this->assertSame(hash('sha256', Storage::disk('private_uploads')->get($object->object_key)), $object->sha256);
        $this->assertSame(Storage::disk('private_uploads')->size($object->object_key), $object->bytes);
        $this->assertSame('image/png', $object->mime_type);
        $this->assertSame('portrait.png', $object->original_name);

        $this->expectException(\RuntimeException::class);
        $object->update(['object_key' => 'profile/replaced.jpg']);
    }

    #[Test]
    public function profile_photo_uses_an_authorized_application_url_without_binary_database_data(): void
    {
        Storage::fake('private_uploads');
        $user = User::factory()->create();
        $png = 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', ['profile_photo' => $png])
            ->assertOk()
            ->assertJsonPath('profile_photo', '/api/auth/profile-photo');

        $user->refresh();
        $this->assertNull($user->profile_photo);
        $this->assertNotNull($user->profile_photo_stored_object_id);
        $this->assertDatabaseCount('stored_objects', 1);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/auth/profile-photo')->assertUnauthorized();
        $this->actingAs($user, 'sanctum')->get('/api/auth/profile-photo')->assertOk();
    }

    #[Test]
    public function stored_object_api_metadata_never_contains_disk_or_object_key(): void
    {
        Storage::fake('private_uploads');
        $user = User::factory()->create();
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $object = app(StoredObjectStorage::class)->storeBytes($bytes, 'image/png', 'png', 'profile', 'photo.png');
        $user->update(['profile_photo_stored_object_id' => $object->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me')->assertOk();

        $response->assertJsonMissing(['object_key' => $object->object_key]);
        $response->assertJsonMissing(['storage_disk' => $object->storage_disk]);
        $this->assertSame('/api/auth/profile-photo', $response->json('profile_photo'));
    }

    #[Test]
    public function replacing_branding_keeps_the_previous_private_object_for_existing_report_snapshots(): void
    {
        Storage::fake('private_uploads');
        $rnd = User::factory()->rnd()->create();
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $old = app(StoredObjectStorage::class)->storeBytes($bytes, 'image/png', 'png', 'branding', 'old.png');
        ReportBranding::singleton()->update(['logo_left_stored_object_id' => $old->id]);

        $this->actingAs($rnd, 'sanctum')->post('/api/rnd/report-branding', [
            'logo_left' => UploadedFile::fake()->createWithContent('new.png', $bytes),
        ])->assertOk();

        $this->assertNotSame($old->id, ReportBranding::singleton()->fresh()->logo_left_stored_object_id);
        $this->assertDatabaseHas('stored_objects', ['id' => $old->id]);
        Storage::disk('private_uploads')->assertExists($old->object_key);
    }

    #[Test]
    public function deferred_cleanup_can_remove_bytes_after_metadata_has_rolled_back(): void
    {
        Storage::fake('private_uploads');
        Storage::disk('private_uploads')->put('profile/orphan.png', 'orphan');

        app()->call([new DeleteStoredObject(999999, 'private_uploads', 'profile/orphan.png'), 'handle']);

        Storage::disk('private_uploads')->assertMissing('profile/orphan.png');
    }
}
