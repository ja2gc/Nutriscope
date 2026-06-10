<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add per-day variance JSON to meal_plan_days.
     *
     * variance: JSON object keyed by nutrient (energy, protein, carbs, fat, water,
     *           and any micronutrient key). Values are either:
     *   - float: percentage deviation from target (e.g. 0.12 = +12%)
     *   - "cannot_validate": prescribed nutrient not reported by any recipe in this day
     *
     * flagged column already exists (boolean); variance gives the details.
     *
     * Phase 4.3 — post-generation ±10% validation.
     */
    public function up(): void
    {
        Schema::table('meal_plan_days', function (Blueprint $table) {
            $table->json('variance')->nullable()->after('flagged');
        });
    }

    public function down(): void
    {
        Schema::table('meal_plan_days', function (Blueprint $table) {
            $table->dropColumn('variance');
        });
    }
};
