<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupRetentionTier;
use App\Enums\BackupState;
use App\Enums\RecoveryStatus;
use App\Models\BackupRun;
use App\Models\RecoveryRequest;
use App\Services\Backup\BackupRetentionService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupRetentionServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_keeps_three_days_two_weeks_and_three_months_without_duplicate_objects(): void
    {
        Carbon::setTestNow('2026-07-31 03:00:00');
        $dates = [
            '2026-07-31', '2026-07-30', '2026-07-29',
            '2026-07-20', '2026-07-13',
            '2026-06-20', '2026-05-20', '2026-04-20',
            '2026-03-20',
        ];

        foreach ($dates as $date) {
            BackupRun::factory()->completed()->create([
                'verified_at' => "{$date} 01:30:00",
                'completed_at' => "{$date} 01:30:00",
            ]);
        }

        app(BackupRetentionService::class)->apply();

        $this->assertSame(3, BackupRun::where('retention_tier', BackupRetentionTier::Daily)->count());
        $this->assertSame(2, BackupRun::where('retention_tier', BackupRetentionTier::Weekly)->count());
        $this->assertSame(3, BackupRun::where('retention_tier', BackupRetentionTier::Monthly)->count());
        $this->assertSame(1, BackupRun::where('state', BackupState::RecentlyDeleted)->count());
        $this->assertSame(8, BackupRun::where('state', BackupState::Completed)->distinct('object_key')->count());
    }

    #[Test]
    public function newest_verified_backup_is_always_retained(): void
    {
        $newest = BackupRun::factory()->completed()->create(['verified_at' => now()]);

        app(BackupRetentionService::class)->apply();

        $this->assertSame(BackupState::Completed, $newest->refresh()->state);
        $this->assertSame(BackupRetentionTier::Daily, $newest->retention_tier);
    }

    #[Test]
    public function an_expired_backup_stays_available_during_every_active_recovery_stage(): void
    {
        $backup = BackupRun::factory()->completed()->create();
        $backup->schedulePeriods()->create([
            'category' => BackupRetentionTier::Daily,
            'period_key' => now()->subDay()->format('Y-m-d'),
            'expires_at' => now()->subMinute(),
        ]);
        RecoveryRequest::factory()->for($backup)->create(['state' => RecoveryStatus::Checking]);

        app(BackupRetentionService::class)->apply();

        $this->assertSame(BackupState::Completed, $backup->refresh()->state);
    }

    #[Test]
    public function scheduled_retention_moves_an_expired_backup_without_waiting_for_another_successful_backup(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');
        BackupRun::factory()->completed()->create(['verified_at' => now()->subHour()]);
        $expired = BackupRun::factory()->completed()->create(['verified_at' => now()->subDays(3)]);
        $expired->schedulePeriods()->create([
            'category' => BackupRetentionTier::Daily,
            'period_key' => '2026-10-02',
            'expires_at' => now()->subMinutes(30),
        ]);

        Artisan::call('schedule:list');
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => $event->getSummaryForDisplay() === 'backups:apply-retention');

        $this->assertNotNull($event);
        $this->assertTrue($event->isDue(app()));
        $event->run(app());

        $this->assertSame(BackupState::RecentlyDeleted, $expired->refresh()->state);
    }

    #[Test]
    public function scheduled_retention_keeps_the_latest_verified_backup_when_all_periods_expire(): void
    {
        $latest = BackupRun::factory()->completed()->create(['verified_at' => now()->subDays(4)]);
        $latest->schedulePeriods()->create([
            'category' => BackupRetentionTier::Daily,
            'period_key' => now()->subDays(4)->format('Y-m-d'),
            'expires_at' => now()->subDay(),
        ]);

        app(BackupRetentionService::class)->apply();

        $this->assertSame(BackupState::Completed, $latest->refresh()->state);
    }
}
