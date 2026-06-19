<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Writes notification rows for the two defense triggers (rnd.md §7).
 * Bulk-inserts one row per recipient — no per-user model events needed.
 */
class NotificationService
{
    /**
     * @param  iterable<User|int>  $users
     */
    public function notify(
        iterable $users,
        string $title,
        string $message,
        string $type,
        ?string $sourceModule = null,
        ?int $sourceId = null,
    ): int {
        $now = Carbon::now();
        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                'user_id'       => $user instanceof User ? $user->id : (int) $user,
                'title'         => $title,
                'message'       => $message,
                'type'          => $type,
                'source_module' => $sourceModule,
                'source_id'     => $sourceId,
                'read'          => false,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        Notification::insert($rows);

        return count($rows);
    }

    /**
     * Trigger A — fan an announcement out to users matching its visibility setting
     * ('All' → everyone, 'FSS'/'Admin' → that role). The author is never notified.
     */
    public function fanOutAnnouncement(Announcement $announcement): int
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->where('id', '!=', $announcement->user_id)
            ->when($announcement->visibility === 'FSS', fn ($q) => $q->where('role', 'FSS'))
            ->when($announcement->visibility === 'Admin', fn ($q) => $q->where('role', 'Admin'))
            ->get(['id']);

        return $this->notify(
            $recipients,
            $announcement->title,
            $announcement->body,
            'announcement',
            'announcements',
            $announcement->id,
        );
    }
}
