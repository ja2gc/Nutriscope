<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Notification;
use App\Models\ReportBranding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
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
            'role' => 'Admin',
            'password' => Hash::make('password'),
        ]);
        $this->rnd = User::factory()->create([
            'role' => 'RND',
            'password' => Hash::make('password'),
        ]);
        $this->fss = User::factory()->create([
            'role' => 'FSS',
            'password' => Hash::make('password'),
        ]);
    }

    // ===== USER MANAGEMENT =====

    public function test_admin_can_list_all_users(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'is_active']]]);
    }

    public function test_user_index_payload_includes_is_active(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users');

        $response->assertOk();
        $users = $response->json('data');
        $this->assertNotEmpty($users);
        $this->assertArrayHasKey('is_active', $users[0]);
    }

    public function test_user_show_payload_includes_is_active(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/users/{$this->rnd->uuid}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'is_active']]);
    }

    public function test_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/users', [
                'name' => 'New RND User',
                'email' => 'newrnd@nutriscope.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'RND',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'RND');

        $this->assertDatabaseHas('users', ['email' => 'newrnd@nutriscope.com']);
        $this->assertSame(1, Activity::where('event', 'created')->where('subject_type', User::class)->count());
    }

    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create(['role' => 'RND']);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$user->uuid}", [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
        $activity = Activity::where('event', 'updated')->where('subject_id', $user->id)->firstOrFail();
        $this->assertSame(['name'], $activity->properties['details']['changed_fields']);
    }

    public function test_admin_role_and_status_change_is_one_safe_account_event(): void
    {
        $user = User::factory()->create(['role' => 'RND', 'is_active' => true]);
        $user->createToken('existing');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->uuid}", [
                'role' => 'FSS',
                'is_active' => false,
            ])->assertOk();

        $activity = Activity::where('event', 'updated')->where('subject_id', $user->id)->sole();
        $this->assertSame(['is_active', 'role'], $activity->properties['details']['changed_fields']);
        $this->assertStringNotContainsString($user->email, $activity->toJson());
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_admin_password_update_is_one_password_reset_event_without_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $secret = 'PATCH-PASSWORD-SENTINEL';

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->uuid}", [
                'password' => $secret,
                'password_confirmation' => $secret,
            ])->assertOk();

        $activity = Activity::where('subject_id', $user->id)->sole();
        $this->assertSame('password_reset', $activity->event);
        $this->assertStringNotContainsString($secret, $activity->toJson());
    }

    public function test_admin_password_update_rolls_back_when_required_audit_is_unavailable(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        config(['activitylog.enabled' => false]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->uuid}", [
                'password' => 'NewPass2026!',
                'password_confirmation' => 'NewPass2026!',
            ])->assertInternalServerError();

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_admin_mixed_password_and_profile_update_has_one_safe_complete_event(): void
    {
        $user = User::factory()->create(['name' => 'Before', 'password' => Hash::make('old-password')]);
        $secret = 'MIXED-PASSWORD-SENTINEL';

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->uuid}", [
                'name' => 'After',
                'password' => $secret,
                'password_confirmation' => $secret,
            ])->assertOk();

        $activity = Activity::where('subject_id', $user->id)->sole();
        $this->assertSame('updated', $activity->event);
        $this->assertSame(['name', 'password'], $activity->properties['details']['changed_fields']);
        $this->assertStringNotContainsString($secret, $activity->toJson());
    }

    public function test_admin_can_deactivate_user(): void
    {
        $user = User::factory()->create(['role' => 'RND']);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/users/{$user->uuid}");

        $response->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertFalse(User::withTrashed()->findOrFail($user->id)->is_active);
        $this->assertSame(1, Activity::where('event', 'deleted')->where('subject_id', $user->id)->count());
    }

    public function test_user_creation_requires_unique_email(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/users', [
                'name' => 'Duplicate',
                'email' => $this->rnd->email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'RND',
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
            ->postJson("/api/admin/users/{$user->uuid}/reset-password", [
                'password' => 'NewPass2026!',
                'password_confirmation' => 'NewPass2026!',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Password reset.');

        $this->assertTrue(Hash::check('NewPass2026!', $user->fresh()->password));
        $this->assertSame(1, Activity::where('event', 'password_reset')->where('subject_id', $user->id)->count());
    }

    public function test_admin_password_reset_requires_valid_confirmation(): void
    {
        $user = User::factory()->create(['role' => 'RND']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/users/{$user->uuid}/reset-password", [
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
                'title' => 'Patient Follow-up',
                'event_date' => '2026-06-15',
                'event_type' => 'followup',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Patient Follow-up');

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Patient Follow-up',
            'user_id' => $this->rnd->id,
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
            ->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_rnd_can_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->rnd->id,
            'read' => false,
        ]);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/notifications/{$notification->uuid}/read");

        $response->assertOk();
        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'read' => true]);
    }

    public function test_rnd_can_mark_all_notifications_as_read(): void
    {
        Notification::factory(5)->create(['user_id' => $this->rnd->id, 'read' => false]);

        $response = $this->actingAs($this->rnd)
            ->patchJson('/api/notifications/read-all');

        $response->assertOk();
        $this->assertEquals(
            0,
            Notification::where('user_id', $this->rnd->id)->where('read', false)->count()
        );
    }

    // ===== REPORT BRANDING (Admin) =====

    public function test_admin_can_get_report_branding(): void
    {
        // Ensure singleton row exists.
        ReportBranding::singleton();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/report-branding');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'hospital_name']]);
    }

    public function test_admin_can_update_report_branding(): void
    {
        ReportBranding::singleton();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/report-branding', [
                'hospital_name' => 'X Hospital',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.hospital_name', 'X Hospital');

        $this->assertDatabaseHas('report_branding', ['hospital_name' => 'X Hospital']);
    }

    public function test_rnd_cannot_access_admin_report_branding(): void
    {
        $response = $this->actingAs($this->rnd)
            ->getJson('/api/admin/report-branding');

        $response->assertForbidden();
    }
}
