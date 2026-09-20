<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('report_templates')
            ->where('type', 'demographic_census')
            ->update([
                'description' => 'ADIME cycle counts by age/sex/ward/diagnosis/status/risk, any date range.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('report_templates')
            ->where('type', 'demographic_census')
            ->update([
                'description' => 'Patient counts by age/sex/ward/diagnosis/status/risk, any date range.',
                'updated_at' => now(),
            ]);
    }
};
