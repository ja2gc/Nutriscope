<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@nutriscope.local')->firstOrFail();
        $rnd = User::where('email', 'rnd@nutriscope.local')->firstOrFail();
        $photoPath = database_path('seeders/assets/announcements/case-conference.jpg');
        $photoBytes = file_get_contents($photoPath);
        if (! is_string($photoBytes) || $photoBytes === '') {
            throw new \RuntimeException("Announcement seed image is missing or unreadable: {$photoPath}");
        }
        $caseConferencePhoto = 'data:image/jpeg;base64,'.base64_encode($photoBytes);

        $posts = [
            [
                'user_id' => $admin->id,
                'title' => 'Nutrition screening compliance reminder',
                'body' => 'All new admissions must have screening documents uploaded before the end of the shift.',
                'category' => 'Urgent',
                'visibility' => 'All',
                'pinned' => true,
                'attachment' => null,
            ],
            [
                'user_id' => $rnd->id,
                'title' => 'Ward 4B follow-up coordination',
                'body' => 'Please coordinate follow-up timing with the ward nurse before morning tray release.',
                'category' => 'Operational',
                'visibility' => 'All',
                'pinned' => false,
                'attachment' => null,
            ],
            [
                'user_id' => $admin->id,
                'title' => 'FSS tray labeling update',
                'body' => 'FSS team should use the current tray-labeling format for every meal service.',
                'category' => 'Operational',
                'visibility' => 'FSS',
                'pinned' => false,
                'attachment' => null,
            ],
            [
                'user_id' => $rnd->id,
                'title' => 'Dietetic case conference',
                'body' => 'Weekly dietetic case conferences are held Fridays at 2:00 PM in the nutrition office.',
                'category' => 'Event',
                'visibility' => 'All',
                'pinned' => false,
                'attachment' => $caseConferencePhoto,
            ],
            [
                'user_id' => $admin->id,
                'title' => 'Admin reporting window',
                'body' => 'Admin users may review census report preparation notes ahead of the monthly reporting cycle.',
                'category' => 'General',
                'visibility' => 'Admin',
                'pinned' => false,
                'attachment' => null,
            ],
        ];

        foreach ($posts as $post) {
            Announcement::updateOrCreate(
                ['title' => $post['title']],
                $post
            );
        }
    }
}
