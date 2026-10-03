<?php

namespace App\Http\Resources;

use App\Models\StoredObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attachments = $this->attachments();

        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'body' => $this->body,
            'category' => $this->category,
            'attachment' => $attachments[0] ?? null,
            'attachments' => $attachments,
            'pinned' => $this->pinned,
            'visibility' => $this->visibility,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'author' => [
                'id' => $this->user?->uuid,
                'name' => $this->user?->display_name,
                'role' => $this->user?->role,
                'profile_photo' => $this->user?->profile_photo_stored_object_id !== null
                    ? "/api/announcements/{$this->uuid}/author-photo"
                    : null,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function attachments(): array
    {
        if (! is_string($this->attachment) || trim($this->attachment) === '') {
            return [];
        }

        $decoded = json_decode($this->attachment, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (($decoded['version'] ?? null) === 1 && is_array($decoded['stored_object_uuids'] ?? null)) {
                $objects = StoredObject::query()
                    ->where('purpose', 'announcement')
                    ->whereIn('uuid', $decoded['stored_object_uuids'])
                    ->get()
                    ->keyBy('uuid');

                return array_values(array_filter(array_map(function (mixed $uuid) use ($objects): ?string {
                    if (! is_string($uuid) || ! ($object = $objects->get($uuid))) {
                        return null;
                    }

                    try {
                        $bytes = Storage::disk($object->storage_disk)->get($object->object_key);

                        return 'data:'.$object->mime_type.';base64,'.base64_encode($bytes);
                    } catch (Throwable $exception) {
                        report($exception);

                        return null;
                    }
                }, $decoded['stored_object_uuids']), 'is_string'));
            }

            return array_values(array_filter($decoded, is_string(...)));
        }

        return [$this->attachment];
    }
}
