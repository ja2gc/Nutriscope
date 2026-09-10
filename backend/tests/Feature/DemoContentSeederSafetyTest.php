<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\Sop;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\AnnouncementSeeder;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\SopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoContentSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminUserSeeder::class);
    }

    public function test_announcement_copy_is_durable_and_photo_post_is_local(): void
    {
        $this->seed(AnnouncementSeeder::class);

        $copy = Announcement::query()->pluck('body')->implode(' ');
        $this->assertStringNotContainsStringIgnoringCase('tomorrow', $copy);
        $this->assertStringNotContainsStringIgnoringCase('scheduled for Friday', $copy);

        $photo = Announcement::query()->where('title', 'Dietetic case conference')->firstOrFail();
        $this->assertStringStartsWith('data:image/jpeg;base64,', (string) $photo->attachment);
    }

    public function test_notification_demo_uses_real_announcement_fan_out_without_duplicates(): void
    {
        $this->seed(AnnouncementSeeder::class);
        $this->seed(NotificationSeeder::class);
        $this->seed(NotificationSeeder::class);

        $notifications = Notification::query()->get();
        $this->assertCount(7, $notifications);
        $this->assertTrue($notifications->every(
            fn (Notification $notification): bool => $notification->type === 'announcement'
                && $notification->source_module === 'announcements'
                && $notification->source_id !== null
                && Announcement::query()->whereKey($notification->source_id)->exists()
        ));
        $this->assertFalse($notifications->contains('title', 'Pending PO needs review'));
        $this->assertFalse($notifications->contains('title', 'Open PO execution'));
    }

    public function test_sop_seeding_preserves_unrelated_history_and_is_repeatable(): void
    {
        $custom = Sop::query()->create([
            'title' => 'Ward-specific sanitation SOP',
            'body' => 'A manually maintained procedure.',
            'created_by' => User::query()->where('role', 'Admin')->value('id'),
        ]);

        $this->seed(SopSeeder::class);
        $this->seed(SopSeeder::class);

        $this->assertDatabaseHas('sops', ['id' => $custom->id, 'body' => $custom->body]);
        $this->assertSame(3, Sop::query()->where('title', 'Food Service SOP')->count());
        $this->assertSame(4, Sop::query()->count());
    }
}
