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

        $posts = [
            [
                'user_id' => $admin->id,
                'title' => 'Nutrition screening compliance reminder',
                'body' => 'All new admissions must have screening documents uploaded before the end of the shift.',
                'category' => 'Urgent',
                'visibility' => 'All',
                'pinned' => true,
            ],
            [
                'user_id' => $rnd->id,
                'title' => 'Ward 4B follow-up coordination',
                'body' => 'Please coordinate follow-up timing with the ward nurse before morning tray release.',
                'category' => 'Operational',
                'visibility' => 'All',
                'pinned' => false,
            ],
            [
                'user_id' => $admin->id,
                'title' => 'FSS tray labeling update',
                'body' => 'FSS team should apply the updated tray labeling format starting with tomorrow breakfast service.',
                'category' => 'Operational',
                'visibility' => 'FSS',
                'pinned' => false,
            ],
            [
                'user_id' => $rnd->id,
                'title' => 'Dietetic case conference',
                'body' => 'The weekly case conference is scheduled for Friday at 2:00 PM in the nutrition office.',
                'category' => 'Event',
                'visibility' => 'All',
                'pinned' => false,
            ],
            [
                'user_id' => $admin->id,
                'title' => 'Admin reporting window',
                'body' => 'Admin users may review census report preparation notes ahead of the monthly reporting cycle.',
                'category' => 'General',
                'visibility' => 'Admin',
                'pinned' => false,
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
