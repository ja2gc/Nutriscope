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
        $ownedDemoPatients = [
            'HN-2026-0042' => 'Diabetes',
            'HN-2026-0078' => 'Malnutrition',
        ];

        foreach ($ownedDemoPatients as $hospitalNumber => $category) {
            $patientId = DB::table('patients')
                ->where('hospital_number', $hospitalNumber)
                ->value('id');

            if ($patientId === null) {
                continue;
            }

            DB::table('patients')->where('id', $patientId)->update(['is_demo' => true]);

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
