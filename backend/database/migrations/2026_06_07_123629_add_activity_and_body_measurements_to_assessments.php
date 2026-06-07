<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add clinical measurement fields to assessments.
     *
     * physical_activity_level — drives TEE calculation (replaces lifestyle free-text)
     * muac_mm                 — mid-upper arm circumference for malnutrition classification
     * waist_cm                — waist circumference for WHR and cardiometabolic risk
     * hip_cm                  — hip circumference to compute waist-hip ratio
     *
     * Existing columns (lifestyle, food_intolerance, physical_assessment) are kept
     * for backward compatibility — they are hidden from UI but not dropped.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('physical_activity_level')->nullable()->after('lifestyle');
            $table->decimal('muac_mm', 5, 1)->nullable()->after('physical_activity_level');
            $table->decimal('waist_cm', 5, 1)->nullable()->after('muac_mm');
            $table->decimal('hip_cm', 5, 1)->nullable()->after('waist_cm');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['physical_activity_level', 'muac_mm', 'waist_cm', 'hip_cm']);
        });
    }
};
