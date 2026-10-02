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
                'description' => 'Monthly ADIME cycle census by age, sex, and risk level.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('report_templates')
            ->where('type', 'demographic_census')
            ->update([
                'description' => 'ADIME cycle counts by primary diagnosis category, nutritional status, and risk level.',
                'updated_at' => now(),
            ]);
    }
};
