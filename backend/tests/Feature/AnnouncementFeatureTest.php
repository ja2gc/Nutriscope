<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnnouncementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rnd_can_create_announcement_without_pinning(): void
    {
        $rnd = $this->user('RND', 'rnd-create@example.com');

        $response = $this->actingAs($rnd, 'sanctum')->postJson('/api/rnd/announcements', [
            'title' => 'Tray schedule update',
            'body' => 'Breakfast tray dispatch moves to 5:30 AM tomorrow.',
            'category' => 'Operational',
            'visibility' => 'All',
            'pinned' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Tray schedule update')
            ->assertJsonPath('data.pinned', false)
            ->assertJsonPath('data.author.id', $rnd->id);

        $this->assertDatabaseHas('announcements', [
            'user_id' => $rnd->id,
            'title' => 'Tray schedule update',
            'pinned' => false,
        ]);
    }

    public function test_rnd_lists_all_posts_and_own_targeted_posts_only(): void
    {
        $rnd = $this->user('RND', 'rnd-list@example.com');
        $otherRnd = $this->user('RND', 'rnd-other@example.com');
        $admin = $this->user('Admin', 'admin-list@example.com');

        Announcement::forceCreate([
            'user_id' => $admin->id,
            'title' => 'All hands briefing',
            'body' => 'General department briefing.',
            'category' => 'General',
            'visibility' => 'All',
        ]);

        Announcement::forceCreate([
            'user_id' => $otherRnd->id,
            'title' => 'FSS only prep reminder',
            'body' => 'Kitchen team only.',
            'category' => 'Operational',
            'visibility' => 'FSS',
        ]);

        Announcement::forceCreate([
            'user_id' => $rnd->id,
            'title' => 'Own FSS handoff',
            'body' => 'Posted by the current RND.',
            'category' => 'Event',
            'visibility' => 'FSS',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')->getJson('/api/rnd/announcements');

        $response->assertOk()
            ->assertJsonFragment(['title' => 'All hands briefing'])
            ->assertJsonFragment(['title' => 'Own FSS handoff'])
            ->assertJsonMissing(['title' => 'FSS only prep reminder']);
    }

    public function test_fss_lists_fss_and_all_announcements_only(): void
    {
        $admin = $this->user('Admin', 'admin-fss-feed@example.com');
        $rnd   = $this->user('RND', 'rnd-fss-feed@example.com');
        $fss   = $this->user('FSS', 'fss-feed@example.com');

        Announcement::forceCreate([
            'user_id' => $admin->id, 'title' => 'All hands',
            'body' => 'x', 'category' => 'General', 'visibility' => 'All',
        ]);
        Announcement::forceCreate([
            'user_id' => $rnd->id, 'title' => 'Kitchen prep reminder',
            'body' => 'x', 'category' => 'Operational', 'visibility' => 'FSS',
        ]);
        Announcement::forceCreate([
            'user_id' => $admin->id, 'title' => 'Admin only memo',
            'body' => 'x', 'category' => 'General', 'visibility' => 'Admin',
        ]);

        $response = $this->actingAs($fss, 'sanctum')->getJson('/api/fss/announcements');

        $response->assertOk()
            ->assertJsonFragment(['title' => 'All hands'])
            ->assertJsonFragment(['title' => 'Kitchen prep reminder'])
            ->assertJsonMissing(['title' => 'Admin only memo']);
    }

    public function test_admin_can_pin_announcement(): void
    {
        $admin = $this->user('Admin', 'admin-pin@example.com');
        $rnd = $this->user('RND', 'rnd-pin@example.com');
        $announcement = Announcement::forceCreate([
            'user_id' => $rnd->id,
            'title' => 'Menu cycle review',
            'body' => 'Review menu cycle adjustments.',
            'category' => 'Event',
            'visibility' => 'All',
            'pinned' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/announcements/{$announcement->id}", [
            'pinned' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.pinned', true);

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'pinned' => true,
        ]);
    }

    public function test_rnd_cannot_edit_another_users_announcement(): void
    {
        $owner = $this->user('RND', 'rnd-owner@example.com');
        $other = $this->user('RND', 'rnd-denied@example.com');
        $announcement = Announcement::forceCreate([
            'user_id' => $owner->id,
            'title' => 'Original title',
            'body' => 'Original body.',
            'category' => 'General',
            'visibility' => 'All',
        ]);

        $response = $this->actingAs($other, 'sanctum')->patchJson("/api/rnd/announcements/{$announcement->id}", [
            'title' => 'Changed title',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Original title',
        ]);
    }

    private function user(string $role, string $email): User
    {
        return User::forceCreate([
            'name' => "{$role} User",
            'email' => $email,
            'password' => Hash::make('pass'),
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
