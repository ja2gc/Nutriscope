<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\StoredObject;
use App\Models\User;
use App\Services\StoredObjectStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_announcement_exposes_and_streams_its_actual_author_photo(): void
    {
        Storage::fake((string) config('filesystems.private_uploads'));
        $owner = $this->user('RND', 'rnd-photo@example.com');
        $viewer = $this->user('FSS', 'fss-photo@example.com');
        $object = app(StoredObjectStorage::class)->storeBytes(
            file_get_contents(database_path('seeders/assets/profile/rnd.jpg')),
            'image/jpeg', 'jpg', 'profile', 'announcement-author.jpg',
        );
        $owner->forceFill(['profile_photo_stored_object_id' => $object->id])->save();
        $announcement = Announcement::forceCreate([
            'user_id' => $owner->id, 'title' => 'Photo contract', 'body' => 'Visible.',
            'category' => 'General', 'visibility' => 'All',
        ]);

        $url = "/api/announcements/{$announcement->uuid}/author-photo";
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($viewer, 'sanctum')->getJson('/api/fss/announcements')
            ->assertOk()->assertJsonPath('data.0.author.profile_photo', $url);
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/jpeg');

        $hidden = Announcement::forceCreate([
            'user_id' => $owner->id, 'title' => 'Admin only', 'body' => 'Hidden.',
            'category' => 'General', 'visibility' => 'Admin',
        ]);
        $this->get("/api/announcements/{$hidden->uuid}/author-photo")->assertNotFound();
    }

    public function test_rnd_can_create_pinned_announcement(): void
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
            ->assertJsonPath('data.pinned', true)
            ->assertJsonPath('data.author.id', $rnd->uuid);

        $this->assertDatabaseHas('announcements', [
            'user_id' => $rnd->id,
            'title' => 'Tray schedule update',
            'pinned' => true,
        ]);
    }

    public function test_rnd_can_pin_own_announcement_when_updating(): void
    {
        $rnd = $this->user('RND', 'rnd-own-pin@example.com');
        $announcement = Announcement::forceCreate([
            'user_id' => $rnd->id,
            'title' => 'Menu cycle review',
            'body' => 'Review menu cycle adjustments.',
            'category' => 'Event',
            'visibility' => 'All',
            'pinned' => false,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/announcements/{$announcement->uuid}", [
            'pinned' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.pinned', true);

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'pinned' => true,
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
        $rnd = $this->user('RND', 'rnd-fss-feed@example.com');
        $fss = $this->user('FSS', 'fss-feed@example.com');

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

    public function test_admin_can_pin_own_announcement(): void
    {
        $admin = $this->user('Admin', 'admin-pin@example.com');
        $announcement = Announcement::forceCreate([
            'user_id' => $admin->id,
            'title' => 'Menu cycle review',
            'body' => 'Review menu cycle adjustments.',
            'category' => 'Event',
            'visibility' => 'All',
            'pinned' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/announcements/{$announcement->uuid}", [
            'pinned' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.pinned', true);

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'pinned' => true,
        ]);
    }

    public function test_announcement_accepts_multiple_image_attachments(): void
    {
        Storage::fake((string) config('filesystems.private_uploads'));
        $rnd = $this->user('RND', 'rnd-images@example.com');
        $image = 'data:image/png;base64,'.base64_encode($this->pngBytes());

        $response = $this->actingAs($rnd, 'sanctum')->postJson('/api/rnd/announcements', [
            'title' => 'Prep photos',
            'body' => 'Two images attached.',
            'category' => 'Operational',
            'visibility' => 'All',
            'attachments' => [$image, $image],
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.attachments')
            ->assertJsonPath('data.attachment', fn (string $src): bool => str_starts_with($src, 'data:image/png;base64,'));

        $announcement = Announcement::query()->where('user_id', $rnd->id)->firstOrFail();
        $storedReferences = json_decode($announcement->attachment, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $storedReferences['version']);
        $this->assertCount(2, $storedReferences['stored_object_uuids']);
        $this->assertStringNotContainsString('base64', $announcement->attachment);
        $this->assertDatabaseCount('stored_objects', 2);
        $this->assertSame('announcement', StoredObject::query()->firstOrFail()->purpose);
    }

    public function test_announcement_enforces_image_count_size_and_body_limits(): void
    {
        $rnd = $this->user('RND', 'rnd-image-limits@example.com');
        $url = '/api/rnd/announcements';
        $image = 'data:image/png;base64,'.base64_encode($this->pngBytes());

        $this->actingAs($rnd, 'sanctum')->postJson($url, [
            'title' => 'Too many images',
            'body' => 'Body',
            'category' => 'General',
            'visibility' => 'All',
            'attachments' => array_fill(0, 11, $image),
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments');

        $this->actingAs($rnd, 'sanctum')->postJson($url, [
            'title' => 'Oversized image',
            'body' => 'Body',
            'category' => 'General',
            'visibility' => 'All',
            'attachments' => ['data:image/png;base64,'.base64_encode(str_repeat('x', 5 * 1024 * 1024 + 1))],
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');

        $this->actingAs($rnd, 'sanctum')->postJson($url, [
            'title' => 'Invalid image bytes',
            'body' => 'Body',
            'category' => 'General',
            'visibility' => 'All',
            'attachments' => ['data:image/png;base64,'.base64_encode('not an image')],
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');

        $this->actingAs($rnd, 'sanctum')->postJson($url, [
            'title' => 'Long body',
            'body' => str_repeat('x', 5001),
            'category' => 'General',
            'visibility' => 'All',
        ])->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_replacing_announcement_images_cleans_up_previous_private_objects(): void
    {
        Storage::fake((string) config('filesystems.private_uploads'));
        $rnd = $this->user('RND', 'rnd-replace-images@example.com');
        $image = 'data:image/png;base64,'.base64_encode($this->pngBytes());
        $created = $this->actingAs($rnd, 'sanctum')->postJson('/api/rnd/announcements', [
            'title' => 'Replace images',
            'body' => 'Body',
            'category' => 'General',
            'visibility' => 'All',
            'attachments' => [$image],
        ])->assertCreated();
        $uuid = $created->json('data.id');

        $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/announcements/{$uuid}", [
            'attachments' => [],
        ])->assertOk()->assertJsonPath('data.attachments', []);

        $this->assertDatabaseCount('stored_objects', 0);
        $this->assertDatabaseHas('announcements', ['uuid' => $uuid, 'attachment' => null]);
    }

    public function test_updating_announcement_with_empty_attachments_removes_images(): void
    {
        $rnd = $this->user('RND', 'rnd-clear-images@example.com');
        $announcement = Announcement::forceCreate([
            'user_id' => $rnd->id,
            'title' => 'Prep photos',
            'body' => 'Two images attached.',
            'category' => 'Operational',
            'visibility' => 'All',
            'attachment' => json_encode(['data:image/png;base64,one', 'data:image/jpeg;base64,two'], JSON_THROW_ON_ERROR),
        ]);

        $response = $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/announcements/{$announcement->uuid}", [
            'attachments' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.attachments', [])
            ->assertJsonPath('data.attachment', null);

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'attachment' => null,
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

        $response = $this->actingAs($other, 'sanctum')->patchJson("/api/rnd/announcements/{$announcement->uuid}", [
            'title' => 'Changed title',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Original title',
        ]);
    }

    public function test_admin_cannot_edit_another_users_announcement_but_can_delete_it(): void
    {
        $owner = $this->user('RND', 'rnd-admin-boundary@example.com');
        $admin = $this->user('Admin', 'admin-boundary@example.com');
        $announcement = Announcement::forceCreate([
            'user_id' => $owner->id,
            'title' => 'Owner text',
            'body' => 'Owner body.',
            'category' => 'General',
            'visibility' => 'All',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/announcements/{$announcement->uuid}", ['title' => 'Admin edit'])
            ->assertForbidden();
        $this->assertDatabaseHas('announcements', ['id' => $announcement->id, 'title' => 'Owner text']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/announcements/{$announcement->uuid}")
            ->assertNoContent();
        $this->assertModelMissing($announcement);
    }

    public function test_feed_exposes_server_authorized_edit_and_delete_actions(): void
    {
        $owner = $this->user('RND', 'rnd-action-owner@example.com');
        $other = $this->user('RND', 'rnd-action-other@example.com');
        $admin = $this->user('Admin', 'admin-actions@example.com');
        Announcement::forceCreate([
            'user_id' => $owner->id,
            'title' => 'Action boundary',
            'body' => 'Owner body.',
            'category' => 'General',
            'visibility' => 'All',
        ]);

        $this->actingAs($other, 'sanctum')->getJson('/api/rnd/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.can_edit', false)
            ->assertJsonPath('data.0.can_delete', false);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.can_edit', false)
            ->assertJsonPath('data.0.can_delete', true);
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

    private function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
