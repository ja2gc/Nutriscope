<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    private const RETIRED_TITLES = [
        'Pending PO needs review',
        'Assessment follow-up due',
        'Open PO execution',
        'Served population reminder',
        'System report templates ready',
        'User access active',
    ];

    public function run(): void
    {
        Notification::query()
            ->whereNull('source_id')
            ->whereIn('title', self::RETIRED_TITLES)
            ->delete();

        $notifications = app(NotificationService::class);
        Announcement::query()->with('user')->orderBy('id')->each(function (Announcement $announcement) use ($notifications): void {
            $alreadyGenerated = Notification::query()
                ->where('type', 'announcement')
                ->where('source_module', 'announcements')
                ->where('source_id', $announcement->id)
                ->exists();

            if (! $alreadyGenerated) {
                $notifications->fanOutAnnouncement($announcement);
            }
        });
    }
}
