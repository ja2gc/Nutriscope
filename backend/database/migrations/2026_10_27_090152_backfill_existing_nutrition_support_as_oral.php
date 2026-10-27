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
        // The owner confirmed all existing cases are Oral; do not invent new clinical observation timestamps.
        foreach (['ncp_records', 'interventions', 'meal_plan_templates'] as $name) {
            DB::table($name)->whereNull('nutrition_support')->update(['nutrition_support' => 'oral']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data backfill is forward-only. Never clear a subsequently confirmed clinical selection.
    }
};
