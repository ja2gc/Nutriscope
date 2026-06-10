<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add fields the nutrition prescription engine needs but were missing from assessments.
     *
     * stress_factor               — multiplier for high_protein / acute illness
     *                               (decimal, e.g. 1.2 for mild stress)
     * edema_present               — boolean; flags weight as unreliable for formula inputs
     * pregnancy_lactation_status  — string enum: none | pregnant | lactating
     *                               Drives PDRI energy/protein add-ons in NutritionPrescriptionService
     * calf_circumference_cm       — AWGS muscle proxy (D13 cut-points: <34 cm M / <33 cm F)
     *
     * All columns are nullable (no existing row has these values) and reversible.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->decimal('stress_factor', 4, 2)->nullable()
                  ->after('physical_activity_level')
                  ->comment('Stress/injury multiplier for high-protein & acute illness goals');

            $table->boolean('edema_present')->nullable()->default(false)
                  ->after('stress_factor')
                  ->comment('True = weight unreliable; surfaced as a UI warning, no formula change');

            $table->string('pregnancy_lactation_status', 20)->nullable()->default('none')
                  ->after('edema_present')
                  ->comment('none | pregnant | lactating — PDRI energy/protein add-ons');

            $table->decimal('calf_circumference_cm', 5, 1)->nullable()
                  ->after('pregnancy_lactation_status')
                  ->comment('AWGS sarcopenia proxy: <34 cm Male / <33 cm Female = low muscle mass');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn([
                'stress_factor',
                'edema_present',
                'pregnancy_lactation_status',
                'calf_circumference_cm',
            ]);
        });
    }
};
