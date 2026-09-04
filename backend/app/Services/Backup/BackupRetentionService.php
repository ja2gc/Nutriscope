<?php

namespace App\Services\Backup;

use App\Enums\BackupRetentionTier;
use App\Enums\BackupSource;
use App\Enums\BackupState;
use App\Models\BackupManifestObject;
use App\Models\BackupRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupRetentionService
{
    public function purge(BackupRun $backup): void
    {
        if (! in_array($backup->state, [BackupState::Failed, BackupState::RecentlyDeleted], true)) {
            throw new RuntimeException('Only failed or recently deleted backups can be purged.');
        }

        if (filled($backup->object_key) && ! Storage::disk($backup->storage_disk)->delete($backup->object_key)) {
            throw new RuntimeException('Backup object could not be removed.');
        }

        $protectedKeys = $backup->manifest?->objects()->pluck('protected_key')->all() ?? [];
        if ($backup->manifest !== null) {
            Storage::disk($backup->manifest->storage_disk)->delete($backup->manifest->object_key);
            $backup->manifest->delete();
        }
        foreach ($protectedKeys as $key) {
            if (! BackupManifestObject::query()->where('protected_key', $key)->exists()) {
                Storage::disk(config('nutriscope-backups.disk'))->delete($key);
            }
        }

        $backup->transitionTo(BackupState::Purged);
        $backup->update([
            'purged_at' => now(),
            'object_key' => null,
            'integrity_value' => null,
        ]);
    }

    public function apply(): void
    {
        $this->expireSafetySnapshots();
        $this->applyScheduledRetention();
        $this->applyLegacyAutomaticRetention();
    }

    private function expireSafetySnapshots(): void
    {
        BackupRun::verified()
            ->where('source', BackupSource::Safety)
            ->where('retention_expires_at', '<=', now())
            ->each(fn (BackupRun $backup) => $this->moveToRecentlyDeleted($backup));
    }

    private function applyScheduledRetention(): void
    {
        BackupRun::verified()
            ->where('source', BackupSource::Automatic)
            ->whereHas('schedulePeriods')
            ->with('schedulePeriods')
            ->each(function (BackupRun $backup): void {
                $active = $backup->schedulePeriods->filter(
                    fn ($period): bool => $period->expires_at->isFuture(),
                );

                if ($active->isEmpty()) {
                    $this->moveToRecentlyDeleted($backup);

                    return;
                }

                $backup->update([
                    'retention_tier' => $active->count() === 1 ? $active->first()->category : null,
                    'retention_expires_at' => $active->max('expires_at'),
                ]);
            });
    }

    private function applyLegacyAutomaticRetention(): void
    {
        $verified = BackupRun::verified()
            ->where('source', BackupSource::Automatic)
            ->whereDoesntHave('schedulePeriods')
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
            ->each(fn (BackupRun $backup) => $this->moveToRecentlyDeleted($backup));
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

    private function moveToRecentlyDeleted(BackupRun $backup): void
    {
        if ($backup->recoveryRequests()->whereNotIn('state', ['completed', 'failed', 'rolled_back', 'cancelled'])->exists()) {
            return;
        }

        $backup->transitionTo(BackupState::RecentlyDeleted);
        $backup->update([
            'retention_tier' => null,
            'retention_expires_at' => null,
            'deleted_at' => now(),
            'recoverable_until' => now()->addHours(config('nutriscope-backups.recoverable_hours')),
        ]);
    }
}
