<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that the shared /api/notifications routes are accessible to any
 * authenticated role (Admin, RND, FSS) — not only to RND.
 */
class NotificationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_their_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        Notification::create([
            'user_id' => $admin->id,
            'title'   => 'Test notice',
            'message' => 'Body text',
            'type'    => 'announcement',
            'read'    => false,
        ]);

        $this->actingAs($admin, 'sanctum')
             ->getJson('/api/notifications')
             ->assertOk()
             ->assertJsonPath('data.0.title', 'Test notice');
    }

    public function test_admin_can_mark_their_notification_read(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $notification = Notification::create([
            'user_id' => $admin->id,
            'title'   => 'Mark me',
            'message' => 'Body',
            'type'    => 'announcement',
            'read'    => false,
        ]);

        $this->actingAs($admin, 'sanctum')
             ->patchJson("/api/notifications/{$notification->uuid}/read")
             ->assertOk()
             ->assertJsonFragment(['message' => 'Notification marked as read.']);

        $this->assertDatabaseHas('notifications', [
            'id'   => $notification->id,
            'read' => true,
        ]);
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        $admin  = User::factory()->create(['role' => 'Admin']);
        $other  = User::factory()->create(['role' => 'RND']);

        $notification = Notification::create([
            'user_id' => $other->id,
            'title'   => 'Not yours',
            'message' => 'Body',
            'type'    => 'announcement',
            'read'    => false,
        ]);

        $this->actingAs($admin, 'sanctum')
             ->patchJson("/api/notifications/{$notification->uuid}/read")
             ->assertForbidden();
    }
}
