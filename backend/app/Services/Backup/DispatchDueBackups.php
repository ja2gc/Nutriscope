<?php

namespace App\Services\Backup;

use App\Enums\BackupRetentionTier;
use App\Enums\BackupSource;
use App\Enums\BackupState;
use App\Jobs\CreateDatabaseBackup;
use App\Models\BackupRun;
use App\Models\BackupSchedulePeriod;
use App\Models\BackupScheduleSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DispatchDueBackups
{
    public function handle(CarbonImmutable $now): ?BackupRun
    {
        Cache::put(config('nutriscope-backups.scheduler_heartbeat_key'), $now->toIso8601String(), now()->addHour());

        $setting = BackupScheduleSetting::current();
        if (! $setting->anyEnabled()) {
            return null;
        }

        $due = collect(BackupRetentionTier::cases())
            ->filter(fn (BackupRetentionTier $category): bool => $setting->{$category->value})
            ->mapWithKeys(fn (BackupRetentionTier $category): array => [
                $category->value => $this->dueTarget($category, $now),
            ]);

        $backup = DB::transaction(function () use ($due): ?BackupRun {
            BackupScheduleSetting::query()->whereKey(1)->lockForUpdate()->first();
            $missing = $due->reject(fn (CarbonImmutable $target, string $category): bool => BackupSchedulePeriod::query()
                ->where('category', $category)
                ->where('period_key', $this->periodKey(BackupRetentionTier::from($category), $target))
                ->exists()
            );

            if ($missing->isEmpty() || BackupRun::query()->whereIn('state', [
                BackupState::Queued,
                BackupState::Running,
                BackupState::Verifying,
            ])->exists()) {
                return null;
            }

            $backup = BackupRun::query()->create([
                'state' => BackupState::Queued,
                'source' => BackupSource::Automatic,
                'storage_disk' => config('nutriscope-backups.disk'),
                'queued_at' => now(),
            ]);

            $missing->each(function (CarbonImmutable $target, string $category) use ($backup): void {
                $tier = BackupRetentionTier::from($category);
                $backup->schedulePeriods()->create([
                    'category' => $tier,
                    'period_key' => $this->periodKey($tier, $target),
                    'expires_at' => $this->expiresAt($tier, $target),
                ]);
            });

            return $backup;
        }, 3);

        if ($backup !== null) {
            CreateDatabaseBackup::dispatch($backup->uuid);
        }

        return $backup;
    }

    public function nextTarget(BackupRetentionTier $category, CarbonImmutable $now): CarbonImmutable
    {
        $candidate = match ($category) {
            BackupRetentionTier::Daily => $now->startOfDay()->setTime(1, 30),
            BackupRetentionTier::Weekly => $now->startOfWeek()->addDays(6)->setTime(1, 30),
            BackupRetentionTier::Monthly => $now->startOfMonth()->setTime(1, 30),
        };

        if ($candidate->greaterThan($now)) {
            return $candidate;
        }

        return match ($category) {
            BackupRetentionTier::Daily => $candidate->addDay(),
            BackupRetentionTier::Weekly => $candidate->addWeek(),
            BackupRetentionTier::Monthly => $candidate->addMonthNoOverflow()->startOfMonth()->setTime(1, 30),
        };
    }

    private function dueTarget(BackupRetentionTier $category, CarbonImmutable $now): CarbonImmutable
    {
        $next = $this->nextTarget($category, $now);

        return match ($category) {
            BackupRetentionTier::Daily => $next->subDay(),
            BackupRetentionTier::Weekly => $next->subWeek(),
            BackupRetentionTier::Monthly => $next->subMonthNoOverflow()->startOfMonth()->setTime(1, 30),
        };
    }

    private function periodKey(BackupRetentionTier $category, CarbonImmutable $target): string
    {
        return match ($category) {
            BackupRetentionTier::Daily => $target->format('Y-m-d'),
            BackupRetentionTier::Weekly => $target->format('o-\WW'),
            BackupRetentionTier::Monthly => $target->format('Y-m'),
        };
    }

    private function expiresAt(BackupRetentionTier $category, CarbonImmutable $target): CarbonImmutable
    {
        return match ($category) {
            BackupRetentionTier::Daily => $target->addDays(config('nutriscope-backups.retention.daily')),
            BackupRetentionTier::Weekly => $target->addWeeks(config('nutriscope-backups.retention.weekly')),
            BackupRetentionTier::Monthly => $target->addMonthsNoOverflow(config('nutriscope-backups.retention.monthly')),
        };
    }
}
