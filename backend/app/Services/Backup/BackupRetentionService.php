<?php

namespace App\Services\Backup;

use App\Enums\BackupRetentionTier;
use App\Enums\BackupState;
use App\Models\BackupRun;
use Illuminate\Support\Collection;

class BackupRetentionService
{
    public function apply(): void
    {
        $verified = BackupRun::verified()
            ->orderByDesc('verified_at')
            ->get();

        if ($verified->isEmpty()) {
            return;
        }

        $daily = $verified
            ->unique(fn (BackupRun $backup): string => $backup->verified_at->format('Y-m-d'))
            ->take(config('nutriscope-backups.retention.daily'));
        $dailyCutoff = $daily->last()->verified_at->copy()->startOfDay();
        $olderThanDaily = $verified->filter(
            fn (BackupRun $backup): bool => $backup->verified_at->lt($dailyCutoff),
        );
        $weekly = $olderThanDaily
            ->unique(fn (BackupRun $backup): string => $backup->verified_at->format('o-W'))
            ->take(config('nutriscope-backups.retention.weekly'));
        $olderThanWeekly = $weekly->isNotEmpty()
            ? $weekly->last()->verified_at->copy()->startOfWeek()
            : $dailyCutoff;
        $monthly = $verified
            ->filter(fn (BackupRun $backup): bool => $backup->verified_at->lt($olderThanWeekly))
            ->unique(fn (BackupRun $backup): string => $backup->verified_at->format('Y-m'))
            ->take(config('nutriscope-backups.retention.monthly'));

        $this->assignTier($daily, BackupRetentionTier::Daily);
        $this->assignTier($weekly, BackupRetentionTier::Weekly);
        $this->assignTier($monthly, BackupRetentionTier::Monthly);

        $keptIds = $daily->merge($weekly)->merge($monthly)->modelKeys();
        $verified
            ->whereNotIn('id', $keptIds)
            ->each(function (BackupRun $backup): void {
                if ($backup->recoveryRequests()->where('state', 'requested')->exists()) {
                    return;
                }

                $backup->update([
                    'state' => BackupState::RecentlyDeleted,
                    'retention_tier' => null,
                    'retention_expires_at' => null,
                    'deleted_at' => now(),
                    'recoverable_until' => now()->addHours(config('nutriscope-backups.recoverable_hours')),
                ]);
            });
    }

    /** @param Collection<int, BackupRun> $backups */
    private function assignTier(Collection $backups, BackupRetentionTier $tier): void
    {
        $backups->each(function (BackupRun $backup) use ($tier): void {
            $expiresAt = match ($tier) {
                BackupRetentionTier::Daily => $backup->verified_at->copy()->addDays(3),
                BackupRetentionTier::Weekly => $backup->verified_at->copy()->addWeeks(2),
                BackupRetentionTier::Monthly => $backup->verified_at->copy()->addMonthsNoOverflow(3),
            };

            $backup->update([
                'retention_tier' => $tier,
                'retention_expires_at' => $expiresAt,
            ]);
        });
    }
}
