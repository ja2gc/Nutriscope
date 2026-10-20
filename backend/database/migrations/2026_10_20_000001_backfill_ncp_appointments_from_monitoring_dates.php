<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('monitorings')
            ->join('ncp_records', 'ncp_records.id', '=', 'monitorings.ncp_record_id')
            ->whereNotNull('monitorings.next_monitoring_date')
            ->whereIn('ncp_records.status', ['draft', 'active'])
            ->orderBy('monitorings.id')
            ->select([
                'monitorings.id as monitoring_id',
                'monitorings.next_monitoring_date',
                'ncp_records.patient_id',
                'ncp_records.rnd_user_id',
            ])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $exists = DB::table('ncp_appointments')
                        ->where('patient_id', $row->patient_id)
                        ->whereDate('scheduled_at', $row->next_monitoring_date)
                        ->where('purpose', 'Nutrition follow-up')
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    DB::table('ncp_appointments')->insert([
                        'uuid' => (string) Str::uuid(),
                        'patient_id' => $row->patient_id,
                        'ncp_record_id' => null,
                        'rnd_user_id' => $row->rnd_user_id,
                        'source' => 'scheduled',
                        'status' => 'scheduled',
                        'purpose' => 'Nutrition follow-up',
                        'scheduled_at' => $row->next_monitoring_date.' 09:00:00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'monitorings.id', 'monitoring_id');
    }

    public function down(): void
    {
        // Keep appointment rows: after backfill, they may have been edited by users.
    }
};
