<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Population redesign (Phase A): population becomes a property of the individual
 * menu cycle day, not the cycle. Each day carries its own estimate headcount, used
 * live as the scaling target for that day's recipes/items.
 *
 * Expand step only — menu_cycles.population and budget_per_head_per_day are dropped
 * in a later (Phase B) migration once all reads have moved off them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->unsignedInteger('estimate_population')->nullable()->after('servings_override');
        });
    }

    public function down(): void
    {
        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->dropColumn('estimate_population');
        });
    }
};
