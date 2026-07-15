<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\NcpRecord;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Notifications — the two defense triggers (rnd.md §7):
 *  A. announcement posted → fan out by the announcement's visibility setting.
 *  B. upcoming follow-up → 1 day before intervention.next_followup_date, to the owning RND.
 */
class NotificationTriggersTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_with_all_visibility_notifies_other_active_users(): void
    {
        $author = User::factory()->create(['role' => 'RND']);
        $rnd2 = User::factory()->create(['role' => 'RND']);
        $fss = User::factory()->create(['role' => 'FSS']);
        $admin = User::factory()->create(['role' => 'Admin']);

        $this->actingAs($author, 'sanctum')->postJson('/api/rnd/announcements', [
            'title' => 'Town hall', 'body' => 'All staff please attend.',
            'category' => 'General', 'visibility' => 'All',
        ])->assertStatus(201);

        // Everyone except the author is notified.
        $this->assertDatabaseHas('notifications', ['user_id' => $rnd2->id, 'type' => 'announcement']);
        $this->assertDatabaseHas('notifications', ['user_id' => $fss->id, 'type' => 'announcement']);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'announcement']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $author->id]);
    }

    public function test_announcement_with_fss_visibility_only_notifies_fss(): void
    {
        $author = User::factory()->create(['role' => 'RND']);
        $rnd2 = User::factory()->create(['role' => 'RND']);
        $fss = User::factory()->create(['role' => 'FSS']);

        $this->actingAs($author, 'sanctum')->postJson('/api/rnd/announcements', [
            'title' => 'Kitchen notice', 'body' => 'FSS only.',
            'category' => 'Operational', 'visibility' => 'FSS',
        ])->assertStatus(201);

        $this->assertDatabaseHas('notifications', ['user_id' => $fss->id, 'type' => 'announcement']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $rnd2->id]);
    }

    public function test_follow_up_reminder_notifies_owning_rnd_one_day_before(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $ncp = $this->ncpWithFollowup($rnd, now()->addDay()->toDateString());

        Artisan::call('notifications:follow-up-reminders');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $rnd->id,
            'type' => 'follow_up',
            'source_id' => $ncp->id,
        ]);
        $this->assertStringContainsString(
            'Maria Luisa De la Cruz',
            Notification::where('type', 'follow_up')->sole()->message,
        );
    }

    public function test_follow_up_reminder_skips_dates_other_than_tomorrow(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $this->ncpWithFollowup($rnd, now()->addDays(5)->toDateString());

        Artisan::call('notifications:follow-up-reminders');

        $this->assertDatabaseMissing('notifications', ['type' => 'follow_up']);
    }

    public function test_follow_up_reminder_is_idempotent(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $this->ncpWithFollowup($rnd, now()->addDay()->toDateString());

        Artisan::call('notifications:follow-up-reminders');
        Artisan::call('notifications:follow-up-reminders');

        $this->assertSame(1, Notification::where('type', 'follow_up')->count());
    }

    private function ncpWithFollowup(User $rnd, string $date): NcpRecord
    {
        $patient = Patient::factory()->create([
            'name' => 'Stale Patient Name',
            'first_name' => 'Maria Luisa',
            'last_name' => 'De la Cruz',
        ]);
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
        ]);
        Intervention::create([
            'ncp_record_id' => $ncp->id,
            'energy_kcal' => 1800,
            'next_followup_date' => $date,
        ]);

        return $ncp;
    }
}
