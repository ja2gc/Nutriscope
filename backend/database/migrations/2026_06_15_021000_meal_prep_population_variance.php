<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture how the day's production matched actual attendance.
 *   population            = headcount the kitchen PREPARED for (drives ingredient use)
 *   served_population     = headcount actually FED (nullable; FSS reports it)
 *   population_variance   = population − served_population
 *       positive -> cooked too much (surplus / leftover food)
 *       negative -> cooked too little (more ate than prepped)
 * Ingredient-level shortfall (stock ran out) is tracked separately per line via
 * meal_prep_log_lines.shortfall_qty + meal_prep_logs.has_shortfall.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_prep_logs', function (Blueprint $table) {
            $table->unsignedInteger('served_population')->nullable()->after('population');
            $table->integer('population_variance')->nullable()->after('served_population');
        });
    }

    public function down(): void
    {
        Schema::table('meal_prep_logs', function (Blueprint $table) {
            $table->dropColumn(['served_population', 'population_variance']);
        });
    }
};
