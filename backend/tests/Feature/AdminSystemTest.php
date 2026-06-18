<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $rnd;
    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role'     => 'Admin',
            'password' => Hash::make('password'),
        ]);
        $this->rnd = User::factory()->create([
            'role'     => 'RND',
            'password' => Hash::make('password'),
        ]);
        $this->fss = User::factory()->create([
            'role'     => 'FSS',
            'password' => Hash::make('password'),
        ]);
    }

    // ===== USER MANAGEMENT =====

    public function test_admin_can_list_all_users(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role']]]);
    }

    public function test_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/users', [
                'name'                  => 'New RND User',
                'email'                 => 'newrnd@nutriscope.com',
                'password'              => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role'                  => 'RND',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'RND');

        $this->assertDatabaseHas('users', ['email' => 'newrnd@nutriscope.com']);
    }

    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create(['role' => 'RND']);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$user->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_admin_can_deactivate_user(): void
    {
        $user = User::factory()->create(['role' => 'RND']);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/users/{$user->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_user_creation_requires_unique_email(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/users', [
                'name'                  => 'Duplicate',
                'email'                 => $this->rnd->email,
                'password'              => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role'                  => 'RND',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_rnd_cannot_access_admin_user_management(): void
    {
        $response = $this->actingAs($this->rnd)
            ->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_can_reset_user_password(): void
    {
        $user = User::factory()->create([
            'role' => 'RND',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/reset-password", [
                'password' => 'NewPass2026!',
                'password_confirmation' => 'NewPass2026!',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Password reset.');

        $this->assertTrue(Hash::check('NewPass2026!', $user->fresh()->password));
    }

    public function test_admin_password_reset_requires_valid_confirmation(): void
    {
        $user = User::factory()->create(['role' => 'RND']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/reset-password", [
                'password' => 'short',
                'password_confirmation' => 'different',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ===== AUDIT LOGS =====

    public function test_admin_can_list_audit_logs(): void
    {
        // Activity log entries are created automatically via audit middleware
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/audit-logs');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ===== CALENDAR EVENTS =====

    public function test_rnd_can_create_calendar_event(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/calendar-events', [
                'title'      => 'Patient Follow-up',
                'event_date' => '2026-06-15',
                'event_type' => 'followup',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Patient Follow-up');

        $this->assertDatabaseHas('calendar_events', [
            'title'      => 'Patient Follow-up',
            'user_id'    => $this->rnd->id,
        ]);
    }

    public function test_rnd_can_list_own_calendar_events(): void
    {
        CalendarEvent::factory(3)->create(['user_id' => $this->rnd->id]);
        CalendarEvent::factory(2)->create(['user_id' => $this->fss->id]);

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/calendar-events');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_calendar_event_requires_title_and_date(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/calendar-events', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'event_date']);
    }

    // ===== NOTIFICATIONS =====

    public function test_rnd_can_list_own_notifications(): void
    {
        Notification::factory(4)->create(['user_id' => $this->rnd->id, 'read' => false]);
        Notification::factory(2)->create(['user_id' => $this->fss->id]);

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/notifications');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_rnd_can_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->rnd->id,
            'read'    => false,
        ]);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/rnd/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'read' => true]);
    }

    public function test_rnd_can_mark_all_notifications_as_read(): void
    {
        Notification::factory(5)->create(['user_id' => $this->rnd->id, 'read' => false]);

        $response = $this->actingAs($this->rnd)
            ->patchJson('/api/rnd/notifications/read-all');

        $response->assertOk();
        $this->assertEquals(
            0,
            Notification::where('user_id', $this->rnd->id)->where('read', false)->count()
        );
    }
}
