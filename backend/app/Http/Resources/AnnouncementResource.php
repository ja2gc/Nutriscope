<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
                'name' => $this->user?->name,
                'role' => $this->user?->role,
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
            return array_values(array_filter($decoded, is_string(...)));
        }

        return [$this->attachment];
    }
}
