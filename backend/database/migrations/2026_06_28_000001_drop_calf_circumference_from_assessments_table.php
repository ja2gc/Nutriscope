<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calf circumference was an AWGS muscle-mass proxy. Per intervention-goals.md
 * (2026-06-28) muscle-mass criteria do not feed the prescription calculator and
 * the field is no longer collected in the assessment UI, so the column is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            if (Schema::hasColumn('assessments', 'calf_circumference_cm')) {
                $table->dropColumn('calf_circumference_cm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            if (! Schema::hasColumn('assessments', 'calf_circumference_cm')) {
                $table->decimal('calf_circumference_cm', 5, 1)->nullable()->after('pregnancy_lactation_status');
            }
        });
    }
};
