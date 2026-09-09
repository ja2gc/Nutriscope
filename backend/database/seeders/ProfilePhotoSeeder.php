<?php

namespace Database\Seeders;

use App\Models\StoredObject;
use App\Models\User;
use App\Services\StoredObjectStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProfilePhotoSeeder extends Seeder
{
    private const DEMO_PHOTOS = [
        'admin@nutriscope.local' => ['asset' => 'admin.jpg', 'original_name' => 'seed-profile-admin-pexels-7780946.jpg'],
        'rnd@nutriscope.local' => ['asset' => 'rnd.jpg', 'original_name' => 'seed-profile-rnd-pexels-18029647.jpg'],
        'fss@nutriscope.local' => ['asset' => 'fss.jpg', 'original_name' => 'seed-profile-fss-pexels-20603347.jpg'],
    ];

    public function run(): void
    {
        activity()->withoutLogs(fn () => $this->seedPhotos());
    }

    private function seedPhotos(): void
    {
        foreach (self::DEMO_PHOTOS as $email => $photo) {
            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                $this->command?->warn("ProfilePhotoSeeder: {$email} was not found.");

                continue;
            }
            if ($user->profile_photo_stored_object_id !== null || $user->profile_photo !== null) {
                continue;
            }

            $object = $this->existingObject($photo['original_name']);
            if ($object === null) {
                $object = $this->storeAsset($photo['asset'], $photo['original_name']);
            }

            try {
                $user->forceFill([
                    'profile_photo' => null,
                    'profile_photo_stored_object_id' => $object->id,
                ])->saveQuietly();
            } catch (Throwable $exception) {
                if (! User::query()->where('profile_photo_stored_object_id', $object->id)->exists()) {
                    app(StoredObjectStorage::class)->deleteOrQueue($object);
                }

                throw $exception;
            }
        }
    }

    private function existingObject(string $originalName): ?StoredObject
    {
        $object = StoredObject::query()
            ->where('purpose', 'profile')
            ->where('original_name', $originalName)
            ->first();
        if ($object === null) {
            return null;
        }
        if (Storage::disk($object->storage_disk)->exists($object->object_key)) {
            return $object;
        }

        $object->delete();

        return null;
    }

    private function storeAsset(string $asset, string $originalName): StoredObject
    {
        $path = database_path('seeders/assets/profile/'.$asset);
        $bytes = file_get_contents($path);
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException("Profile photo asset is missing or unreadable: {$path}");
        }

        return app(StoredObjectStorage::class)->storeBytes(
            $bytes,
            'image/jpeg',
            'jpg',
            'profile',
            $originalName,
        );
    }
}
