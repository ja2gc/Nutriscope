<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('interventions')
            ->join('ncp_records', 'ncp_records.id', '=', 'interventions.ncp_record_id')
            ->whereNotNull('interventions.next_followup_date')
            ->whereIn('ncp_records.status', ['draft', 'active'])
            ->orderBy('interventions.id')
            ->select([
                'interventions.id as intervention_id',
                'interventions.next_followup_date',
                'ncp_records.id as ncp_record_id',
                'ncp_records.patient_id',
                'ncp_records.rnd_user_id',
            ])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $exists = DB::table('ncp_appointments')
                        ->where('patient_id', $row->patient_id)
                        ->whereDate('scheduled_at', $row->next_followup_date)
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
                        'scheduled_at' => $row->next_followup_date.' 09:00:00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'interventions.id', 'intervention_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Historical source rows are intentionally preserved; deleting them could remove user-created appointments.
    }
};
