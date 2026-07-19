<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccountOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_account_requires_password_and_recovery_email_setup(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new.user@example.com',
            'password' => 'temporary-password',
            'password_confirmation' => 'temporary-password',
            'role' => 'RND',
            'is_active' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.onboarding_required', true);
        $created = User::where('email', 'new.user@example.com')->sole();
        $this->assertTrue($created->must_change_password);
        $this->assertTrue($created->must_set_recovery_email);
    }

    public function test_required_user_can_complete_first_login_setup_without_email_verification(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('temporary-password'),
            'must_change_password' => true,
            'must_set_recovery_email' => true,
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/auth/onboarding', [
            'password' => 'private-password',
            'password_confirmation' => 'private-password',
            'recovery_email' => 'Recovery@Example.com',
        ])->assertOk()
            ->assertJsonPath('user.onboarding_required', false)
            ->assertJsonPath('user.recovery_email', 'recovery@example.com')
            ->assertJsonPath('user.recovery_email_verified', true);

        $user->refresh();
        $this->assertTrue(Hash::check('private-password', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertFalse($user->must_set_recovery_email);
        $this->assertNotNull($user->recovery_email_verified_at);
        $this->assertSame(1, Activity::where('event', 'password_changed')->where('causer_id', $user->id)->count());
        $this->assertSame(1, Activity::where('event', 'recovery_email_changed')->where('causer_id', $user->id)->count());
        $this->assertStringNotContainsString('private-password', Activity::where('causer_id', $user->id)->get()->toJson());
        $this->assertStringNotContainsString('recovery@example.com', Activity::where('causer_id', $user->id)->get()->toJson());
    }

    public function test_completed_user_cannot_bypass_current_password_through_onboarding_endpoint(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('existing-password'),
            'must_change_password' => false,
            'must_set_recovery_email' => false,
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/auth/onboarding', [
            'password' => 'bypass-password',
            'password_confirmation' => 'bypass-password',
            'recovery_email' => 'bypass@example.com',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('existing-password', $user->fresh()->password));
    }

    public function test_skipped_setup_remains_required_until_both_settings_are_completed(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'password' => Hash::make('temporary-password'),
            'must_change_password' => true,
            'must_set_recovery_email' => true,
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/auth/onboarding/skip')
            ->assertOk()->assertJsonPath('user.onboarding_skipped', true);

        $this->assertSame(1, Activity::where('event', 'settings_changed')->where('causer_id', $user->id)->count());

        $this->actingAs($user->fresh(), 'sanctum')->postJson('/api/auth/password', [
            'current_password' => 'temporary-password',
            'password' => 'private-password',
            'password_confirmation' => 'private-password',
        ])->assertOk();

        $this->assertTrue($user->fresh()->must_set_recovery_email);

        $this->actingAs($user->fresh(), 'sanctum')->patchJson('/api/auth/recovery-email', [
            'recovery_email' => 'later@example.com',
        ])->assertOk()->assertJsonPath('user.onboarding_required', false);

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertFalse($user->must_set_recovery_email);
        $this->assertNull($user->onboarding_skipped_at);
        $this->assertNotNull($user->recovery_email_verified_at);
        Notification::assertNothingSent();
    }
}
