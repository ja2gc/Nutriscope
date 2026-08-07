<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupState;
use App\Jobs\CreateDatabaseBackup;
use App\Models\BackupSchedulePeriod;
use App\Models\BackupScheduleSetting;
use App\Models\User;
use App\Services\Backup\BackupReadiness;
use App\Services\Backup\DispatchDueBackups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupScheduleSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow();
    }

    #[Test]
    public function schedules_are_disabled_by_default_and_expose_no_next_runs(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/backup-schedules')
            ->assertOk()
            ->assertJsonPath('data.daily.enabled', false)
            ->assertJsonPath('data.weekly.enabled', false)
            ->assertJsonPath('data.monthly.enabled', false)
            ->assertJsonPath('data.daily.next_at', null)
            ->assertJsonPath('data.message', 'Automatic backups are disabled.');
    }

    #[Test]
    public function admin_can_save_every_toggle_combination_and_each_change_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $this->ready();

        foreach ([false, true] as $daily) {
            foreach ([false, true] as $weekly) {
                foreach ([false, true] as $monthly) {
                    $payload = compact('daily', 'weekly', 'monthly');
                    if (! $daily && ! $weekly && ! $monthly) {
                        $payload['confirm_disable_all'] = true;
                    }

                    $this->actingAs($admin, 'sanctum')
                        ->putJson('/api/admin/backup-schedules', $payload)
                        ->assertOk()
                        ->assertJsonPath('data.daily.enabled', $daily)
                        ->assertJsonPath('data.weekly.enabled', $weekly)
                        ->assertJsonPath('data.monthly.enabled', $monthly);
                }
            }
        }

        $this->assertDatabaseCount('activity_log', 7);
    }

    #[Test]
    public function only_admin_can_change_schedules(): void
    {
        $rnd = User::factory()->rnd()->create();

        $this->actingAs($rnd, 'sanctum')
            ->putJson('/api/admin/backup-schedules', [
                'daily' => true,
                'weekly' => false,
                'monthly' => false,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function enabling_is_rejected_when_backup_readiness_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $this->mock(BackupReadiness::class, function (MockInterface $mock): void {
            $mock->shouldReceive('check')->once()->andReturn([
                'storage' => false,
                'encryption' => true,
                'queue' => true,
                'scheduler' => true,
                'locks' => true,
                'ready' => false,
            ]);
        });

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/backup-schedules', [
                'daily' => true,
                'weekly' => false,
                'monthly' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Automatic backups cannot be enabled until backup readiness checks pass.');

        $this->assertFalse(BackupScheduleSetting::current()->daily);
    }

    #[Test]
    public function disabling_the_final_schedule_requires_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        BackupScheduleSetting::current()->update(['daily' => true]);
        $this->assertTrue(BackupScheduleSetting::current()->daily);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/backup-schedules', [
                'daily' => false,
                'weekly' => false,
                'monthly' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirm_disable_all');
    }

    #[Test]
    public function coordinator_catches_up_and_one_archive_satisfies_overlapping_periods_once(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2027-08-01 02:00:00', 'Asia/Manila')); // Sunday and first of month, after 01:30.
        $settings = BackupScheduleSetting::current();
        $settings->forceFill([
            'daily' => true,
            'weekly' => true,
            'monthly' => true,
        ])->save();
        $this->assertTrue(BackupScheduleSetting::current()->anyEnabled());

        $service = app(DispatchDueBackups::class);
        $first = $service->handle(now('Asia/Manila')->toImmutable());
        $second = $service->handle(now('Asia/Manila')->toImmutable());

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertDatabaseCount('backup_runs', 1);
        $this->assertDatabaseCount('backup_schedule_periods', 3);
        $this->assertSame(
            ['daily:2027-08-01', 'monthly:2027-08', 'weekly:2027-W30'],
            BackupSchedulePeriod::query()->orderBy('category')->get()
                ->map(fn (BackupSchedulePeriod $period): string => "{$period->category->value}:{$period->period_key}")
                ->all(),
        );
        $this->assertSame(BackupState::Queued, $first->state);
        Queue::assertPushed(CreateDatabaseBackup::class, 1);
    }

    #[Test]
    public function coordinator_steps_back_before_the_target_time(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2027-08-01 01:00:00', 'Asia/Manila'));
        $settings = BackupScheduleSetting::current();
        $settings->forceFill([
            'daily' => true,
            'weekly' => true,
            'monthly' => true,
        ])->save();
        $this->assertTrue(BackupScheduleSetting::current()->anyEnabled());

        app(DispatchDueBackups::class)->handle(now('Asia/Manila')->toImmutable());

        $this->assertDatabaseHas('backup_schedule_periods', ['category' => 'daily', 'period_key' => '2027-07-31']);
        $this->assertDatabaseHas('backup_schedule_periods', ['category' => 'weekly', 'period_key' => '2027-W29']);
        $this->assertDatabaseHas('backup_schedule_periods', ['category' => 'monthly', 'period_key' => '2027-07']);
    }

    private function ready(): void
    {
        $this->mock(BackupReadiness::class, function (MockInterface $mock): void {
            $mock->shouldReceive('check')->zeroOrMoreTimes()->andReturn([
                'storage' => true,
                'encryption' => true,
                'queue' => true,
                'scheduler' => true,
                'locks' => true,
                'ready' => true,
            ]);
        });
    }
}
