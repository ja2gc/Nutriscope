<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\StoredObject;
use App\Models\User;
use App\Services\StoredObjectStorage;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\ProfilePhotoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('filesystems.private_uploads'));
        $this->seed(AdminUserSeeder::class);
    }

    public function test_demo_profiles_are_private_repeatable_and_streamable(): void
    {
        $this->seed(ProfilePhotoSeeder::class);
        $users = User::query()->whereIn('email', [
            'admin@nutriscope.local', 'rnd@nutriscope.local', 'fss@nutriscope.local',
        ])->with('profilePhotoObject')->orderBy('email')->get();

        $this->assertCount(3, $users);
        foreach ($users as $user) {
            $object = $user->profilePhotoObject;
            $this->assertNotNull($object);
            $this->assertSame('profile', $object->purpose);
            Storage::disk($object->storage_disk)->assertExists($object->object_key);
            $this->assertSame($object->bytes, Storage::disk($object->storage_disk)->size($object->object_key));
        }

        $snapshot = $users->mapWithKeys(fn (User $user) => [$user->email => [
            $user->id, $user->profile_photo_stored_object_id, $user->profilePhotoObject->object_key,
        ]])->all();
        $count = StoredObject::query()->count();
        $auditCount = AuditActivity::query()->count();

        $this->seed(ProfilePhotoSeeder::class);

        $this->assertSame($count, StoredObject::query()->count());
        $this->assertSame($auditCount, AuditActivity::query()->count());
        foreach ($snapshot as $email => $expected) {
            $user = User::query()->where('email', $email)->with('profilePhotoObject')->sole();
            $this->assertSame($expected, [$user->id, $user->profile_photo_stored_object_id, $user->profilePhotoObject->object_key]);
        }

        $this->getJson('/api/auth/profile-photo')->assertUnauthorized();
        $this->actingAs($users->first(), 'sanctum')->get('/api/auth/profile-photo')
            ->assertOk()->assertHeader('content-type', $users->first()->profilePhotoObject->mime_type);
    }

    public function test_existing_manual_profile_photo_is_preserved(): void
    {
        $path = database_path('seeders/assets/profile/admin.jpg');
        $manual = app(StoredObjectStorage::class)->storeBytes(
            file_get_contents($path),
            'image/jpeg',
            'jpg',
            'profile',
            'manual-profile.jpg',
        );
        $admin = User::query()->where('email', 'admin@nutriscope.local')->sole();
        $admin->forceFill(['profile_photo_stored_object_id' => $manual->id])->save();

        $this->seed(ProfilePhotoSeeder::class);

        $this->assertSame($manual->id, $admin->fresh()->profile_photo_stored_object_id);
        $this->assertNotNull(User::query()->where('email', 'rnd@nutriscope.local')->sole()->profile_photo_stored_object_id);
        $this->assertNotNull(User::query()->where('email', 'fss@nutriscope.local')->sole()->profile_photo_stored_object_id);
    }
}
