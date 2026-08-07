<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('backup_runs')
            ->where('source', 'automatic')
            ->where('state', 'completed')
            ->whereNotNull('retention_tier')
            ->whereNotNull('retention_expires_at')
            ->orderBy('id')
            ->each(function (object $backup): void {
                $verified = Carbon::parse($backup->verified_at ?? $backup->completed_at);
                $periodKey = match ($backup->retention_tier) {
                    'daily' => $verified->format('Y-m-d'),
                    'weekly' => $verified->format('o-\WW'),
                    'monthly' => $verified->format('Y-m'),
                };

                DB::table('backup_schedule_periods')->insertOrIgnore([
                    'backup_run_id' => $backup->id,
                    'category' => $backup->retention_tier,
                    'period_key' => $periodKey,
                    'expires_at' => $backup->retention_expires_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void {}
};
