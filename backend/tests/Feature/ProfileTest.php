<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Self-service profile (rnd.md §9): authenticated users update their own
 * name/email and change their password. `name` is the same field used as the
 * report "prepared by". No avatar (backend has no such column).
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Old', 'email' => 'old@example.com']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', [
                'first_name' => 'New',
                'last_name' => 'Name',
                'email' => 'new@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id, 'name' => 'New Name', 'email' => 'new@example.com',
        ]);
        $this->assertSame(1, Activity::where('event', 'profile_changed')->count());
    }

    public function test_user_can_update_extended_profile_fields_but_not_role(): void
    {
        $user = User::factory()->create([
            'name' => 'Old',
            'email' => 'old@example.com',
            'role' => 'RND',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', [
                'first_name' => 'New',
                'last_name' => 'Name',
                'email' => 'new@example.com',
                'contact_number' => '+63 917 000 0000',
                'profile_photo' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                'role' => 'Admin',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('contact_number', '+63 917 000 0000')
            ->assertJsonPath('profile_photo', '/api/auth/profile-photo')
            ->assertJsonPath('role', 'RND');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'contact_number' => '+63 917 000 0000',
            'profile_photo' => null,
            'role' => 'RND',
        ]);
        $this->assertNotNull($user->fresh()->profile_photo_stored_object_id);
    }

    public function test_profile_update_rejects_email_taken_by_another_user(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_profile_update_allows_keeping_own_email(): void
    {
        $user = User::factory()->create(['name' => 'Me', 'email' => 'mine@example.com']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', [
                'first_name' => 'Me',
                'last_name' => 'Renamed',
                'email' => 'mine@example.com',
            ])
            ->assertOk();
    }

    public function test_profile_photo_must_be_supported_data_image_and_size_limited(): void
    {
        $user = User::factory()->create(['name' => 'Me', 'email' => 'mine@example.com']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', [
                'name' => 'Me',
                'email' => 'mine@example.com',
                'profile_photo' => 'not-an-image',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_photo']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', [
                'name' => 'Me',
                'email' => 'mine@example.com',
                'profile_photo' => 'data:image/png;base64,'.str_repeat('a', 300001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_photo']);
    }

    public function test_user_can_change_password_with_correct_current(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpass123')]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/password', [
                'current_password' => 'oldpass123',
                'password' => 'newpass456',
                'password_confirmation' => 'newpass456',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('newpass456', $user->fresh()->password));
    }

    public function test_password_change_rejects_wrong_current(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpass123')]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/password', [
                'current_password' => 'wrongpass',
                'password' => 'newpass456',
                'password_confirmation' => 'newpass456',
            ])
            ->assertStatus(422);

        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));
    }
}
