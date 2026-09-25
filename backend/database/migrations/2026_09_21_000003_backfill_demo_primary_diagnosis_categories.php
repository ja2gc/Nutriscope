<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $seededPatients = [
            'HN-2026-0042' => 'Diabetes',
            'HN-2026-0078' => 'Malnutrition',
        ];

        foreach ($seededPatients as $hospitalNumber => $category) {
            $patientId = DB::table('patients')
                ->where('hospital_number', $hospitalNumber)
                ->value('id');

            if ($patientId === null) {
                continue;
            }

            $cycleIds = DB::table('ncp_records')
                ->where('patient_id', $patientId)
                ->pluck('id');

            DB::table('assessments')
                ->whereIn('ncp_record_id', $cycleIds)
                ->update([
                    'primary_diagnosis_category' => $category,
                    'primary_diagnosis_other' => null,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Forward-only data classification: rollback must not erase later clinical edits.
    }
};
