<?php

use Database\Seeders\InterventionMealPlanTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $seeder = new InterventionMealPlanTemplateSeeder;
            $seeder->seedMissingTemplates();
            $seeder->repairCanonicalCarbSlots();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
